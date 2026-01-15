<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_BOOL
 * Class BoolValidator
 * @package app\customs\zapi\common\validators
 */
class BooleanValidator extends BaseZValidator
{
    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        if (($this->flags & API_ALLOW_NULL) && $value === null) {
            return null;
        }

        if (!is_bool($value)) {
            return $this->setValueError(t('zapi', 'a boolean is expected'));
        }
        return null;
    }
}