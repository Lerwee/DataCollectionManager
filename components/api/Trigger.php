<?php

namespace app\customs\zapi\components\api;

use app\common\components\Result;
use app\customs\zapi\common\helpers\TriggerHelper;
use app\customs\zapi\components\BaseApi;
use app\customs\zapi\services\assist\TriggerAssist;

class Trigger extends BaseApi
{
    /**
     * {@inheritDoc}
     */
    public function get(array $params): Result
    {
        $data = TriggerHelper::getTriggers($params);
        return $this->success($data);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $params): Result
    {
        return TriggerAssist::instance()->create($params);
    }

    /**
     * {@inheritDoc}
     */
    public function update(array $params): Result
    {
        return TriggerAssist::instance()->update($params);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(array $params): Result
    {
        return TriggerAssist::instance()->delete($params);
    }
}