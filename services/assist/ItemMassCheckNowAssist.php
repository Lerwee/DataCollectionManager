<?php

namespace app\customs\zapi\services\assist;

use app\common\components\Result;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\services\TaskService;
use yii\base\Exception;

/**
 * Class ItemMassCheckNowAssist
 * @package app\customs\zapi\services\assist
 */
class ItemMassCheckNowAssist extends BaseAssist
{
    /**
     * @var array  List of selected items from API stored in this item cache variable.
     */
    private $item_cache = [];

    /**
     * @var bool  Whether the request is for items (false) or LLD rules (true).
     */
    private $is_discovery_rule;

    /**
     * @param $params
     * @return Result
     */
    public function checkNow($params): Result
    {
        try {
            $this->raw_input = $params;
            $this->checkInput();
            return $this->check();
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(60750604, $e->getMessage());
        }
    }

    protected function checkInput(): bool {
        $fields = [
            'itemids' => 'required|array_db items.itemid',
            'discovery_rule' => 'in 0,1'
        ];
        $ret = $this->validateInput($fields);
        $this->is_discovery_rule = (bool) $this->getInput('discovery_rule', 0);

        if (!$ret) {
            if ($messages = array_column(get_and_clear_messages(), 'message')) {
                self::exception(60750001, implode("\r\n", array_column($messages, 'message')));
            }
        }

        return $ret;
    }

    protected function check()
    {
        $output = [];

        // List of item IDs that are coming from input (later overwritten).
        $itemids = $this->getInput('itemids');

        // Error message details.
        $errors = [];

        // Find items or LLD rules.
        if ($this->is_discovery_rule) {
            $items = DiscoverRuleHelper::getDiscoverRules([
                'output' => ['type', 'name', 'status', 'flags', 'master_itemid'],
                'selectHosts' => ['name', 'status'],
                'itemids' => $itemids,
                'editable' => true,
                'preservekeys' => true
            ]);
        }
        else {
            $items = ItemHelper::getItems([
                'output' => ['type', 'name', 'status', 'flags', 'master_itemid'],
                'selectHosts' => ['name', 'status'],
                'itemids' => $itemids,
                'editable' => true,
                'webitems' => true,
                'preservekeys' => true
            ]);
        }

        if ($items) {
            /*
             * If some items were not found (deleted or web items) or user may not have permissions, set error message.
             * In case of partial success, error message will not be visible, but in case there are no items to create
             * tasks for, error message will be visible.
             */
            if (count($itemids) != count($items)) {
                $errors = [t('zapi', 'No permissions to referred object or it does not exist!')];
            }

            // Get all allowed item types including dependent items, which will be processed separately.
            $allowed_types =  ItemHelper::checkNowAllowedTypes();

            // Collects master item IDs if some or all items are dependent items.
            $master_itemids = [];

            // Item IDs that are given to task.create API method. These are all top level item IDs.
            $itemids = [];

            foreach ($items as $itemid => $item) {
                if (!in_array($item['type'], $allowed_types)) {
                    // In case items (dependent or master) are not allowed, store the error message.
                    if (!$errors) {
                        // If no errors exist yet, set the first error message.
                        $msg_part = ($item['flags'] == PRS_FLAG_DISCOVERY_RULE)
                            ? t('zapi', 'wrong discovery rule type')
                            : t('zapi', 'wrong item type');
                        $errors = [t('zapi', 'Cannot send request: {name}.', ['name' => $msg_part])];
                    }

                    // Stop processing this item, since it is not valid.
                    continue;
                }

                if ($item['status'] != ITEM_STATUS_ACTIVE || $item['hosts'][0]['status'] != HOST_STATUS_MONITORED) {
                    // In case items (dependent or master) or host is not monitored, store the error message.
                    $host_name = $item['hosts'][0]['name'];

                    if (!$errors) {
                        // If no errors exist yet, set the first error message.
                        $msg_part = ($item['flags'] == PRS_FLAG_DISCOVERY_RULE)
                            ? t('zapi', 'discovery rule "{rule}" on host "{host}" is not monitored', ['rule' => $item['name'], 'host' => $host_name])
                            : t('zapi', 'item "{item}" on host "{host}" is not monitored', ['item' => $item['name'], 'host' => $host_name]);
                        $errors = [t('zapi', 'Cannot send request: {name}.', ['name' => $msg_part])];
                    }

                    // Stop processing this item, since it is not valid.
                    continue;
                }

                if ($item['type'] == ITEM_TYPE_DEPENDENT) {
                    // So far it is not known if master items are allowed or not. Collect IDs to process them later.
                    $master_itemids[$item['master_itemid']] = true;
                }
                else {
                    // These item IDs are top level IDs and will be passed to task.create method.
                    $itemids[$itemid] = true;
                }
            }

            /*
             * If all or some dependent items are found, find master items. Keep looping till all dependent items are
             * processed.
             */
            if ($master_itemids) {
                // Store already found items in item cache.
                $this->item_cache = $items;

                while ($master_itemids) {
                    // Get already known items from cache or DB.
                    $master_items = $this->getMasterItems(array_keys($master_itemids));

                    // Reset master item IDs, so this loop will eventually end.
                    $master_itemids = [];

                    foreach ($master_items as $itemid => $item) {
                        if (!in_array($item['type'], $allowed_types)) {
                            // In case parent item (dependent or master) is not allowed, store the error message.

                            if (!$errors) {
                                // If no errors exist yet, set the first error message.
                                $errors = [t('zapi', 'Cannot send request: {name}.', ['name' => t('zapi', 'wrong master item type')])];
                            }

                            // Stop processing this item, since it is not valid.
                            continue;
                        }

                        if ($item['status'] != ITEM_STATUS_ACTIVE
                            || $item['hosts'][0]['status'] != HOST_STATUS_MONITORED) {
                            // In case items (dependent or master) or host is not monitored, store the error message.
                            if (!$errors) {
                                // If no errors exist yet, set the first error message.
                                $errors = [t('zapi', 'Cannot send request: {name}.', [
                                       'name' => t('zapi', 'item "{item}" on host "{host}" is not monitored', ['item' => $item['name'], 'host' =>  $item['hosts'][0]['name']])
                                ])];
                            }

                            // Stop processing this item, since it is not valid.
                            continue;
                        }

                        if ($item['type'] == ITEM_TYPE_DEPENDENT) {
                            // Again some dependent items found. Keep looping.
                            $master_itemids[$item['master_itemid']] = true;
                        }
                        else {
                            // These item IDs are top level IDs and will be passed to task.create method.
                            $itemids[$itemid] = true;
                        }
                    }
                }
            }
        }
        else {
            // User has no permissions to any of the selected items or they are deleted or web items.
            $errors = [t('zapi', 'No permissions to referred object or it does not exist!')];
        }

        if ($itemids) {
            // If all or some items were valid, create tasks.
            $create_tasks = [];
            $itemids = array_keys($itemids);

            foreach ($itemids as $itemid) {
                $create_tasks[] = [
                    'type' => PRS_TM_TASK_CHECK_NOW,
                    'request' => [
                        'itemid' => $itemid
                    ]
                ];
            }

            $result = TaskService::instance()->create($create_tasks);
            if ($result) {
                // If tasks were created, return either partial success result or full success result.
                return $this->success([], $errors
                    ? t('zapi', 'Request sent successfully. Some items are filtered due to access permissions or type.')
                    : t('zapi', 'Request sent successfully'));
            } else {
                // If task API failed, return with full error message from API.
                return $this->error(60750604, t('zapi', 'Cannot execute operation'), array_column(get_and_clear_messages(), 'message'));
            }
        }
        else {
            // No items left to send to task.create. Returns a full error message that was set before.
            return $this->error(60750604, t('zapi', 'Cannot execute operation'), $errors);
        }
    }

    /**
     * Find master items by given item IDs either stored in cache or DB. Returns the item found. Discovery rules
     * cannot depend on other discovery rules, so only regular items are requested.
     *
     * @param array $itemids  An array of master item IDs.
     *
     * @return array
     */
    private function getMasterItems(array $itemids): array {
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
            $items = ItemHelper::getItems([
                'output' => ['type', 'name', 'status', 'master_itemid'],
                'selectHosts' => ['name', 'status'],
                'itemids' => $itemids,
                'editable' => true,
                'webitems' => true,
                'preservekeys' => true
            ]);

            /*
             * Master item could be removed during the process, which means dependent item is also removed. If that is
             * the case, then this whole function will not execute and it is safe to pass the result to item_cache,
             * since there will always be items returned.
             */

            // Add newly found items to cache.
            $this->item_cache += $items;
        }

        // Return only requested items either from cache or DB.
        return $items;
    }
}