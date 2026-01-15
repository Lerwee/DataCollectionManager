<?php

namespace app\customs\zapi\common\helpers;

use app\customs\zapi\models\search\group\TemplateGroupSearch;
use app\customs\zapi\models\search\group\HostGroupSearch;
use app\customs\zapi\common\helpers\CArrayHelper;

class GroupHelper
{
    /**
     * 返回给定条件的主机分组
     *
     * @see static::getGroups
     * @param  array $options
     * @return array|int
     */
    public static function getHostGroups(array $options)
    {
        $search = new HostGroupSearch();
        $search->is_all = true;
        $provider = $search->search($options);
        if (!empty($search->countOutput)) {
            return (int) $provider->getTotalCount();
        }
        $records = $provider->getModels();
        CArrayHelper::sort($records, ['name']);
        return $search->preservekeys ? $records : array_values($records);
    }

    /**
     * 返回给定条件的模板分组
     *
     * @see static::getGroups
     * @param  array $options
     * @return array|int
     */
    public static function getTemplateGroups(array $options)
    {
        $search = new TemplateGroupSearch();
        $search->is_all = true;
        $provider = $search->search($options);
        if (!empty($search->countOutput)) {
            return (int) $provider->getTotalCount();
        }
        return $provider->getModels();
    }
}
