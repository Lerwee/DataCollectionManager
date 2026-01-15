<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_VARIABLE_NAME
 * Class RegexValidator
 * @package app\customs\zapi\common\validators
 */
class VariableNameValidator extends BaseZValidator
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
        $utfValidator = new Utf8StringValidator(['flags' => API_NOT_EMPTY, 'length' => $this->length]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }
        if (preg_match('/^{[^{}]+}$/', $value) !== 1) {
            return $this->setValueError(t('zapi', 'is not enclosed in {} or is malformed'));
        }
        return null;
    }
}