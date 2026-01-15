<?php

namespace app\customs\zapi\components\api;

use app\common\components\Result;
use app\customs\zapi\components\BaseApi;
use app\customs\zapi\services\ScriptService;

class Script extends BaseApi
{
    /**
     * {@inheritDoc}
     */
    public function create(array $params): Result
    {
        return Result::instance()->setData(ScriptService::instance()->create($params));
    }

    /**
     * {@inheritDoc}
     */
    public function update(array $params): Result
    {
        return Result::instance()->setData(ScriptService::instance()->update($params));
    }

    /**
     * {@inheritDoc}
     */
    public function delete(array $params): Result
    {
        return Result::instance()->setData(ScriptService::instance()->delete($params));
    }

    /**
     * 脚本执行
     *
     * @param  array  $params
     * @return Result
     */
    public function execute(array $params): Result
    {
        return Result::instance()->setData(ScriptService::instance()->execute($params));
    }
}