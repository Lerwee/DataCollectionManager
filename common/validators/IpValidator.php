<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CIPParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_IP
 * Class IpValidator
 * @package app\customs\zapi\common\validators
 */
class IpValidator extends BaseZValidator
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
        $ip_parser = new CIPParser([
            'v6' => PRS_HAVE_IPV6,
            'usermacros' => ($this->flags & API_ALLOW_USER_MACRO),
            'lldmacros' => ($this->flags & API_ALLOW_LLD_MACRO),
            'macros' => ($this->flags & API_ALLOW_MACRO)
        ]);

        if ($ip_parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError(t('zapi', 'an IP address is expected'));
        }
        return null;
    }
}