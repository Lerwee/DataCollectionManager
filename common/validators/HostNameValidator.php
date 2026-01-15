<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CHostNameParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_H_NAME
 * Class HostNameValidator
 * @package app\customs\zapi\common\validators
 */
class HostNameValidator extends BaseZValidator
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

        $host_name_parser = new CHostNameParser(['lldmacros' => ($this->flags & API_REQUIRED_LLD_MACRO)]);

        // For example, host prototype name MUST contain macros.
        if ($host_name_parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError(t('zapi', 'invalid host name'));
        }

        if (($this->flags & API_REQUIRED_LLD_MACRO) && !$host_name_parser->getMacros()) {
            return $this->setValueError(t('zapi', 'must contain at least one low-level discovery macro'));
        }

        return null;
    }
}