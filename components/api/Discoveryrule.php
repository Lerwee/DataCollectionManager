<?php

namespace app\customs\zapi\components\api;

use app\common\components\Result;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\components\BaseApi;
use app\customs\zapi\services\assist\DiscoverRuleAssist;

class Discoveryrule extends BaseApi
{
    /**
     * {@inheritDoc}
     */
    public function get(array $params): Result
    {
        $data = DiscoverRuleHelper::getDiscoverRules($params);
        return $this->success($data);
    }
    /**
     * {@inheritDoc}
     */
    public function create(array $params): Result
    {
        return DiscoverRuleAssist::instance()->create($params);
    }

    /**
     * {@inheritDoc}
     */
    public function update(array $params): Result
    {
        return DiscoverRuleAssist::instance()->update($params);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(array $params): Result
    {
        return DiscoverRuleAssist::instance()->delete($params);
    }
}