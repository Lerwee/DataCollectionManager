<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\InvalidConfigException;

/**
 * Class PreProcParamsValidator
 * @package app\customs\zapi\common\validators
 */
class PreProcParamsValidator extends BaseZValidator
{
    /**
     * @var null|int
     */
    public $length = null;

    /**
     * @var array ['field' = 'attr']
     */
    public $preproc_type = [];

    /**
     * @param $model
     * @param $attribute
     * @return array|null
     * @throws InvalidConfigException
     */
    public function validateModelValue($model, $attribute): ?array
    {
        $data = $model->$attribute;
        $preproc_type = $this->preproc_type['field'] ? $model->{$this->preproc_type['field']} : '';

        $params = [];

        $utfValidator = new Utf8StringValidator();
        $result = $utfValidator->validateValue($data);
        if (!empty($result)) {
            return $result;
        }
        $data = (string)$data;
        $data = str_replace("\r\n", "\n", $data);

        if (is_numeric($this->length) && mb_strlen($data) > $this->length) {
            return $this->setValueError(t('zapi', 'value is too long'));
        }

        if ($preproc_type == PRS_PREPROC_SCRIPT) {
            $params[1] = $data;
        } else {
            foreach (explode("\n", $data) as $i => $param) {
                $params[$i + 1] = $param;
            }
        }
        $fields = [];
        switch ($preproc_type) {
            case PRS_PREPROC_MULTIPLIER:
                $fields = [
                    '1' => [FloatValidator::class, 'flags' => API_REQUIRED | ($this->flags & API_ALLOW_USER_MACRO) | ($this->flags & API_ALLOW_LLD_MACRO)]
                ];
                break;

            case PRS_PREPROC_RTRIM:
            case PRS_PREPROC_LTRIM:
            case PRS_PREPROC_JSONPATH:
            case PRS_PREPROC_XPATH:
            case PRS_PREPROC_ERROR_FIELD_XML:
            case PRS_PREPROC_ERROR_FIELD_JSON:
            case PRS_PREPROC_SCRIPT:
            case PRS_PREPROC_TRIM:
                $fields = [
                    '1' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY]
                ];
                break;

            case PRS_PREPROC_ERROR_FIELD_REGEX:
            case PRS_PREPROC_REGSUB:
                $fields = [
                    '1' => [RegexValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY],
                    '2' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY]
                ];
                break;

            case PRS_PREPROC_VALIDATE_RANGE:
                if (count($params) == 2 && ($params[1] === '' || $params[2] === '')) {
                    if ($params[1] === '' && $params[2] === '') {
                        return $this->setValueError(t('zapi', 'cannot be empty'));
                    }

                    $params[1] = $params[1] === '' ? null : $params[1];
                    $params[2] = $params[2] === '' ? null : $params[2];
                }

                $fields = [
                    '1' => [FloatValidator::class, 'flags' => API_REQUIRED | API_ALLOW_NULL | ($this->flags & API_ALLOW_USER_MACRO) | ($this->flags & API_ALLOW_LLD_MACRO)],
                    '2' => [FloatValidator::class, 'flags' => API_REQUIRED | API_ALLOW_NULL | ($this->flags & API_ALLOW_USER_MACRO) | ($this->flags & API_ALLOW_LLD_MACRO), 'compare' => ['operator' => '>', 'field' => '1']]
                ];
                break;

            case PRS_PREPROC_VALIDATE_REGEX:
            case PRS_PREPROC_VALIDATE_NOT_REGEX:
                $fields = [
                    '1' => [RegexValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY]
                ];
                break;

            case PRS_PREPROC_THROTTLE_TIMED_VALUE:
                $fields = [
                    '1' => [TimeUnitValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY | ($this->flags & API_ALLOW_USER_MACRO) | ($this->flags & API_ALLOW_LLD_MACRO), 'in' => [[1, 25 * SEC_PER_YEAR]]]
                ];
                break;

            case PRS_PREPROC_PROMETHEUS_PATTERN:
                $fields = [
                    '1' => [PrometheusPatternValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY | ($this->flags & API_ALLOW_USER_MACRO) | ($this->flags & API_ALLOW_LLD_MACRO)],
                    '2' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'in' => [PRS_PREPROC_PROMETHEUS_VALUE, PRS_PREPROC_PROMETHEUS_LABEL, PRS_PREPROC_PROMETHEUS_FUNCTION]],
                    '3' => [MultipleValidator::class, 'rules' => [
                        [Utf8StringValidator::class, 'in' => [''], 'default' => '', 'when' => function($model) {
                            return $model['2'] == PRS_PREPROC_PROMETHEUS_VALUE;
                        }],
                        [PrometheusLabelValidator::class, 'flags' => API_REQUIRED | ($this->flags & API_ALLOW_USER_MACRO) | ($this->flags & API_ALLOW_LLD_MACRO), 'when' => function($model) {
                            return $model['2'] == PRS_PREPROC_PROMETHEUS_LABEL;
                        }],
                        [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'in' => [PRS_PREPROC_PROMETHEUS_SUM, PRS_PREPROC_PROMETHEUS_MIN, PRS_PREPROC_PROMETHEUS_MAX, PRS_PREPROC_PROMETHEUS_AVG, PRS_PREPROC_PROMETHEUS_COUNT], 'when' => function($model) {
                            return $model['2'] == PRS_PREPROC_PROMETHEUS_FUNCTION;
                        }]
                    ]]
                ];
                break;

            case PRS_PREPROC_PROMETHEUS_TO_JSON:
                $fields = [
                    '1' => [PrometheusPatternValidator::class, 'flags' => API_REQUIRED | ($this->flags & API_ALLOW_USER_MACRO) | ($this->flags & API_ALLOW_LLD_MACRO)]
                ];
                break;

            case PRS_PREPROC_CSV_TO_JSON:
                $fields = [
                    '1' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'length' => 1],
                    '2' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'length' => 1],
                    '3' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'in' => [PRS_PREPROC_CSV_NO_HEADER, PRS_PREPROC_CSV_HEADER]]
                ];
                break;

            case PRS_PREPROC_STR_REPLACE:
                $fields = [
                    '1' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY],
                    '2' => [Utf8StringValidator::class, 'default' => '']
                ];
                break;

            case PRS_PREPROC_SNMP_WALK_VALUE:
                $fields = [
                    '1' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY],
                    '2' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'in' => [PRS_PREPROC_SNMP_UNCHANGED, PRS_PREPROC_SNMP_UTF8_FROM_HEX, PRS_PREPROC_SNMP_MAC_FROM_HEX, PRS_PREPROC_SNMP_INT_FROM_BITS]]
                ];
                break;

            case PRS_PREPROC_SNMP_WALK_TO_JSON:
                $fields = [];
                foreach ($params as $index => $_) {
                    $fields[$index] = [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'length' => 255];
                }
                //$fields = [Utf8StringsValidator::class, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'length' => 255];
                break;
        }

        if (!ValidateHelper::validateObject($params, $fields, [], $error)) {
            return $this->setValueError($error);
        }
        $model->$attribute =implode("\n", $params);
        return null;
    }
}