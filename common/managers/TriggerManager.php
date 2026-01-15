<?php

namespace app\customs\zapi\common\managers;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\managers\base\BaseManager;
use app\modules\libzbx\models\zbx\Actions;
use app\modules\libzbx\models\zbx\Conditions;
use app\modules\libzbx\models\zbx\Functions;
use app\modules\libzbx\models\zbx\Housekeeper;
use app\modules\libzbx\models\zbx\SysmapElementTrigger;
use app\modules\libzbx\models\zbx\SysmapsElements;
use app\modules\libzbx\models\zbx\TriggerDepends;
use app\modules\libzbx\models\zbx\TriggerDiscovery;
use app\modules\libzbx\models\zbx\Triggers;
use app\modules\libzbx\models\zbx\TriggerTag;
use yii\db\Query;

/**
 * 触发器
 */
class TriggerManager extends BaseManager
{
    /**
     *
     * @param array $triggerIds
     * @return void
     */
    public static function delete(array $triggerIds)
    {
        // 查询所有继承的触发器
        $delTriggerIds = self::getInheritedOrDependentIds($triggerIds, Triggers::tableName(), 'templateid', 'triggerid');

        // 禁用动作
        $actionIds = [];
        $conditionIds = [];
        $query = new Query();
        $query->from(Conditions::tableName());
        $query->where(['conditiontype' => PRS_CONDITION_TYPE_TRIGGER])
            ->andWhere(Sqlhelper::stringWhereIn('value', array_map(function ($tid) {return (string) $tid;}, $delTriggerIds)));
        $query->select(['actionid', 'conditionid'])
            ->indexBy('conditionid');

        if ($cid2aid = $query->column()) {
            foreach ($cid2aid as $conditionId => $actionId) {
                $conditionIds[] = $conditionId;
                $actionIds[$actionId] = true;
            }
        }

        if ($actionIds) {
            Actions::updateAll(['status' => 1], array_keys($actionIds));
            // 删除条件
            Conditions::deleteAll(['conditionid' => $conditionIds]);
        }

        $triggerIdWhere = Sqlhelper::whereIn('triggerid', $delTriggerIds);

        // Remove trigger sysmap elements.
        $elementTriggerIds = [];
        $elementIds = [];
        $query = new Query();
        $query->from(SysmapElementTrigger::tableName());
        $query->where($triggerIdWhere);
        $query->select(['selementid', 'selement_triggerid'])
            ->indexBy('selement_triggerid');
        if ($tid2sid = $query->column()) {
            foreach ($tid2sid as $triggerId => $elementId) {
                $elementTriggerIds[] = $triggerId;
                $elementIds[$elementId] = true;
            }
            SysmapElementTrigger::deleteAll(['selement_triggerid' => $elementTriggerIds]);

            $query = new Query();
            $query->from(SysmapElementTrigger::tableName());
            $query->where(Sqlhelper::whereIn('selementid', array_keys($elementIds)));
            $query->select(['selementid']);

            if ($ids = $query->column()) {
                array_walk($ids, function ($id) use (&$elementIds) {
                    unset($elementIds[$id]);
                });
            }
            if ($elementIds) {
                SysmapsElements::deleteAll(['selementid' => array_keys($elementIds)]);
            }
        }

        // 移除关联事件
        $eventHousekeeper = array_map(function ($triggerId) {
            return [
                'tablename' => 'events',
                'field' => 'triggerid',
                'value' => $triggerId,
            ];
        }, $delTriggerIds);
        DB::insertBatch(Housekeeper::tableName(), $eventHousekeeper);
        Functions::deleteAll($triggerIdWhere);
        TriggerDiscovery::deleteAll($triggerIdWhere);
        TriggerDepends::deleteAll(str_replace('triggerid', 'triggerid_down', $triggerIdWhere));
        TriggerDepends::deleteAll(str_replace('triggerid', 'triggerid_up', $triggerIdWhere));
        TriggerTag::deleteAll($triggerIdWhere);
        Triggers::updateAll(['templateid' => null], $triggerIdWhere);
        Triggers::deleteAll($triggerIdWhere);
    }
}
