<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\helpers\ValueCheckHelper;
use app\customs\zapi\common\parsers\CLLDMacroFunctionParser;
use app\customs\zapi\common\parsers\CLLDMacroParser;
use app\customs\zapi\common\parsers\CNumberParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CUserMacroParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_FLOAT
 * Class FloatValidator
 * @package app\customs\zapi\common\validators
 */
class FloatValidator extends BaseZValidator
{
    /**
     * @var int
     */
    protected $inType = self::IN_TYPE_NUMBER;

    /**
     * .e.g
     * [1, [1, 10]]
     * @var array
     */
    public $in = [];

    /**
     * ['operator' => '>', 'field' => 'active_since']
     * @var array
     */
    public $compare = [];

    /**
     * @var null|int
     */
    public $length = null;

    /**
     * @param $model
     * @param $attribute
     * @return array|null
     */
    public function validateModelValue($model, $attribute): ?array
    {
        $value = $model->$attribute;
        if (($this->flags & API_ALLOW_NULL) && $value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            $valueItem = (float) $value;
        }
        elseif (is_string($value)) {
            $number_parser = new CNumberParser();
            if ($number_parser->parse($value) == CParser::PARSE_SUCCESS) {
                $valueItem = (float) $number_parser->getMatch();
            }
            else {
                $flags = [];
                if ($this->flags & API_ALLOW_USER_MACRO) {
                    $user_macro_parser = new CUserMacroParser();
                    $flags[] = $user_macro_parser->parse($value) == CParser::PARSE_SUCCESS;
                }

                if ($this->flags & API_ALLOW_LLD_MACRO) {
                    $lld_macro_parser = new CLLDMacroParser();
                    $lld_macro_function_parser = new CLLDMacroFunctionParser();
                    $flags[] = ($lld_macro_parser->parse($value) == CParser::PARSE_SUCCESS || $lld_macro_function_parser->parse($value) == CParser::PARSE_SUCCESS);
                }
                if (in_array(true, $flags)) {
                    return null;
                }
                $valueItem = NAN;
            }
        }
        else {
            $valueItem = NAN;
        }

        if (is_nan($valueItem)) {
            return $this->setValueError(t('zapi', 'a floating point value is expected'));
        }

        $error = '';
        if (!ValueCheckHelper::checkFloatIn($value, $this->in, $error)) {
            return $this->setValueError($error);
        }

        if (!ValueCheckHelper::checkCompare($value, $this->in, $error)) {
            return $this->setValueError($error);
        }

        $error = '';
        if (!empty($this->compare) && !empty($this->compare['field']) && property_exists($model, $this->compare['field'])) {
            $compare = [
                'operator' => $this->compare['operator'] ?? '',
                'value' => $model->{$this->compare['field']}
            ];
            if (!ValueCheckHelper::checkCompare($value, $compare, $error)) {
                return $this->setValueError($error);
            }
        }
        $model->$attribute = $valueItem;
        return null;
    }
}