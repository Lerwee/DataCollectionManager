<?php

namespace app\customs\zapi\common\helpers;

use app\models\AuditLog;

class AuditHelper
{
    const ACTION_CREATE = AuditLog::ACTION_ADD;
    const ACTION_UPDATE = AuditLog::ACTION_UPDATE;
    const ACTION_DELETE = AuditLog::ACTION_DELETE;

    /**
     * @var array 审计资源数据
     * e.g.
     * ```php
     * return [
     *      '<resource_id_1>' => '<resource_name_1>', 
     *      '<resource_id_2>' => '<resource_name_2>', 
     * ];
     * ```
     */
    static $resources;

    /**
     * @var array 审计详情数据
     * 
     * e.g.
     * ```php
     * return [
     *      '<resource_id_1>' => [
     *          ['attribute_1', 'old_value_1', 'new_value_1'],
     *          ['attribute_2', 'old_value_2', 'new_value_2'],
     *      ], 
     *      '<resource_id_2>' => '<resource_name_2>', 
     * ];
     * ```
     * 
     */
    static $details;

    /**
     * 采集审计资源数据
     *
     * @param  array $resources
     * @param  array $details
     */
    public static function collect(array $resources, array $newDetails = [], array $oldDetails = [])
    {
        // if (self::$resources === null) {
        //     self::$resources = [];
        // }
        // self::$resources += $resources;

        foreach($resources as $id => $name) {
            self::$resources[$id] = $name;
            static::collectDetails($id, $newDetails[$id] ?? [], $oldDetails[$id] ?? []);
        }
    }

    /**
     * 采集审计资源详情数据
     *
     * @param  array $resourceId
     * @param  array $details
     */
    public static function collectDetails(int $resourceId, array $details, array $origins = [])
    {
        if (self::$details === null) {
            self::$details = [];
        }
        
        if (!array_key_exists($resourceId, self::$details)) {
            self::$details[$resourceId] = [];
        }
        if($details) {
            self::$details[$resourceId] = static::formatDetails($details, $origins);
        }
    }

    
    /**
     * @param  integer $resourceId
     * @param  string  $attr
     * @param  mixed  $new
     * @param  mixed  $old
     * @return void
     */
    public static function collectDetail(int $resourceId, string $attr, $new, $old = '')
    {
        if(self::$details === null) {
            self::$details = [];
        }
        if (!array_key_exists($resourceId, self::$details)) {
            self::$details[$resourceId] = [];
        }
        self::$details[$resourceId][] = audit_detail($attr, static::fmtValue($old), static::fmtValue($new));
    }

    /**
     * @param  array $newData
     * @param  array $oldData
     * @return array
     */
    public static function formatDetails(array $newData, array $oldData = []): array
    {
        $details = [];
        array_walk($newData, function($value, $attr) use(&$details, $oldData) {
            $oldValue = array_key_exists($attr, $oldData) ? $oldData[$attr] : '';
            if ($value != $oldValue) {
                $details[] = audit_detail($attr, static::fmtValue($oldValue), static::fmtValue($value));
            }
        });
        return $details;
    }

    private static function fmtValue($value)
    {
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $value;
    }

    /**
     * Saves
     *
     * @param  string  $message
     * @param  integer  $action
     * @param  boolean $afterClear
     * @return void
     */
    public static function save(string $message, int $action = AuditLog::ACTION_UPDATE, bool $afterClear = true)
    {
        if (self::$resources) {
            foreach(self::$resources as $id => $name) {
                audit_log(RESOURCE_ZAPI, [$id => $name], $message, self::$details[$id] ?? [], $action);
            }
            if ($afterClear) {
                self::$resources = self::$details = null;
            }
        }
    }
}