<?php

namespace app\customs\zapi\components\api;

use app\common\components\Result;
use app\customs\zapi\components\BaseApi;
use app\customs\zapi\services\TemplateService;

class Template extends BaseApi
{
    /**
     * {@inheritDoc}
     */
    public function create(array $params): Result
    {
        return TemplateService::instance()->createByInternal($params);
    }

    /**
     * {@inheritDoc}
     */
    public function update(array $params): Result
    {
        return TemplateService::instance()->updateByInternal($params);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(array $params): Result
    {
        return TemplateService::instance()->deleteByInternal($params);
    }
}