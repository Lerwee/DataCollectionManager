<?php

namespace app\customs\zapi\common\helpers;

use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CRangeTimeParser;
use app\customs\zapi\common\parsers\CSimpleIntervalParser;
use app\customs\zapi\common\parsers\CUserMacroParser;
use app\customs\zapi\common\validators\base\BaseZValidator;
use app\customs\zapi\common\validators\BooleanValidator;
use app\customs\zapi\common\validators\CondFormulaIdValidator;
use app\customs\zapi\common\validators\CondFormulaValidator;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32RangesValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\IpRangesValidator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\ObjectValidator;
use app\customs\zapi\common\validators\PortValidator;
use app\customs\zapi\common\validators\ScriptMenuPathValidator;
use app\customs\zapi\common\validators\TimePeriodValidator;
use app\customs\zapi\common\validators\TimestampValidator;
use app\customs\zapi\common\validators\TimeUnitValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\UrlValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\components\ValidateObject;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\validators\Validator;

/**
 * Class ValidateHelper
 * @package app\customs\zapi\common\helpers
 */
class ValidateHelper
{
    /**
     *  * .e.g
     *
     * $data = ["name" => "abc", "key_" => "asd];
     * $rules = [
     *     'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
     *     'key_' => [ItemKeyValidator::class, 'flags' => API_REQUIRED, 'length' => 2048],
     * ];
     * $rule = [
     *      'flags' => API_ALLOW_NULL,
     * ]
     * $bool = ValidateHelper::validate($data, $rules, $error);
     * @param array|Model $data
     * @param array $fieldRules
     * @param array $rule
     * @param string|null $error
     * @return bool
     * @throws Exception
     */
    public static function validateObject(&$data, array $fieldRules, array $rule = [], ?string &$error = ''): bool
    {
        $flags = $rule['flags'] ?? 0x00;
        $path = (array)($rule['_path'] ?? []);

        if (($flags & API_ALLOW_NULL) && $data === null) {
            return true;
        }
        if (!(is_array($data) || is_object($data))) {
            $error = t('zapi', 'an array is expected');
            return false;
        }

        // unexpected parameter validation
        if (!($data instanceof Model)) {
            $attributes = array_keys($data);
            $model = new ValidateObject();
            $model->loadData($data);
        } elseif ($data instanceof ValidateObject) {
            $attributes = array_keys($data->getData());
            $model = $data;
        } else {
            $attributes = get_object_vars($data);
            $model = $data;
        }
        if (!($flags & API_ALLOW_UNEXPECTED)) {
            foreach ($attributes as $field_name) {
                if (!$fieldRules) {
                    $error = t('zapi', 'Invalid object attribute {attribute}, should be empty', ['attribute' => $field_name]);
                    return false;
                }
                if (!array_key_exists($field_name, $fieldRules)) {
                    $error = t('zapi', 'Invalid object attribute {attribute}', ['attribute' => $field_name]);
                    return false;
                }
            }
        }
        foreach ($fieldRules as $attribute => $rule) {
            if (empty($rule['validator']) && !empty($rule[0])) {
                $rule['validator'] = $rule[0];
                unset($rule[0]);
            }
            if (empty($rule['validator'])) {
                throw new InvalidConfigException('Invalid validation rule: a rule must specify both attribute names and validator type.');
            }
            $fieldFlags = $rule['flags'] ?? 0x00;
            if (!in_array($attribute, $attributes)) {
                if ($fieldFlags & API_REQUIRED) {
                    $error = t('zapi', 'the parameter "{parameter}" is missing', ['parameter' => $attribute]);
                    return false;
                }
            }
            $params = array_diff_key($rule, array_flip(['validator']));
            $validator = Validator::createValidator($rule['validator'], $model, $attribute, $params);
            if ($validator instanceof BaseZValidator) {
                $validator->setPath(array_merge($path, [$attribute]));
            }
            $validator->validateAttribute($model, $attribute);
            if ($model->hasErrors($attribute)) {
                $error = $model->getFirstFilterError();
                return false;
            }
        }
        if ($model instanceof ValidateObject) {
            $data = $model->getData();
        }
        return true;
    }

    /**
     * $data = [
     *      ["name" => "abc", "key_" => "asd]
     *      ["name" => "abc1", "key_" => "asd1]
     * ];
     * or
     * $data = [
     *     Model(),
     *     Model(),
     * ];
     *
     * $fieldRules = [
     *     'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
     *     'key_' => [ItemKeyValidator::class, 'flags' => API_REQUIRED, 'length' => 2048],
     * ];
     * $rule = [
     *      'flags' => API_NOT_EMPTY,
     *      'length' => 5
     * ]
     * @param array|null $list
     * @param array $fieldRules
     * @param array $rule
     * @param string|null $error
     * @return bool
     * @throws Exception
     */
    public static function validateObjects(?array &$list, array $fieldRules, array $rule = [], ?string &$error = ''): bool
    {
        $object = new ValidateObject();
        $field = BaseZValidator::LIST_FLAG;
        $object[$field] = $list;
        $rule = array_intersect_key($rule, array_flip(['flags', 'length', 'uniq', 'uniq_by_values', 'default']));
        $fields = [
            $field => [ObjectsValidator::class] + ['fields' => $fieldRules] + $rule
        ];
        if (!self::validateObject($object, $fields, [], $error)) {
            return false;
        }
        $list = $object[$field];
        return true;
    }

    public static function validate($api_input_rules, &$list, $path, &$error)
    {
        $isObject = $api_input_rules['type'] == API_OBJECT;
        $api_input_rules = self::changeRuleFunc($api_input_rules);
        $rule['path'] = $path;
        if (isset($api_input_rules['flags'])) {
            $rule['flags'] = $api_input_rules['flags'];
        }
        if ($isObject) {
            return self::validateObject($list, $api_input_rules['fields'], $rule, $error);
        }
        return self::validateObjects($list, $api_input_rules['fields'], $rule, $error);
    }

    protected static function changeRuleFunc($rule)
    {
        if (isset($rule['type'])) {
            $rule['validator'] = self::getFuncMap()[$rule['type']];
            unset($rule['type']);
        }
        if (!empty($rule['fields'])) {
            foreach ($rule['fields'] as $k => $field) {
                $rule['fields'][$k] = self::changeRuleFunc($field);
            }
        }
        if (!empty($rule['rules'])) {
            foreach ($rule['rules'] as $k => $ruleItem) {
                $rule['rules'][$k] = self::changeRuleFunc($ruleItem);
            }
        }
        return $rule;
    }

    protected static function getFuncMap()
    {
        return [
            API_OBJECTS => ObjectsValidator::class,
            API_OBJECT => ObjectValidator::class,
            API_STRING_UTF8 => Utf8StringValidator::class,
            API_INT32 => Int32Validator::class,
            API_ID => IdValidator::class,
            API_BOOLEAN => BooleanValidator::class,
            API_PORT => PortValidator::class,
            API_TIME_UNIT => TimeUnitValidator::class,
            API_URL => UrlValidator::class,
            API_IDS => IdsValidator::class,
            API_TIMESTAMP => TimestampValidator::class,
            API_MULTIPLE => MultipleValidator::class,
            API_UNEXPECTED => UnexpectedValidator::class,
            API_COND_FORMULA => CondFormulaValidator::class,
            API_COND_FORMULAID => CondFormulaIdValidator::class,
            API_TIME_PERIOD => TimePeriodValidator::class,
            API_IP_RANGES => IpRangesValidator::class,
            API_INT32_RANGES => Int32RangesValidator::class,
            API_SCRIPT_MENU_PATH => ScriptMenuPathValidator::class,
        ];
    }

    /**
     * @param $port
     * @return bool
     */
    public static function validatePortNumberOrMacro($port): bool
    {
        return (self::validatePortNumber($port) || self::validateUserMacro($port));
    }

    /**
     * @param $port
     * @return bool
     */
    public static function validatePortNumber($port): bool
    {
        return self::validateNumber($port, PRS_MIN_PORT_NUMBER, PRS_MAX_PORT_NUMBER);
    }

    /**
     * @param $value
     * @param null $min
     * @param null $max
     * @return bool
     */
    public static function validateNumber($value, $min = null, $max = null): bool
    {
        if (!prs_is_int($value)) {
            return false;
        }

        if ($min !== null && $value < $min) {
            return false;
        }

        if ($max !== null && $value > $max) {
            return false;
        }

        return true;
    }

    /**
     * @param $value
     * @return bool
     */
    public static function validateUserMacro($value): bool
    {
        return ((new CUserMacroParser())->parse($value) == CParser::PARSE_SUCCESS);
    }

    /**
     * Validate, if unix time in (1970.01.01 00:00:01 - 2038.01.19 00:00:00).
     *
     * @param int $time
     *
     * @return bool
     */
    public static function validateUnixTime(int $time): bool
    {
        return (is_numeric($time) && $time > 0 && $time <= 2147464800);
    }

    /**
     * Validate if date and time are in correct range, e.g. month is not greater than 12 etc.
     *
     * @param int $year
     * @param int $month
     * @param int $day
     * @param int $minutes
     * @param int $seconds
     *
     * @return bool
     */
    public static function validateDateTime($year, $month, $day, $hours, $minutes, $seconds = null): bool
    {
        return !($month < 1 || $month > 12
            || $day < 1 || $day > 31 || (($month == 4 || $month == 6 || $month == 9 || $month == 11) && $day > 30)
            || ($month == 2 && ((($year % 4) == 0 && $day > 29) || (($year % 4) != 0 && $day > 28)))
            || $hours < 0 || $hours > 23
            || $minutes < 0 || $minutes > 59
            || (!is_null($seconds) && ($seconds < 0 || $seconds > 59)));
    }

    /**
     * Validate allowed date interval (1970.01.01-2038.01.18).
     *
     * @param int $year
     * @param int $month
     * @param int $day
     *
     * @return bool
     */
    public static function validateDateInterval($year, $month, $day): bool
    {
        return !($year < 1970 || $year > 2038 || ($year == 2038 && (($month > 1) || ($month == 1 && $day > 18))));
    }

    /**
     * Validate a configuration value. Use simple interval parser to parse the string, convert to seconds and check
     * if the value is in between given min and max values. In some cases it's possible to enter 0, or even 0s or 0d.
     * If the value is incorrect, set an error.
     *
     * @param string $value Value to parse and validate.
     * @param int $min Lower bound.
     * @param int $max Upper bound.
     * @param bool $allow_zero Set to "true" to allow value to be zero.
     * @param string $error
     * @param array $options
     * @param bool $options ['usermacros']
     * @param bool $options ['lldmacros']
     * @param bool $options ['with_year']
     *
     * @return bool
     */
    public static function validateTimeUnit($value, $min, $max, $allow_zero, &$error, array $options = []): bool
    {
        $simple_interval_parser = new CSimpleIntervalParser($options);
        $value = (string)$value;

        if ($simple_interval_parser->parse($value) == CParser::PARSE_SUCCESS) {
            if ($value[0] !== '{') {
                $value = timeUnitToSeconds($value, array_key_exists('with_year', $options) ? $options['with_year'] : false);

                if ($allow_zero && $value == 0) {
                    return true;
                }

                if ($value < $min || $value > $max) {
                    $error = t('zapi', 'value must be one of {list}', ['list' => $allow_zero ? '0, ' . $min . '-' . $max : $min . '-' . $max]);

                    return false;
                }
            }
        } else {
            $error = t('zapi', 'a time unit is expected');
            return false;
        }
        return true;
    }

}
