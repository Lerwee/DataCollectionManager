<?php

namespace app\customs\zapi\services;

use app\common\base\BaseService;
use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\SettingHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\managers\HostGroupManager;
use app\customs\zapi\common\validators\HostGroupNameValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\UuidValidator;
use app\customs\zapi\forms\HostGroupForm;
use app\customs\zapi\models\search\GroupSearch;
use app\modules\libzbx\models\zbx\GroupPrototype;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\HostsGroups;
use app\modules\libzbx\models\zbx\Hstgrp;
use phpDocumentor\Reflection\DocBlock\Tags\Throws;
use Yii;
use yii\base\Exception;
use yii\db\Expression;
use yii\db\Query;

/**
 * 主机分组
 *
 * @package app\customs\zapi\services
 */
class HostGroupService extends BaseService
{
    public function getList(): Result
    {
        return $this->success();
    }

    /**
     * @see createByInternal()
     * @param array $params
     * @return Result
     */
    public function create(array $params): Result
    {
        return $this->createByInternal($params);
    }

    /**
     * 新增(内部调用)
     *
     * NOTE: 超管创建，不对分组添加关联的用户权限
     *
     * Example
     * ```php
     * // insert one data
     *  $params = ['name' => 'os-linux'];
     * HostGroupService::instance()->createByInternal($params);
     * // insert batch data
     * $params = [
     *     [
     *         'name' => 'os-linux',
     *     ],
     *     [
     *         'name' => 'os-windows',
     *     ]
     * ];
     * HostGroupService::instance()->createByInternal($params);
     * ```
     * 
     * @param array $params
     * @return Result
     */
    public function createByInternal(array $params): Result
    {
        $names = [];
        $rows = to_array($params);
        $form = new HostGroupForm();

        foreach ($rows as &$row) {
            $form->clearAttributes();
            $form->generateUUID();
            $form->setAttributes($row);
            if (!$form->validate()) {
                return $this->error($form->getFirstFilterError());
            }

            $names['name'][] = $form->name;
            $names['uuid'][] = $form->uuid;
            $row = $form->getAttributes();
        }

        if ($duplicate = ArrayHelper::findDuplicate($rows, 'name')) {
            return $this->error(10000026, Yii::t('yii', '{attribute} "{value}" has already been taken.', [
                'attribute' => 'name',
                'value' => $duplicate['name']
            ]));
        }

        foreach ($names as $field => $sets) {
            // 检查主机分组名称名称是否存在
            if ($check = HostGroupForm::getOneByValues($sets, $field)) {
                $msg = Yii::t('yii', '{attribute} "{value}" has already been taken.', [
                    'attribute' => $field,
                    'value' => $check[$field]
                ]);
                return $this->error(10000026, $msg);
            }
        }

        $groupIds = DB::insertBatch(Hstgrp::tableName(), $rows);

        // 超管创建忽略添加分组权限

        // 添加审计
        $msg = Yii::t('msg', '{name} created successfully', [
            'name' => Yii::t('app', 'Group')
        ]);
        foreach ($rows as $i => $row) {
            $details = [
                audit_detail('name', '', $row['name'])
            ];
            $this->auditAdd(RESOURCE_ZAPI, [$groupIds[$i] => $row['name']], $msg, $details);
        }

        return $this->success(['groupids' => $groupIds], $msg);
    }

    /**
     * @see updateByInternal()
     *
     * @param array $params
     * @return Result
     */
    public function update(array $params): Result
    {
        return $this->updateByInternal($params);
    }

    /**
     * 更新(内部调用)
     *
     * @param array $params
     * @return Result
     */
    public function updateByInternal(array $params): Result
    {
        $rules = [
            [
                ObjectsValidator::class, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => ['groupid', 'name', 'uuid'],
                'fields' => [
                    'groupid' => [IdValidator::class],
                    'name' => [HostGroupNameValidator::class, 'length' => DB::getFieldLength(Hstgrp::tableName(), 'name')],
                    'uuid' => [UuidValidator::class],
                ]
            ],
        ];

        $data = [
            to_array($params)
        ];

        $bool = ValidateHelper::validateObject($data, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE], $error);
        if (!$bool) {
            return $this->error(error_code(10000021), $error);
        }
        $params = reset($data);
        $groupIds = array_column($params, 'groupid');
        $groups = Hstgrp::find()
            ->where(SqlHelper::whereIn('groupid', $groupIds))
            ->indexBy('groupid')
            ->asArray()
            ->all();

        if (empty($groups) || count($groups) != count($params)) {
            return $this->error(10000404);
        }

        $table = Hstgrp::tableName();

        $names = [];
        foreach ($params as $param) {
            if (array_key_exists('name', $param)) {
                if ($param['name'] !== $groups[$param['groupid']]['name']) {
                    $names['name'][] = $param['name'];
                }
            }
            if (array_key_exists('uuid', $param)) {
                if ($param['uuid'] !== $groups[$param['groupid']]['uuid']) {
                    $names['uuid'][] = $param['uuid'];
                    $details[$param['groupid']][] = audit_detail('uuid', $groups[$param['groupid']]['uuid'], $param['uuid'], $table);
                }
            }
        }

        $form = new HostGroupForm();
        foreach ($names as $field => $values) {
            if ($names && ($diff = $form->getOneByValues($values, $field))) {
                return $this->error(10000026, Yii::t('yii', '{attribute} "{value}" has already been taken.', [
                    'attribute' => $field,
                    'value' => $diff[$field]
                ]));
            }
        }

        $updates = [];
        $details = [];
        $resources = [];
        foreach ($params as $param) {
            $groupId = $param['groupid'];
            $updateData = DB::getUpdatedValues($table, $param, $groups[$groupId]);
            if ($updateData) {
                if (array_key_exists('name', $updateData)) {
                    $details[$groupId][] = audit_detail('name', $groups[$groupId]['name'], $updateData['name'], $table);
                    $resources[$groupId] = [$groupId => $updateData['name']];
                } else {
                    $resources[$groupId] = [$groupId => $groups[$groupId]['name']];
                }

                if (array_key_exists('uuid', $updateData)) {
                    $details[$param['groupid']][] = audit_detail('uuid', $groups[$param['groupid']]['uuid'], $updateData['uuid'], $table);
                }
                $updates[] = [
                    'values' => $updateData,
                    'where' => ['groupid' => (int)$param['groupid']]
                ];
            }
        }


        if ($updates) {
            DB::update($table, $updates);
        }

        $msg = Yii::t('msg', '{name} updated successfully', [
            'name' => Yii::t('app', 'Group')
        ]);

        // foreach ($resources as $id => $resource) {
        //     $this->auditUpdate(RESOURCE_ZAPI, $resource, $msg, $details[$id]);
        // }

        return $this->success(['groupids' => $groupIds], $msg);
    }

    /**
     * @see deleteByInternal()
     * 
     * @param array $ids
     * @return Result
     */
    public function delete(array $ids): Result
    {
        return $this->deleteByInternal($ids);
    }

    /**
     * 删除(内部调用)
     * 
     * @param array $ids
     * @return Result
     */
    public function deleteByInternal(array $ids): Result
    {
        $models = Hstgrp::find()->where(SqlHelper::whereIn('groupid', $ids))->indexBy('groupid')->asArray()->all();
        if (empty($models)) {
            return $this->error(10000404);
        }
        $id2name = array_column($models, 'name', 'groupid');
        $ids = array_column($models, 'groupid');

        $discoveryGroupId = SettingHelper::get(SettingHelper::DISCOVERY_GROUPID);
        if (array_key_exists($discoveryGroupId, $models)) {
            $msg = t('zapi', 'Host group "{name}" is group for discovered hosts and cannot be deleted.', [
                'name' => $models[$discoveryGroupId]['name']
            ]);
            return $this->error(error_code(10000003, ['name' => Yii::t('app', 'Group')], $msg));
        }

        // Check if a group is used by a host prototype.
        $query = GroupPrototype::find()->where(SqlHelper::whereIn('groupid', $ids))->select('groupid')->limit(1);
        if ($prototype = $query->scalar()) {
            $msg = t('zapi', 'Group "{name}" cannot be deleted, because it is used by a host prototype.', [
                'name' => $models[$prototype]['name']
            ]);
            return $this->error(error_code(10000003, ['name' => Yii::t('app', 'Group')]), $msg);
        }

        try {
            HostGroupManager::validateDeleteForce($id2name);
            HostGroupManager::deleteForce($id2name);
        } catch (Exception $e) {
            return $this->error(error_code(10000003, ['name' => Yii::t('app', 'Group')]), $e->getMessage());
        }

        $msg = Yii::t('msg', '{name} deleted successfully', ['name' => Yii::t('app', 'Group')]);
        // foreach ($id2name as $id => $name) {
        //     $details = [
        //         audit_detail('groupid', $id, ''),
        //         audit_detail('name', $name, ''),
        //         audit_detail('uuid', $models[$id]['uuid'], ''),
        //     ];
        //     $this->auditDelete(RESOURCE_ZAPI, [$id => $name], $msg, $details);
        // }

        return $this->success($id2name, $msg);
    }

    /**
     * Check to exclude an opportunity to leave template without groups.
     *
     * @static
     *
     * @param array  $templates
     * @param string $templates[<templateid>]['host']
     * @param array  $groupIds
     *
     * @throws Exception
     */
    public static function checkTemplatesWithoutGroups(array $templates, array $groupIds): void
    {
        $templateIds = array_keys($templates);
        $without = HostsGroups::find()
            ->select(new Expression('DISTINCT hostid'))
            ->where(SqlHelper::whereIn('groupid', $groupIds, true))
            ->andWhere(SqlHelper::whereIn('hostid', $templateIds))
            ->asArray()
            ->column();

        if ($diff = array_diff($templateIds, $without)) {
            throw new Exception(t('zapi', 'Template "{name}" cannot be without template group.', [
                'name' => $templates[current($diff)]['host']
            ]));
        }
    }


    /**
     * @param array $params
     * @param array $output
     * @return array
     */
    public function getGroups(array $params = [], array $output = []): array
    {
        $searcher = new GroupSearch();
        if ($output) {
            $searcher->assignable = $output;
        }
        $searcher->isPage = false;
        $dataProvider = $searcher->search($params);
        return $dataProvider->getModels();
    }

    /**
     * 统计给定条件的分组数量
     *
     * @param array $params
     * @return integer
     */
    public function count(array $params = []): int
    {
        $searcher = new GroupSearch();

        $dataProvider = $searcher->search($params);
        return $dataProvider->getTotalCount();
    }

    /**
     * 批量新增
     *
     * @param array $params
     * @return Result
     */
    public function massAdd(array $params): Result
    {
        try {
            $this->validateMassAdd($params, $dbGroups);

            $groups = self::getGroupsByData($params, $dbGroups);
            $ins_hosts_groups = self::getInsHostsGroups($groups, __FUNCTION__);

            if ($ins_hosts_groups) {
                $hostgroupids = DB::insertBatch('hosts_groups', $ins_hosts_groups);
                self::addHostgroupids($groups, $hostgroupids);
            }
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        }

        // zbx audit

        return $this->success(['groupids' => array_column($params['groups'], 'groupid')]);
    }

    /**
     * Remove given hosts from given host groups.
     *
     * @param array $data
     *
     * @return Result
     */
    public function massRemove(array $data): Result
    {
        $this->validateMassRemove($data, $dbGroups);

        //$groups = self::getGroupsByData([], $dbGroups);
        $delHostGroupIds = self::getDelHostGroupIds($dbGroups);

        if ($delHostGroupIds) {
            DB::delete(HostsGroups::tableName(), ['hostgroupid' => $delHostGroupIds]);
        }

        return $this->success(['groupids' => $data['groupids']]);
    }

    /**
     * @param array $groups
     * @param array|null $dbGroups
     * @throws ValidateException
     */
    private function validateMassAdd(array &$groups, ?array &$dbGroups)
    {
        if (!ValidateHelper::validateObject($groups, HostGroupForm::getMassValidationRules(), [], $error)) {
            throw new ValidateException(60750501, $error);
        }

        $dbGroups = $this->getGroups([
            'groupids' => array_column($groups['groups'], 'groupid'),
            'preserveKey' => true,
        ]);

        if (count($dbGroups) != count($groups['groups'])) {
            throw new ValidateException(60750501, t('zapi', 'Invalid parameter {attribute}, {error}', [
                'attribute' => 'groups',
                'error' => ''
            ]));
        }

        $hostIds = array_column($groups['hosts'], 'hostid');

        $dbHosts = Hosts::find()
            ->select(['hostid', 'name', 'host', 'flags'])
            ->where(['hostid' => $hostIds])
            ->andWhere(['status' => [0, 1]])
            ->asArray()
            ->all();

        if (count($dbHosts) != count($hostIds)) {
            throw new ValidateException(60750501, t('zapi', 'Invalid parameter {attribute}, {error}', [
                'attribute' => 'hosts',
                'error' => ''
            ]));
        }

        self::checkHostsNotDiscovered($dbHosts);
        self::addAffectedObjects($hostIds, $dbGroups);
    }

    /**
     * Validation of massRemove input fields.
     *
     * @param array      $groups     [IN/OUT]
     * @param array|null $dbGroups   [OUT]
     *
     * @throws ValidateException if the input is invalid.
     */
    private function validateMassRemove(array &$groups, ?array &$dbGroups): void
    {
        if (!ValidateHelper::validateObject($groups, HostGroupForm::getMassValidationRules('delete'), [], $error)) {
            throw new ValidateException(60750501, $error);
        }

        $query = Hstgrp::find()->select(['groupid', 'name'])
            ->where(SqlHelper::whereIn('groupid', $groups['groupids']))
            ->indexBy('groupid')
            ->asArray();

        $dbGroups = $query->all();

        if (count($dbGroups) != count($groups['groupids'])) {
            throw new ValidateException(60750501, t('zapi', 'Invalid parameter {attribute}, {error}', [
                'attribute' => 'groupids',
                'error' => ''
            ]));
        }

        $dbHosts = Hosts::find()
            ->select(['hostid', 'host', 'name', 'flags'])
            ->where(['hostid' => $groups['hostids']])
            ->andWhere(['status' => [0, 1]])
            ->indexBy('hostid')
            ->asArray()
            ->all();

        if (count($dbHosts) != count($groups['hostids'])) {
            throw new ValidateException(60750501, t('zapi', 'Invalid parameter {attribute}, {error}', [
                'attribute' => 'hostids',
                'error' => ''
            ]));
        }

        self::checkHostsNotDiscovered($dbHosts);
        self::checkHostsWithoutGroups($dbHosts, $groups['groupids']);

        self::addAffectedObjects($groups['hostids'], $dbGroups);
    }



    /**
     * Check whether no one of given hosts are discovered host.
     *
     * @static
     *
     * @param array  $dbHosts
     * @param string $dbHosts[][host]
     * @param int    $dbHosts[][flags]
     *
     * @throws ValidateException
     */
    private static function checkHostsNotDiscovered(array $dbHosts): void
    {
        foreach ($dbHosts as $dbHost) {
            if ($dbHost['flags'] == PRS_FLAG_DISCOVERY_CREATED) {
                throw new ValidateException(60750501, t('zapi', 'Cannot update groups for discovered host "{name}".', [
                    'name' => $dbHost['host']
                ]));
            }
        }
    }

    /**
     * Check to exclude an opportunity to leave host without groups.
     *
     * @static
     *
     * @param array  $dbHosts
     * @param string $dbHosts[<hostid>]['host']
     * @param array  $groupIds
     *
     * @throws ValidateException
     */
    public static function checkHostsWithoutGroups(array $dbHosts, array $groupIds): void
    {
        $hostIds = array_keys($dbHosts);

        $hostIdsWithGroups = HostsGroups::find()
            ->select(new Expression('DISTINCT hostid'))
            ->where(SqlHelper::whereIn('groupid', $groupIds, true))
            ->andWhere(SqlHelper::whereIn('hostid', $hostIds))
            ->column();

        $hostIdsWithGroups = array_diff($hostIds, $hostIdsWithGroups);

        if ($hostIdsWithGroups) {
            $hostid = reset($hostIdsWithGroups);
            throw new ValidateException(60750501, t('zapi', 'Host "{name}" cannot be without host group.', [
                'name' => $dbHosts[$hostid]['host']
            ]));
        }
    }

    /**
     * Add the existing hosts whether these are affected by the mass methods.
     * If host IDs passed as empty array, all host links of given groups will be collected from database and all
     * existing host IDs will be collected in $db_hostids.
     *
     * @static
     *
     * @param array      $hostids
     * @param array      $db_groups
     * @param array|null $db_hostids
     */
    private static function addAffectedObjects(array $hostIds, array &$dbGroups, array &$dbHostIds = null): void
    {
        if (!$hostIds) {
            $dbHostIds = [];
        }

        foreach ($dbGroups as &$dbGroup) {
            $dbGroup['hosts'] = [];
        }
        unset($dbGroup);

        if ($hostIds) {
            $query = HostsGroups::find()
                ->select(['hostgroupid', 'hostid', 'groupid'])
                ->where([
                    'hostid' => $hostIds,
                    'groupid' => array_keys($dbGroups)
                ])
                ->asArray();
        } else {
            $query = new Query();
            $query->select(['hg.hostgroupid', 'hg.hostid', 'hg.groupid'])
                ->from([
                    'hg' => HostsGroups::tableName(),
                    'h' => Hosts::tableName()
                ])
                ->where('hg.hostid=h.hostid')
                ->andWhere(SqlHelper::whereIn('{{hg}}.groupid', array_keys($dbGroups)))
                ->andWhere(['hg.flags' => PRS_FLAG_DISCOVERY_NORMAL]);
        }

        foreach ($query->each() as $link) {
            $dbGroups[$link['groupid']]['hosts'][$link['hostgroupid']] = [
                'hostgroupid' => $link['hostgroupid'],
                'hostid' => $link['hostid']
            ];

            if (!$hostIds) {
                $dbHostIds[$link['hostid']] = true;
            }
        }

        if (!$hostIds) {
            $dbHostIds = array_keys($dbHostIds);
        }
    }

    /**
     * Get host groups input array based on requested data and database data.
     *
     * @static
     *
     * @param array $data
     * @param array $db_groups
     *
     * @return array
     */
    private static function getGroupsByData(array $data, array $db_groups): array
    {
        $groups = [];

        foreach ($db_groups as $db_group) {
            $group = ['groupid' => $db_group['groupid']];

            $group['hosts'] = [];
            $db_hosts = array_column($db_group['hosts'], null, 'hostid');

            if (array_key_exists('hosts', $data)) {
                foreach ($data['hosts'] as $host) {
                    if (array_key_exists($host['hostid'], $db_hosts)) {
                        $group['hosts'][] = $db_hosts[$host['hostid']];
                    } else {
                        $group['hosts'][] = ['hostid' => $host['hostid']];
                    }
                }
            }

            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * Get rows to insert hosts on the given host groups.
     *
     * @static
     *
     * @param array      $groups
     * @param string     $method
     * @param array|null $db_hostgroupids
     *
     * @return array
     */
    private static function getInsHostsGroups(array $groups, string $method, array &$db_hostgroupids = null): array
    {
        $ins_hosts_groups = [];

        if ($method === 'massUpdate') {
            $db_hostgroupids = [];
        }

        foreach ($groups as $group) {
            foreach ($group['hosts'] as $host) {
                if (!array_key_exists('hostgroupid', $host)) {
                    $ins_hosts_groups[] = [
                        'hostid' => $host['hostid'],
                        'groupid' => $group['groupid']
                    ];
                } elseif ($method === 'massUpdate') {
                    $db_hostgroupids[$host['hostgroupid']] = true;
                }
            }
        }

        return $ins_hosts_groups;
    }

    /**
     * Add IDs of inserted hosts on the given host groups.
     *
     * @param array $groups
     * @param array $hostgroupids
     */
    private static function addHostgroupids(array &$groups, array $hostgroupids): void
    {
        foreach ($groups as &$group) {
            foreach ($group['hosts'] as &$host) {
                if (!array_key_exists('hostgroupid', $host)) {
                    $host['hostgroupid'] = array_shift($hostgroupids);
                }
            }
            unset($host);
        }
        unset($group);
    }

    /**
     * Get IDs to delete hosts from the given host groups.
     *
     * @static
     *
     * @param array $db_groups
     * @param array $db_hostgroupids
     *
     * @return array
     */
    private static function getDelHostGroupIds(array $db_groups, array $db_hostgroupids = []): array
    {
        $del_hostgroupids = [];

        foreach ($db_groups as $db_group) {
            $del_hostgroupids += array_diff_key($db_group['hosts'], $db_hostgroupids);
        }

        $del_hostgroupids = array_keys($del_hostgroupids);

        return $del_hostgroupids;
    }
}
