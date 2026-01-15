<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * Class FilterValueValidator
 * @package app\customs\zapi\common\validators
 */
class FilterValueValidator extends BaseZValidator
{
    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        if (!is_string($value) && !is_double($value) && !is_int($value)) {
            return $this->setValueError(t('zapi', 'a character string, integer or floating point value is expected'));
        }

        if (is_string($value) &&  mb_check_encoding($value, 'UTF-8') !== true) {
            return $this->setValueError(t('zapi', 'invalid byte sequence in UTF-8'));
        }

        return null;
    }
}