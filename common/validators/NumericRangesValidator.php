<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CRangesParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_NUMERIC_RANGES
 * Validate numeric ranges. Multiple ranges separated by comma character.
 * Example:
 *   10-20,-20--10,-5-0,0.5-0.7,-20--10,-20.20--20.10
 *   30,-10,0.7,-0.5
 *
 * Class NumericRangesValidator
 * @package app\customs\zapi\common\validators
 */
class NumericRangesValidator extends BaseZValidator
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
        $utfValidator = new Utf8StringValidator(['flags' => API_NOT_EMPTY, 'length' => $this->length]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }
        $parser = new CRangesParser(['with_minus' => true, 'with_float' => true, 'with_suffix' => true]);

        if ($parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError(t('zapi', 'invalid range expression'));
        }

        return null;
    }

}