<?php

namespace app\customs\zapi\components\api;

use app\common\components\Result;
use app\customs\zapi\components\BaseApi;
use app\customs\zapi\services\EventService;

class Event extends BaseApi
{
    /**
     * {@inheritDoc}
     */
    public function create(array $params): Result
    {
        return Result::instance();
    }

    /**
     * {@inheritDoc}
     */
    public function update(array $params): Result
    {
        return Result::instance();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(array $params): Result
    {
        return Result::instance();
    }

    /**
     * 确认/关闭告警
     *
     * @param  array  $params
     * @return Result
     */
    public function acknowledge(array $params): Result
    {
        return Result::instance()->setData(EventService::instance()->acknowledge($params));
    }
}