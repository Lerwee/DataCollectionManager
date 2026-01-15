<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_FLAG
 * Class SortOrderValidator
 * @package app\customs\zapi\common\validators
 */
class FlagValidator extends BaseZValidator
{
    /**
     * @param $model
     * @param $attribute
     * @return array|null
     */
    public function validateModelValue($model, $attribute): ?array
    {
        $value = $model->$attribute;
        if (is_bool($value)) {
            return null;
        }

        /**
         * @deprecated  As of version 3.4, use boolean flags only.
         */
        trigger_error(t('zapi', 'Non-boolean flags are deprecated.'), E_USER_NOTICE);
        $model->$attribute = !is_null($value);
        return null;
    }
}