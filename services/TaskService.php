<?php

namespace app\customs\zapi\services;


use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\forms\TaskForm;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\zbx\Task;

class TaskService extends BaseService
{

    public function createTask($params)
    {
        try {
            $result = $this->create($params);
            return $this->success($result, t('act', 'Create Success'));
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(60750204, $e->getMessage());
        }
    }


    /**
     * Create tasks.
     *
     * @param array $tasks Tasks to create.
     * @param string|array $tasks []['type']                     Type of task.
     * @param string $tasks []['request']['itemid']        Must be set for PRS_TM_TASK_CHECK_NOW task.
     * @param array $tasks []['request']['historycache']  (optional) object of history cache data request.
     * @param array $tasks []['request']['valuecache']    (optional) object of value cache data request.
     * @param array $tasks []['request']['preprocessing'] (optional) object of preprocessing data request.
     * @param array $tasks []['request']['alerting']      (optional) object of alerting data request.
     * @param array $tasks []['request']['lld']           (optional) object of lld cache data request.
     * @param array $tasks []['proxy_hostid']             (optional) Proxy to get diagnostic data about.
     *
     * @return array
     */
    public function create(array $tasks): array
    {
        $check_now_itemids = $this->validateCreate($tasks);

        $tasks_by_types = [
            PRS_TM_DATA_TYPE_DIAGINFO => [],
            PRS_TM_DATA_TYPE_PROXY_HOSTIDS => [],
            PRS_TM_TASK_CHECK_NOW => []
        ];
        foreach ($tasks as $index => $task) {
            if ($task['type'] == PRS_TM_TASK_CHECK_NOW) {
                $tasks_by_types[$task['type']][$index] = [
                    'type' => $task['type'],
                    'request' => [
                        'itemid' => $check_now_itemids[$task['request']['itemid']]
                    ]
                ];
            } else {
                $tasks_by_types[$task['type']][$index] = $task;
            }
        }


        $return = $this->createTasksDiagInfo($tasks_by_types[PRS_TM_DATA_TYPE_DIAGINFO])
            + $this->createTasksProxyHostids($tasks_by_types[PRS_TM_DATA_TYPE_PROXY_HOSTIDS])
            + $this->createTasksCheckNow($tasks_by_types[PRS_TM_TASK_CHECK_NOW]);

        ksort($return);

        return ['taskids' => array_values($return)];
    }


    /**
     * 验证创建方法的输入参数，并返回有效的项目ID。
     * 如果提供了依赖项ID，则尝试查找有效的主项目。
     * 一旦找到有效的主项目，即返回这些项目的ID
     *
     * @param array $tasks Tasks to validate.
     *
     * @return array
     * @throws APIException if the input is invalid.
     *
     */
    protected function validateCreate(array &$tasks): array
    {

        $rules = TaskForm::getValidationRules();
        $bool = ValidateHelper::validateObjects($actions, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE,], $error);
        if (!$bool) {
            self::exception(60750001, $error);
        }
        $itemids_editable = [];
        $proxy_hostids = [];

        foreach ($tasks as $task) {
            switch ($task['type']) {
                case PRS_TM_DATA_TYPE_DIAGINFO:
                    $proxy_hostids[$task['proxy_hostid']] = true;
                    break;

                case PRS_TM_DATA_TYPE_PROXY_HOSTIDS:
                    $proxy_hostids = array_fill_keys($task['request']['proxy_hostids'], true);
                    break;

                case PRS_TM_TASK_CHECK_NOW:
                    $itemids_editable[$task['request']['itemid']] = true;
                    break;
            }
        }

        unset($proxy_hostids[0]);

        $this->checkProxyHostids(array_keys($proxy_hostids));
        $itemids_editable = $this->checkEditableItems(array_keys($itemids_editable));

        return $itemids_editable;
    }


    /**
     * Create PRS_TM_TASK_CHECK_NOW tasks.
     *
     * @param array $tasks Request object for tasks to create.
     * @param string|array $tasks []['request']['itemid']   Item or LLD rule IDs to create tasks for.
     *
     * @return array
     * @throws APIException
     *
     */
    protected function createTasksCheckNow(array $tasks): array
    {
        if (!$tasks) {
            return [];
        }

        $itemids = [];
        $return = [];

        foreach ($tasks as $index => $task) {
            $itemids[$index] = $task['request']['itemid'];
        }

        // Check if tasks for items and LLD rules already exist.
        $db_tasks_sql =
            'SELECT t.taskid,tcn.itemid' .
            ' FROM task t,task_check_now tcn' .
            ' WHERE t.taskid=tcn.taskid' .
            ' AND t.type=' . PRS_TM_TASK_CHECK_NOW .
            ' AND t.status=' . PRS_TM_STATUS_NEW .
            ' AND ' . ZSqlHelper::dbConditionId('tcn.itemid', $itemids);
        $db_tasks = Task::getDb()->createCommand($db_tasks_sql)->queryAll();


        foreach ($db_tasks as $db_task) {
            foreach (array_keys($itemids, $db_task['itemid']) as $index) {
                $return[$index] = $db_task['taskid'];
                unset($itemids[$index]);
            }
        }

        // Create new tasks.
        if ($itemids) {
            $item_cnt = count(array_keys(array_flip($itemids)));
            $taskid = DB::reserveIds('task', $item_cnt);
            $task_rows = [];
            $task_check_now_rows = [];
            $time = time();
            $itemids_taskids = [];

            foreach ($itemids as $index => $itemid) {
                // Use already existing task ID and do not create a duplicate record in DB.
                if (array_key_exists($itemid, $itemids_taskids)) {
                    $return[$index] = $itemids_taskids[$itemid];
                } else {
                    $task_rows[] = [
                        'taskid' => $taskid,
                        'type' => PRS_TM_TASK_CHECK_NOW,
                        'status' => PRS_TM_STATUS_NEW,
                        'clock' => $time,
                        'ttl' => SEC_PER_HOUR
                    ];
                    $task_check_now_rows[] = [
                        'taskid' => $taskid,
                        'itemid' => $itemid,
                        'parent_taskid' => $taskid
                    ];

                    $return[$index] = $taskid;
                    $itemids_taskids[$itemid] = $taskid;
                    $taskid = bcadd($taskid, 1, 0);
                }
            }

            DB::insertBatch('task', $task_rows, false);
            DB::insertBatch('task_check_now', $task_check_now_rows, false);
        }

        return $return;
    }

    /**
     * Create PRS_TM_DATA_TYPE_DIAGINFO tasks.
     *
     * @param array $tasks []
     * @param array $tasks []['request']['historycache']  (optional) object of history cache data request.
     * @param array $tasks []['request']['valuecache']    (optional) object of value cache data request.
     * @param array $tasks []['request']['preprocessing'] (optional) object of preprocessing data request.
     * @param array $tasks []['request']['alerting']      (optional) object of alerting data request.
     * @param array $tasks []['request']['lld']           (optional) object of lld cache data request.
     * @param array $tasks []['proxy_hostid']             Proxy to get diagnostic data about.
     *
     * @return array
     * @throws APIException
     *
     */
    protected function createTasksDiagInfo(array $tasks): array
    {
        $task_rows = [];
        $task_data_rows = [];
        $return = [];
        $taskid = DB::reserveIds('task', count($tasks));

        foreach ($tasks as $index => $task) {
            $task_rows[] = [
                'taskid' => $taskid,
                'type' => PRS_TM_TASK_DATA,
                'status' => PRS_TM_STATUS_NEW,
                'clock' => time(),
                'ttl' => SEC_PER_HOUR,
                'proxy_hostid' => $task['proxy_hostid']
            ];

            $task_data_rows[] = [
                'taskid' => $taskid,
                'type' => $task['type'],
                'data' => json_encode($task['request']),
                'parent_taskid' => $taskid
            ];

            $return[$index] = $taskid;
            $taskid = bcadd($taskid, 1, 0);
        }

        DB::insertBatch('task', $task_rows, false);
        DB::insertBatch('task_data', $task_data_rows, false);

        return $return;
    }

    /**
     * @param array $tasks
     *
     * @return array
     */
    protected function createTasksProxyHostids(array $tasks): array
    {
        $task_rows = [];
        $task_data_rows = [];
        $proxy_hostids = [];
        $return = [];
        $taskid = DB::reserveIds('task', count($tasks));

        foreach ($tasks as $index => $task) {
            $task_rows[] = [
                'taskid' => $taskid,
                'type' => PRS_TM_TASK_DATA,
                'status' => PRS_TM_STATUS_NEW,
                'clock' => time(),
                'ttl' => SEC_PER_HOUR,
                'proxy_hostid' => null
            ];

            $task_data_rows[] = [
                'taskid' => $taskid,
                'type' => PRS_TM_DATA_TYPE_PROXY_HOSTIDS,
                'data' => json_encode([
                    'proxy_hostids' => $task['request']['proxy_hostids']
                ]),
                'parent_taskid' => $taskid
            ];

            $proxy_hostids += array_flip($task['request']['proxy_hostids']);

            $return[$index] = $taskid;
            $taskid = bcadd($taskid, 1, 0);
        }

        DB::insertBatch('task', $task_rows, false);
        DB::insertBatch('task_data', $task_data_rows, false);
        return $return;
    }


    /**
     * Function to check if specified proxies exists.
     *
     * @param array $proxy_hostids Proxy IDs to check.
     *
     * @throws Exception if proxy doesn't exist.
     */
    protected function checkProxyHostids(array $proxy_hostids): void
    {
        if (!$proxy_hostids) {
            return;
        }
        $proxies = Hosts::find()
            ->andWhere(['hostid' => $proxy_hostids, 'status' => [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE]])
            ->count();
        if ($proxies != count($proxy_hostids)) {
            self::exception(PRS_API_ERROR_PERMISSIONS, t('zapi', 'No permissions to referred object or it does not exist!'));
        }
    }


    /**
     * items "{"43807":{"itemid":"43807","type":"5","name":"任务管理器进程繁忙百分比","status":"0","flags":"0","master_itemid":"0","hosts":[{"hostid":"10532","name":"监控系统-Server","status":"0"}]}}"
     * @param array $itemids
     * @return array
     * @throws \Exception
     */
    protected function checkEditableItems(array $itemids): array
    {
        if (!$itemids) {
            return [];
        }

        /*
         * Array keys are the original item IDs given by user, but values are item IDs than can change whether item
         * is dependent or not. If item is dependent key remains the same, but value changes to master item ID. Until
         * the value is changed to top most master item ID.
         */
        $itemid_mapping = array_combine($itemids, $itemids);


        $items = ItemHelper::getItems([
            'output' => ['type', 'name', 'status', 'flags', 'master_itemid'],
            'selectHosts' => ['name', 'status'],
            'itemids' => $itemids,
            'preservekeys' => true
        ]);


        $itemids_cnt = count($itemids);

        if (count($items) != $itemids_cnt) {
            $items += DiscoverRuleHelper::getItems([
                'output' => ['type', 'name', 'status', 'flags', 'master_itemid'],
                'selectHosts' => ['name', 'status'],
                'itemids' => $itemids,
                'filter'=> ['flags' => PRS_FLAG_DISCOVERY_RULE],
                'preservekeys' => true
            ]);

            if (count($items) != $itemids_cnt) {
                self::exception(PRS_API_ERROR_PERMISSIONS,
                    t('zapi', 'No permissions to referred object or it does not exist!')
                );
            }
        }

        // Validate item and LLD rule type and status.
        $allowed_types = self::checkNowAllowedTypes();
        $master_itemids = [];

        // Check item and LLD rule first level. Collect master item IDs if type is dependent.
        foreach ($items as $itemid => $item) {
            if (!in_array($item['type'], $allowed_types)) {
                self::exception(PRS_API_ERROR_PARAMETERS,
                    t('zapi', 'Cannot send request: {name}.', ['name' =>
                            ($item['flags'] == PRS_FLAG_DISCOVERY_RULE)
                                ? t('zapi', 'wrong discovery rule type')
                                : t('zapi', 'wrong item type')]
                    )
                );
            }

            if ($item['status'] != ITEM_STATUS_ACTIVE || $item['hosts'][0]['status'] != HOST_STATUS_MONITORED) {
                $host_name = $item['hosts'][0]['name'];
                $problem = ($item['flags'] == PRS_FLAG_DISCOVERY_RULE)
                    ? t('zapi', 'discovery rule "{rule}" on host "{host}" is not monitored', ['rule' => $item['name'], 'host' => $host_name])
                    : t('zapi', 'item {item} on host {host} is not monitored', ['item' => $item['name'], 'host' => $host_name]);
                self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Cannot send request: {name}.', ['name' => $problem]));
            }

            // Collect master item IDs and real item IDs.
            if ($item['type'] == ITEM_TYPE_DEPENDENT) {
                $master_itemids[$item['master_itemid']] = $itemid;

                // Replace the dependent item IDs with master item ID.
                $itemid_mapping[$itemid] = $item['master_itemid'];
            }
        }

        // Put already collected items in cache.
        if ($master_itemids) {
            $this->item_cache = $items;

            // Get master items.
            while ($master_itemids) {
                // Get already known items from cache or DB.
                $master_items = $this->getMasterItems(array_keys($master_itemids));
                $master_itemids = [];

                // Check the master item type and status.
                foreach ($master_items as $itemid => $item) {
                    if (!in_array($item['type'], $allowed_types)) {
                        self::exception(PRS_API_ERROR_PARAMETERS,
                            t('zapi', 'Cannot send request: {name}.', ['name' => t('zapi', 'wrong master item type')])
                        );
                    }

                    if ($item['status'] != ITEM_STATUS_ACTIVE || $item['hosts'][0]['status'] != HOST_STATUS_MONITORED) {
                        self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Cannot send request: {name}.', ['name' =>
                                t('zapi', 'item {item} on host {host} is not monitored', ['item' => $item['name'], 'host' => $item['hosts'][0]['name']])]
                        ));
                    }

                    /*
                     * If the master item was also another dependent item, add master item IDs for next loop, otherwise
                     * store the item ID. This will replace the original item ID from request.
                     */
                    if ($item['type'] == ITEM_TYPE_DEPENDENT) {
                        // Look matching items and replace once again with master item ID.
                        foreach ($itemid_mapping as &$itemid_new) {
                            if (bccomp($itemid, $itemid_new) == 0) {
                                $itemid_new = $item['master_itemid'];
                            }
                        }
                        unset($itemid_new);

                        $master_itemids[$item['master_itemid']] = $itemid;
                    }
                }
            }
        }

        // Returns both original item IDs as keys and real item IDs as values.
        return $itemid_mapping;
    }


    /**
     * Find master items by given item IDs either stored in cache or DB. Returns the item if found.
     *
     * @param array $itemids An array of master item IDs.
     *
     * @return array
     * @throws APIException if item is not found or user has no permissions.
     *
     */
    private function getMasterItems(array $itemids): array
    {
        $items = [];

        // First try get items from cache if possible.
        foreach ($itemids as $num => $itemid) {
            if (array_key_exists($itemid, $this->item_cache)) {
                $item = $this->item_cache[$itemid];
                $items[$itemid] = $item;
                unset($itemids[$num]);
            }
        }

        // If some items were not found in cache, select them from DB.
        if ($itemids) {
            $items_db = ItemHelper::getItems([
                'output' => ['type', 'name', 'status', 'flags', 'master_itemid'],
                'selectHosts' => ['name', 'status'],
                'itemids' => $itemids,
                'preservekeys' => true
            ]);

            if (!$items_db) {
                self::exception(PRS_API_ERROR_PERMISSIONS,
                    t('zapi', 'No permissions to referred object or it does not exist!')
                );
            }

            // Add newly found items to cache.
            $this->item_cache += $items_db;

            // Append newly found items to items requested from cache.
            $items += $items_db;
        }

        // Return only requested items either from cache or DB.
        return $items;
    }

    protected static function checkNowAllowedTypes()
    {
        return [
            ITEM_TYPE_PERSEUS,
            ITEM_TYPE_SIMPLE,
            ITEM_TYPE_INTERNAL,
            ITEM_TYPE_EXTERNAL,
            ITEM_TYPE_DB_MONITOR,
            ITEM_TYPE_IPMI,
            ITEM_TYPE_SSH,
            ITEM_TYPE_TELNET,
            ITEM_TYPE_CALCULATED,
            ITEM_TYPE_JMX,
            ITEM_TYPE_DEPENDENT,
            ITEM_TYPE_HTTPAGENT,
            ITEM_TYPE_SNMP,
            ITEM_TYPE_SCRIPT
        ];
    }

}
