<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\components\data\MaintenanceRequestData;
use app\customs\zapi\forms\MaintenanceForm;
use app\customs\zapi\services\assist\BaseAssist;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\Maintenances;
use app\modules\libzbx\models\zbx\MaintenancesGroups;
use app\modules\libzbx\models\zbx\MaintenancesHosts;
use app\modules\libzbx\models\zbx\MaintenancesWindows;
use app\modules\libzbx\models\zbx\MaintenanceTag;
use app\modules\libzbx\models\zbx\Timeperiods;
use yii\base\Exception;
use yii\db\Query;

class MaintenanceService extends BaseAssist
{
    /**
     * @see createByInternal()
     * @param array $params
     * @param bool $internal 是否内部调用（即原生API），当为false时，需要格式化
     * @return Result
     */
    public function create(array $params, bool $internal = true): Result
    {
        if (!$internal) {
            $request = new MaintenanceRequestData(['data' => $params]);
            if (!$request->isSuccess()) {
                return $request->getResult();
            }
            $params = $request->getData();
        }
        return $this->createByInternal($params);
    }

    /**
     * 新增(内部调用)
     *
     * @param array $params
     * @return Result
     */
    public function createByInternal(array $params): Result
    {
        $enableTransaction = true;
        if (isset($params['enableTransaction'])) {
            $enableTransaction = (bool) $params['enableTransaction'];
            unset($params['enableTransaction']);
        }
        try {
            $this->validateCreate($params);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage(), [explode(PHP_EOL, (string) $e)]);
        } catch (Exception $e) {
            return $this->error(60750001, $e->getMessage(), [explode(PHP_EOL, (string) $e)]);
        }
        $enableTransaction && $transaction = Hosts::getDb()->beginTransaction();
        try {
            $maintenanceIds = DB::insert(Maintenances::tableName(), $params);
            foreach ($params as $i => &$param) {
                $param['maintenanceid'] = $maintenanceIds[$i];
            }
            unset($param);
            self::updateTags($params);
            self::updateGroups($params);
            self::updateHosts($params);
            self::updateTimePeriods($params);
            $enableTransaction && $transaction->commit();
            // zbx audit
            return $this->success(['maintenanceids' => $maintenanceIds]);
        } catch (Exception $e) {
            $enableTransaction && $transaction->rollBack();
            return $this->errorException($e);
        }
    }

    /**
     * @param array $maintenances
     *
     * @throws ValidateException if no permissions to object, it does not exist or the input is invalid.
     */
    protected function validateCreate(array &$maintenances)
    {
        $rules = MaintenanceForm::getValidationRules();
        $bool = ValidateHelper::validateObjects($maintenances, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']]], $error);
        if (!$bool) {
            self::exception(60750001, $error);
        }

        MaintenanceForm::checkDeprecates($maintenances);
        MaintenanceForm::validateGroupsAndHosts($maintenances);

        unset($maintenance);

        $maintenances = MaintenanceForm::validateTimePeriods($maintenances);

        MaintenanceForm::checkDuplicates($maintenances);
        MaintenanceForm::checkAvailable($maintenances);
        MaintenanceForm::checkAvailable($maintenances, null, 'hosts');
    }

    /**
     * @see updateByInternal()
     *
     * @param array $params
     * @param bool  $internal
     * @return Result
     */
    public function update(array $params, bool $internal = true): Result
    {
        if (!$internal) {
            $request = new MaintenanceRequestData(['data' => $params]);
            if (!$request->isSuccess()) {
                return $request->getResult();
            }
            $params = $request->getData();
        }
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
        $enableTransaction = true;
        if (isset($params['enableTransaction'])) {
            $enableTransaction = (bool) $params['enableTransaction'];
            unset($params['enableTransaction']);
        }
        try {
            $this->validateUpdate($params, $dbMaintenances);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage(), explode(PHP_EOL, (string) $e));
        } catch (Exception $e) {
            return $this->error(60750001, $e->getMessage(), explode(PHP_EOL, (string) $e));
        }
        $enableTransaction && $transaction = Hosts::getDb()->beginTransaction();
        try {
            $udpMaintenances = [];
            foreach ($params as $param) {
                $udpMaintenance = DB::getUpdatedValues(
                    'maintenances',
                    $param,
                    $dbMaintenances[$param['maintenanceid']]
                );

                if ($udpMaintenance) {
                    $udpMaintenances[] = [
                        'values' => $udpMaintenance,
                        'where' => ['maintenanceid' => $param['maintenanceid']]
                    ];
                }
            }

            if ($udpMaintenances) {
                DB::update(Maintenances::tableName(), $udpMaintenances);
            }

            self::updateTags($params, $dbMaintenances);
            self::updateGroups($params, $dbMaintenances);
            self::updateHosts($params, $dbMaintenances);
            self::updateTimePeriods($params, $dbMaintenances);
            $enableTransaction && $transaction->commit();
            // TODO:zbx audit

            return $this->success(['maintenanceids' => array_column($params, 'maintenanceid')]);
        } catch (Exception $e) {
            $enableTransaction && $transaction->rollBack();
            return $this->errorException($e);
        }
    }

    /**
     * @param array      $maintenances
     * @param array|null $dbMaintenances
     *
     * @throws APIException if the input is invalid.
     */
    protected function validateUpdate(array &$maintenances, array &$dbMaintenances = null)
    {
        $rules = MaintenanceForm::getIdsValidationRules();
        $bool = ValidateHelper::validateObjects($maintenances, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['maintenanceid']]], $error);
        if (!$bool) {
            self::exception(60750001, $error);
        }

        MaintenanceForm::checkDeprecates($maintenances);

        $dbMaintenances = Maintenances::find()
            ->select(['maintenanceid', 'name', 'maintenance_type', 'description', 'active_since', 'active_till', 'tags_evaltype'])
            ->where(SqlHelper::whereIn('maintenanceid', array_column($maintenances, 'maintenanceid')))
            ->indexBy('maintenanceid')
            ->asArray()
            ->all();

        if (count($dbMaintenances) != count($maintenances)) {
            self::exception(60750001, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        $maintenances = $this->extendObjectsByKey(
            $maintenances,
            $dbMaintenances,
            'maintenanceid',
            ['maintenance_type', 'active_since', 'active_till']
        );

        $rules = MaintenanceForm::getValidationRules('update');
        $bool = ValidateHelper::validateObjects($maintenances, $rules, ['uniq' => [['maintenanceid'], ['name']]], $error);
        if (!$bool) {
            self::exception(60750001, $error);
        }

        $maintenances = MaintenanceForm::validateTimePeriods($maintenances);

        self::addAffectedObjects($maintenances, $dbMaintenances);

        MaintenanceForm::validateGroupsAndHosts($maintenances, $dbMaintenances);

        MaintenanceForm::checkDuplicates($maintenances, $dbMaintenances);
        MaintenanceForm::checkAvailable($maintenances, $dbMaintenances);
        MaintenanceForm::checkAvailable($maintenances, $dbMaintenances, 'hosts');
    }

    /**
     * @param array $maintenances
     * @param array $dbMaintenances
     */
    private static function addAffectedObjects(array $maintenances, array &$dbMaintenances): void
    {
        self::addAffectedTags($maintenances, $dbMaintenances);
        self::addAffectedGroupsAndHosts($maintenances, $dbMaintenances);
        self::addAffectedTimePeriods($maintenances, $dbMaintenances);
    }

    /**
     * @param array $maintenances
     * @param array $db_maintenances
     */
    private static function addAffectedTags(array $maintenances, array &$db_maintenances): void
    {
        $maintenanceIds = [];

        foreach ($maintenances as $maintenance) {
            $db_maintenance_type = $db_maintenances[$maintenance['maintenanceid']]['maintenance_type'];

            if (
                array_key_exists('tags', $maintenance)
                || ($maintenance['maintenance_type'] != $db_maintenance_type
                    && $maintenance['maintenance_type'] == MAINTENANCE_TYPE_NODATA)
            ) {
                $maintenanceIds[] = $maintenance['maintenanceid'];
                $db_maintenances[$maintenance['maintenanceid']]['tags'] = [];
            }
        }

        if (!$maintenanceIds) {
            return;
        }

        $dbTags = MaintenanceTag::find()
            ->select(['maintenancetagid', 'maintenanceid', 'tag', 'operator', 'value'])
            ->where(SqlHelper::whereIn('maintenanceid', $maintenanceIds))
            ->asArray()
            ->all();

        foreach ($dbTags as $dbTag) {
            $db_maintenances[$dbTag['maintenanceid']]['tags'][$dbTag['maintenancetagid']] = [
                'maintenancetagid' => $dbTag['maintenancetagid'],
                'tag' => $dbTag['tag'],
                'operator' => $dbTag['operator'],
                'value' => $dbTag['value']
            ];
        }
    }

    /**
     * @param array $maintenances
     * @param array $db_maintenances
     */
    private static function addAffectedGroupsAndHosts(array $maintenances, array &$db_maintenances): void
    {
        $maintenanceIds = [];

        foreach ($maintenances as $maintenance) {
            if (array_key_exists('groups', $maintenance) || array_key_exists('hosts', $maintenance)) {
                $maintenanceIds[] = $maintenance['maintenanceid'];
                $db_maintenances[$maintenance['maintenanceid']]['groups'] = [];
                $db_maintenances[$maintenance['maintenanceid']]['hosts'] = [];
            }
        }

        if (!$maintenanceIds) {
            return;
        }

        $dbGroups = MaintenancesGroups::find()
            ->select(['maintenance_groupid', 'maintenanceid', 'groupid'])
            ->where(SqlHelper::whereIn('maintenanceid', $maintenanceIds))
            ->asArray()
            ->all();


        foreach ($dbGroups as $dbGroup) {
            $db_maintenances[$dbGroup['maintenanceid']]['groups'][$dbGroup['maintenance_groupid']] = [
                'maintenance_groupid' => $dbGroup['maintenance_groupid'],
                'groupid' => $dbGroup['groupid']
            ];
        }

        $dbHosts = MaintenancesHosts::find()
            ->select(['maintenance_hostid', 'maintenanceid', 'hostid'])
            ->where(SqlHelper::whereIn('maintenanceid', $maintenanceIds))
            ->asArray()
            ->all();

        foreach ($dbHosts as $dbHost) {
            $db_maintenances[$dbHost['maintenanceid']]['hosts'][$dbHost['maintenance_hostid']] = [
                'maintenance_hostid' => $dbHost['maintenance_hostid'],
                'hostid' => $dbHost['hostid']
            ];
        }
    }

    /**
     * @param array $maintenances
     * @param array $db_maintenances
     */
    private static function addAffectedTimePeriods(array $maintenances, array &$db_maintenances): void
    {
        $maintenanceIds = [];

        foreach ($maintenances as $maintenance) {
            if (array_key_exists('timeperiods', $maintenance)) {
                $maintenanceIds[] = $maintenance['maintenanceid'];
                $db_maintenances[$maintenance['maintenanceid']]['timeperiods'] = [];
            }
        }

        if (!$maintenanceIds) {
            return;
        }

        $dbTimePeriods = (new Query())
            ->select(['mw.maintenanceid', 'mw.timeperiodid', 't.timeperiod_type', 't.every', 't.month', 't.dayofweek', 't.day', 't.start_time', 't.period', 't.start_date'])
            ->from([
                'mw' => MaintenancesWindows::tableName(),
                't' => Timeperiods::tableName()
            ])
            ->where('mw.timeperiodid=t.timeperiodid')
            ->andWhere(SqlHelper::whereIn('{{mw}}.maintenanceid', $maintenanceIds))
            ->all();

        foreach ($dbTimePeriods as $dbTimePeriod) {
            $db_maintenances[$dbTimePeriod['maintenanceid']]['timeperiods'][$dbTimePeriod['timeperiodid']] =
                array_diff_key($dbTimePeriod, array_flip(['maintenanceid']));
        }
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
        $maintenances = Maintenances::find()
            ->where(SqlHelper::whereIn('maintenanceid', filter_integer($ids)))
            ->select('name')
            ->indexBy('maintenanceid')
            ->column();
        if (empty($maintenances)) {
            return $this->error(10000404);
        }

        $idWhereIn = SqlHelper::whereIn('maintenanceid', array_keys($maintenances));
        $timePeriodIds = MaintenancesWindows::find()
            ->where($idWhereIn)
            ->select('timeperiodid')
            ->column();

        // Lock maintenances table before maintenance delete to prevent server from adding host to maintenance. 
        //$table = Maintenances::tableName();
        //$sql = "SELECT NULL FROM {$table} WHERE {$idWhereIn} FOR UPDATE";
        //Maintenances::getDb()->createCommand($sql)->execute();

        // 移除主机表的维护ID
        Hosts::updateAll(['maintenanceid' => null], $idWhereIn);

        MaintenancesWindows::deleteAll($idWhereIn);
        if ($timePeriodIds) {
            Timeperiods::deleteAll(['timeperiodid' => $timePeriodIds]);
        }
        MaintenancesHosts::deleteAll($idWhereIn);
        MaintenancesGroups::deleteAll($idWhereIn);
        MaintenanceTag::deleteAll($idWhereIn);
        Maintenances::deleteAll($idWhereIn);

        // TODO: zbx audit
        return $this->success(['maintenanceids' => $ids]);
    }

    /**
     * Update table "maintenance_tag".
     *
     * @param array      $maintenances
     * @param array|null $db_maintenances
     */
    private static function updateTags(array &$maintenances, array $db_maintenances = null): void
    {
        $ins_maintenance_tags = [];
        $delTagIds = [];

        foreach ($maintenances as &$maintenance) {
            if (($db_maintenances === null && !array_key_exists('tags', $maintenance))
                || ($db_maintenances !== null
                    && !array_key_exists('tags', $db_maintenances[$maintenance['maintenanceid']]))
            ) {
                continue;
            }

            if ($db_maintenances !== null && !array_key_exists('tags', $maintenance)) {
                $maintenance['tags'] = [];
            }

            $db_tags = ($db_maintenances !== null) ? $db_maintenances[$maintenance['maintenanceid']]['tags'] : [];

            foreach ($maintenance['tags'] as &$tag) {
                $db_maintenancetagid = key(
                    array_filter($db_tags, static function (array $db_tag) use ($tag): bool {
                        return $tag['tag'] == $db_tag['tag'] && $tag['operator'] == $db_tag['operator']
                            && $tag['value'] == $db_tag['value'];
                    })
                );

                if ($db_maintenancetagid !== null) {
                    $tag['maintenancetagid'] = $db_maintenancetagid;
                    unset($db_tags[$db_maintenancetagid]);
                } else {
                    $ins_maintenance_tags[] = ['maintenanceid' => $maintenance['maintenanceid']] + $tag;
                }
            }
            unset($tag);

            $delTagIds = array_merge($delTagIds, array_keys($db_tags));
        }
        unset($maintenance);

        if ($delTagIds) {
            DB::delete(MaintenanceTag::tableName(), ['maintenancetagid' => $delTagIds]);
        }

        if ($ins_maintenance_tags) {
            $maintenancetagids = DB::insert(MaintenanceTag::tableName(), $ins_maintenance_tags);
        }

        foreach ($maintenances as &$maintenance) {
            if (!array_key_exists('tags', $maintenance)) {
                continue;
            }

            foreach ($maintenance['tags'] as &$tag) {
                if (!array_key_exists('maintenancetagid', $tag)) {
                    $tag['maintenancetagid'] = array_shift($maintenancetagids);
                }
            }
            unset($tag);
        }
        unset($maintenance);
    }

    /**
     * Update table "maintenances_groups".
     *
     * @param array      $maintenances
     * @param array|null $db_maintenances
     */
    private static function updateGroups(array &$maintenances, array $db_maintenances = null): void
    {
        $insGroups = [];
        $delGroupIds = [];

        foreach ($maintenances as &$maintenance) {
            if (!array_key_exists('groups', $maintenance)) {
                continue;
            }

            $maintenanceid = $maintenance['maintenanceid'];

            $db_groups = ($db_maintenances !== null)
                ? array_column($db_maintenances[$maintenanceid]['groups'], null, 'groupid')
                : [];

            foreach ($maintenance['groups'] as &$group) {
                if (array_key_exists($group['groupid'], $db_groups)) {
                    $group['maintenance_groupid'] = $db_groups[$group['groupid']]['maintenance_groupid'];
                    unset($db_groups[$group['groupid']]);
                } else {
                    $insGroups[] = [
                        'maintenanceid' => $maintenanceid,
                        'groupid' => $group['groupid']
                    ];
                }
            }
            unset($group);

            $delGroupIds = array_merge($delGroupIds, array_column($db_groups, 'maintenance_groupid'));
        }
        unset($maintenance);

        if ($delGroupIds) {
            DB::delete(MaintenancesGroups::tableName(), ['maintenance_groupid' => $delGroupIds]);
        }

        if ($insGroups) {
            $groupIds = DB::insertBatch(MaintenancesGroups::tableName(), $insGroups);
        }

        foreach ($maintenances as &$maintenance) {
            if (!array_key_exists('groups', $maintenance)) {
                continue;
            }

            foreach ($maintenance['groups'] as &$group) {
                if (!array_key_exists('maintenance_groupid', $group)) {
                    $group['maintenance_groupid'] = array_shift($groupIds);
                }
            }
            unset($group);
        }
        unset($maintenance);
    }

    /**
     * Update table "maintenances_hosts".
     *
     * @param array      $maintenances
     * @param array|null $db_maintenances
     */
    private static function updateHosts(array &$maintenances, array $db_maintenances = null): void
    {
        $insHosts = [];
        $delHostIds = [];

        foreach ($maintenances as &$maintenance) {
            if (!array_key_exists('hosts', $maintenance)) {
                continue;
            }

            $maintenanceid = $maintenance['maintenanceid'];

            $db_hosts = ($db_maintenances !== null)
                ? array_column($db_maintenances[$maintenanceid]['hosts'], null, 'hostid')
                : [];

            foreach ($maintenance['hosts'] as &$host) {
                if (array_key_exists($host['hostid'], $db_hosts)) {
                    $host['maintenance_hostid'] = $db_hosts[$host['hostid']]['maintenance_hostid'];
                    unset($db_hosts[$host['hostid']]);
                } else {
                    $insHosts[] = [
                        'maintenanceid' => $maintenanceid,
                        'hostid' => $host['hostid']
                    ];
                }
            }
            unset($host);

            $delHostIds = array_merge(
                $delHostIds,
                array_column($db_hosts, 'maintenance_hostid')
            );
        }
        unset($maintenance);

        if ($delHostIds) {
            DB::delete(MaintenancesHosts::tableName(), ['maintenance_hostid' => $delHostIds]);
        }

        if ($insHosts) {
            $maintenance_hostids = DB::insertBatch(MaintenancesHosts::tableName(), $insHosts);
        }

        foreach ($maintenances as &$maintenance) {
            if (!array_key_exists('hosts', $maintenance)) {
                continue;
            }

            foreach ($maintenance['hosts'] as &$host) {
                if (!array_key_exists('maintenance_hostid', $host)) {
                    $host['maintenance_hostid'] = array_shift($maintenance_hostids);
                }
            }
            unset($host);
        }
        unset($maintenance);
    }

    /**
     * Update tables "periods" and "maintenances_windows".
     *
     * @param array      $maintenances
     * @param array|null $db_maintenances
     */
    private static function updateTimePeriods(array &$maintenances, array $db_maintenances = null): void
    {
        $ins_timeperiods = [];
        $ins_maintenances_windows = [];
        $del_timeperiodids = [];

        foreach ($maintenances as &$maintenance) {
            if (!array_key_exists('timeperiods', $maintenance)) {
                continue;
            }

            $db_timeperiods = ($db_maintenances !== null)
                ? $db_maintenances[$maintenance['maintenanceid']]['timeperiods']
                : [];

            foreach ($maintenance['timeperiods'] as &$timeperiod) {
                $db_timeperiodid = key(
                    array_filter($db_timeperiods, static function (array $db_timeperiod) use ($timeperiod): bool {
                        return $timeperiod['period'] == $db_timeperiod['period']
                            && $timeperiod['timeperiod_type'] == $db_timeperiod['timeperiod_type']
                            && (!array_key_exists('start_date', $timeperiod)
                                || $timeperiod['start_date'] == $db_timeperiod['start_date'])
                            && (!array_key_exists('start_time', $timeperiod)
                                || $timeperiod['start_time'] == $db_timeperiod['start_time'])
                            && (!array_key_exists('every', $timeperiod)
                                || $timeperiod['every'] == $db_timeperiod['every'])
                            && (!array_key_exists('day', $timeperiod) || $timeperiod['day'] == $db_timeperiod['day'])
                            && (!array_key_exists('dayofweek', $timeperiod)
                                || $timeperiod['dayofweek'] == $db_timeperiod['dayofweek'])
                            && (!array_key_exists('month', $timeperiod)
                                || $timeperiod['month'] == $db_timeperiod['month']);
                    })
                );

                if ($db_timeperiodid !== null) {
                    $timeperiod['timeperiodid'] = $db_timeperiodid;
                    unset($db_timeperiods[$db_timeperiodid]);
                } else {
                    $ins_timeperiods[] = $timeperiod;
                    $ins_maintenances_windows[] = ['maintenanceid' => $maintenance['maintenanceid']];
                }
            }
            unset($timeperiod);

            $del_timeperiodids = array_merge($del_timeperiodids, array_keys($db_timeperiods));
        }
        unset($maintenance);

        if ($del_timeperiodids) {
            DB::delete(MaintenancesWindows::tableName(), ['timeperiodid' => $del_timeperiodids]);
            DB::delete(Timeperiods::tableName(), ['timeperiodid' => $del_timeperiodids]);
        }

        if ($ins_timeperiods) {
            $timeperiodids = DB::insert(Timeperiods::tableName(), $ins_timeperiods);

            foreach ($ins_maintenances_windows as $i => &$maintenance_window) {
                $maintenance_window += ['timeperiodid' => $timeperiodids[$i]];
            }
            unset($maintenance_window);

            DB::insertBatch(MaintenancesWindows::tableName(), $ins_maintenances_windows);
        }

        foreach ($maintenances as &$maintenance) {
            if (!array_key_exists('timeperiods', $maintenance)) {
                continue;
            }

            foreach ($maintenance['timeperiods'] as &$timeperiod) {
                if (!array_key_exists('timeperiodid', $timeperiod)) {
                    $timeperiod['timeperiodid'] = array_shift($timeperiodids);
                }
            }
            unset($timeperiod);
        }
        unset($maintenance);
    }
}
