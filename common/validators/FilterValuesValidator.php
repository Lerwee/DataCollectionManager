<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * Class FilterValuesValidator
 * @package app\customs\zapi\common\validators
 */
class FilterValuesValidator extends BaseZValidator
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

        $utfValidator = new FilterValueValidator();
        if (($this->flags & API_NORMALIZE) && empty($utfValidator->validateValue($value))) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return $this->setValueError(t('zapi', 'an array is expected'));
        }

        $value = array_values($value);
        foreach ($value as $index => &$valueItem) {
            $result = $utfValidator->validateValue($valueItem);
            if (!empty($result)) {
                return $result;
            }
        }
        unset($valueItem);
        $model->$attribute = $value;
        return null;
    }
}