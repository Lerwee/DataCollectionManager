<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * 
 * Class LimitedSetValidator
 * @package app\customs\zapi\common\validators
 */
class LimitedSetValidator extends BaseZValidator
{
    /**
     * Allowed values.
     *
     * @var array
     */
    public $values = [];

    /**
     * Error message if the value is invalid or is not of an acceptable type.
     *
     * @var string
     */
    public $messageInvalid = null;

    /**
     * Checks if the given value belongs to some set.
     *
     * @param $value
     *
     * @return 
     */
    public function validateValue($value)
    {
        if (!is_string($value) && !is_int($value)) {
            if ($this->messageInvalid !== null) {
                return $this->setValueError(t('zapi', $this->messageInvalid, ['value' => $this->stringify($value)]));
            }
            return 'error';
        }
        $values = array_flip($this->values);
        if (!isset($values[$value])) {
            if ($this->messageInvalid !== null) {
                return $this->setValueError(t('zapi', $this->messageInvalid, ['value' => $this->stringify($value)]));
            }
            return 'error';
        }
        return null;
    }
}
