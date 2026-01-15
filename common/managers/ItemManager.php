<?php

namespace app\customs\zapi\common\managers;

use app\common\helpers\SqlHelper;
use app\customs\sgcc\models\Hosts;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\CuidHelper;
use app\customs\zapi\common\helpers\HousekeepingHelper;
use app\customs\zapi\common\managers\base\ItemGeneralManager;
use app\modules\libzbx\models\Items;
use app\modules\libzbx\models\zbx\GraphsItems;
use app\modules\libzbx\models\zbx\ItemRtdata;
use app\modules\libzbx\models\zbx\Profiles;
use yii\db\Expression;
use yii\db\Query;

/**
 * 指标
 */
class ItemManager extends ItemGeneralManager
{

    public const SUPPORTED_ITEM_TYPES = [
        Items::TYPE_PRS_AGENT, Items::TYPE_PRS_TRAPPER, Items::TYPE_SIMPLE_CHECK, Items::TYPE_PRS_INTERNAL, Items::TYPE_PRS_AGENT_ACTIVE,
        Items::TYPE_EXTERNAL_CHECK, Items::TYPE_DATABASE_MONITOR, Items::TYPE_IPMI_AGENT, Items::TYPE_SSH_AGENT, Items::TYPE_TELNET_AGENT, Items::TYPE_CALCULATED,
        Items::TYPE_JMX_AGENT, Items::TYPE_SNMP_TRAP, Items::TYPE_DEPENDENT_ITEM, Items::TYPE_HTTP_AGENT, Items::TYPE_SNMP_AGENT, Items::TYPE_SCRIPT
    ];

    /**
     *
     * @param array $id2name
     * @return void
     */
    public static function deleteForce(array $id2name)
    {
        // 继承指标
        $id2name = self::getInheritedOrDependentData($id2name, Items::tableName(), 'templateid', 'itemid');
        // 依赖指标相关数据
        [$id2name, $ruleIds, $prototypeId2name] = self::findDependentItems($id2name);

        if ($ruleIds) {
            DiscoveryRuleManager::delete($ruleIds);
        }

        if ($prototypeId2name) {
            ItemPrototypeManager::deleteForce($prototypeId2name);
        }

        $itemIds = array_keys($id2name);

        $itemIdWhere = SqlHelper::whereIn('itemid', $itemIds);

        self::deleteAffectedGraphs($itemIds);
        self::resetGraphsYAxis($itemIdWhere);
        self::deleteFromFavoriteGraphs($itemIds);

        self::deleteAffectedTriggers($itemIdWhere);

        self::clearHistoryAndTrends($itemIds);

        ItemRtdata::deleteAll($itemIdWhere);
        self::clearItemExtraAttributes($itemIdWhere);

        // TODO: ZBX audit
    }

    /**
     * find the dependent items of the given items to the given item array. Also add the dependent LLD rules and item
     * prototypes to the given appropriate variables.
     *
     * @param array      $id2name
     */
    protected static function findDependentItems(array $id2name)
    {
        $parentIds = array_keys($id2name);
        $ruleIds = [];
        $prototypes = [];
        do {

            $query = new Query();
            $query->from(Items::tableName())
                ->select(['itemid', 'name', 'flags'])
                ->where(SqlHelper::whereIn('master_itemid', array_keys($parentIds)));

            $parentIds = [];

            if ($items = $query->all()) {
                foreach ($items as $item) {
                    if ($item['flags'] == PRS_FLAG_DISCOVERY_RULE) {
                        $ruleIds[] = $item['itemid'];
                    } elseif ($item['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                        $itemPrototypes[$item['itemid']] = $item['name'];
                    } else {
                        $parentIds[] = $item['itemid'];
                        $id2name[$item['itemid']] = $item['name'];
                    }
                }
            }
        } while ($parentIds);

        return [$id2name, $ruleIds, $prototypes];
    }

    /**
     * Delete graphs, which would remain without items after the given items deletion.
     *
     * @param array $itemIdWhere
     */
    private static function deleteAffectedGraphs(array $itemIds): void
    {
        $subQuery = GraphsItems::find()
            ->alias('gii');
        $subQuery->select(new Expression('NULL'));
        $subQuery->where('gi.graphid=gii.graphid')
            ->andWhere(SqlHelper::whereIn('{{gii}}.itemid', $itemIds, true));

        $query = GraphsItems::find()
            ->alias('gi');
        $query->where(SqlHelper::whereIn('{{gi}}.itemid', $itemIds))
            ->andWhere(['NOT EXISTS', $subQuery]);

        $query->select(new Expression('DISTINCT gi.graphid'));

        if ($graphIds = $query->column()) {
            GraphManager::delete($graphIds);
        }
    }

    /**
     * Delete the latest data graph of the given items from the favorites.
     *
     * @param array $itemIds
     */
    private static function deleteFromFavoriteGraphs(array $itemIds): void
    {
        Profiles::deleteAll([
            'idx' => 'web.favorite.graphids',
            'source' => 'itemid',
            'value_id' => $itemIds
        ]);
    }

    /**
     * Clear the history and trends of the given items.
     *
     * @param array $itemIds
     */
    private static function clearHistoryAndTrends(array $itemIds): void
    {
        $driverName = Items::getDb()->driverName;

        $tableNames = ['events'];

        $timescaleExtension = $driverName == 'pgsql'
            && HousekeepingHelper::get(HousekeepingHelper::DB_EXTENSION) === PRS_DB_EXTENSION_TIMESCALEDB;

        if (
            HousekeepingHelper::get(HousekeepingHelper::HK_HISTORY_MODE) == 1
            && (!$timescaleExtension || HousekeepingHelper::get(HousekeepingHelper::HK_HISTORY_GLOBAL) == 0)
        ) {
            array_push($tableNames, 'history', 'history_log', 'history_str', 'history_text', 'history_uint');
        }

        if (
            HousekeepingHelper::get(HousekeepingHelper::HK_TRENDS_MODE) == 1
            && (!$timescaleExtension || HousekeepingHelper::get(HousekeepingHelper::HK_TRENDS_GLOBAL) == 0)
        ) {
            array_push($tableNames, 'trends', 'trends_uint');
        }

        $housekeeper = [];

        foreach ($itemIds as $itemId) {
            foreach ($tableNames as $tableName) {
                $housekeeper[] = [
                    'tablename' => $tableName,
                    'field' => 'itemid',
                    'value' => $itemId
                ];

                if (count($housekeeper) == PRS_DB_MAX_INSERTS) {
                    DB::insertBatch('housekeeper', $housekeeper);
                    $housekeeper = [];
                }
            }
        }

        if ($housekeeper) {
            DB::insertBatch('housekeeper', $housekeeper);
        }
    }

    /**
     * @param array      $templateIds
     * @param array|null $hostIds
     */
    public static function unlinkTemplateObjects(array $templateIds, array $hostIds = null): void
    {
        $query = new Query();
        $query->from([
            'i' => Items::tableName(),
            'ii' => Items::tableName(),
            'h' => Hosts::tableName(),
        ]);
        $query->where('i.itemid=ii.templateid')
            ->where('ii.hostid=h.hostid')
            ->andWhere(SqlHelper::whereIn('i.hostid', $templateIds))
            ->andWhere(['i.flags' => PRS_FLAG_DISCOVERY_NORMAL])
            ->andWhere(['i.type' => self::SUPPORTED_ITEM_TYPES]);

        if ($hostIds) {
            $query->andWhere(SqlHelper::whereIn('ii.hostid', $hostIds));
        }

        $query->select(['ii.itemid', 'ii.name', 'ii.type', 'ii.key_', 'ii.value_type', 'ii.templateid', 'ii.uuid', 'ii.valuemapid', 'ii.hostid']);

        $items = [];
        $db_items = [];
        $i = 0;
        $tpl_itemids = [];
        $internal_fields = array_flip(['key_', 'value_type', 'hostid', 'flags', 'host_status']);

        foreach ($query->each() as $row) {
            $item = [
                'itemid' => $row['itemid'],
                'type' => $row['type'],
                'templateid' => 0
            ];

            if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
                $item += ['uuid' => generateUuidV4()];
            }

            if ($row['valuemapid'] != 0) {
                $item += ['valuemapid' => 0];

                if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
                    $tpl_itemids[$i] = $row['itemid'];
                    $item += array_intersect_key($row, $internal_fields);
                }
            }

            $items[$i++] = $item;
            $db_items[$row['itemid']] = $row;
        }

        if ($items) {
            self::updateItems($items, $db_items);

            if ($tpl_itemids) {
                $items = array_intersect_key($items, $tpl_itemids);
                $db_items = array_intersect_key($db_items, array_flip($tpl_itemids));

                self::inherit($items, $db_items);
            }
        }
    }

    /**
     * @param array      $templateIds
     * @param array|null $hostIds
     */
    public static function clearTemplateObjects(array $templateIds, array $hostIds = null): void
    {
        $query = new Query();
        $query->from([
            'i' => Items::tableName(),
            'ii' => Items::tableName(),
        ]);
        $query->where('i.itemid=ii.templateid')
            ->andWhere(SqlHelper::whereIn('i.hostid', $templateIds))
            ->andWhere(['i.flags' => PRS_FLAG_DISCOVERY_NORMAL])
            ->andWhere(['i.type' => self::SUPPORTED_ITEM_TYPES]);

        if ($hostIds) {
            $query->andWhere(SqlHelper::whereIn('ii.hostid', $hostIds));
        }

        $query->select(['ii.name', 'ii.itemid'])->indexBy('itemid');
        $id2name = $query->column();
        if ($id2name) {
            self::deleteForce($id2name);
        }
    }

    /**
     * @param array $templateIds
     * @param array $hostIds
     */
    public static function linkTemplateObjects(array $templateIds, array $hostIds): void
    {
    }
}
