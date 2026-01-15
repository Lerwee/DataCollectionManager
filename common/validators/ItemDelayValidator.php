<?php

namespace app\customs\zapi\common\validators;

use app\common\helpers\ArrayHelper;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CUpdateIntervalParser;
use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Model;

/**
 * API_ITEM_DELAY
 * Class ItemDelayValidator
 * @package app\customs\zapi\common\validators
 */
class ItemDelayValidator extends BaseZValidator
{
    /**
     * @var null|int
     */
    public $length = null;

    /**
     * @param $model
     * @param $attribute
     * @return array|null
     */
    public function validateModelValue($model, $attribute): ?array
    {
        if (is_int($model->$attribute)) {
            $model->$attribute = (string)$model->$attribute;
        }
        $value = $model->$attribute;
        $utfValidator = new Utf8StringValidator(['flags' => API_NOT_EMPTY, 'length' => $this->length]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }

        $update_interval_parser = new CUpdateIntervalParser([
            'usermacros' => (bool) ($this->flags & API_ALLOW_USER_MACRO),
            'lldmacros' => (bool) ($this->flags & API_ALLOW_LLD_MACRO)
        ]);

        if ($update_interval_parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError(t('zapi', strpos($value, ';') === false ? 'a time unit is expected' : $update_interval_parser->getError()));
        }

        $delay = $update_interval_parser->getDelay();
        $intervals = $update_interval_parser->getIntervals();

        if ($delay[0] !== '{') {
            $delay_sec = timeUnitToSeconds($delay);

            if ($delay_sec == 0 && !$intervals) {
                return $this->setValueError(t('zapi', 'cannot be equal to zero without custom intervals'));
            }

            if ($delay_sec > SEC_PER_DAY) {
                return $this->setValueError(t('zapi', 'value must be one of {value}', ['value' => implode('-', [0, SEC_PER_DAY])]));
            }
        }

        if (!$intervals || array_key_exists(ITEM_DELAY_SCHEDULING, array_column($intervals, null, 'type'))) {
            return null;
        }

        $active_macro_interval = false;

        foreach ($intervals as $i => $interval) {
            if (strpos($interval['interval'], '{') !== false) {
                unset($intervals[$i]);

                if (strpos($interval['update_interval'], '{') === false) {
                    if ($interval['update_interval'] != 0) {
                        $active_macro_interval = true;
                    }
                }
                else {
                    $active_macro_interval = true;
                }
            }
        }

        $inactive_intervals = [];
        $active_intervals = [];

        foreach ($intervals as $interval) {
            $update_interval = timeUnitToSeconds($interval['update_interval']);

            [$day_period, $time_period] = explode(',', $interval['time_period']);

            [$day_from, $day_to] = (strpos($day_period, '-') === false)
                ? [$day_period, $day_period]
                : explode('-', $day_period);

            [$time_from, $time_to] = explode('-', $time_period);

            [$time_from_hours, $time_from_minutes] = explode(':', $time_from);
            [$time_to_hours, $time_to_minutes] = explode(':', $time_to);

            $time_from = $time_from_hours * SEC_PER_HOUR + $time_from_minutes * SEC_PER_MIN;
            $time_to = $time_to_hours * SEC_PER_HOUR + $time_to_minutes * SEC_PER_MIN;

            if ($update_interval > 0) {
                if ($time_from == 0 && $time_to == SEC_PER_DAY && $day_to - $day_from > 0) {
                    $_interval = $day_to * SEC_PER_DAY + $time_to - $day_from * SEC_PER_DAY + $time_from;
                }
                else {
                    $_interval = $time_to - $time_from;
                }

                if ($update_interval > $_interval) {
                    return $this->setValueError(t('zapi', 'update interval "{value1}" is longer than period "{value2}"', ['value1' => $interval['update_interval'], 'value2' => $interval['time_period']]));
                }
            }

            for ($day = $day_from; $day <= $day_to; $day++) {
                if ($update_interval == 0) {
                    $inactive_intervals[] = [
                        'time_from' => ($day - 1) * SEC_PER_DAY + $time_from,
                        'time_to' => ($day - 1) * SEC_PER_DAY + $time_to
                    ];
                }
                else {
                    $active_intervals[] = [
                        'update_interval' => $update_interval,
                        'time_from' => ($day - 1) * SEC_PER_DAY + $time_from,
                        'time_to' => ($day - 1) * SEC_PER_DAY + $time_to
                    ];
                }
            }
        }

        if ($delay[0] !== '{' && $delay_sec == 0 && !$active_intervals && !$active_macro_interval) {
            return $this->setValueError(t('zapi', 'must have at least one interval greater than 0'));
        }

        ArrayHelper::multisort($inactive_intervals, ['time_from']);

        $_inactive_intervals = $inactive_intervals ? [array_shift($inactive_intervals)] : [];
        $last = 0;

        foreach ($inactive_intervals as $interval) {
            if ($interval['time_from'] > $_inactive_intervals[$last]['time_to']) {
                $_inactive_intervals[++$last] = $interval;
                continue;
            }

            if ($interval['time_to'] <= $_inactive_intervals[$last]['time_to']) {
                continue;
            }

            $_inactive_intervals[$last]['time_to'] = $interval['time_to'];
        }

        $inactive_intervals = $_inactive_intervals;

        if ($inactive_intervals && $inactive_intervals[0]['time_from'] == 0
            && $inactive_intervals[0]['time_to'] == 7 * SEC_PER_DAY) {
            return $this->setValueError(t('zapi', 'non-active intervals cannot fill the entire time'));
        }

        if ($delay[0] === '{' || $active_macro_interval) {
            return null;
        }

        ArrayHelper::multisort($active_intervals, ['time_from']);

        $_active_intervals = $active_intervals ? [array_shift($active_intervals)] : [];
        $last = 0;

        foreach ($active_intervals as $i => $interval) {
            if ($interval['time_from'] > $_active_intervals[$last]['time_to']) {
                $_active_intervals[++$last] = $interval;
                continue;
            }

            if ($interval['update_interval'] >= $_active_intervals[$last]['update_interval']) {
                if ($interval['time_to'] <= $_active_intervals[$last]['time_to']) {
                    continue;
                }

                if ($interval['update_interval'] == $_active_intervals[$last]['update_interval']) {
                    $_active_intervals[$last]['time_to'] = $interval['time_to'];
                }
                else {
                    ++$last;
                    $_active_intervals[$last] = ['time_from' => $_active_intervals[$last - 1]['time_to']] + $interval;
                }
            }
            else {
                $_active_interval = $_active_intervals[$last];

                if ($_active_intervals[$last]['time_from'] == $interval['time_from']) {
                    $_active_intervals[$last] = $interval;
                }
                else {
                    $_active_intervals[$last]['time_to'] = $interval['time_from'];
                    $_active_intervals[++$last] = $interval;
                }

                if ($_active_interval['time_to'] > $interval['time_to']) {
                    $_active_intervals[++$last] = ['time_from' => $interval['time_to']] + $_active_interval;
                }
            }
        }

        $active_intervals = $_active_intervals;

        foreach ($active_intervals as $active_interval) {
            if ($active_interval['time_to'] - $active_interval['time_from'] < $active_interval['update_interval']) {
                continue;
            }

            if (!$inactive_intervals) {
                return null;
            }

            $_inactive_intervals = [];

            foreach ($inactive_intervals as $inactive_interval) {
                if ($inactive_interval['time_from'] < $active_interval['time_to']
                    && $inactive_interval['time_to'] > $active_interval['time_from']) {
                    $_inactive_intervals[] = $inactive_interval;
                }
            }

            if (!$_inactive_intervals) {
                return null;
            }

            foreach ($_inactive_intervals as $i => $inactive_interval) {
                if ($i == 0 && $inactive_interval['time_from'] > $active_interval['time_from']) {
                    $active_time_from = $active_interval['time_from'];
                    $active_time_to = $inactive_interval['time_from'];

                    if ($active_time_to - $active_time_from >= $active_interval['update_interval']) {
                        return null;
                    }
                }

                $active_time_from = $inactive_interval['time_to'];

                $active_time_to = array_key_exists($i + 1, $_inactive_intervals)
                    ? $_inactive_intervals[$i + 1]['time_from']
                    : $active_interval['time_to'];

                if ($active_time_to - $active_time_from >= $active_interval['update_interval']) {
                    return null;
                }
            }
        }

        if ($delay_sec > 0) {
            $intervals = array_merge($inactive_intervals, $active_intervals);
            ArrayHelper::multisort($intervals, ['time_from']);

            $_intervals = $intervals ? [array_shift($intervals)] : [];
            $last = 0;

            foreach ($intervals as $interval) {
                if ($interval['time_from'] > $_intervals[$last]['time_to']) {
                    $_intervals[++$last] = $interval;
                    continue;
                }

                if ($interval['time_to'] <= $_intervals[$last]['time_to']) {
                    continue;
                }

                $_intervals[$last]['time_to'] = $interval['time_to'];
            }

            foreach ($_intervals as $i => $interval) {
                if ($i == 0) {
                    if ($interval['time_from'] > 0 && $interval['time_from'] >= $delay_sec) {
                        return null;
                    }

                    continue;
                }

                if ($interval['time_from'] - $_intervals[$i - 1]['time_to'] >= $delay_sec) {
                    return null;
                }
            }

            if (7 * SEC_PER_DAY - $interval['time_to'] >= $delay_sec) {
                return null;
            }
        }
        return $this->setValueError(t('zapi', 'must have a polling interval not blocked by non-active interval periods'));
    }
}