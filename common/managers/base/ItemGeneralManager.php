<?php

namespace app\customs\zapi\common\managers\base;

use app\customs\zapi\common\managers\TriggerManager;
use app\customs\zapi\common\managers\TriggerPrototypeManager;
use app\customs\zapi\services\assist\ItemAssist;
use app\modules\libzbx\models\Functions;
use app\modules\libzbx\models\Items;
use app\modules\libzbx\models\zbx\Graphs;
use app\modules\libzbx\models\zbx\GraphsItems;
use app\modules\libzbx\models\zbx\ItemDiscovery;
use app\modules\libzbx\models\zbx\ItemParameter;
use app\modules\libzbx\models\zbx\ItemPreproc;
use app\modules\libzbx\models\zbx\ItemTag;
use app\modules\libzbx\models\zbx\Triggers;
use app\modules\libzbx\models\zbx\WidgetField;
use yii\db\Query;

class ItemGeneralManager extends ItemAssist
{
    use ManagerTrait;
    /**
     * 重置图形中 Y 轴的 MIN 和 MAX 值（如果使用给定项目计算）。
     *
     * @param string $itemIdWhere
     */
    protected static function resetGraphsYAxis($itemIdWhere): void
    {
        Graphs::updateAll([
            'ymin_type' => GRAPH_YAXIS_TYPE_CALCULATED,
            'ymin_itemid' => null
        ], str_replace('itemid', 'ymin_itemid', $itemIdWhere));

        Graphs::updateAll([
            'ymin_type' => GRAPH_YAXIS_TYPE_CALCULATED,
            'ymin_itemid' => null
        ], str_replace('itemid', 'ymax_itemid', $itemIdWhere));
    }


    /**
     * 删除触发器和触发器原型，它们包含表达式中的给定项。
     *
     * @param string $itemIdWhere
     */
    protected static function deleteAffectedTriggers($itemIdWhere): void
    {
        $query = new Query();
        $query->from([
            'f' => Functions::tableName(),
            't' => Triggers::tableName()
        ]);

        $query->where('f.triggerid=t.triggerid')
            ->andWhere(str_replace('itemid', '{{f}}.itemid', $itemIdWhere));

        $triggerPrototypeIds = [];
        $triggerIds = [];

        $query->select(['t.flags', 'f.triggerid'])
            ->indexBy('triggerid');

        if ($id2flag = $query->column()) {
            array_walk($id2flag, function ($flag, $id) use (&$triggerPrototypeIds, &$triggerIds) {
                if ($flag === PRS_FLAG_DISCOVERY_PROTOTYPE) {
                    $triggerPrototypeIds[] = $id;
                } else {
                    $triggerIds[] = $id;
                }
            });
        }

        if ($triggerIds) {
            TriggerManager::delete($triggerIds);
        }

        if ($triggerPrototypeIds) {
            TriggerPrototypeManager::delete($triggerPrototypeIds);
        }
    }

    protected static function clearItemExtraAttributes($itemIdWhere)
    {
        GraphsItems::deleteAll($itemIdWhere);
        WidgetField::deleteAll(str_replace('itemid', 'value_itemid', $itemIdWhere));
        ItemDiscovery::deleteAll($itemIdWhere);
        ItemParameter::deleteAll($itemIdWhere);
        ItemPreproc::deleteAll($itemIdWhere);
        ItemTag::deleteAll($itemIdWhere);
        Items::updateAll(['templateid' => null, 'master_itemid' => null], $itemIdWhere);
        Items::deleteAll($itemIdWhere);
    }
}
