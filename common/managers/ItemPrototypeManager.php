<?php

namespace app\customs\zapi\common\managers;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\managers\base\ItemGeneralManager;
use app\modules\libzbx\models\Items;
use app\modules\libzbx\models\zbx\Graphs;
use app\modules\libzbx\models\zbx\GraphsItems;
use app\modules\libzbx\models\zbx\ItemDiscovery;
use yii\db\Expression;
use yii\db\Query;

/**
 * 指标原型
 */
class ItemPrototypeManager extends ItemGeneralManager
{
    /**
     * 强制删除指定规则ID的数据
     *
     * @param array $id2name [itemid => name]
     * @return void
     */
    public static function deleteForce(array $id2name)
    {
        // 查询所有继承指标
        $id2name = static::getInheritedOrDependentData($id2name, Items::tableName(), 'templateid', 'itemid');
        // 查询所有依赖指标
        $id2name = static::getInheritedOrDependentData($id2name, Items::tableName(), 'master_itemid', 'itemid');

        $itemIds = array_keys($id2name);
        $table = Items::tableName();
        $whereIn = SqlHelper::whereIn('itemid', $itemIds);


        // Lock graph prototypes before delete to prevent server from adding new LLD elements.
        $sql = "SELECT NULL FROM {$table} WHERE {$whereIn} FOR UPDATE";
        Items::getDb()->createCommand($sql)->execute();

        self::deleteAffectedGraphPrototypes($itemIds);
        self::resetGraphsYAxis($whereIn);
        self::deleteDiscoveredItems($whereIn);

        self::deleteAffectedTriggers($whereIn);

        self::clearItemExtraAttributes($whereIn);

        // TODO: ZBX audit
    }


    /**
     * 删除图形原型，在删除给定的项目原型后，图形原型将保留为没有项目原型。
     *
     * @param array $itemIds
     */
    private static function deleteAffectedGraphPrototypes(array $itemIds): void
    {
        $query = GraphsItems::find()
            ->alias('gi');

        $subQuery = new Query();

        $subQuery->from([
            'gii' => GraphsItems::tableName(),
            'i' => Items::tableName()
        ]);
        $subQuery->select(new Expression('NULL'));
        $subQuery->where('gii.itemid=i.itemid')
            ->andWhere('gi.graphid=gii.graphid')
            ->andWhere(SqlHelper::whereIn('{{gii}}.itemid', $itemIds, true))
            ->andWhere(['i.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE]);

        $query->where(SqlHelper::whereIn('{{gi}}.itemid', $itemIds))
            ->andWhere(['NOT EXISTS', $subQuery]);

        $query->select(new Expression('DISTINCT gi.graphid'));

        if ($graphIds = $query->column()) {
            GraphPrototypeManager::delete($graphIds);
        }
    }

    /**
     * 删除给定指标原型的发现指标数据
     *
     * @param string $itemIdWhere
     */
    private static function deleteDiscoveredItems($itemIdWhere): void
    {
        $query = new Query();
        $query->from([
            'id' => ItemDiscovery::tableName(),
            'i' => Items::tableName()
        ]);
        $query->where('id.itemid=i.itemid')
            ->andWhere(str_replace('itemid', '{{id}}.parent_itemid', $itemIdWhere));

        $query->select(['i.name', 'id.itemid']);

        $query->indexBy('itemid');


        if ($id2name = $query->column()) {
            ItemManager::deleteForce($id2name);
        }
    }

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
}
