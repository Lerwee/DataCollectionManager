<?php

namespace app\customs\zapi\common\managers;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\managers\base\BaseManager;
use app\modules\libzbx\models\zbx\Actions;
use app\modules\libzbx\models\zbx\Conditions;
use app\modules\libzbx\models\zbx\CorrConditionGroup;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\HostsGroups;
use app\modules\libzbx\models\zbx\Hstgrp;
use app\modules\libzbx\models\zbx\OpcommandGrp;
use app\modules\libzbx\models\zbx\Operations;
use app\modules\libzbx\models\zbx\Opgroup;
use app\modules\libzbx\models\zbx\Scripts;
use app\modules\libzbx\models\zbx\SysmapsElements;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;

/**
 * 主机分组
 */
class HostGroupManager extends BaseManager
{
    /**
     * @param array $id2name [groupid => name]
     *
     * @throws Exception if unable to delete groups.
     */
    public static function validateDeleteForce(array $id2name): void
    {
        $groupIds = array_keys($id2name);

        $groupWhereIn = SqlHelper::whereIn('groupid', $groupIds);

        $query = new Query();

        $query->from([
            'h' => Hosts::tableName(),
            'hg' => HostsGroups::tableName()
        ]);

        $query->where('hg.hostid=h.hostid')
            ->andWhere(['h.status' => [0, 1]]) // HOST_STATUS_MONITORED HOST_STATUS_NOT_MONITORED
            ->andWhere(str_replace('groupid', '{{hg}}.groupid', $groupWhereIn));

        $query->select(['h.host', 'h.hostid'])
            ->indexBy('hostid');

        if ($hosts = $query->column()) {
            self::checkHostsWithoutGroups($hosts, $groupIds);
        }

        $script = Scripts::find()
            ->select(['groupid', 'scriptid', 'name'])
            ->where($groupWhereIn)
            ->limit(1)
            ->asArray()
            ->one();

        if ($script) {
            $error = t('zapi', 'Host group "{name}" cannot be deleted, because it is used in a global script.', ['name' =>  $id2name[$script['groupid']]]);
            throw new Exception($error);
        }

        $corrGroupId = CorrConditionGroup::find()
            ->select('groupid')
            ->where($groupWhereIn)
            ->asArray()
            ->scalar();

        if ($corrGroupId) {
            $error = t('zapi', 'Group "{name}" cannot be deleted, because it is used in a correlation condition.', ['name' =>  $id2name[$corrGroupId]]);
            throw new Exception($error);
        }

        self::checkMaintenancesByGroupId($groupIds);
    }

    /**
     *
     * @param array $id2name
     * @return void
     */
    public static function deleteForce(array $id2name)
    {
        $groupIds = array_keys($id2name);

        $groupIdWhere = SqlHelper::whereIn('groupid', $groupIds);

        // delete sysmap element
        SysmapsElements::deleteAll([
            'elementtype' => SYSMAP_ELEMENT_TYPE_HOST_GROUP,
            'elementid' => $groupIds
        ]);

        // disable actions
        // actions from conditions
        $query = new Query();
        $query->from(Conditions::tableName());
        $query->where(['conditiontype' => PRS_CONDITION_TYPE_HOST_GROUP])
            ->andWhere(SqlHelper::stringWhereIn('value', array_map(function ($id) {return (string) $id;}, $groupIds)));
        $query->select(['actionid'])
            ->indexBy('actionid');
        $actionIds = $query->column();

        // actions from operations
        $query = new Query();
        $query->from([
            'o' => Operations::tableName(),
            'og' => Opgroup::tableName()
        ]);
        $query->where('o.operationid=og.operationid')
            ->andWhere(str_replace('groupid', '{{og}}.groupid', $groupIdWhere));

        $query->select(['o.actionid'])
            ->indexBy('actionid');

        $actionIds += $query->column();

        if ($actionIds) {
            Actions::updateAll(['status' => ACTION_STATUS_DISABLED], ['actionid' => $actionIds]);
        }

        // delete action conditions
        Conditions::deleteAll([
            'conditiontype' => PRS_CONDITION_TYPE_HOST_GROUP,
            'value' => $actionIds
        ]);

        // delete action operation groups
        $query = Opgroup::find()
            ->select(new Expression('DISTINCT operationid'))
            ->where($groupIdWhere);

        $operationIds = $query->indexBy('operationid')->column();

        Opgroup::deleteAll($groupIdWhere);

        // delete action operation commands
        $query = OpcommandGrp::find()
            ->select(new Expression('DISTINCT operationid'))
            ->where($groupIdWhere)
            ->indexBy('operationid');
        $operationIds += $query->column();

        OpcommandGrp::deleteAll($groupIdWhere);

        // delete empty operations
        $query = Operations::find()
            ->alias('o')
            ->where(SqlHelper::whereIn('{{o}}.operationid', $operationIds));
        $query->andWhere([
            'NOT EXISTS',
            Opgroup::find()->alias('og')->select(new Expression('null'))->where('{{o}}.operationid={{og}}.operationid')
        ])->andWhere([
            'NOT EXISTS',
            OpcommandGrp::find()->alias('ocg')->select(new Expression('null'))->where('{{o}}.operationid={{ocg}}.operationid')
        ]);

        $query = $query->select(new Expression('o.operationid'))
            ->indexBy('operationid');

        $operationIds += $query->column();

        Operations::deleteAll(['operationid' => $operationIds]);

        Hstgrp::deleteAll($groupIdWhere);

        // TODO: zbx audit
    }

    /**
     * Check to exclude an opportunity to leave host without groups.
     *
     * @static
     *
     * @param array  $id2host [hostid => host] 
     * @param array  $groupIds
     *
     * @throws Exception
     */
    public static function checkHostsWithoutGroups(array $id2host, $groupIds): void
    {
        $hostIds = array_keys($id2host);

        $query = HostsGroups::find();
        $query->select(new Expression('DISTINCT hostid'));
        $query->where(SqlHelper::whereIn('hostid', $hostIds))
            ->andWhere(SqlHelper::whereIn('groupid', $groupIds, true));

        $ids = $query->column();
        if ($diff = array_diff($hostIds, $ids)) {
            $hostid = reset($diff);
            $error = t('zapi', 'Host "{host}" cannot be without host group.', ['host' => $id2host[$hostid]]);
            throw new Exception($error);
        }
    }
}
