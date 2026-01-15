<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_PSK
 * Class PSKValidator
 * @package app\customs\zapi\common\validators
 */
class PSKValidator extends BaseZValidator
{
    /**
     * @var null|int
     */
    public $length = null;

    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        $utfValidator = new Utf8StringValidator(['flags' => $this->flags & API_NOT_EMPTY]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }
        $mb_len = mb_strlen($value);
        if ($mb_len != 0 && $mb_len < PSK_MIN_LEN) {
            return $this->setValueError(t('zapi', 'minimum length is {num} characters', ['num' => PSK_MIN_LEN]));
        }

        if (preg_match('/^([0-9a-f]{2})*$/i', $value) !== 1) {
            return $this->setValueError(t('zapi', 'an even number of hexadecimal characters is expected'));
        }

        if ($this->length && $mb_len > $this->length) {
            return $this->setValueError(t('zapi', 'value is too long'));
        }
        return null;
    }
}