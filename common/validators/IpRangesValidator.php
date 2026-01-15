<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CIPRangeParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_IP_RANGES
 * Validate IP ranges. Multiple IPs separated by comma character.
 * Example:
 *   127.0.0.1,192.168.1.1-254,192.168.2.1-100,192.168.3.0/24,{$MACRO}
 *
 * Class IpRangesValidator
 * @package app\customs\zapi\common\validators
 */
class IpRangesValidator extends BaseZValidator
{
    /**
     * @var null|int
     */
    public $length = null;

    /**
     * @var array
     */
    public $macros = [];

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
        $ip_range_parser = new CIPRangeParser([
            'v6' => PRS_HAVE_IPV6,
            'dns' => (bool) ($this->flags & API_ALLOW_DNS),
            'ranges' => (bool) ($this->flags & API_ALLOW_RANGE),
            'usermacros' => (bool) ($this->flags & API_ALLOW_USER_MACRO),
            'macros' => $this->macros
        ]);

        if (!$ip_range_parser->parse($value)) {
            return $this->setValueError($ip_range_parser->getError());
        }

        return null;
    }
}