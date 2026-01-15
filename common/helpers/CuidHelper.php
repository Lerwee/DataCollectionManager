<?php

namespace app\customs\zapi\common\helpers;

/**
 * Class CuidHelper
 * @package app\customs\zapi\common\helpers
 */
class CuidHelper
{
    /**
     * Add the UUID to those of the given params that don't have the 'uuid' parameter set.
     *
     * @param array $params
     */
    public static function addUUID(array &$params, $field = 'uuid')
    {
        foreach ($params as &$param) {
            if (!array_key_exists($field, $param)) {
                $param[$field] = generateUuidV4();
            }
        }
        unset($param);
    }
}
