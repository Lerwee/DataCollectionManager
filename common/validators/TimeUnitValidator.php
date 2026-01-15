<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\helpers\ValueCheckHelper;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CSimpleIntervalParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * Class TimeUnitValidator
 * @package app\customs\zapi\common\validators
 */
class TimeUnitValidator extends BaseZValidator
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

    public $inType = self::IN_TYPE_NUMBER;

    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        $value = is_int($value) ? (string)$value : $value;//30m
        $utfValidator = new Utf8StringValidator(['flags' => $this->flags & API_NOT_EMPTY, 'length' => $this->length]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }

        if (($this->flags & API_NOT_EMPTY) == 0 && $value === '') {
            return null;
        }

        $simple_interval_parser = new CSimpleIntervalParser([
            'usermacros' => ($this->flags & API_ALLOW_USER_MACRO),
            'lldmacros' => ($this->flags & API_ALLOW_LLD_MACRO),
            'negative' => true,
            'with_year' => ($this->flags & API_TIME_UNIT_WITH_YEAR)
        ]);

        if ($simple_interval_parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError(t('zapi', 'a time unit is expected'));
        }

        if (($this->flags & (API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO)) && $value[0] === '{') {
            return null;
        }

        $seconds = timeUnitToSeconds($value, ($this->flags & API_TIME_UNIT_WITH_YEAR));

        if ($seconds < PRS_MIN_INT32 || $seconds > PRS_MAX_INT32) {
            return $this->setValueError(t('zapi', 'a number is too large'));
        }

        $error = '';
        if (!ValueCheckHelper::checkInt32In($seconds, $this->in, $error)) {
            return [$error, []];
        }

        return null;
    }
}