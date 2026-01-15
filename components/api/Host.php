<?php

namespace app\customs\zapi\components\api;

use app\common\components\Result;
use app\customs\zapi\components\BaseApi;
use app\customs\zapi\services\HostService;

class Host extends BaseApi
{
    /**
     * {@inheritDoc}
     */
    public function create(array $params): Result
    {
        return HostService::instance()->createByInternal($params);
    }

    /**
     * {@inheritDoc}
     */
    public function update(array $params): Result
    {
        return HostService::instance()->updateByInternal($params);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(array $params): Result
    {
        return HostService::instance()->deleteByInternal($params);
    }

    /**
     * 批量添加
     *
     * @param  array  $params
     * @return Result
     */
    public function massadd(array $params): Result
    {
        return HostService::instance()->massAdd($params);
    }

    /**
     * 批量更新
     *
     * @param  array  $params
     * @return Result
     */
    public function massupdate(array $params): Result
    {
        return HostService::instance()->massUpdate($params, false);
    }
}