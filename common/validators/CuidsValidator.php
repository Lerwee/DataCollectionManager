<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_CUIDS
 * Class CuidsValidator
 * @package app\customs\zapi\common\validators
 */
class CuidsValidator extends BaseZValidator
{
    /**
     * @param $model
     * @param $attribute
     * @return array|null
     */
    public function validateModelValue($model, $attribute): ?array
    {
        $value = $model->$attribute;
        if (($this->flags & API_ALLOW_NULL) && $value === null) {
            return null;
        }
        $validator = new CuidValidator();
        if (($this->flags & API_NORMALIZE) && empty($validator->validateValue($value))) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return $this->setValueError(t('zapi', 'an array is expected'));
        }

        $data = array_values($value);
        foreach ($data as $index => $value) {
            $result = $validator->validateValue($data);
            if (!empty($result)) {
                return $result;
            }
        }
        $model->$attribute = $data;
        return null;
    }
}