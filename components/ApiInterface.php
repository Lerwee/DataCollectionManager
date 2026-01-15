<?php

namespace app\customs\zapi\components;

use app\common\components\Result;

interface ApiInterface
{
    /**
     * create
     * @param  array  $params
     * @return Result
     */
    public function create(array $params): Result;
    /**
     * update
     * @param  array  $params
     * @return Result
     */
    public function update(array $params): Result;
    /**
     * delete
     * @param  array  $params
     * @return Result
     */
    public function delete(array $params): Result;
}
