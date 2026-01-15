<?php
namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CTimePeriodsParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_TIME_PERIOD
 * Class TimePeriodValidator
 * @package app\customs\zapi\common\validators
 */
class TimePeriodValidator extends BaseZValidator
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
        $time_period_parser = new CTimePeriodsParser(['usermacros' => ($this->flags & API_ALLOW_USER_MACRO)]);

        if ($time_period_parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError(t('zapi', 'a time period is expected'));
        }
        return null;
    }
}