<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\helpers\ValueCheckHelper;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CRangesParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_INT32_RANGES
 * Validate integer ranges.
 * Example:
 *   -100-0,0-100,200,300-{$MACRO},{$MACRO},{#LLD},400-500
 *
 * Class Int32RangesValidator
 * @package app\customs\zapi\common\validators
 */
class Int32RangesValidator extends BaseZValidator
{
    /**
     * @var null|int
     */
    public $length = null;

    /**
     * .e.g
     * [1, [1, 10]]
     * @var array
     */
    public $in = [];

    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        if ($value === '') {
            return null;
        }
        $utfValidator = new Utf8StringValidator(['flags' => $this->flags & API_NOT_EMPTY, 'length' => $this->length]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }

        $parser = new CRangesParser([
            'usermacros' => (bool) ($this->flags & API_ALLOW_USER_MACRO),
            'lldmacros' => (bool) ($this->flags & API_ALLOW_LLD_MACRO),
            'with_minus' => true
        ]);

        if ($parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError(t('zapi', 'invalid range expression'));
        }

        foreach ($parser->getRanges() as $ranges) {
            foreach ($ranges as $range) {
                if (($this->flags & (API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO)) && $range[0] === '{') {
                    continue;
                }

                $error = '';
                if (!ValueCheckHelper::checkInt32In($value, $this->in, $error)) {
                    return [$error, []];
                }
            }
        }

        return null;
    }
}