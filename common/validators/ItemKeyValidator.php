<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CItemKey;
use app\customs\zapi\common\parsers\CLLDMacroFunctionParser;
use app\customs\zapi\common\parsers\CLLDMacroParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_ITEM_KEY
 * Class ItemKeyValidator
 * @package app\customs\zapi\common\validators
 */
class ItemKeyValidator extends BaseZValidator
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
        $item_key_parser = new CItemKey();

        if ($item_key_parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError($item_key_parser->getError());
        }

        if (($this->flags & API_REQUIRED_LLD_MACRO)) {
            $parameters = $item_key_parser->getParamsRaw();
            $lld_macro_parser = new CLLDMacroParser();
            $lld_macro_function_parser = new CLLDMacroFunctionParser();
            $has_lld_macros = false;

            if ($parameters) {
                $parameters = $parameters[0]['raw'];
                $p = 1;

                while (isset($parameters[$p])) {
                    if ($lld_macro_parser->parse($parameters, $p) != CParser::PARSE_FAIL
                        || $lld_macro_function_parser->parse($parameters, $p) != CParser::PARSE_FAIL) {
                        $has_lld_macros = true;
                        break;
                    }

                    $p++;
                }
            }

            if (!$has_lld_macros) {
                return $this->setValueError(t('zapi', 'must contain at least one low-level discovery macro'));
            }
        }
        return null;
    }
}