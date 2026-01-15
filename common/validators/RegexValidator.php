<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_REGEX
 * Class RegexValidator
 * @package app\customs\zapi\common\validators
 */
class RegexValidator extends BaseZValidator
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
        $utfValidator = new Utf8StringValidator(['flags' => $this->flags, 'length' => $this->length]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }
        if (($this->flags & API_ALLOW_GLOBAL_REGEX) && $value !== '' && $value[0] === '@') {
            return null;
        }

        if (@preg_match('(' . $value . ')', '') === false) {
            return $this->setValueError(t('zapi', 'invalid regular expression'));
        }
        return null;
    }
}