<?php

namespace app\customs\zapi\common\managers\base;

use app\common\helpers\SqlHelper;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\Hstgrp;
use app\modules\libzbx\models\zbx\Maintenances;
use app\modules\libzbx\models\zbx\MaintenancesGroups;
use app\modules\libzbx\models\zbx\MaintenancesHosts;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;

trait ManagerTrait
{
    /**
     * 查询给定的继承ID集合
     *
     * @param array $ids          父级ID集合
     * @param string $table       表名
     * @param string $selectField 查询字段
     * @param string $whereField  条件字段
     * @return array
     */
    public static function getInheritedOrDependentIds(array $ids, string $table, string $whereField, string $selectField): array
    {
        $deleteIds = [];
        $parentIds = array_flip($ids);

        do {
            $query = new Query();
            $query->from($table)
                ->select($selectField)
                ->where(SqlHelper::whereIn($whereField, $ids));

            $deleteIds += $parentIds;
            $parentIds = [];

            if ($ids = $query->column()) {
                foreach ($ids as $id) {
                    if (!array_key_exists($id, $deleteIds)) {
                        $parentIds[$id] = true;
                    }
                }
            }
        } while ($parentIds);

        return array_keys($deleteIds);
    }

    /**
     * 查询给定集合的继承或依赖数据
     *
     * @param array $id2name      父集合
     * @param string $table       表名
     * @param string $whereField  条件字段
     * @param string $indexField  下标字段
     * @param string $nameField   名称字段
     * @return array
     */
    public static function getInheritedOrDependentData(array $id2name, string $table, string $whereField, string $indexField, $nameField = 'name'): array
    {
        $nodeIds = [];
        $parentIds = $id2name;

        do {

            $query = new Query();
            $query->from($table)
                ->select($nameField)
                ->where(SqlHelper::whereIn($whereField, array_keys($parentIds)))
                ->indexBy($indexField);

            $nodeIds += $parentIds;
            $parentIds = [];

            if ($buf = $query->column()) {
                foreach ($buf as $id => $name) {
                    if ($id && !array_key_exists($id, $nodeIds)) {
                        $parentIds[$id] = $name;
                    }
                }
            }
        } while ($parentIds);

        return $nodeIds;
    }

    /**
     * Check that no maintenance object will be left without hosts and host groups as the result of the given host
     * groups deletion.
     *
     * @param int[]|int $groupId
     *
     * @throws Exception
     */
    public static function checkMaintenancesByGroupId($groupId): void
    {
        $maintenance = self::checkMaintenances(null, $groupId);
        if ($maintenance) {
            $query = new Query();
            $query->from([
                'mg' => MaintenancesGroups::tableName(),
                'g' => Hstgrp::tableName(),
            ]);
            $query->where('{{mg}}.groupid={{g}}.groupid')
                ->andWhere(['mg.maintenanceid' => $maintenance['maintenanceid']]);

            $query->select('g.name');

            $maintenanceGroups = $query->column();

            $error = tn('zapi', [
                'Cannot delete host group {group_name} because maintenance "{maintenance_name}" must contain at least one host or host group.',
                'Cannot delete host groups %1$s because maintenance "{maintenance_name}" must contain at least one host or host group.',
                count($maintenanceGroups)
            ], [
                'group_name' => json_encode($maintenanceGroups, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'maintenance_name' => $maintenance['name'],
            ]);
            throw new Exception($error);
        }
    }


    /**
     * Check that no maintenance object will be left without hosts and host groups as the result of the given hosts
     * deletion.
     *
     * @param int[]|int $hostId
     *
     * @throws Exception
     */
    public static function checkMaintenancesByHostId(array $hostId): void
    {
        $maintenance = self::checkMaintenances($hostId, null);
        if ($maintenance) {

            $query = new Query();
            $query->from([
                'mh' => MaintenancesHosts::tableName(),
                'h' => Hosts::tableName(),
            ]);

            $query->where('{{mh}}.hostid={{h}}.groupid')
                ->andWhere(['mh.maintenanceid' => $maintenance['maintenanceid']]);

            $query->select('h.host');

            $maintenanceHosts = $query->column();

            $error = tn('zapi', [
                'Cannot delete host {host} because maintenance "{maintenance_name}" must contain at least one host or host group.',
                'Cannot delete hosts {host} because maintenance "{maintenance_name}" must contain at least one host or host group.',
                count($maintenanceHosts)
            ], [
                'host' => json_encode($maintenanceHosts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'maintenance_name' => $maintenance['name'],
            ]);

            throw new Exception($error);
        }
    }

    /**
     * @param int[]|int|null $hostId
     * @param int[]|int|null  $groupId
     * @return array|null
     */
    private static function checkMaintenances($hostId = null, $groupId = null)
    {
        $null = new Expression('NULL');

        $hostQuery = MaintenancesHosts::find()
            ->select($null)
            ->alias('mh')
            ->where('{{m}}.maintenanceid={{mh}}.maintenanceid');
        if ($hostId) {
            $hostQuery->andWhere(is_array($hostId) ? SqlHelper::whereIn('{{mh}}.hostid', filter_integer($hostId), true) : ['<>', 'mh.hostid', (int) $hostId]);
        }

        $groupQuery = MaintenancesGroups::find()
            ->select($null)
            ->alias('mg')
            ->where('{{m}}.maintenanceid={{mg}}.maintenanceid');
        if ($groupId) {
            $groupQuery->andWhere(is_array($groupId) ? SqlHelper::whereIn('{{mg}}.groupid', filter_integer($groupId), true) : ['<>', 'mg.groupid', (int) $groupId]);
        }


        $query = Maintenances::find()
            ->alias('m')
            ->select(['m.maintenanceid', 'm.name']);

        $query->where(['NOT EXISTS', $hostQuery])
            ->andWhere(['NOT EXISTS', $groupQuery]);

        return $query->asArray()->limit(1)->one();
    }
}
