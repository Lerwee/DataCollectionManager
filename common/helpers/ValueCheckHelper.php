<?php

namespace app\customs\zapi\common\helpers;

class ValueCheckHelper
{
    /**
     * @param $data
     * @param array $ranges [1, [1, 10]]
     * @param string $error
     * @return bool
     */
    public static function checkInt32In($data, array $ranges = [], ?string &$error = ''): bool
    {
        if (empty($ranges)) {
            return true;
        }

        if (!self::isInRange($data, $ranges)) {
            $inStr = implode(', ', array_map(function ($v) {
                return is_array($v) ? implode('-', $v) : $v;
            }, $ranges));
            $error = tn('zapi', ['value must be {value}', 'value must be one of {value}', (strpbrk($inStr, ',:') === false) ? 1 : 2], ['value' => $inStr]);
            return false;
        }
        return true;
    }

    /**
     * @param $data
     * @param array $ranges [1, [1, 10]]
     * @param string $error
     * @return bool
     */
    public static function checkFloatIn($data, array $ranges = [], ?string &$error = ''): bool
    {
        if (empty($ranges)) {
            return true;
        }

        if (!self::isInRange($data, $ranges)) {
            $inStr = implode(', ', array_map(function ($v) {
                return is_array($v) ? implode('-', $v) : $v;
            }, $ranges));
            $error = t('zapi', 'value must be within the range of {value}', ['value' => $inStr]);
            return false;
        }
        return true;
    }

    /**
     * @param $data
     * @param array $compare ['operator' => '>', 'value' => 10]
     * @param string $error
     * @return bool
     */
    public static function checkCompare($data, array $compare = [], ?string &$error = ''): bool
    {
        if (empty($compare) || (!isset($compare['value']) || !is_numeric($compare['value']))) {
            return true;
        }

        switch ($compare['operator'] ?? '') {
            case '>':
                if ($data <= $compare['value']) {
                    $error = t('zapi', 'cannot be less than or equal to the value of parameter "{value}"', ['value' => $compare['path']]);
                    return false;
                }
                break;

            default:
                $error = 'Incorrect validation rules.';
                return false;
        }

        return true;
    }

    /**
     * @param $data
     * @param array $ranges [1, [1, 10]]
     * @param string $format Y-m-d H:i:s
     * @param null $timezone
     * @param string|null $error
     * @return bool
     */
    public static function checkTimestampIn($data, array $ranges = [], string $format = 'Y-m-d H:i:s', $timezone = null, ?string &$error = ''): bool
    {
        if (empty($ranges)) {
            return true;
        }

        if (!self::isInRange($data, $ranges)) {
            if ($timezone) {
                $default_timezone = date_default_timezone_get();
                date_default_timezone_set('UTC');
            }

            $inStr = implode(', ', array_map(function ($v) use ($format) {
                if (is_array($v)) {
                    list($from, $to) = array_values($v);
                    return date($format, $from).'-'.date($format, $to);
                } else {
                    return date($format, $v);
                }
            }, $ranges));
            if ($timezone) {
                date_default_timezone_set($default_timezone);
            }
            $error = tn('zapi', ['value must be {value}', 'value must be one of {value}', (strpbrk($inStr, ',:') === false) ? 1 : 2], ['value' => $inStr]);
            return false;
        }
        return true;
    }

    /**
     * .e.g
     * $data: 1
     * $ranges: [1, [0, 100]]
     * @param $data
     * @param array $ranges
     * @return bool
     */
    public static function isInRange($data, array $ranges): bool
    {
        $valid = false;

        foreach ($ranges as $in) {
            if (is_array($in)) {
                $values = array_values($in);;
                list($from, $to) = [$values[0] ?? 0, $values[1] ?? 0];
            } else {
                $from = $to = $in;
            }
            if ($from <= $data && $data <= $to) {
                $valid = true;
                break;
            }
        }

        return $valid;
    }
}
