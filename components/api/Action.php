<?php

namespace app\customs\zapi\components\api;

use app\common\components\Result;
use app\customs\zapi\components\BaseApi;
use app\customs\zapi\services\ActionService;

class Action extends BaseApi
{
    /**
     * {@inheritDoc}
     */
    public function create(array $params): Result
    {
        return Result::instance()->setData(ActionService::instance()->create($params));
    }

    /**
     * {@inheritDoc}
     */
    public function update(array $params): Result
    {
        return Result::instance()->setData(ActionService::instance()->update($params));
    }

    /**
     * {@inheritDoc}
     */
    public function delete(array $params): Result
    {
        return Result::instance();
    }
}