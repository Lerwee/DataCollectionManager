<?php

namespace app\customs\zapi\components\api;

use app\common\components\Result;
use app\customs\zapi\components\BaseApi;
use app\customs\zapi\services\MaintenanceService;

class Maintenance extends BaseApi
{
    /**
     * {@inheritDoc}
     */
    public function create(array $params): Result
    {
        return MaintenanceService::instance()->createByInternal($params);
    }

    /**
     * {@inheritDoc}
     */
    public function update(array $params): Result
    {
        return MaintenanceService::instance()->updateByInternal($params);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(array $params): Result
    {
        return MaintenanceService::instance()->deleteByInternal($params);
    }
}