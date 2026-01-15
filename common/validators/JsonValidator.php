<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\macros\CMacrosResolverGeneral;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * Class JsonValidator
 * @package app\customs\zapi\common\validators
 */
class JsonValidator extends BaseZValidator
{
    /**
     * @var null|int
     */
    public $length = null;

    public $macros_n = null;

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
        if ($value === '') {
            return null;
        }
        if (is_numeric($this->length) && mb_strlen($value) > $this->length) {
            return $this->setValueError(t('zapi', 'value is too long'));
        }
        $json = $value;
        $types = [];
        if ($this->flags & API_ALLOW_USER_MACRO) {
            $types['usermacros'] = true;
        }

        if ($this->flags & API_ALLOW_LLD_MACRO) {
            $types['lldmacros'] = true;
        }

        if ($this->macros_n !== null) {
            $types['macros_n'] = $this->macros_n;
        }

        if ($types) {
            $matches = CMacrosResolverGeneral::getMacroPositions($json, $types);
            $shift = 0;

            foreach ($matches as $pos => $substr) {
                $json = substr_replace($json, '1', $pos + $shift, strlen($substr));
                $shift = $shift + 1 - strlen($substr);
            }
        }

        json_decode($json);

        if (json_last_error() != JSON_ERROR_NONE) {
            return $this->setValueError(t('zapi', 'JSON is expected'));
        }
        return null;
    }
}