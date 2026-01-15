<?php

namespace app\customs\zapi\common\helpers;

use app\common\helpers\SqlHelper;
use app\customs\zapi\models\search\ProxySearch;

class ProxyHelper
{
    /**
     * 返回给定条件的主机代理
     *
     * @see static::getGroups
     * @param  array $options
     * @return array|int
     */
    public static function getProxies(array $options)
    {
        $search = new ProxySearch();
        $search->is_all = true;
        $provider = $search->search($options);
        if (!empty($search->countOutput)) {
            return (int) $provider->getTotalCount();
        }
        return $provider->getModels();
    }
}