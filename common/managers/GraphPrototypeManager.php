<?php

namespace app\customs\zapi\common\managers;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\managers\base\GraphGeneral;
use app\modules\libzbx\models\zbx\GraphDiscovery;
use app\modules\libzbx\models\zbx\Graphs;
use app\modules\libzbx\models\zbx\GraphsItems;

/**
 * 图形原型
 */
class GraphPrototypeManager extends GraphGeneral
{
    /**
     * Deleting graph prototypes
     *
     * @param array $graphIds
     */
    public static function delete(array $graphIds)
    {
        // 查询所有继承图形
        $deleteIds = static::getInheritedOrDependentIds($graphIds, Graphs::tableName(),  'templateid', 'graphid');

        $table = Graphs::tableName();
        $whereIn = SqlHelper::whereIn('graphid', $deleteIds);
        // Lock graph prototypes before delete to prevent server from adding new LLD elements.
        // $sql = "SELECT NULL FROM {$table} WHERE {$whereIn} FOR UPDATE";
        // Graphs::getDb()->createCommand($sql)->execute();

        // 删除发现图片
        $query = GraphDiscovery::find()->select('graphid')->where(str_replace('graphid', 'parent_graphid', $whereIn));
        if ($discoveryGraphIds = $query->column()) {
            GraphManager::delete($discoveryGraphIds);
        }

        GraphsItems::deleteAll(['graphid' => $deleteIds]);
        Graphs::deleteAll(['graphid' => $deleteIds]);
    }
}
