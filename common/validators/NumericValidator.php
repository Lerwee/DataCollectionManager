<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CNumberParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Model;

/**
 * API_NUMERIC
 * Validator for numeric data with optional suffix.
 * Supported time suffixes: s, m, h, d, w
 * Supported metric suffixes: K, M, G, T
 * Class NumericValidator
 * @package app\customs\zapi\common\validators
 */
class NumericValidator extends BaseZValidator
{
    /**
     * @var null|int
     */
    public $length = null;

    /**
     * @param $model
     * @param $attribute
     * @return array|null
     * @throws \Exception
     */
    public function validateModelValue($model, $attribute): ?array
    {
        $value = $model->$attribute;
        $value = is_int($value) ? (string)$value : $value;
        $utfValidator = new Utf8StringValidator(['flags' => $this->flags | API_NOT_EMPTY, 'length' => $this->length]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }
        if (($this->flags & API_NOT_EMPTY) == 0 && $value === '') {
            return null;
        }

        $number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

        if ($number_parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError(t('zapi', 'a number is expected'));
        }

        $value = $number_parser->calcValue();

        if (env('DOUBLE_IEEE754')) {
            if (abs($value) > PRS_FLOAT_MAX) {
                return $this->setValueError(t('zapi', 'a number is too large'));
            }
        }
        else {
            if (abs($value) >= 1E+16) {
                return $this->setValueError(t('zapi', 'a number is too large'));
            }
            elseif ($value != round($value, 4)) {
                return $this->setValueError(t('zapi', 'a number has too many fractional digits'));
            }
        }

        // Remove leading zeros.
        $value = preg_replace('/^(-)?(0+)?(\d.*)$/', '${1}${3}', $value);

        // Add leading zero.
        $value = preg_replace('/^(-)?(\..*)$/', '${1}0${2}', $value);
        $model->$attribute = $value;
        return null;
    }
}