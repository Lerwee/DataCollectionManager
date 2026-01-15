<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CPrometheusOutputParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * Class PrometheusLabelValidator
 * @package app\customs\zapi\common\validators
 */
class PrometheusLabelValidator extends BaseZValidator
{
    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        $utfValidator = new Utf8StringValidator(['flags' => API_NOT_EMPTY]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }
        $prometheus_output_parser = new CPrometheusOutputParser([
            'usermacros' => (bool) ($this->flags & API_ALLOW_USER_MACRO),
            'lldmacros' => (bool) ($this->flags & API_ALLOW_LLD_MACRO)
        ]);

        if ($prometheus_output_parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError(t('zapi', 'invalid Prometheus label'));
        }
        return null;
    }
}