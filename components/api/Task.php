<?php

namespace app\customs\zapi\components\api;

use app\common\components\Result;
use app\customs\zapi\components\BaseApi;
use app\customs\zapi\services\TaskService;

class Task extends BaseApi
{
    /**
     * {@inheritDoc}
     */
    public function create(array $params): Result
    {
        return Result::instance()->setData(TaskService::instance()->create($params));
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
}