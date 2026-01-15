<?php

namespace app\customs\zapi\components\api;

use app\common\components\Result;
use app\customs\zapi\components\BaseApi;
use app\customs\zapi\services\TemplateGroupService;

class Templategroup extends BaseApi
{
    /**
     * {@inheritDoc}
     */
    public function create(array $params): Result
    {
        return TemplateGroupService::instance()->create($params);
    }

    /**
     * {@inheritDoc}
     */
    public function update(array $params): Result
    {
        return TemplateGroupService::instance()->update($params);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(array $params): Result
    {
        return TemplateGroupService::instance()->delete($params);
    }
}