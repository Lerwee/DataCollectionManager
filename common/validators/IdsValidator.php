<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\NotSupportedException;

/**
 * API_IDS
 * Class IdsValidator
 * @package app\customs\zapi\common\validators
 */
class IdsValidator extends BaseZValidator
{
    /**
     * @param $model
     * @param $attribute
     * @return array|null
     * @throws NotSupportedException
     */
    public function validateModelValue($model, $attribute): ?array
    {
        $value = $model->$attribute;
        if (($this->flags & API_ALLOW_NULL) && $value === null) {
            return null;
        }

        $utfValidator = new IdValidator();
        if (($this->flags & API_NORMALIZE) && empty($utfValidator->validateValue($value))) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return $this->setValueError(t('zapi', 'an array is expected'));
        }

        if (($this->flags & API_NOT_EMPTY) && !$value) {
            return $this->setValueError(t('zapi', 'cannot be empty'));
        }

        $data = array_values($value);
        foreach ($data as $index => $valueItem) {
            $result = $utfValidator->validateValue($valueItem);
            if (!empty($result)) {
                return $result;
            }
        }
        unset($valueItem);
        $model->$attribute = $data;
        return null;
    }

    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        if (($this->flags & API_ALLOW_NULL) && $value === null) {
            return null;
        }

        $utfValidator = new IdValidator();
        if (($this->flags & API_NORMALIZE) && empty($utfValidator->validateValue($value))) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return $this->setValueError(t('zapi', 'an array is expected'));
        }

        if (($this->flags & API_NOT_EMPTY) && !$value) {
            return $this->setValueError(t('zapi', 'cannot be empty'));
        }

        $data = array_values($value);
        foreach ($data as $index => $valueItem) {
            $result = $utfValidator->validateValue($valueItem);
            if (!empty($result)) {
                return $result;
            }
        }
        unset($valueItem);
        return null;
    }
}