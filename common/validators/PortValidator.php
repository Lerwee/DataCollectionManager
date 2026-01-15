<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CLLDMacroParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CUserMacroParser;
use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\NotSupportedException;

/**
 * API_PORT
 * Class PortValidator
 * @package app\customs\zapi\common\validators
 */
class PortValidator extends BaseZValidator
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
        if (!is_int($value) && !is_string($value)) {
            return $this->setValueError(t('zapi', 'a number is expected'));
        }
        $value = (string)$value;
        if (($this->flags & API_NOT_EMPTY) == 0 && $value === '') {
            return null;
        }
        $utfValidator = new Utf8StringValidator(['flags' => $this->flags & API_NOT_EMPTY, 'length' => $this->length]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }

        if ($this->flags & API_ALLOW_USER_MACRO) {
            $user_macro_parser = new CUserMacroParser();
            if ($user_macro_parser->parse($value) == CParser::PARSE_SUCCESS) {
                return null;
            }
        }

        if ($this->flags & API_ALLOW_LLD_MACRO) {
            $lld_macro_parser = new CLLDMacroParser();
            if ($lld_macro_parser->parse($value) == CParser::PARSE_SUCCESS) {
                return null;
            }
        }

        $utfValidator = new Int32Validator(['in' => [[PRS_MIN_PORT_NUMBER, PRS_MAX_PORT_NUMBER]]]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }
        return null;
    }
}