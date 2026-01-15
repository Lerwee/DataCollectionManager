<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CPrometheusPatternParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * Class PrometheusPatternValidator
 * @package app\customs\zapi\common\validators
 */
class PrometheusPatternValidator extends BaseZValidator
{
    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        $utfValidator = new Utf8StringValidator(['flags' => $this->flags & API_NOT_EMPTY]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }
        if (($this->flags & API_NOT_EMPTY) == 0 && $value === '') {
            return null;
        }

		$prometheus_pattern_parser = new CPrometheusPatternParser([
            'usermacros' => (bool) ($this->flags & API_ALLOW_USER_MACRO),
            'lldmacros' => (bool) ($this->flags & API_ALLOW_LLD_MACRO)
        ]);

		if ($prometheus_pattern_parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError(t('zapi', 'invalid Prometheus pattern'));
        }
        return null;
    }
}