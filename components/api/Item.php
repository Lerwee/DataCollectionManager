<?php

namespace app\customs\zapi\components\api;

use app\common\components\Result;
use app\customs\zapi\components\BaseApi;
use app\customs\zapi\services\assist\ItemAssist;

class Item extends BaseApi
{
    /**
     * {@inheritDoc}
     */
    public function create(array $params): Result
    {
        return ItemAssist::instance()->create($params);
    }

    /**
     * {@inheritDoc}
     */
    public function update(array $params): Result
    {
        return ItemAssist::instance()->update($params);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(array $params): Result
    {
        return ItemAssist::instance()->delete($params);
    }
}