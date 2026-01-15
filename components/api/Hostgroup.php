<?php

namespace app\customs\zapi\components\api;

use app\common\components\Result;
use app\customs\zapi\components\BaseApi;
use app\customs\zapi\services\HostGroupService;

class Hostgroup extends BaseApi
{
    /**
     * {@inheritDoc}
     */
    public function create(array $params): Result
    {
        return HostGroupService::instance()->createByInternal($params);
    }

    /**
     * {@inheritDoc}
     */
    public function update(array $params): Result
    {
        return HostGroupService::instance()->updateByInternal($params);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(array $params): Result
    {
        return HostGroupService::instance()->deleteByInternal($params);
    }
}