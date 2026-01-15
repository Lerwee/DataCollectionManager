<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\AuditHelper;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\managers\HostManager;
use app\customs\zapi\common\parsers\CHostNameParser;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\LimitedSetValidator;
use app\customs\zapi\forms\hosts\HostForm;
use app\customs\zapi\models\forms\HostInterfaceForm;
use app\customs\zapi\models\search\host\HostSearch;
use app\customs\zapi\services\HostInterfaceService;
use app\customs\zapi\services\hosts\HostGeneralService;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Interfaces;
use app\modules\libzbx\models\zbx\HostInventory;
use app\modules\libzbx\models\zbx\Hostmacro;
use app\modules\libzbx\models\zbx\HostsGroups;
use app\modules\libzbx\models\zbx\HostsTemplates;
use app\modules\libzbx\models\zbx\HostTag;
use app\modules\libzbx\models\zbx\Valuemap;
use Yii;
use yii\base\Exception;
use yii\db\Query;

/**
 * Class HostService
 * @package app\customs\zapi\services
 */
class HostService extends HostGeneralService
{
    /**
     * 监控项列表
     * @param array $params
     * @return Result
     */
    public function getList(array $params = []): Result
    {
        $default = array_merge([
			'output' => ['name', 'host', 'proxy_hostid', 'maintenance_status', 'maintenance_type', 'maintenanceid', 'flags',
				'status', 'tls_connect', 'tls_accept', 'active_available'
			],
			'selectParentTemplates' => ['templateid', 'name'],
			'selectInterfaces' => ['interfaceid', 'main', 'type', 'useip',  'ip', 'dns', 'port', 'available', 'error',
				'details'
			],
			'selectItems' => API_OUTPUT_COUNT,
			'selectDiscoveries' => API_OUTPUT_COUNT,
			'selectTriggers' => API_OUTPUT_COUNT,
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectHttpTests' => API_OUTPUT_COUNT,
			'selectDiscoveryRule' => ['itemid', 'name'],
			'selectHostDiscovery' => ['parent_hostid', 'ts_delete'],
		#	'selectTags' => ['tag', 'value'],
			'preservekeys' => true,
            'sortfield' => ['name', 'status'],
            'sortorder' => 'in '.PRS_SORT_DOWN.','.PRS_SORT_UP,
		], $params);
        
        $searcher = new HostSearch();
        $dataProvider = $searcher->search($default);

        return $this->success([
            'rows' => $this->format($dataProvider->getModels()),
            'total' => $dataProvider->getTotalCount(),
        ]);
    }

    public function format(array $hosts)
    {
        $hostIds = array_keys($hosts);

        // 代理程序
        $proxies = Hosts::find()
            ->where(['status' => [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE]])
            ->select('host')
            ->indexBy('hostid')
            ->column();

        // Selecting linked templates to templates linked to hosts.
		$templateIds = [];

		foreach ($hosts as $host) {
			$templateIds = array_merge($templateIds, array_column($host['parentTemplates'], 'templateid'));
		}

        $templates = TemplateHelper::getTemplates([
			'output' => ['templateid', 'name'],
			'selectParentTemplates' => ['templateid', 'name'],
			'templateids' => $templateIds,
			'preservekeys' => true
		]);

        // Get count for every host with item type ITEM_TYPE_PERSEUS_ACTIVE (7) and ITEM_TYPE_PERSEUS (0).
		$active_item_count_by_hostid = ItemHelper::getItemTypeCountByHostId(ITEM_TYPE_PERSEUS_ACTIVE, $hostIds);
		$passive_item_count_by_hostid = ItemHelper::getItemTypeCountByHostId(ITEM_TYPE_PERSEUS, $hostIds);

        foreach($hosts as $hostId => $host) {
            // Sort interfaces to be listed starting with one selected as 'main'.
			CArrayHelper::sort($host['interfaces'], [
				['field' => 'main', 'order' => PRS_SORT_DOWN]
			]);

            foreach($host['interfaces'] as &$interface) {
                $interface['error'] = str_replace(['Perseus', 'perseus'], ['Perseus', 'perseus'], $interface['error']);
            }
            unset($interface);
            $hosts[$hostId]['interfaces'] = array_values($host['interfaces']);

            $hosts[$hostId]['proxy_name'] = $host['proxy_hostid'] && 
                array_key_exists($host['proxy_hostid'], $proxies) ? $proxies[$host['proxy_hostid']]: '';
            foreach($host['parentTemplates'] as $i => $pTpl) {
                $host['parentTemplates'][$i]['parentTemplates'] = [];
                if (array_key_exists($pTpl['templateid'], $templates)) {
                    $host['parentTemplates'][$i]['parentTemplates'] = $templates[$pTpl['templateid']]['parentTemplates'];
                }
            }
            $hosts[$hostId]['parentTemplates'] = $host['parentTemplates'];

            // Add active checks interface if host have items with type ITEM_TYPE_PERSEUS_ACTIVE (7).
			if (array_key_exists($hostId, $active_item_count_by_hostid)
					&& $active_item_count_by_hostid[$hostId] > 0) {
				$hosts[$hostId]['interfaces'][] = [
					'type' => INTERFACE_TYPE_AGENT_ACTIVE,
					'available' => $host['active_available'],
					'error' => ''
				];
			}
			unset($hosts[$hostId]['active_available']);

            $hosts[$hostId]['has_passive_checks'] = array_key_exists($host['hostid'], $passive_item_count_by_hostid)
				&& $passive_item_count_by_hostid[$host['hostid']] > 0;
        }
        
        return array_values($hosts);
    }

    /**
     * @param array $params
     * @return Result
     */
    public function create(array $params, bool $internal = true): Result
    {
        $valueMappings = [];
        if (array_key_exists('valuemaps', $params)) {
            $valueMappings = $params['valuemaps'];
            unset($params['valuemaps']);
        }
        $params['enableTransaction'] = true;
        $result = $this->createByInternal($params);
        if ($result->isSuccess()) {
            $result->setErrmsg(Yii::t('msg', 'Create Success'));
            $hostId = current(current($result->getData()));
            $this->createValueMaps($hostId, $valueMappings);
            AuditHelper::save($result->getErrmsg(), AuditHelper::ACTION_CREATE);
        }

        return $result;
    }

    /**
     * @param array $hosts
     * @return Result
     */
    public function createByInternal(array $hosts): Result
    {
        $enableTransaction = false;
        if (isset($hosts['enableTransaction'])) {
            $enableTransaction = (bool) $hosts['enableTransaction'];
            unset($hosts['enableTransaction']);
        }
        try {
            $this->validateCreate($hosts);
        } catch (Exception $e) {
            return $this->errorException($e, 60750101);
        }

        $enableTransaction && $transaction = Hosts::getDb()->beginTransaction();
        try {
            // 批量新增
            $hostIds = DB::insert(Hosts::tableName(), $hosts);

            // 关联分组数据
            $hostGroups = [];
            // 关联标签数据
            $hostTags = [];
            // 关联接口数据
            $hostInterfaces = [];
            // 主机宏
            $hostMacros = [];
            // 模板ID对于的主机ID集合
            $templateId2HostIds = [];
            // 主机资产
            $hostInventories = [];


            foreach ($hosts as $index => &$host) {
                $host['hostid'] = $hostIds[$index];

                foreach ($host['groups'] as $group) {
                    $hostGroups[] = [
                        'hostid' => $host['hostid'],
                        'groupid' => $group['groupid']
                    ];
                }

                if (array_key_exists('tags', $host)) {
                    foreach (to_array($host['tags']) as $tag) {
                        $hostTags[] = ['hostid' => $host['hostid']] + $tag;
                    }
                }

                if (array_key_exists('interfaces', $host)) {
                    foreach (to_array($host['interfaces']) as $interface) {
                        $hostInterfaces[] = ['hostid' => $host['hostid']] + $interface;
                    }
                }

                if (array_key_exists('macros', $host)) {
                    foreach (to_array($host['macros']) as $macro) {
                        $hostMacros[] = ['hostid' => $host['hostid']] + $macro;
                    }
                }

                if (array_key_exists('templates', $host)) {
                    foreach (to_array($host['templates']) as $template) {
                        $templateId2HostIds[$template['templateid']][] = $host['hostid'];
                    }
                }

                $inventories = [];
                if (array_key_exists('inventory', $host) && $host['inventory']) {
                    $inventories = $host['inventory'];
                    $inventories['inventory_mode'] = 0;
                }

                if (array_key_exists('inventory_mode', $host) && $host['inventory_mode'] != -1) {
                    $inventories['inventory_mode'] = $host['inventory_mode'];
                }

                if (array_key_exists('inventory_mode', $inventories)) {
                    $hostInventories[] = ['hostid' => $host['hostid']] + $inventories;
                }
            }
            unset($host);

            DB::insertBatch(HostsGroups::tableName(), $hostGroups);

            if ($hostTags) {
                DB::insert(HostTag::tableName(), $hostTags);
            }

            if ($hostInterfaces) {
                HostInterfaceService::instance()->create($hostInterfaces);
            }

            $this->createHostMacros($hosts);

            while ($templateId2HostIds) {
                $templateId = key($templateId2HostIds);
                $linkHostIds = reset($templateId2HostIds);
                $linkTemplateIds = [$templateId];
                unset($templateId2HostIds[$templateId]);

                foreach ($templateId2HostIds as $templateId => $hostIds) {
                    if ($linkHostIds === $hostIds) {
                        $linkTemplateIds[] = $templateId;
                        unset($templateId2HostIds[$templateId]);
                    }
                }

                $this->link($linkTemplateIds, $linkHostIds);
            }

            if ($hostInventories) {
                DB::insert(HostInventory::tableName(), $hostInventories, false);
            }
            $enableTransaction && $transaction->commit();
            foreach($hosts as $host) {
                AuditHelper::collect([$host['hostid'] => $host['name']]);
                AuditHelper::collectDetails($host['hostid'], $host);
            }
            return $this->success(['hostids' => array_column($hosts, 'hostid')]);
        } catch (Exception $e) {
            $enableTransaction && $transaction->rollBack();
            return $this->errorException($e, 60750101);
        }
    }

    protected function validateCreate(array &$hosts)
    {
        $hosts = to_array($hosts);
        $hosts = HostForm::validateHosts($hosts);

        HostForm::validateTags($hosts);

        // 主机名称重复检查
        $duplicate = ArrayHelper::findDuplicate($hosts, 'host');
        if ($duplicate) {
            self::exception(60750101, Yii::t('yii', '{attribute} "{value}" has already been taken.', [
                'attribute' => 'host',
                'value' => $duplicate['host']
            ]));
        }

        // 主机业务名称重复检查
        $duplicate = ArrayHelper::findDuplicate($hosts, 'name');
        if ($duplicate) {
            self::exception(60750101, Yii::t('yii', '{attribute} "{value}" has already been taken.', [
                'attribute' => 'name',
                'value' => $duplicate['name']
            ]));
        }

        $statusValidator = new LimitedSetValidator([
            'values' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED],
            'messageInvalid' => t('zapi', 'Incorrect status for host "{host}".')
        ]);

        $names = [];

        foreach ($hosts as $host) {
            if (
                array_key_exists('interfaces', $host) && $host['interfaces'] !== null
                && !is_array($host['interfaces'])
            ) {
                self::exception(60750101);
            }

            if (array_key_exists('status', $host) && !$statusValidator->validate($host['status'], $error)) {
                self::exception(60750101, str_replace('{host}', $host['host'], $error));
            }

            $names['host'][] = $host['host'];
            $names['name'][] = $host['name'];
        }


        foreach ($names as $field => $sets) {
            // 检查主机名称是否存在于模板/主机
            if ($check = HostForm::getOneByNames($sets, $field)) {
                $msg = $check['status'] == 3 ? Yii::t('zapi', 'Template with the same name "{name}" already exists.', [
                    'name' => $check[$field]
                ]) : Yii::t('yii', '{attribute} "{value}" has already been taken.', [
                    'attribute' => $field,
                    'value' => $check[$field]
                ]);
                self::exception(60750101, $msg);
            }
        }


        $this->validateEncryption($hosts);
    }

    /**
     * 更新主机
     *
     * @param integer $hostid
     * @param array $params
     * @return Result
     */
    public function update(array $params, bool $internal = true): Result
    {
        $valueMappings = null;
        if (array_key_exists('valuemaps', $params)) {
            $valueMappings = $params['valuemaps'];
            unset($params['valuemaps']);
        }
        $params['enableTransaction'] = true;
        $result = $this->updateByInternal($params);
        
        if ($result->isSuccess()) {
            $result->setErrmsg(Yii::t('msg', 'Update Success'));
            if ($valueMappings !== null) {
                $hostId = current(current($result->getData()));
                $this->renewValueMaps($hostId, $valueMappings);
            }
            AuditHelper::save($result->getErrmsg(), AuditHelper::ACTION_UPDATE);
        }
        return $result;
    }

    /**
     * 更新(内部调用)
     * 
     * @param array $hosts
     * @return Result
     */
    public function updateByInternal(array $hosts): Result
    {
        $enableTransaction = false;
        if (isset($hosts['enableTransaction'])) {
            $enableTransaction = (bool) $hosts['enableTransaction'];
            unset($hosts['enableTransaction']);
        }
        $hosts = \to_array($hosts);
        if (empty($hosts)) {
            return $this->error(10000021);
        }

        $enableTransaction && $transaction = Hosts::getDb()->beginTransaction();
        try {
            $this->validateUpdate($hosts, $dbHosts);

            $inventories = [];
            foreach ($hosts as &$host) {
                // If visible name is not given or empty it should be set to host name.
                if (array_key_exists('host', $host) && (!array_key_exists('name', $host) || trim($host['name']) === '')) {
                    $host['name'] = $host['host'];
                }

                // Fetch fields required to update host inventory.
                if (array_key_exists('inventory', $host)) {
                    $inventory = $host['inventory'];
                    $inventory['hostid'] = $host['hostid'];

                    $inventories[] = $inventory;
                }
            }
            unset($host);

            $inventories = $this->extendObjects('host_inventory', $inventories, ['inventory_mode']);
            $inventories = prs_toHash($inventories, 'hostid');
            $this->updateMacros($hosts, $dbHosts);
            foreach ($hosts as &$host) {
                if (array_key_exists('macros', $host)) {
                    AuditHelper::collectDetail($host['hostid'], 'macros:update', $host['macros'], $dbHosts[$host['hostid']]['macros'] ?? []);
                    unset($host['macros']);
                }
            }
            unset($host);

            $hosts = $this->extendObjectsByKey($hosts, $dbHosts, 'hostid', [
                'tls_connect', 'tls_accept', 'tls_issuer',
                'tls_subject', 'tls_psk_identity', 'tls_psk'
            ]);

            foreach ($hosts as $host) {
                // Extend host inventory with the required data.
                if (array_key_exists('inventory', $host) && $host['inventory']) {
                    // If inventory mode is HOST_INVENTORY_DISABLED, database record is not created.
                    if (
                        array_key_exists('inventory_mode', $inventories[$host['hostid']])
                        && ($inventories[$host['hostid']]['inventory_mode'] == HOST_INVENTORY_MANUAL
                            || $inventories[$host['hostid']]['inventory_mode'] == HOST_INVENTORY_AUTOMATIC)
                    ) {
                        $host['inventory'] = $inventories[$host['hostid']];
                    }
                }

                $data = $host;
                $data['hosts'] = ['hostid' => $host['hostid']];
                $result = $this->massUpdate($data, false);
                if (!$result->isSuccess()) {
                    $enableTransaction && $transaction->rollBack();
                    return $result;
                }
            }
            $this->updateTags($hosts, $dbHosts);
            $enableTransaction && $transaction->commit();
            
            return $this->success(['hostids' => array_column($hosts, 'hostid')]);
        } catch (Exception $e) {
            $enableTransaction && $transaction->rollBack();
            return $this->errorException($e, 60750101);
        }
    }

    /**
     * @param array $hosts
     * @param array|null $dbHosts
     * @throws ValidateException
     */
    protected function validateUpdate(array &$hosts, array &$dbHosts = null)
    {
        $dbHosts = Hosts::find()
            ->select(['hostid', 'host', 'flags', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject'])
            ->where(SqlHelper::whereIn('hostid', array_column($hosts, 'hostid')))
            ->indexBy('hostid')
            ->asArray()
            ->all();

        $hostsPskFields = Hosts::find()
            ->select(['hostid', 'tls_psk_identity', 'tls_psk'])
            ->where(SqlHelper::whereIn('hostid', array_keys($dbHosts)))
            ->indexBy('hostid')
            ->asArray()
            ->all();

        foreach ($hostsPskFields as $hostid => $psk_fields) {
            $dbHosts[$hostid] += $psk_fields;
        }


        $hosts = HostForm::validateHosts($hosts, $dbHosts);

        if (array_column($hosts, 'macros')) {
            $dbHosts = $this->getHostMacros($dbHosts);
            $hosts = $this->validateHostMacros($hosts, $dbHosts);
        }

        $statusValidator = new LimitedSetValidator([
            'values' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED],
            'messageInvalid' => t('zapi', 'Incorrect status for host "{host}".')
        ]);

        $allowedUpdateDiscoveredFields = array_flip([
            'hostid', 'status', 'description', 'tags', 'macros', 'inventory', 'templates', 'templates_clear'
        ]);

        $messageAllowedField = t('zapi', 'Cannot update "{field}" for a discovered host "{host}".');

        $hostNameParser = new CHostNameParser();

        $hostNames = [];

        foreach ($hosts as &$host) {
            $dbHost = $dbHosts[$host['hostid']];
            $hostName = array_key_exists('host', $host) ? $host['host'] : $dbHost['host'];

            if (array_key_exists('status', $host) && !$statusValidator->validate($host['status'], $error)) {
                throw new ValidateException(60750101, str_replace('{host}', $hostName, $error));
            }

            // cannot update certain fields for discovered hosts
            if ($dbHost['flags'] == PRS_FLAG_DISCOVERY_CREATED) {
                foreach ($host as $field => $value) {
                    if (!array_key_exists($field, $allowedUpdateDiscoveredFields)) {
                        // if we allow to update some fields, throw an error referencing a specific field
                        // we check if there is more than 1 field, because the PK must always be present
                        throw new ValidateException(60750101, str_replace('{host}', $hostName, str_replace('{field}', $field, $messageAllowedField)));
                    }
                }
            }

            if (
                array_key_exists('interfaces', $host) && $host['interfaces'] !== null
                && !is_array($host['interfaces'])
            ) {
                throw new ValidateException(60750101, t('zapi', 'Incorrect arguments passed to function.'));
            }

            if (array_key_exists('host', $host)) {
                if ($hostNameParser->parse($host['host']) != CHostNameParser::PARSE_SUCCESS) {
                    throw new ValidateException(60750101, t('zapi', 'Incorrect characters used for host name "{name}".', [
                        'name' => $hostName,
                    ]));
                }

                if (array_key_exists('host', $hostNames) && array_key_exists($host['host'], $hostNames['host'])) {
                    throw new ValidateException(60750101, t('zapi', 'Duplicate host. Host with the same host name "{name}" already exists in data.', [
                        'name' => $hostName,
                    ]));
                }

                $hostNames['host'][$host['host']] = $host['hostid'];
            }

            if (array_key_exists('name', $host)) {
                // if visible name is empty replace it with host name
                if (trim($host['name']) === '') {
                    if (!array_key_exists('host', $host)) {
                        throw new ValidateException(60750101, t('zapi', 'Visible name cannot be empty if host name is missing.'));
                    }
                    $host['name'] = $host['host'];
                }

                if (array_key_exists('name', $hostNames) && array_key_exists($host['name'], $hostNames['name'])) {
                    throw new ValidateException(60750101, t('zapi', 'Duplicate host. Host with the same visible name "{name}" already exists in data.', [
                        'name' => $hostName,
                    ]));
                }
                $hostNames['name'][$host['name']] = $host['hostid'];
            }

            if (array_key_exists('tls_connect', $host) || array_key_exists('tls_accept', $host)) {
                $tls_connect = array_key_exists('tls_connect', $host) ? $host['tls_connect'] : $dbHost['tls_connect'];
                $tls_accept = array_key_exists('tls_accept', $host) ? $host['tls_accept'] : $dbHost['tls_accept'];

                // Clean PSK fields.
                if ($tls_connect != HOST_ENCRYPTION_PSK && !($tls_accept & HOST_ENCRYPTION_PSK)) {
                    if (!array_key_exists('tls_psk_identity', $host)) {
                        $host['tls_psk_identity'] = '';
                    }
                    if (!array_key_exists('tls_psk', $host)) {
                        $host['tls_psk'] = '';
                    }
                }

                // Clean certificate fields.
                if ($tls_connect != HOST_ENCRYPTION_CERTIFICATE && !($tls_accept & HOST_ENCRYPTION_CERTIFICATE)) {
                    if (!array_key_exists('tls_issuer', $host)) {
                        $host['tls_issuer'] = '';
                    }
                    if (!array_key_exists('tls_subject', $host)) {
                        $host['tls_subject'] = '';
                    }
                }
            }
        }
        unset($host);

        $this->addAffectedTags($hosts, $dbHosts);

        HostForm::validateTags($hosts);

        if (array_key_exists('host', $hostNames) || array_key_exists('name', $hostNames)) {
            $filter = [];

            if (array_key_exists('host', $hostNames)) {
                $filter['host'] = array_keys($hostNames['host']);
            }

            if (array_key_exists('name', $hostNames)) {
                $filter['name'] = array_keys($hostNames['name']);
            }

            $this->checkExists($filter, $hostNames);
        }

        $this->validateEncryption($hosts, $dbHosts);


        return $hosts;
    }


    /**
     * @param array $filter
     * @param array $hostNames
     * @throws ValidateException
     */
    private function checkExists(array $filter, array $hostNames)
    {
        $name2status = [
            'Host' => [0, 1],
            'Template' => 3
        ];

        foreach ($name2status as $name => $status) {
            $query =  Hosts::find()
                ->select(['hostid', 'host', 'name'])
                ->where($filter);
            $query->andWhere(['status' => $status]);
            $exists = $query->asArray()
                ->indexBy('hostid')
                ->all();

            foreach ($exists as $exist) {
                if (
                    array_key_exists('host', $hostNames)
                    && array_key_exists($exist['host'], $hostNames['host'])
                    && bccomp($exist['hostid'], $hostNames['host'][$exist['host']]) != 0
                ) {
                    throw new ValidateException(60750101, t('zapi', '{target} with the same name "{name}" already exists.', [
                        'target' => $name,
                        'name' => $exist['host']
                    ]));
                }

                if (
                    array_key_exists('name', $hostNames)
                    && array_key_exists($exist['name'], $hostNames['name'])
                    && bccomp($exist['hostid'], $hostNames['name'][$exist['name']]) != 0
                ) {
                    throw new ValidateException(60750101, t('zapi', '{target} with the visible name "{name}" already exists.', [
                        'target' => $name,
                        'name' => $exist['name']
                    ]));
                }
            }
        }
    }

    /**
     * Validate connections from/to host and PSK fields.
     *
     * @param array  $hosts
     * @param string $hosts[]['hostid']                    (optional if $dbHosts is null)
     * @param int    $hosts[]['tls_connect']               (optional)
     * @param int    $hosts[]['tls_accept']                (optional)
     * @param string $hosts[]['tls_psk_identity']          (optional)
     * @param string $hosts[]['tls_psk']                   (optional)
     * @param string $hosts[]['tls_issuer']                (optional)
     * @param string $hosts[]['tls_subject']               (optional)
     * @param array  $dbHosts                             (optional)
     * @param int    $hosts[<hostid>]['tls_connect']
     * @param int    $hosts[<hostid>]['tls_accept']
     * @param string $hosts[<hostid>]['tls_psk_identity']
     * @param string $hosts[<hostid>]['tls_psk']
     * @param string $hosts[<hostid>]['tls_issuer']
     * @param string $hosts[<hostid>]['tls_subject']
     *
     * @throws ValidateException if incorrect encryption options.
     */
    protected function validateEncryption(array $hosts, array $dbHosts = null)
    {
        $available_connect_types = [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE];
        $min_accept_type = HOST_ENCRYPTION_NONE;
        $max_accept_type = HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE;

        $beEmpty = t('zapi', 'should be empty');
        $notEmpty = t('zapi', 'cannot be empty');
        $unexpected = t('zapi', 'unexpected value "{value}"');
        $error = t('zapi', 'Incorrect value for field "{attribute}", {error}.');
        foreach ($hosts as $host) {
            foreach (['tls_connect', 'tls_accept'] as $field_name) {
                $$field_name = array_key_exists($field_name, $host)
                    ? $host[$field_name]
                    : ($dbHosts !== null ? $dbHosts[$host['hostid']][$field_name] : HOST_ENCRYPTION_NONE);
            }

            if (!in_array($tls_connect, $available_connect_types)) {;
                throw new ValidateException(60750101, str_replace('{attribute}', 'tls_connect', str_replace('{error}', str_replace('{value}', $tls_connect, $unexpected), $error)));
            }

            if ($tls_accept < $min_accept_type || $tls_accept > $max_accept_type) {
                throw new ValidateException(60750101, str_replace('{attribute}', 'tls_accept', str_replace('{error}', str_replace('{value}', $tls_accept, $unexpected), $error)));
            }

            foreach (['tls_psk_identity', 'tls_psk', 'tls_issuer', 'tls_subject'] as $field_name) {
                $$field_name = array_key_exists($field_name, $host)
                    ? $host[$field_name]
                    : ($dbHosts !== null ? $dbHosts[$host['hostid']][$field_name] : '');
            }

            // PSK validation.
            if ($tls_connect == HOST_ENCRYPTION_PSK || ($tls_accept & HOST_ENCRYPTION_PSK)) {
                if ($tls_psk_identity === '') {
                    throw new ValidateException(60750101, str_replace('{attribute}', 'tls_psk_identity', str_replace('{error}', $notEmpty, $error)));
                }

                if ($tls_psk === '') {
                    throw new ValidateException(60750101, str_replace('{attribute}', 'tls_psk', str_replace('{error}', $notEmpty, $error)));
                }

                if (!preg_match('/^([0-9a-f]{2})+$/i', $tls_psk)) {
                    $err = t('zapi', 'an even number of hexadecimal characters is expected');
                    throw new ValidateException(60750101, str_replace('{attribute}', 'tls_psk', str_replace('{error}', $err, $error)));
                }

                if (strlen($tls_psk) < PSK_MIN_LEN) {
                    $err = t('zapi', 'minimum length is {min} characters', ['min' => PSK_MIN_LEN]);
                    throw new ValidateException(60750101, str_replace('{attribute}', 'tls_psk', str_replace('{error}', $err, $error)));
                }
            } else {
                if ($tls_psk_identity !== '') {
                    throw new ValidateException(60750101, str_replace('{attribute}', 'tls_psk', str_replace('{error}', $beEmpty, $error)));
                }

                if ($tls_psk !== '') {
                    throw new ValidateException(60750101, str_replace('{attribute}', 'tls_psk', str_replace('{error}', $beEmpty, $error)));
                }
            }

            // Certificate validation.
            if ($tls_connect != HOST_ENCRYPTION_CERTIFICATE && !($tls_accept & HOST_ENCRYPTION_CERTIFICATE)) {
                if ($tls_issuer !== '') {
                    throw new ValidateException(60750101, str_replace('{attribute}', 'tls_issuer', str_replace('{error}', $beEmpty, $error)));
                }

                if ($tls_subject !== '') {
                    throw new ValidateException(60750101, str_replace('{attribute}', 'tls_subject', str_replace('{error}', $beEmpty, $error)));
                }
            }
        }
    }


    public function delete(array $ids): Result
    {
        $result = $this->deleteByInternal($ids);
        if($result->isSuccess()) {
            $result->setErrmsg(Yii::t('msg', 'Delete Success'));
            AuditHelper::save($result->getErrmsg(), AuditHelper::ACTION_DELETE);
        }
        return $result;
    }

    /**
     * 删除(内部调用)
     *
     * @param array $ids
     * @return Result
     */
    public function deleteByInternal(array $ids): Result
    {
        $rules = [
            'ids' => [IdsValidator::class, 'flags' => API_NOT_EMPTY, 'uniq' => true],
        ];
        $params = [
            'ids' => $ids
        ];
        if (!ValidateHelper::validateObject($params, $rules, [], $error)) {
            return $this->error(error_code(10000026), $error);
        }

        $hostIdWhereIn = SqlHelper::whereIn('hostid', $ids);

        $id2name = Hosts::find()->where($hostIdWhereIn)->select('name')->indexBy('hostid')->column();

        if (empty($id2name)) {
            return $this->error(10000404);
        }

        // 以超管权限执行，无需验证权限
        if (false) {
        }

        try {
            // 检查是否存在维护模式
            HostManager::checkMaintenancesByHostId($ids);
        } catch (Exception $e) {
            return $this->error(60750101, $e->getMessage());
        }

        $transaction = Hosts::getDb()->beginTransaction();
        try {
            // 首先删除发现规则
            self::deleteDiscoveryRules($hostIdWhereIn);
            // 删除常规指标
            self::deletePlainItems($hostIdWhereIn);
            // 删除拨测
            self::deleteHttpTest($hostIdWhereIn);
            // delete host from maps
            self::deleteMapsByHostIds($ids);
            // 禁用动作和清理动作条件
            $this->disableActionsWithClearConditions($ids, $hostIdWhereIn);
            // 删除主机资产
            HostInventory::deleteAll($hostIdWhereIn);
            // 删除主机
            HostTag::deleteAll($hostIdWhereIn);
            Hosts::updateAll(['templateid' => null], ['hostid' => $ids, 'flags' => 2]); // PRS_FLAG_DISCOVERY_PROTOTYPE
            Hosts::deleteAll($hostIdWhereIn);

            $transaction->commit();
           
            AuditHelper::collect($id2name);


        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->errorException($e, 60750103);
        }

        return $this->success(['hostids' => $ids]);
    }

    /**
     * Mass update hosts.
     *
     * @param array  $hosts								multidimensional array with Hosts data
     * @param array  $hosts['hosts']					Array of Host objects to update
     * @param string $hosts['fields']['host']			Host name.
     * @param array  $hosts['fields']['groupids']		HostGroup IDs add Host to.
     * @param int    $hosts['fields']['port']			Port. OPTIONAL
     * @param int    $hosts['fields']['status']			Host Status. OPTIONAL
     * @param int    $hosts['fields']['useip']			Use IP. OPTIONAL
     * @param string $hosts['fields']['dns']			DNS. OPTIONAL
     * @param string $hosts['fields']['ip']				IP. OPTIONAL
     * @param int    $hosts['fields']['details']		Details. OPTIONAL
     * @param int    $hosts['fields']['proxy_hostid']	Proxy Host ID. OPTIONAL
     * @param int    $hosts['fields']['ipmi_authtype']	IPMI authentication type. OPTIONAL
     * @param int    $hosts['fields']['ipmi_privilege']	IPMI privilege. OPTIONAL
     * @param string $hosts['fields']['ipmi_username']	IPMI username. OPTIONAL
     * @param string $hosts['fields']['ipmi_password']	IPMI password. OPTIONAL
     *
     * @param bool $enableCheck
     * @return Result
     */
    public function massUpdate($data, bool $enableCheck = true): Result
    {
        if (!array_key_exists('hosts', $data) || !is_array($data['hosts'])) {
            return $this->error(60750101, t('zapi', 'Field "{field}" is mandatory.', ['field' => 'hosts']));
        }

        $hosts = to_array($data['hosts']);
        $inputHostIds =  array_column($hosts, 'hostid');
        $hostIds = array_unique($inputHostIds);

        $dbHosts = Hosts::find()
            ->select([
                'hostid', 'proxy_hostid', 'host', 'name', 'description', 'status',
                'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password',
                'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject', 'tls_psk_identity', 'tls_psk',
            ])
            ->where(SqlHelper::whereIn('hostid', $hostIds))
            ->asArray()
            ->indexBy('hostid')
            ->all();

        foreach ($hosts as $host) {
            if (!array_key_exists($host['hostid'], $dbHosts)) {
                return $this->error(60750101, t('zapi', 'You do not have permission to perform this operation.'));
            }
        }

        // Check inventory mode value.
        if (array_key_exists('inventory_mode', $data)) {
            $valid_inventory_modes = [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC];
            $validator = new LimitedSetValidator([
                'values' => $valid_inventory_modes,
                'messageInvalid' => t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                    'attribute' => 'inventory_mode',
                    'error' => t('zapi', 'value must be one of {value}', ['value' => implode(', ', $valid_inventory_modes)])
                ])
            ]);
            if (!$validator->validate($data['inventory_mode'], $error)) {
                self::exception(60750003, $error);
            }
        }

        if ($enableCheck) {
            // Check connection fields only for massupdate action.
            if (
                array_key_exists('tls_connect', $data) || array_key_exists('tls_accept', $data)
                || array_key_exists('tls_psk_identity', $data) || array_key_exists('tls_psk', $data)
                || array_key_exists('tls_issuer', $data) || array_key_exists('tls_subject', $data)
            ) {
                if (!array_key_exists('tls_connect', $data) || !array_key_exists('tls_accept', $data)) {
                    return $this->error(60750101, t('zapi', 'Cannot update host encryption settings. Connection settings for both directions should be specified.'));
                }

                // Clean PSK fields.
                if ($data['tls_connect'] != HOST_ENCRYPTION_PSK && !($data['tls_accept'] & HOST_ENCRYPTION_PSK)) {
                    $data['tls_psk_identity'] = '';
                    $data['tls_psk'] = '';
                }

                // Clean certificate fields.
                if (
                    $data['tls_connect'] != HOST_ENCRYPTION_CERTIFICATE
                    && !($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE)
                ) {
                    $data['tls_issuer'] = '';
                    $data['tls_subject'] = '';
                }
            }

            $this->validateEncryption([$data]);

            if (array_key_exists('groups', $data) && !$data['groups'] && $dbHosts) {
                $host = reset($dbHosts);
                return $this->error(60750101, t('zapi', 'Host "{host}" cannot be without host group.', [
                    'host' => $host['host']
                ]));
            }

            // Property 'auto_compress' is not supported for hosts.
            if (array_key_exists('auto_compress', $data)) {
                return $this->error(60750101, t('zapi', 'Incorrect input parameters.', [
                    'host' => $host['host']
                ]));
            }

            /*
            * Update hosts properties
            */
            if (isset($data['name'])) {
                if (count($hosts) > 1) {
                    return $this->error(60750101, t('zapi', 'Cannot mass update visible host name.'));
                }
            }

            if (array_key_exists('host', $data)) {
                $hostNameParser = new CHostNameParser();
                if ($hostNameParser->parse($data['host']) != CHostNameParser::PARSE_SUCCESS) {
                    return $this->error(60750101, t('zapi', 'Incorrect characters used for host name "{name}".', ['host' => $data['host']]));
                }
                if (count($hosts) > 1) {
                    return $this->error(60750101, t('zapi', 'Cannot mass update host name.'));
                }

                $curHost = reset($hosts);

                $name2status = [
                    'Host' => [0, 1],
                    'Template' => 3
                ];

                foreach ($name2status as $name => $status) {
                    $sameHostId = Hosts::find()
                        ->select('hostid')
                        ->where(['host' => $data['host']])
                        ->andWhere(['status' => $status])
                        ->asArray()
                        ->scalar();
                    if ($sameHostId) {
                        if ($name == 'Host' && (bccomp($sameHostId, $curHost['hostid']) != 0)) {
                            return $this->error(60750101, t('zapi', '{target} "{name}" already exists.', [
                                'target' => $name,
                                'name' => $data['host']
                            ]));
                        }

                        if ($name == 'Template') {
                            return $this->error(60750101, t('zapi', '{target} "{name}" already exists.', [
                                'target' => $name,
                                'name' => $data['host']
                            ]));
                        }
                    }
                }
            }
        }


        if (isset($data['groups'])) {
            $updateGroups = $data['groups'];
        }

        if (isset($data['interfaces'])) {
            $updateInterfaces = $data['interfaces'];
        }

        if (array_key_exists('templates_clear', $data)) {
            $updateTemplatesClear = to_array($data['templates_clear']);
        }

        if (isset($data['templates'])) {
            $updateTemplates = $data['templates'];
        }

        if (isset($data['macros'])) {
            $updateMacros = $data['macros'];
        }

        // second check is necessary, because import incorrectly inputs unset 'inventory' as empty string rather than null
        if (isset($data['inventory']) && $data['inventory']) {
            if (isset($data['inventory_mode']) && $data['inventory_mode'] == HOST_INVENTORY_DISABLED) {
                self::exception(60750101, t('zapi', 'Cannot set inventory fields for disabled inventory.'));
            }

            $updateInventory = $data['inventory'];
            $updateInventory['inventory_mode'] = null;
        }

        if (isset($data['inventory_mode'])) {
            if (!isset($updateInventory)) {
                $updateInventory = [];
            }
            $updateInventory['inventory_mode'] = $data['inventory_mode'];
        }

        unset(
            $data['hosts'],
            $data['groups'],
            $data['interfaces'],
            $data['templates_clear'],
            $data['templates'],
            $data['macros'],
            $data['inventory'],
            $data['inventory_mode']
        );

        if (!empty($data)) {
            DB::update(Hosts::tableName(), [
                'values' => $data,
                'where' => ['hostid' => $hostIds]
            ]);
            $dbHost = current($dbHosts);
            AuditHelper::collect([$dbHost['hostid'] => $data['name'] ?? $dbHosts[$dbHost['hostid']]['name']]);
            AuditHelper::collectDetails($dbHost['hostid'], $data, $dbHost);
        }

        /*
		 * Update template linkage
		 */
        if (isset($updateTemplatesClear)) {
            $templateIdsClear = array_column($updateTemplatesClear, 'templateid');

            if ($updateTemplatesClear) {
                $this->massRemove(['hostids' => $hostIds, 'templateids_clear' => $templateIdsClear]);
                AuditHelper::collectDetail(current($hostIds), 'templates:clear', [], $templateIdsClear);
            }
        } else {
            $templateIdsClear = [];
        }

        // unlink templates
        if (isset($updateTemplates)) {
            $hostTemplateIds = Hosts::find()->select(['hostid'])
                ->where(SqlHelper::whereIn('hostid', $hostIds))
                ->andWhere(['status' => 3])
                ->asArray()
                ->column();

            $newTemplateIds = array_column($updateTemplates, 'templateid');

            $templatesToDel = array_diff($hostTemplateIds, $newTemplateIds);
            $templatesToDel = array_diff($templatesToDel, $templateIdsClear);

            if ($templatesToDel) {
                $result = $this->massRemove([
                    'hostids' => $hostIds,
                    'templateids' => $templatesToDel
                ]);
                if (!$result->isSuccess()) {
                    $result->setErrmsg(t('zapi', 'Cannot unlink template'));
                    return $result;
                }
                AuditHelper::collectDetail(current($hostIds), 'templates:update', $templatesToDel, $hostTemplateIds);
            }
        }

        /*
		 * update interfaces
		 */
        if (isset($updateInterfaces)) {
            foreach ($hostIds as $hostid) {
                HostInterfaceService::instance()->replaceHostInterfaces([
                    'hostid' => $hostid,
                    'interfaces' => $updateInterfaces
                ]);
            }
        }

        // link new templates
        if (isset($updateTemplates)) {
            $result = $this->massAdd([
                'hosts' => $hosts,
                'templates' => $updateTemplates
            ]);

            if (!$result->isSuccess()) {
                $result->setErrmsg(t('zapi', 'Cannot link template'));
                return $result;
            }
        }

        // macros
        if (isset($updateMacros)) {
            DB::delete(Hostmacro::tableName(), ['hostid' => $hostIds]);
            $this->massAdd([
                'hosts' => $hosts,
                'macros' => $updateMacros
            ]);
        }

        /*
		 * Inventory
         * @todo TODO:
		 */

        /*
		 * Update host and host group linkage. This procedure should be done the last because user can unlink
		 * him self from a group with write permissions leaving only read permissions. Thus other procedures, like
		 * host-template linkage, inventory update, macros update, must be done before this.
		 */
        if (isset($updateGroups)) {
            $updateGroups = prs_toArray($updateGroups);

            $hostGroups = GroupHelper::getHostGroups([
				'output' => ['groupid'],
				'hostids' => $hostIds
            ]);

            $hostGroupIds = array_column($hostGroups, 'groupid');
            $newGroupIds = array_column($updateGroups, 'groupid');

            $groupsToAdd = array_diff($newGroupIds, $hostGroupIds);
            if ($groupsToAdd) {
                $this->massAdd([
                    'hosts' => $hosts,
                    'groups' => prs_toObject($groupsToAdd, 'groupid')
                ]);
            }

            $groupIdsToDelete = array_diff($hostGroupIds, $newGroupIds);
            if ($groupIdsToDelete) {
                $this->massRemove([
                    'hostids' => $hostIds,
                    'groupids' => $groupIdsToDelete
                ]);
            }
        }

        $msg = '';
        $new_hosts = [];
        foreach ($dbHosts as $hostid => $db_host) {
            $new_host = $data + $db_host;
            if ($new_host['status'] != $db_host['status']) {
                $msg = t('zapi', 'Updated status of host "{name}".', ['name' =>  $new_host['host']]);
            }

            $new_hosts[] = $new_host;
        }

        // zbx audit

        return $this->success(['hostids' => $inputHostIds], $msg);
    }

    /**
     * Additionally allows to create new interfaces on hosts.
     *
     * Checks write permissions for hosts.
     *
     * Additional supported $data parameters are:
     * - interfaces - an array of interfaces to create on the hosts
     * - templates  - an array of templates to link to the hosts, overrides the CHostGeneral::massAdd()
     *                'templates' parameter
     *
     * @param array $data
     *
     * @return Result
     */
    public function massAdd($data): Result
    {
        // $hosts = isset($data['hosts']) ? prs_toArray($data['hosts']) : [];
        // $hostIds = array_column($hosts, 'hostid');
        // checkPermissions

        // add new interfaces
        if (!empty($data['interfaces'])) {
            HostInterfaceService::instance()->massAdd([
                'hosts' => $data['hosts'],
                'interfaces' => prs_toArray($data['interfaces'])
            ]);
        }

        // rename the "templates" parameter to the common "templates_link"
        if (isset($data['templates'])) {
            $data['templates_link'] = $data['templates'];
            unset($data['templates']);
        }

        $data['templates'] = [];

        return parent::massAdd($data);
    }

    /**
     * Removes templates and interfaces from hosts.
     *
     * @param array $data
     * @param array $data['interfaces']         Interfaces to delete from the hosts.
     * @param array $data['templateids']        Templates to unlink from host.
     * @param array $data['templateids_clear']  Templates to unlink and clear from host.
     *
     * @throws ValidateException if the input is invalid.
     *
     * @return Result
     */
    public function massRemove(array $data): Result
    {
        if (!array_key_exists('hostids', $data) || $data['hostids'] === null) {
            throw new ValidateException(60750101, t('zapi', 'Incorrect arguments passed to function.'));
        }

        $data['hostids'] = prs_toArray($data['hostids']);

        if (isset($data['interfaces'])) {
            $options = [
                'hostids' => $data['hostids'],
                'interfaces' => prs_toArray($data['interfaces'])
            ];
            HostInterfaceService::instance()->massRemove($options);
        }

        // rename the "templates" parameter to the common "templates_link"
        if (isset($data['templateids'])) {
            $data['templateids_link'] = $data['templateids'];
            unset($data['templateids']);
        }

        $data['templateids'] = [];

        if (
            array_key_exists('templateids_link', $data) && $data['templateids_link']
            || array_key_exists('templateids_clear', $data) && $data['templateids_clear']
        ) {
            // If unlink or clear is requested, get existing host templates to determine the link type.
            $query = HostsTemplates::find();
            $query->select(['templateid', 'link_type', 'hostid'])
                ->andWhere(SqlHelper::whereIn('hostid', $data['hostids']));

            $hostsTemplates = $query->asArray()->all();
            $prohibitedTemplateIds = [];
            foreach ($hostsTemplates as $hostTemplates) {
                if ($hostTemplates['link_type'] == TEMPLATE_LINK_LLD) {
                    $prohibitedTemplateIds[$hostTemplates['templateid']] = true;
                }
            }

            // Some templates may not be allowed to unlink. Remove IDs from both lists.
            if ($prohibitedTemplateIds) {
                foreach (['templateids_link', 'templateids_clear'] as $field) {
                    if (array_key_exists($field, $data) && $data[$field]) {
                        foreach ($data[$field] as $idx => $templateid) {
                            if (array_key_exists($templateid, $prohibitedTemplateIds)) {
                                unset($data[$field][$idx]);
                            }
                        }
                    }
                }
            }
        }

        return parent::massRemove($data);
    }

    /**
	 * Create valuemaps.
	 *
	 * @param int $hostid      Target hostid.
	 *
	 * @return Result
	 */
	private function createValueMaps(int $hostid, array $valueMappings): Result 
    {
		foreach ($valueMappings as $key => $valueMapping) {
			if (array_key_exists('valuemapid', $valueMapping)) {
                unset($valueMapping['valuemapid']);
            }
			$valueMappings[$key] = $valueMapping + ['hostid' => $hostid];
		}
		
		$result = ValueMapService::instance()->create($valueMappings);
        if ($result->isSuccess()) {
            AuditHelper::collectDetail($hostid, 'value_mapping:create', $valueMappings);
        }
		return $result;
	}

    protected function renewValueMaps(int $hostid, array $valueMaps)
    {
        $insValueMaps = [];
        $updValueMaps = [];
        $delValueMaps = [];

        $delValueMaps = Valuemap::find()->where(['hostid' => $hostid])->indexBy('valuemapid')->asArray()->all();

        foreach ($valueMaps as $valueMap) {
            if (array_key_exists('valuemapid', $valueMap)) {
                $updValueMaps[] = $valueMap;
                unset($delValueMaps[$valueMap['valuemapid']]);
            } else {
                $insValueMaps[] = $valueMap + ['hostid' => $hostid];
            }
        }

        if ($updValueMaps) {
            $result = ValueMapService::instance()->update($updValueMaps);
            if (!$result->isSuccess()) {
                return $result;
            }
            if ($insValueMaps || $delValueMaps) {
                AuditHelper::collectDetail($hostid, 'value_mapping:update',$updValueMaps);
            }
        }

        if ($insValueMaps) {
            $result = ValueMapService::instance()->create($insValueMaps);
            if (!$result->isSuccess()) {
                return $result;
            }
            AuditHelper::collectDetail($hostid, 'value_mapping:create', $insValueMaps);
        }

        if ($delValueMaps) {
            $result = ValueMapService::instance()->delete(array_keys($delValueMaps));
            AuditHelper::collectDetail($hostid, 'value_mapping:delete', $delValueMaps);
            if (!$result->isSuccess()) {
                return $result;
            }
        } 
    }
}
