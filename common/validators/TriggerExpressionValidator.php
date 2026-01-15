<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CExpressionParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\validators\base\BaseZValidator;
use app\customs\zapi\common\validators\z\CExpressionValidator;

/**
 * API_TRIGGER_EXPRESSION
 * Class TriggerExpressionValidator
 * @package app\customs\zapi\common\validators
 */
class TriggerExpressionValidator extends BaseZValidator
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
        if (($this->flags & API_NOT_EMPTY) == 0 && $value === '') {
            return null;
        }
        $utfValidator = new Utf8StringValidator(['flags' => $this->flags & API_NOT_EMPTY, 'length' => $this->length]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }

        $expression_parser = new CExpressionParser([
            'usermacros' => true,
            'lldmacros' => ($this->flags & API_ALLOW_LLD_MACRO)
        ]);

        if ($expression_parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError($expression_parser->getError());
        }

        $expression_validator = new CExpressionValidator([
            'usermacros' => true,
            'lldmacros' => ($this->flags & API_ALLOW_LLD_MACRO)
        ]);

        if (!$expression_validator->validate($expression_parser->getResult()->getTokens())) {
            return $this->setValueError($expression_validator->getError());
        }
        return null;
    }
}