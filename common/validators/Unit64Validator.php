<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\helpers\ValueCheckHelper;
use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Model;

/**
 * Class Unit64Validator
 * @package app\customs\zapi\common\validators
 */
class Unit64Validator extends BaseZValidator
{
    /**
     * @param $model
     * @param $attribute
     * @return array|null
     */
    public function validateModelValue($model, $attribute): ?array
    {
        $value = $model->$attribute;
        $result = $this->validateValue($value);
        if ($result === null) {
            $data = (string)$value;
            if ($data[0] === '0') {
                $data = ltrim($data, '0');
                if ($data === '') {
                    $data = '0';
                }
            }
            $model->$attribute = floatval($data);
        }
        return $result;
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

        if (!is_scalar($value) || is_bool($value) || is_double($value) || !ctype_digit(strval($value))) {
            return $this->setValueError(t('zapi', 'an integer is expected'));
        }

        if (bccomp($value, PRS_MAX_UINT64) > 0) {
            return $this->setValueError(t('zapi', 'a number is too large'));
        }
        return null;
    }
}