<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CLLDMacroParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_LLD_MACRO
 * Class LLDMacroValidator
 * @package app\customs\zapi\common\validators
 */
class LLDMacroValidator extends BaseZValidator
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
        if ((new CLLDMacroParser())->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError(t('zapi', 'a low-level discovery macro is expected'));
        }

        return null;
    }
}