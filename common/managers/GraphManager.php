<?php

namespace app\customs\zapi\common\managers;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\managers\base\GraphGeneral;
use app\modules\libzbx\models\zbx\GraphDiscovery;
use app\modules\libzbx\models\zbx\Graphs;
use app\modules\libzbx\models\zbx\GraphsItems;
use app\modules\libzbx\models\zbx\Profiles;
use app\modules\libzbx\models\zbx\WidgetField;

/**
 * 图形
 */
class GraphManager extends GraphGeneral
{
    /**
     *
     * @param array $ruleIds
     * @return void
     */
    public static function delete(array $graphIds)
    {
        // 查询所有继承图形
        $deleteIds = static::getInheritedOrDependentIds($graphIds, Graphs::tableName(), 'templateid', 'graphid');

        Profiles::deleteAll([
            'idx' => 'web.latest.graphid',
            'value_id' => $deleteIds
        ]);

        DB::delete('widget_field', ['value_graphid' => $deleteIds]);
        DB::delete('graph_discovery', ['graphid' => $deleteIds]);
        DB::delete('graphs_items', ['graphid' => $deleteIds]);
        DB::delete('graphs', ['graphid' => $deleteIds]);
    }
}
