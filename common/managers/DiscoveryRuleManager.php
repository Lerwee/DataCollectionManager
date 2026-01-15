<?php

namespace app\customs\zapi\common\managers;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\managers\base\BaseManager;
use app\customs\zapi\services\assist\ItemPrototypeAssist;
use app\customs\zapi\services\HostPrototypeService;
use app\modules\libzbx\models\zbx\HostDiscovery;
use app\modules\libzbx\models\zbx\Housekeeper;
use app\modules\libzbx\models\zbx\ItemDiscovery;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\Items;
use Yii;
use yii\db\Query;

/**
 * 发现规则
 */
class DiscoveryRuleManager extends BaseManager
{
    /**
     * 删除指定发现规则数据
     *
     * @param array $ruleIds
     * @return void
     */
    public static function delete(array $ruleIds)
    {
        // 读取子类发现规则数据
        $ruleIds = self::getChildRuleIds($ruleIds);

        $itemCondition = SqlHelper::whereIn('itemid', $ruleIds);

        // 删除指标原型
        $query = new Query();
        $query->from([
            'id' => ItemDiscovery::tableName(),
            'i' => Items::tableName()
        ]);
        $query->where('{{id}}.itemid={{i}}.itemid')
            ->andWhere(str_replace('itemid', '{{id}}.parent_itemid', $itemCondition));
        $query->select(['i.name', 'i.itemid'])->indexBy('itemid');

        if ($items = $query->all()) {
            ItemPrototypeAssist::deleteItems($items);
        }

        // 删除主机原型
        $query = HostDiscovery::find();
        $query->from([
            'hd' => HostDiscovery::tableName(),
            'h' => Hosts::tableName(),
        ]);
        $query->select(['h.host', 'hd.hostid']);
        $query->where(str_replace('itemid', 'hd.parent_itemid', $itemCondition));
        $query->andWhere('hd.hostid=h.hostid');
        $query->indexBy('hostid');
        if ($hostPrototypeIds = $query->column()) {
            HostPrototypeService::deleteForce($hostPrototypeIds);
        }

        // Delete LLD rules.
		DB::delete('item_tag', ['itemid' => $ruleIds]);
		DB::delete('item_preproc', ['itemid' => $ruleIds]);
		DB::update('items', [
			'values' => ['templateid' => 0],
			'where' => ['itemid' => $ruleIds]
		]);
        // 删除自动发现规则
        Items::deleteAll($itemCondition);

        // Housekeeper
        $insert = array_map(function ($ruleId) {
            return [
                'tablename' => 'events',
                'field' => 'lldruleid',
                'value' => $ruleId
            ];
        }, $ruleIds);

        DB::insertBatch(Housekeeper::tableName(), $insert);
    }

    /**
     * 返回给定规则的子类规则集合
     *
     * @param array $ruleIds
     * @return array
     */
    public static function getChildRuleIds(array $ruleIds)
    {
        $parentItemIds = $ruleIds;
        $childRuleIds = [];
        do {
            $query = Items::find()->select('itemid');
            $query->where(SqlHelper::whereIn('templateid', $parentItemIds));
            $ids = $query->column();
            $parentItemIds = [];
            array_walk($ids, function ($id) use (&$parentItemIds, &$childRuleIds) {
                $parentItemIds[$id] = $id;
                $childRuleIds[$id] = $id;
            });
        } while ($parentItemIds);

        return array_merge($ruleIds, $childRuleIds);
    }
}
