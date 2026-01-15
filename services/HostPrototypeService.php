<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\managers\base\ManagerTrait;
use app\customs\zapi\common\managers\HostGroupManager;
use app\customs\zapi\common\validators\HostGroupNameValidator;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\components\data\HostPrototypeMassRequestData;
use app\customs\zapi\components\data\HostPrototypeSearchRequestData;
use app\customs\zapi\forms\HostPrototypeForm;
use app\customs\zapi\models\search\host\HostPrototypeSearch;
use app\customs\zapi\services\hosts\BaseHostService;
use app\modules\libzbx\models\zbx\GroupDiscovery;
use app\modules\libzbx\models\zbx\GroupPrototype;
use app\modules\libzbx\models\zbx\HostDiscovery;
use app\modules\libzbx\models\zbx\HostInventory;
use app\modules\libzbx\models\zbx\Hostmacro;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\HostsTemplates;
use app\modules\libzbx\models\zbx\HostTag;
use app\modules\libzbx\models\zbx\Hstgrp;
use app\modules\libzbx\models\zbx\Interfaces;
use app\modules\libzbx\models\zbx\InterfaceSnmp;
use app\modules\libzbx\models\zbx\Items;
use Yii;
use yii\db\Expression;
use yii\db\Query;

/**
 * 主机原型
 */
class HostPrototypeService extends BaseHostService
{
    use ManagerTrait;

    /**
     * Maximum number of inheritable items per iteration.
     *
     * @var int
     */
    protected const INHERIT_CHUNK_SIZE = 1000;

    /**
     * 主机原型清单
     *
     * @param  array  $params
     * @return Result
     */
    public function getList(array $params): Result
    {
        $requestData = new HostPrototypeSearchRequestData(['data' => $params]);
        if (!$requestData->isSuccess()) {
            return $requestData->getResult();
        }
        $searcher = new HostPrototypeSearch();
        $provider = $searcher->search($requestData->getData(), ['visible' => 1]);
        return $this->success([
            'total' => $provider->getTotalCount(),
            'rows' => $provider->getModels(),
        ]);
    }

    /**
     * @param array $hosts
     *
     * @return Result
     */
    public function create(array $hosts): Result
    {
        $this->validateCreate($hosts);
        $this->createForce($hosts);
        $this->inherit($hosts);

        return $this->success(['hostids' => array_column($hosts, 'hostid')], Yii::t('msg', 'Create Success'));
    }

    /**
     * @param array $hosts
     *
     * @throws ValidateException
     */
    private function validateCreate(array &$hosts): void
    {
        $rules = [
            'ruleid' => [IdValidator::class, 'flags' => API_REQUIRED],
        ];
        if (!ValidateHelper::validateObjects($hosts, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED], $error)) {
            self::exception(10000026, $error);
        }

        self::checkDiscoveryRules($hosts, $db_lld_rules);
        self::addHostStatus($hosts, $db_lld_rules);
        $rules = HostPrototypeForm::getValidationRules('create', false);
        if (!ValidateHelper::validateObjects($hosts, $rules, ['uniq' => [['uuid'], ['ruleid', 'host'], ['ruleid', 'name']]], $error)) {
            self::exception(10000026, $error);
        }

        self::addUuid($hosts);

        self::checkUuidDuplicates($hosts);
        self::checkDuplicates($hosts);
        self::checkMainInterfaces($hosts);
        self::checkGroupLinks($hosts);
        $this->checkTemplates($hosts);
    }

    /**
     * Check for unique host prototype names per LLD rule.
     *
     * @param array      $hosts
     * @param array|null $db_hosts
     * @param bool       $inherited
     *
     * @throws APIException
     */
    private static function checkDuplicates(array $hosts, array $db_hosts = null, bool $inherited = false): void
    {
        $h_names = [];
        $v_names = [];

        foreach ($hosts as $host) {
            if (array_key_exists('host', $host)) {
                if (
                    $db_hosts === null
                    || $host['host'] !== $db_hosts[$host['hostid']]['host']
                ) {
                    $h_names[$host['ruleid']][] = $host['host'];
                }
            }

            if (array_key_exists('name', $host)) {
                if (
                    $db_hosts === null
                    || $host['name'] !== $db_hosts[$host['hostid']]['name']
                ) {
                    $v_names[$host['ruleid']][] = $host['name'];
                }
            }
        }

        if ($h_names) {
            $where = [];
            foreach ($h_names as $ruleid => $names) {
                $where[] = '(' . SqlHelper::whereIn('{{i}}.itemid', [$ruleid]) . ' AND ' . SqlHelper::stringWhereIn('{{h}}.host', $names) . ')';
            }

            if (!$inherited) {
                $query = new Query();
                $query->from([
                    'i' => 'items',
                    'hd' => 'host_discovery',
                    'h' => 'hosts',
                ])
                    ->where('i.itemid=hd.parent_itemid')
                    ->andWhere('hd.hostid=h.hostid');
                $duplicate = $query->select(['rule' => 'i.name', 'h.host'])
                    ->andWhere(implode(' OR ', $where))
                    ->limit(1)
                    ->one();

                if ($duplicate) {
                    self::exception(60750101, t('zapi', 'Host prototype with host name "{name}" already exists in discovery rule "{rule}".', [
                        'name' => $duplicate['host'],
                        'rule' => $duplicate['rule'],
                    ]));
                }
            } else {
                $query = new Query();
                $query->from([
                    'i' => 'items',
                    'hd' => 'host_discovery',
                    'h' => 'hosts',
                    'hh' => 'hosts',
                ])
                    ->where('i.itemid=hd.parent_itemid')
                    ->andWhere('hd.hostid=h.hostid')
                    ->andWhere('i.hostid=hh.hostid');
                $duplicate = $query->select(['rule' => 'i.name', 'h.host', 'parent_host' => 'hh.host', 'hh.status'])
                    ->andWhere(implode(' OR ', $where))
                    ->limit(1)
                    ->one();

                if ($duplicate) {
                    if ($duplicate['status'] == HOST_STATUS_TEMPLATE) {
                        $error = 'Host prototype with host name "{name}" already exists in discovery rule "{rule}" of template "{template}".';
                    } else {
                        $error = 'Host prototype with host name "{name}" already exists in discovery rule "{rule}" of host "{host}".';
                    }
                    self::exception(60750101, t('zapi', $error, [
                        'name' => $duplicate['name'],
                        'rule' => $duplicate['rule'],
                        'template' => $duplicate['parent_host'],
                    ]));
                }
            }
        }

        if ($v_names) {
            $where = [];
            foreach ($v_names as $ruleid => $names) {
                $where[] = '(' . SqlHelper::whereIn('{{i}}.itemid', [$ruleid]) . ' AND ' . SqlHelper::stringWhereIn('{{h}}.name', $names) . ')';
            }

            if (!$inherited) {
                $query = new Query();
                $query->from([
                    'i' => 'items',
                    'hd' => 'host_discovery',
                    'h' => 'hosts',
                ])
                    ->where('i.itemid=hd.parent_itemid')
                    ->andWhere('hd.hostid=h.hostid');
                $duplicate = $query->select(['rule' => 'i.name', 'h.host'])
                    ->andWhere(implode(' OR ', $where))
                    ->limit(1)
                    ->one();

                if ($duplicate) {
                    self::exception(60750101, t('zapi', 'Host prototype with host name "{name}" already exists in discovery rule "{rule}".', [
                        'name' => $duplicate['host'],
                        'rule' => $duplicate['rule'],
                    ]));
                }
            } else {
                $query = new Query();
                $query->from([
                    'i' => 'items',
                    'hd' => 'host_discovery',
                    'h' => 'hosts',
                    'hh' => 'hosts',
                ])
                    ->where('i.itemid=hd.parent_itemid')
                    ->andWhere('hd.hostid=h.hostid')
                    ->andWhere('i.hostid=hh.hostid');
                $duplicate = $query->select(['rule' => 'i.name', 'h.host', 'parent_host' => 'hh.host', 'hh.status'])
                    ->andWhere(implode(' OR ', $where))
                    ->limit(1)
                    ->one();

                if ($duplicate) {
                    if ($duplicate['status'] == HOST_STATUS_TEMPLATE) {
                        $error = 'Host prototype with host name "{name}" already exists in discovery rule "{rule}" of template "{template}".';
                    } else {
                        $error = 'Host prototype with host name "{name}" already exists in discovery rule "{rule}" of host "{host}".';
                    }
                    self::exception(60750101, t('zapi', $error, [
                        'name' => $duplicate['name'],
                        'rule' => $duplicate['rule'],
                        'template' => $duplicate['parent_host'],
                    ]));
                }
            }
        }
    }

    /**
     * Add the UUID to those of the given host prototypes that belong to a template and don't have the 'uuid' parameter
     * set.
     *
     * @param array $hosts
     */
    private static function addUuid(array &$hosts): void
    {
        foreach ($hosts as &$host) {
            if ($host['host_status'] == HOST_STATUS_TEMPLATE && !array_key_exists('uuid', $host)) {
                $host['uuid'] = generateUuidV4();
            }
        }
        unset($host);
    }

    /**
     * Verify host prototype UUIDs are not repeated.
     *
     * @param array      $hosts
     * @param array|null $db_hosts
     *
     * @throws ValidateException
     */
    private static function checkUuidDuplicates(array $hosts, array $db_hosts = null): void
    {
        $host_indexes = [];

        foreach ($hosts as $i => $host) {
            if (!array_key_exists('uuid', $host)) {
                continue;
            }

            if ($db_hosts === null || $host['uuid'] !== $db_hosts[$host['hostid']]['uuid']) {
                $host_indexes[$host['uuid']] = $i;
            }
        }

        if (!$host_indexes) {
            return;
        }

        $query = Hosts::find()->where([
            'flags' => PRS_FLAG_DISCOVERY_PROTOTYPE,
            'uuid' => array_keys($host_indexes),
        ]);
        $uuid = $query->select('uuid')->limit(1)->asArray()->scalar();

        if ($uuid) {
            self::exception(60750501, t('zapi', 'Invalid parameter {parameter}, {error}', [
                'parameter' => '/' . ($host_indexes[$uuid] + 1),
                'error' => t('zapi', 'host prototype with the same UUID already exists'),
            ]));
        }
    }

    /**
     * @param array      $hosts
     * @param array|null $db_lld_rules
     *
     * @throws ValidateException
     */
    private static function checkDiscoveryRules(array $hosts, array &$db_lld_rules = null): void
    {
        $ruleIds = array_unique(array_column($hosts, 'ruleid'));
        $idWhereIn = SqlHelper::whereIn('itemid', $ruleIds);
        $count = Items::find()->where(['flags' => PRS_FLAG_DISCOVERY_RULE])->andWhere($idWhereIn)->count();

        if ($count != count($ruleIds)) {
            self::exception(60750001, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        $query = new Query();
        $query->select(['i.itemid', 'i.hostid', 'h.status', 'h.flags'])
            ->from([
                'i' => 'items',
                'h' => 'hosts',
            ])
            ->where('i.hostid=h.hostid');
        $query->andWhere(str_replace('itemid', '{{i}}.itemid', $idWhereIn));
        $rows = $query->all();

        $db_lld_rules = [];

        foreach ($rows as $row) {
            if ($row['flags'] == PRS_FLAG_DISCOVERY_CREATED) {
                $host = Hosts::find()->select('host')->where(['hostid' => $row['hostid']])->scalar();
                self::exception(
                    60750001,
                    t('zapi', 'Cannot create a host prototype on a discovered host "{host}".', ['host' => $host])
                );
            }

            $db_lld_rules[$row['itemid']] = ['host_status' => $row['status']];
        }
    }

    /**
     * @param array $hostIds
     *
     * @return Result
     */
    public function delete(array $hostIds): Result
    {
        $this->validateDelete($hostIds, $db_hosts);

        self::deleteForce($db_hosts);

        return $this->success(['hostids' => $hostIds], Yii::t('msg', 'Delete Success'));
    }

    /**
     * @param array      $hostIds
     * @param array|null $db_hosts
     *
     * @throws APIException if the input is invalid.
     */
    private function validateDelete(array &$hostIds, array &$db_hosts = null)
    {
        $rules = [
            'ids' => [IdsValidator::class, 'flags' => API_NOT_EMPTY, 'uniq' => true],
        ];
        $params = [
            'ids' => $hostIds,
        ];
        if (!ValidateHelper::validateObject($params, $rules, [], $error)) {
            self::exception(10000026, $error);
        }

        $query = Hosts::find()->where(['flags' => PRS_FLAG_DISCOVERY_PROTOTYPE]);
        $query->andWhere(SqlHelper::whereIn('hostid', $hostIds));
        $query->select(['hostid', 'host', 'templateid'])
            ->indexBy('hostid');
        $db_hosts = $query->asArray()->all();

        if (count($db_hosts) != count($hostIds)) {
            self::exception(60750101, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        foreach ($hostIds as $i => $hostid) {
            if ($db_hosts[$hostid]['templateid'] != 0) {
                self::exception(60750501, t('zapi', 'Invalid parameter {parameter}, {error}', [
                    'parameter' => '/' . ($i + 1),
                    'error' => t('zapi', 'cannot delete templated host prototype'),
                ]));
            }
        }
    }

    /**
     *
     * @param array $id2host [hostid => host]
     * @return void
     */
    public static function deleteForce(array $id2host)
    {
        $table = Hosts::tableName();
        $id2host = self::getInheritedOrDependentData($id2host, $table, 'templateid', 'hostid', 'host');
        $hostIdWhere = SqlHelper::whereIn('hostid', array_keys($id2host));

        // Lock host prototypes before deletion to prevent server from adding new LLD hosts.
        #$sql = "SELECT NULL FROM {$table} WHERE {$hostIdWhere} FOR UPDATE";
        #Hosts::getDb()->createCommand($sql)->execute();

        $query = GroupPrototype::find()->select(['group_prototypeid'])->where($hostIdWhere);
        if ($groupPrototypeIds = $query->column()) {
            self::deleteGroupPrototypes($groupPrototypeIds);
        }

        $query = new Query();
        $query->from([
            'hd' => HostDiscovery::tableName(),
            'h' => Hosts::tableName(),
        ]);
        $query->where('hd.hostid=h.hostid')
            ->andWhere(str_replace('hostid', 'hd.parent_hostid', $hostIdWhere));

        $query->select(['h.host', 'hd.hostid'])
            ->indexBy('hostid');

        $discoveredHosts = $query->column();

        HostService::instance()->deleteByInternal(array_keys($discoveredHosts));

        Interfaces::deleteAll($hostIdWhere);
        HostsTemplates::deleteAll($hostIdWhere);
        HostTag::deleteAll($hostIdWhere);
        Hostmacro::deleteAll($hostIdWhere);
        HostInventory::deleteAll($hostIdWhere);

        Hosts::updateAll(['templateid' => null], $hostIdWhere);
        Hosts::deleteAll($hostIdWhere);

        // TODO: ZBX audit
    }

    /**
     *@param array $groupPrototypeIds
     */
    private static function deleteGroupPrototypes(array $groupPrototypeIds): void
    {
        $table = GroupPrototype::tableName();
        $idWhereIn = SqlHelper::whereIn('group_prototypeid', $groupPrototypeIds);
        // Lock group prototypes before the deletion to prevent server from adding new LLD elements.
        $sql = "SELECT NULL FROM {$table} WHERE {$idWhereIn} FOR UPDATE";
        GroupPrototype::getDb()->createCommand($sql)->execute();

        self::deleteDiscoveredGroups($idWhereIn);

        GroupPrototype::updateAll(['templateid' => null], $idWhereIn);
        GroupPrototype::deleteAll($idWhereIn);
    }

    /**
     * Delete the discovered host groups of the given group prototypes.
     *
     * @param string $prototypeIdsWhere
     */
    private static function deleteDiscoveredGroups($prototypeIdsWhere): void
    {
        $query = new Query();
        $query->from([
            'gd' => GroupDiscovery::tableName(),
            'g' => Hstgrp::tableName(),
        ]);

        $query->where('gd.groupid=g.groupid')
            ->andWhere(str_replace('group_prototypeid', '{{gd}}.parent_group_prototypeid', $prototypeIdsWhere));

        $query->select(['g.name', 'gd.groupid'])
            ->indexBy('groupid');

        if ($id2name = $query->column()) {
            HostGroupManager::validateDeleteForce($id2name);
            HostGroupManager::deleteForce($id2name);
        }
    }

    /**
     * @return Query
     */
    protected function getQuery(): Query
    {
        $query = new Query();
        $query->from([
            'h' => Hosts::tableName(),
            'hd' => HostDiscovery::tableName(),
            'i' => Items::tableName(),
            'ph' => Hosts::tableName(),
        ]);

        $query->where('hd.hostid=h.hostid')
            ->andWhere('hd.parent_itemid=i.itemid')
            ->andWhere('i.hostid=ph.hostid')
            ->andWhere(['ph.flags' => PRS_FLAG_DISCOVERY_NORMAL])
            ->andWhere(['h.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE]);
        return $query;
    }

    private function getByRuleIds(array $ruleIds, bool $withInventory = true)
    {
        $query = $this->getQuery();
        $query->andWhere(SqlHelper::whereIn('hd.parent_itemid', $ruleIds));
        $query->select(['h.hostid', 'h.host', 'h.name', 'h.custom_interfaces', 'h.status', 'h.discover']);
        $query->indexBy('hostid');

        $prototypes = $query->all();

        if ($prototypes && $withInventory) {
            $hostInventories = HostInventory::find()
                ->select(['inventory_mode'])
                ->where(['hostid' => array_keys($prototypes)])
                ->indexBy('hostid')
                ->column();

            foreach ($prototypes as $hostid => $prototype) {
                $prototypes[$hostid]['inventory_mode'] = $hostInventories[$hostid] ?? HOST_INVENTORY_DISABLED;
            }
        }

        return $prototypes;
    }

    /**
     * @param array $ruleIds
     */
    public function unlinkTemplateObjects(array $ruleIds): void
    {
        $query = self::getInternalQuery();
        $query->select([
            'hd.hostid',
            'h.host',
            'h.uuid',
            'h.templateid',
            'ruleid' => 'hd.parent_itemid',
            'host_status' => 'hh.status',
        ]);
        $query->where('hd.hostid=h.hostid')
            ->andWhere('hd.parent_itemid=i.itemid')
            ->andWhere('i.hostid=hh.hostid')
            ->andWhere(SqlHelper::whereIn('{{hd}}.parent_itemid', $ruleIds));

        $hosts = [];
        $dbHosts = [];

        foreach ($query->each() as $row) {
            $host = [
                'hostid' => $row['hostid'],
                'groupLinks' => [],
                'groupPrototypes' => [],
                'templateid' => 0,
                'ruleid' => $row['ruleid'],
                'host_status' => $row['host_status'],
            ];

            if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
                $host += ['uuid' => generateUuidV4()];
            }

            $hosts[] = $host;

            $dbHosts[$row['hostid']] = array_intersect_key(
                $row,
                array_flip(['hostid', 'host', 'uuid', 'templateid', 'ruleid', 'host_status'])
            );
        }

        if ($hosts) {
            $this->addAffectedObjects($hosts, $dbHosts);

            foreach ($hosts as &$host) {
                foreach ($dbHosts[$host['hostid']]['groupLinks'] as $group_link) {
                    $host['groupLinks'][] = [
                        'groupid' => $group_link['groupid'],
                        'templateid' => 0,
                    ];
                }

                foreach ($dbHosts[$host['hostid']]['groupPrototypes'] as $group_prototype) {
                    $host['groupPrototypes'][] = [
                        'name' => $group_prototype['name'],
                        'templateid' => 0,
                    ];
                }
            }
            unset($host);

            self::updateForce($hosts, $dbHosts);
        }
    }

    /**
     * @param array $ruleIds
     * @param array $hostIds
     */
    public function linkTemplateObjects(array $ruleIds, array $hostIds): void
    {
        $db_host_prototypes = $this->getByRuleIds($ruleIds);

        if (!$db_host_prototypes) {
            return;
        }

        self::addInternalFields($db_host_prototypes);

        $_host_prototypes = [];

        foreach ($db_host_prototypes as $db_host_prototype) {
            $_host_prototype = array_intersect_key($db_host_prototype, array_flip(['hostid', 'custom_interfaces']));

            if ($db_host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
                $_host_prototype += ['interfaces' => []];
            }

            $_host_prototypes[] = $_host_prototype + [
                'groupLinks' => [],
                'groupPrototypes' => [],
                'templates' => [],
                'tags' => [],
                'macros' => [],
            ];
        }

        $this->addAffectedObjects($_host_prototypes, $db_host_prototypes);

        $host_prototypes = array_values($db_host_prototypes);

        foreach ($host_prototypes as &$host_prototype) {
            if (array_key_exists('interfaces', $host_prototype)) {
                $host_prototype['interfaces'] = array_values($host_prototype['interfaces']);
            }

            $host_prototype['groupLinks'] = array_values($host_prototype['groupLinks']);
            $host_prototype['groupPrototypes'] = array_values($host_prototype['groupPrototypes']);
            $host_prototype['templates'] = array_values($host_prototype['templates']);
            $host_prototype['tags'] = array_values($host_prototype['tags']);
            $host_prototype['macros'] = array_values($host_prototype['macros']);
        }
        unset($host_prototype);

        $lld_links = self::getLldLinks($ruleIds, $hostIds);

        $this->inherit($host_prototypes, [], $lld_links);
    }

    /**
     * @param array      $hosts
     * @param array      $db_hosts
     * @param array|null $lld_links
     */
    private function inherit(array $hosts, array $db_hosts = [], array $lld_links = null): void
    {
        if ($lld_links === null) {
            $lld_links = self::getLldLinks(array_unique(array_column($hosts, 'ruleid')));

            self::filterObjectsToInherit($hosts, $db_hosts, $lld_links);

            if (!$hosts) {
                return;
            }
        }

        $chunks = self::getInheritChunks($hosts, $lld_links);

        foreach ($chunks as $chunk) {
            $_hosts = array_intersect_key($hosts, array_flip($chunk['host_indexes']));
            $_db_hosts = array_intersect_key($db_hosts, array_flip(array_column($_hosts, 'hostid')));
            $ruleids = array_keys($chunk['lld_rules']);

            $this->inheritChunk($_hosts, $_db_hosts, $lld_links, $ruleids);
        }
    }

    /**
     * @param array      $ruleIds
     * @param array|null $hostIds
     *
     * @param array
     */
    private static function getLldLinks(array $ruleIds, array $hostIds = null): array
    {
        $query = new Query();
        $query->from([
            'h' => Hosts::tableName(),
            'i' => Items::tableName(),
        ]);

        $query->where('h.hostid=i.hostid')
            ->andWhere(SqlHelper::whereIn('{{i}}.templateid', $ruleIds));

        if ($hostIds) {
            $query->andWhere(SqlHelper::whereIn('{{i}}.hostid', $hostIds));
        }

        $query->select(['i.templateid', 'i.itemid', 'host_status' => 'h.status']);

        $lldLinks = [];
        foreach ($query->each() as $row) {
            $lldLinks[$row['templateid']][$row['itemid']] = [
                'itemid' => $row['itemid'],
                'host_status' => $row['host_status'],
            ];
        }
        return $lldLinks;
    }

    /**
     * Filter out inheritable host prototypes.
     *
     * @param array $hosts
     * @param array $db_hosts
     * @param array $lld_links
     */
    private static function filterObjectsToInherit(array &$hosts, array &$db_hosts, array $lld_links): void
    {
        foreach ($hosts as $i => $host) {
            if (!array_key_exists($host['ruleid'], $lld_links)) {
                unset($hosts[$i]);

                if (array_key_exists($host['hostid'], $db_hosts)) {
                    unset($db_hosts[$host['hostid']]);
                }
            }
        }
    }

    /**
     * Get host prototype chunks to inherit.
     *
     * @param array $hosts
     * @param array $lld_links
     *
     * @return array
     */
    private static function getInheritChunks(array $hosts, array $lld_links): array
    {
        $chunks = [
            [
                'host_indexes' => [],
                'lld_rules' => [],
                'size' => 0,
            ],
        ];
        $last = 0;

        foreach ($hosts as $i => $host) {
            $lld_rules_chunks = array_chunk($lld_links[$host['ruleid']], self::INHERIT_CHUNK_SIZE, true);

            foreach ($lld_rules_chunks as $lld_rules) {
                if ($chunks[$last]['size'] < self::INHERIT_CHUNK_SIZE) {
                    $_lld_rules = array_slice($lld_rules, 0, self::INHERIT_CHUNK_SIZE - $chunks[$last]['size'], true);

                    $new_lld_rules = array_diff_key($_lld_rules, $chunks[$last]['lld_rules']);
                    $can_add_lld_rules = true;

                    foreach ($chunks[$last]['host_indexes'] as $_i) {
                        if (array_intersect_key($lld_links[$hosts[$_i]['ruleid']], $new_lld_rules)) {
                            $can_add_lld_rules = false;
                            break;
                        }
                    }

                    if ($can_add_lld_rules) {
                        $chunks[$last]['host_indexes'][] = $i;
                        $chunks[$last]['lld_rules'] += $_lld_rules;
                        $chunks[$last]['size'] += count($_lld_rules);

                        $lld_rules = array_diff_key($lld_rules, $_lld_rules);
                    }
                }

                if ($lld_rules) {
                    $chunks[++$last] = [
                        'host_indexes' => [$i],
                        'lld_rules' => $lld_rules,
                        'size' => count($lld_rules),
                    ];
                }
            }
        }

        return $chunks;
    }

    /**
     * @param array $hosts
     * @param array $db_hosts
     * @param array $lld_links
     * @param array $ruleids
     */
    protected function inheritChunk(array $hosts, array $db_hosts, array $lld_links, array $ruleids): void
    {
        $hosts_to_link = [];
        $hosts_to_update = [];

        foreach ($hosts as $i => $host) {
            if (!array_key_exists($host['hostid'], $db_hosts)) {
                $hosts_to_link[] = $host;
            } else {
                $hosts_to_update[] = $host;
            }

            unset($hosts[$i]);
        }

        $ins_hosts = [];
        $upd_hosts = [];
        $upd_db_hosts = [];

        if ($hosts_to_link) {
            $upd_db_hosts = $this->getChildObjectsUsingName($hosts_to_link, $ruleids);

            if ($upd_db_hosts) {
                $upd_hosts = self::getUpdChildObjectsUsingName($hosts_to_link, $upd_db_hosts);
            }

            $ins_hosts = self::getInsChildObjects($hosts_to_link, $upd_db_hosts, $lld_links, $ruleids);
        }

        if ($hosts_to_update) {
            $_upd_db_hosts = self::getChildObjectsUsingTemplateId($hosts_to_update, $db_hosts, $ruleids);
            $_upd_hosts = self::getUpdChildObjectsUsingTemplateId($hosts_to_update, $db_hosts, $_upd_db_hosts);

            HostPrototypeForm::checkDuplicates($_upd_hosts, $_upd_db_hosts, true);

            $upd_hosts = array_merge($upd_hosts, $_upd_hosts);
            $upd_db_hosts += $_upd_db_hosts;
        }

        if ($upd_hosts) {
            $this->updateForce($upd_hosts, $upd_db_hosts);
        }

        if ($ins_hosts) {
            $this->createForce($ins_hosts);
        }

        $this->inherit(array_merge($upd_hosts, $ins_hosts), $upd_db_hosts);
    }

    /**
     * @param array $items
     * @param array $ruleids
     *
     * @return array
     */
    private function getChildObjectsUsingName(array $hosts, array $ruleids): array
    {
        $query = self::getInternalQuery();

        $query->select(['h.hostid', 'h.host', 'h.templateid', 'ruleid' => 'i.itemid', 'parent_ruleid' => 'i.templateid']);
        $query->andWhere(SqlHelper::whereIn('{{i}}.itemid', $ruleids))
            ->andWhere(SqlHelper::stringWhereIn('{{h}}.host', array_unique(array_column($hosts, 'host'))));

        $upd_db_hosts = [];
        $parent_indexes = [];

        foreach ($query->each() as $row) {
            foreach ($hosts as $i => $host) {
                if (bccomp($row['parent_ruleid'], $host['ruleid']) == 0 && $row['host'] === $host['host']) {
                    $upd_db_hosts[$row['hostid']] = $row;
                    $parent_indexes[$row['hostid']] = $i;
                }
            }
        }

        if (!$upd_db_hosts) {
            return [];
        }

        $query = Hosts::find()
            ->alias('h')
            ->leftJoin(['hi' => HostInventory::tableName()], '{{h}}.hostid={{hi}}.hostid')
            ->where(SqlHelper::whereIn('{{h}}.hostid', array_keys($upd_db_hosts)))
            ->select(['h.uuid', 'h.hostid', 'h.host', 'h.name', 'h.custom_interfaces', 'h.status', 'h.discover', 'inventory_mode' => new Expression('COALESCE(hi.inventory_mode, -1)')]);

        $rows = $query->all();
        foreach ($rows as $row) {
            $upd_db_hosts[$row['hostid']] = $row + $upd_db_hosts[$row['hostid']];
        }

        $_upd_hosts = [];

        foreach ($upd_db_hosts as $upd_db_host) {
            $host = $hosts[$parent_indexes[$upd_db_host['hostid']]];

            $_upd_hosts[] = [
                'hostid' => $upd_db_host['hostid'],
                'custom_interfaces' => $host['custom_interfaces'],
                'interfaces' => [],
                'groupLinks' => [],
                'groupPrototypes' => [],
                'templates' => [],
                'tags' => [],
                'macros' => [],
            ];
        }

        $this->addAffectedObjects($_upd_hosts, $upd_db_hosts);

        return $upd_db_hosts;
    }

    /**
     * @param array $hosts
     * @param array $upd_db_hosts
     *
     * @return array
     */
    private static function getUpdChildObjectsUsingName(array $hosts, array $upd_db_hosts): array
    {
        $parent_indexes = [];

        foreach ($hosts as $i => &$host) {
            $parent_indexes[$host['ruleid']][$host['host']] = $i;
        }
        unset($host);

        $upd_hosts = [];

        foreach ($upd_db_hosts as $upd_db_host) {
            $host = $hosts[$parent_indexes[$upd_db_host['parent_ruleid']][$upd_db_host['host']]];

            $upd_host = [
                'uuid' => '',
                'hostid' => $upd_db_host['hostid'],
                'templateid' => $host['hostid'],
                'ruleid' => $upd_db_host['ruleid'],
                'host_status' => $upd_db_host['host_status'],
            ];

            self::addInheritedFields($upd_host, $host, $upd_db_host);

            $upd_host += [
                'interfaces' => [],
                'groupLinks' => [],
                'groupPrototypes' => [],
                'templates' => [],
                'tags' => [],
                'macros' => [],
            ];

            $upd_hosts[] = $upd_host;
        }

        return $upd_hosts;
    }

    /**
     * @param array $hosts
     * @param array $upd_db_items
     * @param array $lld_links
     * @param array $ruleids
     *
     * @return array
     */
    private static function getInsChildObjects(array $hosts, array $upd_db_hosts, array $lld_links, array $ruleids): array
    {
        $ins_hosts = [];

        $upd_host_names = [];

        foreach ($upd_db_hosts as $upd_db_host) {
            $upd_host_names[$upd_db_host['ruleid']][] = $upd_db_host['host'];
        }

        foreach ($hosts as $host) {
            foreach ($lld_links[$host['ruleid']] as $lld_rule) {
                if (
                    !in_array($lld_rule['itemid'], $ruleids)
                    || (array_key_exists($lld_rule['itemid'], $upd_host_names)
                        && in_array($host['name'], $upd_host_names[$lld_rule['itemid']]))
                ) {
                    continue;
                }

                $ins_host = [
                    'uuid' => '',
                    'templateid' => $host['hostid'],
                    'ruleid' => $lld_rule['itemid'],
                    'host_status' => $lld_rule['host_status'],
                ];

                self::addInheritedFields($ins_host, $host);

                $ins_hosts[] = $ins_host;
            }
        }

        return $ins_hosts;
    }

    /**
     * @param array $hosts
     * @param array $db_hosts
     * @param array $ruleids
     *
     * @return array
     */
    private function getChildObjectsUsingTemplateId(array $hosts, array $db_hosts, array $ruleids): array
    {
        $query = $this->getQuery();
        $query->andWhere(['h.templateid' => array_column($hosts, 'hostid')])
            ->andWhere(['hd.parent_itemid' => $ruleids]);
        $query->select(['h.hostid', 'h.host', 'h.name', 'h.custom_interfaces', 'h.status', 'h.discover']);
        
        $query->indexBy('hostid');
        $upd_db_hosts = $query->all();

        foreach($upd_db_hosts as &$upd_db_host) {
           $upd_db_host['inventory_mode'] = HOST_INVENTORY_DISABLED; 
        }
        unset($upd_db_host);

        self::addInternalFields($upd_db_hosts);

        $parent_indexes = array_flip(array_column($hosts, 'hostid'));

        $_upd_hosts = [];

        foreach ($upd_db_hosts as $upd_db_host) {
            $host = $hosts[$parent_indexes[$upd_db_host['templateid']]];
            $db_host = $db_hosts[$upd_db_host['templateid']];

            $_upd_host = [
                'hostid' => $upd_db_host['hostid'],
                'custom_interfaces' => $host['custom_interfaces'],
            ];

            $_upd_host += array_intersect_key([
                'interfaces' => [],
                'groupLinks' => [],
                'groupPrototypes' => [],
                'templates' => [],
                'tags' => [],
                'macros' => [],
            ], $db_host);

            $_upd_hosts[] = $_upd_host;
        }

        $this->addAffectedObjects($_upd_hosts, $upd_db_hosts);

        return $upd_db_hosts;
    }

    /**
     * @param array $hosts
     * @param array $db_hosts
     * @param array $upd_db_hosts
     *
     * @return array
     */
    private static function getUpdChildObjectsUsingTemplateId(array $hosts, array $db_hosts, array $upd_db_hosts): array
    {
        $parent_indexes = array_flip(array_column($hosts, 'hostid'));

        $upd_hosts = [];

        foreach ($upd_db_hosts as $upd_db_host) {
            $upd_host = array_intersect_key(
                $upd_db_host,
                array_flip(['hostid', 'ruleid', 'host_status'])
            );
            $host = $hosts[$parent_indexes[$upd_db_host['templateid']]];
            $db_host = $db_hosts[$host['hostid']];

            self::addInheritedFields($upd_host, $host, $upd_db_host, $db_host);

            $upd_hosts[] = $upd_host;
        }

        return $upd_hosts;
    }

    /**
     * @param array      $inh_host
     * @param array      $host
     * @param array|null $inh_db_host
     * @param array|null $db_host
     */
    private static function addInheritedFields(array &$inh_host, array $host, array $inh_db_host = null, array $db_host = null): void
    {
        $inh_host += array_intersect_key(
            $host,
            array_flip(['host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode'])
        );

        if (array_key_exists('interfaces', $host)) {
            $inh_host['interfaces'] = [];

            foreach ($host['interfaces'] as $interface) {
                $inh_host['interfaces'][] = array_diff_key($interface, array_flip(['interfaceid']));
            }
        }

        if (array_key_exists('groupLinks', $host)) {
            $inh_host['groupLinks'] = [];

            foreach ($host['groupLinks'] as $group_link) {
                $inh_host['groupLinks'][] = [
                    'groupid' => $group_link['groupid'],
                    'templateid' => $group_link['group_prototypeid'],
                ];
            }
        }

        if (array_key_exists('groupPrototypes', $host)) {
            $inh_host['groupPrototypes'] = [];

            $inh_group_prototypeids = $inh_db_host !== null
            ? array_column($inh_db_host['groupPrototypes'], 'group_prototypeid', 'name')
            : [];

            foreach ($host['groupPrototypes'] as $group_prototype) {
                if ($db_host === null) {
                    $name = $group_prototype['name'];
                } else {
                    $name = array_key_exists($group_prototype['group_prototypeid'], $db_host['groupPrototypes'])
                    ? $db_host['groupPrototypes'][$group_prototype['group_prototypeid']]['name']
                    : $group_prototype['name'];
                }

                $inh_group_prototype = [
                    'name' => $group_prototype['name'],
                    'templateid' => $group_prototype['group_prototypeid'],
                ];

                if (array_key_exists($name, $inh_group_prototypeids)) {
                    $inh_group_prototype = ['group_prototypeid' => $inh_group_prototypeids[$name]]
                         + $inh_group_prototype;
                }

                $inh_host['groupPrototypes'][] = $inh_group_prototype;
            }
        }

        if (array_key_exists('templates', $host)) {
            $inh_host['templates'] = [];

            foreach ($host['templates'] as $template) {
                $inh_host['templates'][] = array_diff_key($template, array_flip(['hosttemplateid']));
            }
        }

        if (array_key_exists('tags', $host)) {
            $inh_host['tags'] = [];

            foreach ($host['tags'] as $tag) {
                $inh_host['tags'][] = array_diff_key($tag, array_flip(['hosttagid']));
            }
        }

        if (array_key_exists('macros', $host)) {
            $inh_host['macros'] = [];

            $inh_hostmacroids = $inh_db_host !== null
            ? array_column($inh_db_host['macros'], 'hostmacroid', 'macro')
            : [];

            foreach ($host['macros'] as $host_macro) {
                if ($db_host === null) {
                    $macro = $host_macro['macro'];
                } else {
                    $macro = array_key_exists($host_macro['hostmacroid'], $db_host['macros'])
                    ? $db_host['macros'][$host_macro['hostmacroid']]['macro']
                    : $host_macro['macro'];
                }

                if (array_key_exists($macro, $inh_hostmacroids)) {
                    $inh_host['macros'][] = ['hostmacroid' => $inh_hostmacroids[$macro]] + $host_macro;
                } else {
                    $inh_host['macros'][] = array_diff_key($host_macro, array_flip(['hostmacroid']));
                }
            }
        }
    }

    /**
     * Add the internally used fields to the given $db_hosts.
     *
     * @param array $db_hosts
     */
    private static function addInternalFields(array &$db_hosts): void
    {
        $query = self::getInternalQuery();

        $query->andWhere(SqlHelper::whereIn('{{h}}.hostid', array_keys($db_hosts)));

        $query->select([
            'h.hostid',
            'h.templateid',
            'ruleid' => 'hd.parent_itemid',
            'host_status' => 'hh.status',
        ]);

        foreach ($query->each() as $row) {
            $db_hosts[$row['hostid']] += $row;
        }
    }

    private static function getInternalQuery(): Query
    {
        $query = new Query();
        $query->from([
            'h' => Hosts::tableName(),
            'hd' => HostDiscovery::tableName(),
            'i' => Items::tableName(),
            'hh' => Hosts::tableName(),
        ]);

        $query->where('hd.hostid=h.hostid')
            ->andWhere('i.itemid=hd.parent_itemid')
            ->andWhere('i.hostid=hh.hostid');

        return $query;
    }

    /**
     * Add host_status property to given host prototypes based on given LLD rules.
     *
     * @param array $items
     * @param array $db_lld_rules
     */
    private static function addHostStatus(array &$hosts, array $db_lld_rules): void
    {
        foreach ($hosts as &$host) {
            $host['host_status'] = $db_lld_rules[$host['ruleid']]['host_status'];
        }
        unset($host);
    }

    /**
     * Assign given flags value to the flags property of given host prototypes.
     *
     * @param array $hosts
     * @param int   $flags
     */
    private static function addFlags(array &$hosts, int $flags): void
    {
        foreach ($hosts as &$host) {
            $host['flags'] = $flags;
        }
        unset($host);
    }

    /**
     * Check if host groups links are valid.
     *
     * @param array      $hosts
     * @param array|null $db_hosts
     *
     * @throws ValidateException
     */
    private static function checkGroupLinks(array $hosts, array $db_hosts = null): void
    {
        $edit_groupids = [];

        foreach ($hosts as $host) {
            if (!array_key_exists('groupLinks', $host)) {
                continue;
            }

            $groupids = array_column($host['groupLinks'], 'groupid');

            if ($db_hosts === null) {
                $edit_groupids += array_flip($groupids);
            } else {
                $db_groupids = array_column($db_hosts[$host['hostid']]['groupLinks'], 'groupid');

                $ins_groupids = array_flip(array_diff($groupids, $db_groupids));
                $del_groupids = array_flip(array_diff($db_groupids, $groupids));

                $edit_groupids += $ins_groupids + $del_groupids;
            }
        }

        if (!$edit_groupids) {
            return;
        }

        $db_groups = GroupHelper::getHostGroups([
            'output' => ['name', 'flags'],
            'groupids' => array_keys($edit_groupids),
            'editable' => true,
            'preservekeys' => true,
        ]);
        if (count($db_groups) != count($edit_groupids)) {
            self::exception(60750101, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        // Check if group prototypes use discovered host groups.
        foreach ($db_groups as $db_group) {
            if ($db_group['flags'] == PRS_FLAG_DISCOVERY_CREATED) {
                self::exception(
                    60750101,
                    t('zapi', 'Group prototype cannot be based on a discovered host group "{name}".', ['name' => $db_group['name']])
                );
            }
        }
    }

    /**
     * Check if main interfaces are correctly set for every interface type. Each host must either have only one main
     * interface for each interface type, or have no interface of that type at all.
     *
     * @param array $hosts
     *
     * @throws ValidateException if two main or no main interfaces are given.
     */
    private static function checkMainInterfaces(array $hosts): void
    {
        foreach ($hosts as $i => $host) {
            if (
                $host['custom_interfaces'] != HOST_PROT_INTERFACES_CUSTOM
                || !array_key_exists('interfaces', $host) || !$host['interfaces']
            ) {
                continue;
            }

            $primary_interfaces = [];
            $path = '/' . ($i + 1) . '/interfaces';

            foreach ($host['interfaces'] as $interface) {
                if (!array_key_exists($interface['type'], $primary_interfaces)) {
                    $primary_interfaces[$interface['type']] = 0;
                }

                if ($interface['main'] == INTERFACE_PRIMARY) {
                    $primary_interfaces[$interface['type']]++;
                }

                if ($primary_interfaces[$interface['type']] > 1) {
                    self::exception(60750501, t('zapi', 'Invalid parameter {parameter}, {error}', [
                        'parameter' => $path,
                        'error' => t('zapi', 'cannot have more than one default interface of the same type'),
                    ]));
                }
            }

            foreach ($primary_interfaces as $type => $count) {
                if ($count == 0) {
                    self::exception(60750501, t('zapi', 'Invalid parameter {parameter}, {error}', [
                        'parameter' => $path,
                        'error' => t('zapi', 'no default interface for "{type}" type', ['type' => HostHelper::hostInterfaceTypeNumToName($type)]),
                    ]));
                }
            }
        }
    }

    /**
     * @param array $hosts
     * @param bool  $inherited
     */
    protected function createForce(array &$hosts, bool $inherited = false): void
    {
        self::addFlags($hosts, PRS_FLAG_DISCOVERY_PROTOTYPE);

        $hostids = DB::insert('hosts', $hosts);

        $host_statuses = [];

        foreach ($hosts as &$host) {
            $host['hostid'] = array_shift($hostids);

            $host_statuses[] = $host['host_status'];
            unset($host['host_status'], $host['flags']);
        }
        unset($host);

        if (!$inherited) {
            $this->checkTemplatesLinks($hosts);
        }

        self::createHostDiscoveries($hosts);

        self::updateInterfaces($hosts);
        self::updateGroupLinks($hosts);
        self::updateGroupPrototypes($hosts);
        $this->updateTemplates($hosts);
        $this->updateTags($hosts);
        $this->updateMacros($hosts);
        self::updateHostInventories($hosts);

        // zbx audit

        foreach ($hosts as &$host) {
            $host['host_status'] = array_shift($host_statuses);
        }
        unset($host);
    }

    /**
     * @param array $hosts
     *
     * @return Result
     */
    public function update(array $hosts): Result
    {
        $db_hosts = [];
        $this->validateUpdate($hosts, $db_hosts);

        $hostids = array_column($hosts, 'hostid');

        $this->updateForce($hosts, $db_hosts);
        $this->inherit($hosts, $db_hosts);

        return $this->success(['hostids' => $hostids], Yii::t('msg', 'Update Success'));
    }

    /**
     * @param array      $hosts
     * @param array|null $db_hosts
     *
     * @throws APIException
     */
    protected function validateUpdate(array &$hosts, array &$db_hosts = null)
    {
        $rules = [
            'hostid' => [IdValidator::class, 'flags' => API_REQUIRED],
            'groupPrototypes' => [
                ObjectsValidator::class,
                'flags' => API_NORMALIZE | API_ALLOW_UNEXPECTED,
                'uniq' => [['group_prototypeid']],
                'fields' => [
                    'group_prototypeid' => [IdValidator::class],
                ],
            ],
        ];
        if (!ValidateHelper::validateObjects($hosts, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['hostid']]], $error)) {
            self::exception(10000026, $error);
        }

        $db_hosts = Hosts::find()
            ->select(['uuid', 'hostid', 'host', 'name', 'custom_interfaces', 'status', 'discover'])
            ->where(SqlHelper::whereIn('hostid', array_column($hosts, 'hostid')))
            ->andWhere(['flags' => PRS_FLAG_DISCOVERY_PROTOTYPE])
            ->asArray()
            ->indexBy('hostid')
            ->all();
        if ($db_hosts) {
            $inventories = (new query())
                ->from('host_inventory')
                ->where(SqlHelper::whereIn('hostid', array_keys($db_hosts)))
                ->select('inventory_mode')
                ->indexBy('hostid')
                ->column();
            foreach ($db_hosts as &$db_host) {
                $db_host['inventory_mode'] = $inventories[$db_host['hostid']] ?? HOST_INVENTORY_DISABLED;
            }
            unset($db_host);
        }

        if (count($db_hosts) != count($hosts)) {
            self::exception(60750101, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        self::addInternalFields($db_hosts);
        self::addAffectedGroupPrototypes($hosts, $db_hosts);

        foreach ($hosts as $i => &$host) {
            $db_host = $db_hosts[$host['hostid']];
            $host['host_status'] = $db_host['host_status'];

            if ($db_host['templateid'] == 0) {
                $host += array_intersect_key($db_host, array_flip(['custom_interfaces']));

                $api_input_rules = HostPrototypeForm::getValidationRules('update');
            } else {
                $api_input_rules = HostPrototypeForm::getInheritedValidationRules();
            }

            $path = '/' . ($i + 1);
            if (!ValidateHelper::validateObject($host, $api_input_rules['fields'], ['_path' => $path], $error)) {
                self::exception(60750101, $error);
            }

            self::validateGroupPrototypes($host, $db_host, $path . '/groupPrototypes');
        }
        unset($host);

        $hosts = $this->extendObjectsByKey($hosts, $db_hosts, 'hostid', ['custom_interfaces', 'ruleid']);

        self::validateUniqueness($hosts);

        $this->addAffectedObjects($hosts, $db_hosts);

        self::checkUuidDuplicates($hosts, $db_hosts);
        self::checkDuplicates($hosts, $db_hosts);
        self::checkMainInterfaces($hosts);
        self::checkGroupLinks($hosts, $db_hosts);
        $this->checkTemplates($hosts, $db_hosts);
        $this->checkTemplatesLinks($hosts, $db_hosts);
        $hosts = parent::validateHostMacros($hosts, $db_hosts);
    }

    /**
     * @param array  $host
     * @param array  $db_host
     * @param string $path
     *
     * @throws APIException
     */
    private static function validateGroupPrototypes(array &$host, array $db_host, string $path): void
    {
        if (!array_key_exists('groupPrototypes', $host)) {
            return;
        }

        foreach ($host['groupPrototypes'] as $i => &$group_prototype) {
            if (array_key_exists('group_prototypeid', $group_prototype)) {
                if (!array_key_exists($group_prototype['group_prototypeid'], $db_host['groupPrototypes'])) {
                    self::exception(60750501, t('zapi', 'Invalid parameter {parameter}, {error}', [
                        'parameter' => $path . '/' . ($i + 1),
                        'error' => t('zapi', 'object does not exist or belongs to another object'),
                    ]));
                }

                $db_group_prototype = $db_host['groupPrototypes'][$group_prototype['group_prototypeid']];

                $group_prototype += array_intersect_key($db_group_prototype, array_flip(['name']));

                $api_input_rules = HostPrototypeForm::getGroupPrototypeValidationFields(true);
            } else {
                $api_input_rules = HostPrototypeForm::getGroupPrototypeValidationFields();
            }

            if (!ValidateHelper::validateObject($group_prototype, $api_input_rules, ['_path' => $path . '/' . ($i + 1)], $error)) {
                self::exception(60750101, $error);
            }
        }
        unset($group_prototype);

        $api_input_rules = [
            'name' => [HostGroupNameValidator::class]
        ];

        if (!ValidateHelper::validateObjects($host['groupPrototypes'], $api_input_rules, ['_path' => $path, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['name']]], $error)) {
            self::exception(60750101, $error);
        }
    }

    /**
     * @param array $hosts
     *
     * @throws APIException
     */
    private static function validateUniqueness(array &$hosts): void
    {
        $api_input_rules = [
            'uuid' => ['safe'],
            'ruleid' => ['safe'],
            'host' => ['safe'],
            'name' => ['safe'],
        ];

        if (!ValidateHelper::validateObjects($hosts, $api_input_rules, ['uniq' => [['uuid'], ['ruleid', 'host'], ['ruleid', 'name']], 'flags' => API_ALLOW_UNEXPECTED], $error)) {
            self::exception(60750101, $error);
        }
    }

    /**
     * @param array $hosts
     * @param array $db_hosts
     */
    private function updateForce(array &$hosts, array &$db_hosts): void
    {
        // Helps to avoid deadlocks.
        CArrayHelper::sort($hosts, ['hostid', 'order' => PRS_SORT_DOWN]);

        $upd_hosts = [];
        $upd_hostids = [];

        $internal_fields = array_flip(['hostid', 'custom_interfaces', 'ruleid']);
        $inventory_fields = array_flip(['inventory_mode']);
        $nested_object_fields = array_flip(
            ['interfaces', 'groupLinks', 'groupPrototypes', 'templates', 'tags', 'macros']
        );

        foreach ($hosts as $i => &$host) {
            $upd_host = DB::getUpdatedValues(Hosts::tableName(), $host, $db_hosts[$host['hostid']]);

            if ($upd_host) {
                $upd_hosts[] = [
                    'values' => $upd_host,
                    'where' => ['hostid' => $host['hostid']],
                ];

                $upd_hostids[$i] = $host['hostid'];
            }

            $host = array_intersect_key(
                $host,
                $internal_fields + $upd_host + $nested_object_fields + $inventory_fields
            );
        }
        unset($host);

        if ($upd_hosts) {
            DB::update('hosts', $upd_hosts);
        }

        self::updateInterfaces($hosts, $db_hosts, $upd_hostids);
        self::updateGroupLinks($hosts, $db_hosts, $upd_hostids);
        self::updateGroupPrototypes($hosts, $db_hosts, $upd_hostids);
        $this->updateTemplates($hosts, $db_hosts, $upd_hostids);
        $this->updateTags($hosts, $db_hosts, $upd_hostids);
        $this->updateMacros($hosts, $db_hosts, $upd_hostids);
        self::updateHostInventories($hosts, $db_hosts, $upd_hostids);

        $hosts = array_intersect_key($hosts, $upd_hostids);
        $db_hosts = array_intersect_key($db_hosts, array_flip($upd_hostids));

        // zbx audit
    }

    /**
     * @param array      $hosts
     * @param array|null $db_hosts
     * @param array|null $upd_hostids
     */
    private static function updateInterfaces(array &$hosts, array &$db_hosts = null, array &$upd_hostids = null): void
    {
        $ins_interfaces = [];
        $del_interfaceids = [];

        foreach ($hosts as $i => &$host) {
            $update = false;

            if ($db_hosts === null) {
                if (
                    $host['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM && array_key_exists('interfaces', $host)
                    && $host['interfaces']
                ) {
                    $update = true;
                }
            } else {
                if (!array_key_exists('custom_interfaces', $db_hosts[$host['hostid']])) {
                    continue;
                }

                if ($host['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
                    if (array_key_exists('interfaces', $host)) {
                        $update = true;
                    }
                } elseif (
                    $db_hosts[$host['hostid']]['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM
                    && $db_hosts[$host['hostid']]['interfaces']
                ) {
                    $update = true;
                    $host['interfaces'] = [];
                }
            }

            if (!$update) {
                continue;
            }

            $changed = false;
            $db_interfaces = ($db_hosts !== null) ? $db_hosts[$host['hostid']]['interfaces'] : [];

            foreach ($host['interfaces'] as &$interface) {
                $db_interfaceid = self::getInterfaceId($interface, $db_interfaces);

                if ($db_interfaceid !== null) {
                    $interface['interfaceid'] = $db_interfaceid;
                    unset($db_interfaces[$db_interfaceid]);
                } else {
                    $ins_interfaces[] = ['hostid' => $host['hostid']] + $interface;
                    $changed = true;
                }
            }
            unset($interface);

            if ($db_interfaces) {
                $del_interfaceids = array_merge($del_interfaceids, array_keys($db_interfaces));
                $changed = true;
            }

            if ($db_hosts !== null) {
                if ($changed) {
                    $upd_hostids[$i] = $host['hostid'];
                } else {
                    unset($host['interfaces'], $db_hosts[$host['hostid']]['interfaces']);
                }
            }
        }
        unset($host);

        if ($del_interfaceids) {
            DB::delete('interface_snmp', ['interfaceid' => $del_interfaceids]);
            DB::delete('interface', ['interfaceid' => $del_interfaceids]);
        }

        if ($ins_interfaces) {
            $interfaceids = DB::insert('interface', $ins_interfaces);
        }

        $ins_interfaces_snmp = [];

        foreach ($hosts as &$host) {
            if (!array_key_exists('interfaces', $host)) {
                continue;
            }

            foreach ($host['interfaces'] as &$interface) {
                if (!array_key_exists('interfaceid', $interface)) {
                    $interface['interfaceid'] = array_shift($interfaceids);

                    if ($interface['type'] == INTERFACE_TYPE_SNMP) {
                        $ins_interfaces_snmp[] = ['interfaceid' => $interface['interfaceid']] + $interface['details'];
                    }
                }
            }
            unset($interface);
        }
        unset($host);

        if ($ins_interfaces_snmp) {
            DB::insert('interface_snmp', $ins_interfaces_snmp, false);
        }
    }

    /**
     * Get the ID of interface if all fields of given interface are equal to all fields of one of existing interfaces.
     *
     * @param array $interface
     * @param array $db_interfaces
     *
     * @return string|null
     */
    private static function getInterfaceId(array $interface, array $db_interfaces): ?string
    {
        $def_interface = array_intersect_key(DB::getDefaults(Interfaces::tableName()), array_flip(['ip', 'dns']));
        $def_details = array_intersect_key(DB::getDefaults(InterfaceSnmp::tableName()), array_flip([
            'bulk',
            'community',
            'max_repetitions',
            'contextname',
            'securityname',
            'securitylevel',
            'authprotocol',
            'authpassphrase',
            'privprotocol',
            'privpassphrase',
        ]));

        $interface += $def_interface;
        $details = array_key_exists('details', $interface) ? $interface['details'] : [];

        if ($interface['type'] == INTERFACE_TYPE_SNMP && array_key_exists('details', $interface)) {
            $details += $def_details;
        }

        foreach ($db_interfaces as $db_interface) {
            if (!DB::getUpdatedValues(Interfaces::tableName(), $interface, $db_interface)) {
                if ($interface['type'] == INTERFACE_TYPE_SNMP) {
                    if (!DB::getUpdatedValues(InterfaceSnmp::tableName(), $details, $db_interface['details'])) {
                        return $db_interface['interfaceid'];
                    }
                } else {
                    return $db_interface['interfaceid'];
                }
            }
        }

        return null;
    }

    /**
     * @param array      $hosts
     * @param array|null $db_hosts
     * @param array|null $upd_hostids
     */
    private static function updateGroupLinks(array &$hosts, array &$db_hosts = null, array &$upd_hostids = null): void
    {
        $ins_group_links = [];
        $upd_group_links = []; // Used to update templateid value upon inheritance.
        $del_group_prototypeids = [];

        foreach ($hosts as $i => &$host) {
            if (!array_key_exists('groupLinks', $host)) {
                continue;
            }

            $changed = false;
            $db_group_links = $db_hosts !== null
            ? array_column($db_hosts[$host['hostid']]['groupLinks'], null, 'groupid')
            : [];

            foreach ($host['groupLinks'] as &$group_link) {
                if (array_key_exists($group_link['groupid'], $db_group_links)) {
                    $group_link['group_prototypeid'] = $db_group_links[$group_link['groupid']]['group_prototypeid'];
                    $upd_group_link = DB::getUpdatedValues(
                        'group_prototype',
                        $group_link,
                        $db_group_links[$group_link['groupid']]
                    );

                    if ($upd_group_link) {
                        $upd_group_links[] = [
                            'values' => $upd_group_link,
                            'where' => ['group_prototypeid' => $group_link['group_prototypeid']],
                        ];
                        $changed = true;
                    }

                    unset($db_group_links[$group_link['groupid']]);
                } else {
                    $ins_group_links[] = ['hostid' => $host['hostid']] + $group_link;
                    $changed = true;
                }
            }
            unset($group_link);

            if ($db_group_links) {
                $del_group_prototypeids = array_merge(
                    $del_group_prototypeids,
                    array_column($db_group_links, 'group_prototypeid')
                );
                $changed = true;
            }

            if ($db_hosts !== null) {
                if ($changed) {
                    $upd_hostids[$i] = $host['hostid'];
                } else {
                    unset($host['groupLinks'], $db_hosts[$host['hostid']]['groupLinks']);
                }
            }
        }
        unset($host);

        if ($del_group_prototypeids) {
            self::deleteGroupPrototypes($del_group_prototypeids);
        }

        if ($upd_group_links) {
            DB::update(GroupPrototype::tableName(), $upd_group_links);
        }

        if ($ins_group_links) {
            $group_prototypeids = DB::insert(GroupPrototype::tableName(), $ins_group_links);
        }

        foreach ($hosts as &$host) {
            if (!array_key_exists('groupLinks', $host)) {
                continue;
            }

            foreach ($host['groupLinks'] as &$group_link) {
                if (!array_key_exists('group_prototypeid', $group_link)) {
                    $group_link['group_prototypeid'] = array_shift($group_prototypeids);
                }
            }
            unset($group_link);
        }
        unset($host);
    }

    /**
     * @param array      $hosts
     * @param array|null $db_hosts
     * @param array|null $upd_hostids
     */
    private static function updateGroupPrototypes(array &$hosts, array &$db_hosts = null, array &$upd_hostids = null): void
    {
        $ins_group_prototypes = [];
        $upd_group_prototypes = []; // Used to update templateid value upon inheritance.
        $del_group_prototypeids = [];

        foreach ($hosts as $i => &$host) {
            if (!array_key_exists('groupPrototypes', $host)) {
                continue;
            }

            $db_group_prototypes = ($db_hosts !== null) ? $db_hosts[$host['hostid']]['groupPrototypes'] : [];
            $changed = false;

            foreach ($host['groupPrototypes'] as &$group_prototype) {
                if (array_key_exists('group_prototypeid', $group_prototype)) {
                    $upd_group_prototype = DB::getUpdatedValues(
                        'group_prototype',
                        $group_prototype,
                        $db_group_prototypes[$group_prototype['group_prototypeid']]
                    );

                    if ($upd_group_prototype) {
                        $upd_group_prototypes[] = [
                            'values' => $upd_group_prototype,
                            'where' => ['group_prototypeid' => $group_prototype['group_prototypeid']],
                        ];
                        $changed = true;
                    }

                    unset($db_group_prototypes[$group_prototype['group_prototypeid']]);
                } else {
                    $ins_group_prototypes[] = ['hostid' => $host['hostid']] + $group_prototype;
                    $changed = true;
                }
            }
            unset($group_prototype);

            if ($db_group_prototypes) {
                $del_group_prototypeids = array_merge($del_group_prototypeids, array_keys($db_group_prototypes));
                $changed = true;
            }

            if ($db_hosts !== null) {
                if ($changed) {
                    $upd_hostids[$i] = $host['hostid'];
                } else {
                    unset($host['groupPrototypes'], $db_hosts[$host['hostid']]['groupPrototypes']);
                }
            }
        }
        unset($host);

        if ($del_group_prototypeids) {
            self::deleteGroupPrototypes($del_group_prototypeids);
        }

        if ($upd_group_prototypes) {
            DB::update(GroupPrototype::tableName(), $upd_group_prototypes);
        }

        if ($ins_group_prototypes) {
            $group_prototypeids = DB::insert(GroupPrototype::tableName(), $ins_group_prototypes);
        }

        foreach ($hosts as &$host) {
            if (!array_key_exists('groupPrototypes', $host)) {
                continue;
            }

            foreach ($host['groupPrototypes'] as &$group_prototype) {
                if (!array_key_exists('group_prototypeid', $group_prototype)) {
                    $group_prototype['group_prototypeid'] = array_shift($group_prototypeids);
                }
            }
            unset($group_prototype);
        }
        unset($host);
    }

    /**
     * @param array $hosts
     */
    private static function createHostDiscoveries(array $hosts): void
    {
        $host_discoveries = [];

        foreach ($hosts as $host) {
            $host_discoveries[] = [
                'hostid' => $host['hostid'],
                'parent_itemid' => $host['ruleid'],
            ];
        }

        if ($host_discoveries) {
            DB::insertBatch(HostDiscovery::tableName(), $host_discoveries, false);
        }
    }

    /**
     * @param array      $hosts
     * @param array|null $db_hosts
     * @param array|null $upd_hostids
     */
    private static function updateHostInventories(array $hosts, array $db_hosts = null, array &$upd_hostids = null): void
    {
        $ins_inventories = [];
        $upd_inventories = [];
        $del_hostids = [];

        foreach ($hosts as $i => $host) {
            if (!array_key_exists('inventory_mode', $host)) {
                continue;
            }

            $db_inventory_mode = ($db_hosts !== null)
            ? $db_hosts[$host['hostid']]['inventory_mode']
            : HOST_INVENTORY_DISABLED;

            if ($host['inventory_mode'] == $db_inventory_mode) {
                continue;
            }

            if ($host['inventory_mode'] == HOST_INVENTORY_DISABLED) {
                $del_hostids[] = $host['hostid'];
            } elseif ($db_inventory_mode != HOST_INVENTORY_DISABLED) {
                $upd_inventories = [
                    'values' => ['inventory_mode' => $host['inventory_mode']],
                    'where' => ['hostid' => $host['hostid']],
                ];
            } else {
                $ins_inventories[] = [
                    'hostid' => $host['hostid'],
                    'inventory_mode' => $host['inventory_mode'],
                ];
            }

            $upd_hostids[$i] = $host['hostid'];
        }

        if ($del_hostids) {
            DB::delete(HostInventory::tableName(), ['hostid' => $del_hostids]);
        }

        if ($upd_inventories) {
            DB::update(HostInventory::tableName(), $upd_inventories);
        }

        if ($ins_inventories) {
            DB::insertBatch(HostInventory::tableName(), $ins_inventories, false);
        }
    }

    /**
     * @param array $hosts
     * @param array $db_hosts
     */
    protected function addAffectedObjects(array $hosts, array &$db_hosts): void
    {
        self::addAffectedInterfaces($hosts, $db_hosts);
        self::addAffectedGroupLinks($hosts, $db_hosts);
        self::addAffectedGroupPrototypes($hosts, $db_hosts);
        parent::addAffectedObjects($hosts, $db_hosts);
    }

    /**
     * @param array $hosts
     * @param array $db_hosts
     */
    private static function addAffectedInterfaces(array $hosts, array &$db_hosts): void
    {
        $hostids = [];

        foreach ($hosts as $host) {
            if (!array_key_exists('custom_interfaces', $host)) {
                continue;
            }

            $db_custom_interfaces = $db_hosts[$host['hostid']]['custom_interfaces'];

            if ((array_key_exists('interfaces', $host) && $host['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM)
                || ($host['custom_interfaces'] != $db_custom_interfaces
                    && $db_custom_interfaces == HOST_PROT_INTERFACES_CUSTOM)
            ) {
                $hostids[] = $host['hostid'];
                $db_hosts[$host['hostid']]['interfaces'] = [];
            } elseif (array_key_exists('interfaces', $host)) {
                $db_hosts[$host['hostid']]['interfaces'] = [];
            }
        }

        if (!$hostids) {
            return;
        }

        $details_interfaces = [];

        $db_interfaces = Interfaces::find()->select(['interfaceid', 'hostid', 'main', 'type', 'useip', 'ip', 'dns', 'port'])
            ->where(SqlHelper::whereIn('hostid', $hostids))
            ->asArray()
            ->all();

        foreach ($db_interfaces as $db_interface) {
            $db_hosts[$db_interface['hostid']]['interfaces'][$db_interface['interfaceid']] =
                array_diff_key($db_interface, array_flip(['hostid'])) + ['details' => []];

            if ($db_interface['type'] == INTERFACE_TYPE_SNMP) {
                $details_interfaces[$db_interface['interfaceid']] = $db_interface['hostid'];
            }
        }

        if ($details_interfaces) {
            $db_details = InterfaceSnmp::find()
                ->select([
                    'interfaceid',
                    'version',
                    'bulk',
                    'community',
                    'securityname',
                    'securitylevel',
                    'authpassphrase',
                    'privpassphrase',
                    'authprotocol',
                    'privprotocol',
                    'contextname',
                    'max_repetitions',
                ])
                ->where(SqlHelper::whereIn('interfaceid', array_keys($details_interfaces)))
                ->asArray()
                ->all();

            foreach ($db_details as $db_detail) {
                $hostid = $details_interfaces[$db_detail['interfaceid']];
                $db_hosts[$hostid]['interfaces'][$db_detail['interfaceid']]['details'] =
                    array_diff_key($db_detail, array_flip(['interfaceid']));
            }
        }
    }

    /**
     * @param array $hosts
     * @param array $db_hosts
     */
    private static function addAffectedGroupLinks(array $hosts, array &$db_hosts): void
    {
        $hostids = [];

        foreach ($hosts as $host) {
            if (array_key_exists('groupLinks', $host)) {
                $hostids[] = $host['hostid'];
                $db_hosts[$host['hostid']]['groupLinks'] = [];
            }
        }

        if (!$hostids) {
            return;
        }

        $db_links = GroupPrototype::find()
            ->select(['group_prototypeid', 'hostid', 'groupid', 'templateid'])
            ->where(SqlHelper::whereIn('hostid', $hostids))
            ->andWhere(new Expression('groupid IS NOT NULL'))
            ->asArray()
            ->all();

        foreach ($db_links as $db_link) {
            $db_hosts[$db_link['hostid']]['groupLinks'][$db_link['group_prototypeid']] =
                array_diff_key($db_link, array_flip(['hostid']));
        }
    }

    /**
     * @param array $hosts
     * @param array $db_hosts
     */
    private static function addAffectedGroupPrototypes(array $hosts, array &$db_hosts): void
    {
        $hostids = [];

        foreach ($hosts as $host) {
            if (
                array_key_exists('groupPrototypes', $host)
                && !array_key_exists('groupPrototypes', $db_hosts[$host['hostid']])
            ) {
                $hostids[] = $host['hostid'];
                $db_hosts[$host['hostid']]['groupPrototypes'] = [];
            }
        }

        if (!$hostids) {
            return;
        }

        $db_links = GroupPrototype::find()
            ->select(['group_prototypeid', 'hostid', 'name', 'templateid'])
            ->where(SqlHelper::whereIn('hostid', $hostids))
            ->andWhere(new Expression('groupid IS NULL'))
            ->asArray()
            ->all();

        foreach ($db_links as $db_link) {
            $db_hosts[$db_link['hostid']]['groupPrototypes'][$db_link['group_prototypeid']] =
                array_diff_key($db_link, array_flip(['hostid']));
        }
    }

    /**
     * 批量启用/禁用
     *
     * @param  array  $params
     * @return Result
     */
    public function mass(array $params): Result
    {
        $requestData = new HostPrototypeMassRequestData(['data' => $params]);
        if (!$requestData->isSuccess()) {
            return $requestData->getResult();
        }
        return $this->update($requestData->getData());
    }
}
