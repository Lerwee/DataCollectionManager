<?php
use app\customs\zapi\common\helpers\CSettingsHelper;
use app\customs\zapi\common\parsers\CUserMacroParser;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\common\helpers\CMessageHelper;
use app\customs\zapi\common\parsers\results\CExpressionParserResult;
use app\customs\zapi\common\parsers\CExpressionParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CTimePeriodsParser;


/**
 * Verify that function exists and can be called as a function.
 *
 * @param array		$names
 *
 * @return bool
 */
function prs_is_callable(array $names) {
	foreach ($names as $name) {
		if (!is_callable($name)) {
			return false;
		}
	}

	return true;
}

/************ REQUEST ************/
function redirect($url) {
	$curl = (new CUrl($url))->removeArgument(CCsrfTokenHelper::CSRF_TOKEN_NAME);
	header('Location: '.$curl->getUrl());
	exit;
}

/**
 * Check the HTTP request method.
 *
 * @param string $method  HTTP request method
 *
 * @return bool  true, if the request method matches
 */
function isRequestMethod($method) {
	return (strtolower($method) === strtolower($_SERVER['REQUEST_METHOD']));
}

/**
 * Check if request exist.
 *
 * @param string	$name
 *
 * @return bool
 */
function hasRequest($name) {
	return isset($_REQUEST[$name]);
}

/**
 * Check request, if exist request - return request value, else return default value.
 *
 * @param string	$name
 * @param mixed		$def
 *
 * @return mixed
 */
function getRequest($name, $def = null) {
	return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $def;
}

function countRequest($str = null) {
	if (!empty($str)) {
		$count = 0;

		foreach ($_REQUEST as $name => $value) {
			if (strpos($name, $str) !== false) {
				$count++;
			}
		}

		return $count;
	}
	else {
		return count($_REQUEST);
	}
}

/************* DATE *************/
function getMonthCaption($num) {
	switch ($num) {
		case 1: return t('zapi', 'January');
		case 2: return t('zapi', 'February');
		case 3: return t('zapi', 'March');
		case 4: return t('zapi', 'April');
		case 5: return t('zapi', 'May');
		case 6: return t('zapi', 'June');
		case 7: return t('zapi', 'July');
		case 8: return t('zapi', 'August');
		case 9: return t('zapi', 'September');
		case 10: return t('zapi', 'October');
		case 11: return t('zapi', 'November');
		case 12: return t('zapi', 'December');
	}

	return t('zapi', '[Wrong value for month: "{num}" ]', ['num' => $num]);
}

function getDayOfWeekCaption($num) {
	switch ($num) {
		case 1: return t('zapi', 'Monday');
		case 2: return t('zapi', 'Tuesday');
		case 3: return t('zapi', 'Wednesday');
		case 4: return t('zapi', 'Thursday');
		case 5: return t('zapi', 'Friday');
		case 6: return t('zapi', 'Saturday');
		case 0:
		case 7: return t('zapi', 'Sunday');
	}

	return t('zapi', '[Wrong value for day: "{num}" ]', ['num' => $num]);
}

// Convert seconds (0..SEC_PER_WEEK) to string representation. For example, 212400 -> 'Tuesday 11:00'
function dowHrMinToStr($value, $display24Hours = false) {
	$dow = $value - $value % SEC_PER_DAY;
	$hr = $value - $dow;
	$hr -= $hr % SEC_PER_HOUR;
	$min = $value - $dow - $hr;
	$min -= $min % SEC_PER_MIN;

	$dow /= SEC_PER_DAY;
	$hr /= SEC_PER_HOUR;
	$min /= SEC_PER_MIN;

	if ($display24Hours && $hr == 0 && $min == 0) {
		$dow--;
		$hr = 24;
	}

	return sprintf('%s %02d:%02d', getDayOfWeekCaption($dow), $hr, $min);
}

// Convert Day Of Week, Hours and Minutes to seconds representation. For example, 2 11:00 -> 212400. false if error occurred
function dowHrMinToSec($dow, $hr, $min) {
	if (prs_empty($dow) || prs_empty($hr) || prs_empty($min) || !prs_ctype_digit($dow) || !prs_ctype_digit($hr) || !prs_ctype_digit($min)) {
		return false;
	}

	if ($dow == 7) {
		$dow = 0;
	}

	if ($dow < 0 || $dow > 6) {
		return false;
	}

	if ($hr < 0 || $hr > 24) {
		return false;
	}

	if ($min < 0 || $min > 59) {
		return false;
	}

	return $dow * SEC_PER_DAY + $hr * SEC_PER_HOUR + $min * SEC_PER_MIN;
}

/**
 * Convert time to a string representation. Return 'Never' if timestamp is 0.
 *
 * @param             $format
 * @param null        $time
 * @param string|null $timezone
 *
 * @throws Exception
 *
 * @return string
 */
function prs_date2str($format, $time = null, string $timezone = null) {
	static $weekdaynames, $weekdaynameslong, $months, $monthslong;

	if ($time === null) {
		$time = time();
	}

	if ($time == 0) {
		return t('zapi', 'Never');
	}

	if ($time > PRS_MAX_DATE) {
		$prefix = '> ';
		$datetime = new DateTime('@'.PRS_MAX_DATE);
	}
	else {
		$prefix = '';
		$datetime = new DateTime('@'.(int) $time);
	}

	$datetime->setTimezone(new DateTimeZone($timezone ?? date_default_timezone_get()));

	if ($weekdaynames === null) {
		$weekdaynames = [
			0 => t('zapi', 'Sun'),
			1 => t('zapi', 'Mon'),
			2 => t('zapi', 'Tue'),
			3 => t('zapi', 'Wed'),
			4 => t('zapi', 'Thu'),
			5 => t('zapi', 'Fri'),
			6 => t('zapi', 'Sat')
		];
	}

	if ($weekdaynameslong === null) {
		$weekdaynameslong = [
			0 => t('zapi', 'Sunday'),
			1 => t('zapi', 'Monday'),
			2 => t('zapi', 'Tuesday'),
			3 => t('zapi', 'Wednesday'),
			4 => t('zapi', 'Thursday'),
			5 => t('zapi', 'Friday'),
			6 => t('zapi', 'Saturday')
		];
	}

	if ($months === null) {
		$months = [
			1 => t('zapi', 'Jan'),
			2 => t('zapi', 'Feb'),
			3 => t('zapi', 'Mar'),
			4 => t('zapi', 'Apr'),
			5 => _x('May', 'May short'),
			6 => t('zapi', 'Jun'),
			7 => t('zapi', 'Jul'),
			8 => t('zapi', 'Aug'),
			9 => t('zapi', 'Sep'),
			10 => t('zapi', 'Oct'),
			11 => t('zapi', 'Nov'),
			12 => t('zapi', 'Dec')
		];
	}

	if ($monthslong === null) {
		$monthslong = [
			1 => t('zapi', 'January'),
			2 => t('zapi', 'February'),
			3 => t('zapi', 'March'),
			4 => t('zapi', 'April'),
			5 => t('zapi', 'May'),
			6 => t('zapi', 'June'),
			7 => t('zapi', 'July'),
			8 => t('zapi', 'August'),
			9 => t('zapi', 'September'),
			10 => t('zapi', 'October'),
			11 => t('zapi', 'November'),
			12 => t('zapi', 'December')
		];
	}

	$replacements = [
		'l' => $weekdaynameslong[$datetime->format('w')],
		'F' => $monthslong[$datetime->format('n')],
		'D' => $weekdaynames[$datetime->format('w')],
		'M' => $months[$datetime->format('n')]
	];

	$output = '';

	$length = strlen($format);

	for ($i = 0; $i < $length; $i++) {
		$char = $format[$i];
		$char_escaped = $i > 0 && $format[$i - 1] === '\\';

		if (!$char_escaped && array_key_exists($char, $replacements)) {
			$output .= $replacements[$char];
		}
		else {
			$output .= $datetime->format($char);
		}
	}

	return $prefix.$output;
}

/**
 * Calculates and converts timestamp to string representation.
 *
 * @param int|string $start_date  Start date timestamp.
 * @param int|string $end_date    End date timestamp.
 *
 * @return string
 */
function prs_date2age($start_date, $end_date = 0) {
	$end_date = ($end_date != 0) ? $end_date : time();

	return convertUnitsS($end_date - $start_date);
}

function zbxDateToTime($strdate) {
	if (6 == sscanf($strdate, '%04d%02d%02d%02d%02d%02d', $year, $month, $date, $hours, $minutes, $seconds)) {
		return mktime($hours, $minutes, $seconds, $month, $date, $year);
	}
	elseif (5 == sscanf($strdate, '%04d%02d%02d%02d%02d', $year, $month, $date, $hours, $minutes)) {
		return mktime($hours, $minutes, 0, $month, $date, $year);
	}
	else {
		return ($strdate && is_numeric($strdate)) ? $strdate : time();
	}
}

/*************** CONVERTING ******************/
/**
 * Convert the Windows new line (CR+LF) to Linux style line feed (LF).
 *
 * @param string $string  Input string that will be converted.
 *
 * @return string
 */
function CRLFtoLF($string) {
	return str_replace("\r\n", "\n", $string);
}

function rgb2hex($color) {
	$HEX = [
		dechex($color[0]),
		dechex($color[1]),
		dechex($color[2])
	];
	foreach ($HEX as $id => $value) {
		if (strlen($value) != 2) {
			$HEX[$id] = '0'.$value;
		}
	}

	return $HEX[0].$HEX[1].$HEX[2];
}

function hex2rgb($color) {
	if ($color[0] == '#') {
		$color = substr($color, 1);
	}

	if (strlen($color) == 6) {
		list($r, $g, $b) = [$color[0].$color[1], $color[2].$color[3], $color[4].$color[5]];
	}
	elseif (strlen($color) == 3) {
		list($r, $g, $b) = [$color[0].$color[0], $color[1].$color[1], $color[2].$color[2]];
	}
	else {
		return false;
	}

	return [hexdec($r), hexdec($g), hexdec($b)];
}

function getColorVariations($color, $variations_requested = 1) {
	if ($variations_requested <= 1) {
		return [$color];
	}

	$change = hex2rgb('#ffffff'); // Color which is increased/decreased in variations.
	$max = 50;

	$color = hex2rgb($color);
	$variations = [];

	$range = range(-1 * $max, $max, $max * 2 / $variations_requested);

	// Remove redundant values.
	while (count($range) > $variations_requested) {
		(count($range) % 2) ? array_shift($range) : array_pop($range);
	}

	// Calculate colors.
	foreach ($range as $var) {
		$r = $color[0] + ($change[0] / 100 * $var);
		$g = $color[1] + ($change[1] / 100 * $var);
		$b = $color[2] + ($change[2] / 100 * $var);

		$variations[] = '#' . rgb2hex([
			$r < 0 ? 0 : ($r > 255 ? 255 : (int) $r),
			$g < 0 ? 0 : ($g > 255 ? 255 : (int) $g),
			$b < 0 ? 0 : ($b > 255 ? 255 : (int) $b)
		]);
	}

	return $variations;
}

/**
 * Convert suffixed string to decimal bytes ('10K' => 10240).
 * Note: this function must not depend on optional PHP libraries, since it is used in Perseus setup.
 *
 * @param string $value
 *
 * @return int
 */
function str2mem($value) {
	$value = trim($value);
	$suffix = strtoupper(substr($value, -1));

	if (ctype_digit($suffix)) {
		return (int) $value;
	}

	$value = (int) substr($value, 0, -1);

	if ($suffix === 'G') {
		$value *= PRS_GIBIBYTE;
	}
	elseif ($suffix === 'M') {
		$value *= PRS_MEBIBYTE;
	}
	elseif ($suffix === 'K') {
		$value *= PRS_KIBIBYTE;
	}

	return $value;
}

/**
 * Convert decimal bytes to suffixed string (10240 => '10K').
 * Note: this function must not depend on optional PHP libraries, since it is used in Perseus setup.
 *
 * @param int $bytes
 *
 * @return string
 */
function mem2str($bytes) {
	if ($bytes > PRS_GIBIBYTE) {
		return round($bytes / PRS_GIBIBYTE, PRS_UNITS_ROUNDOFF_SUFFIXED).'G';
	}
	elseif ($bytes > PRS_MEBIBYTE) {
		return round($bytes / PRS_MEBIBYTE, PRS_UNITS_ROUNDOFF_SUFFIXED).'M';
	}
	elseif ($bytes > PRS_KIBIBYTE) {
		return round($bytes / PRS_KIBIBYTE, PRS_UNITS_ROUNDOFF_SUFFIXED).'K';
	}
	else {
		return round($bytes).'B';
	}
}

function convertUnitsUptime($value) {
	$value = round($value);
	$value_abs = abs($value);

	$result = $value < 0 ? '-' : '';

	$days = floor($value_abs / SEC_PER_DAY);

	if ($days != 0) {
		$result .= _n('%1$d day', '%1$d days', formatFloat($days));
	}

	// Is original value precise enough for showing detailed data?
	if (strlen($value_abs) <= PRS_FLOAT_DIG) {
		if ($days != 0) {
			$result .= ', ';
		}

		$value_abs = $value_abs - $days * SEC_PER_DAY;

		$hours = floor($value_abs / SEC_PER_HOUR);
		$value_abs -= $hours * SEC_PER_HOUR;

		$minutes = floor($value_abs / SEC_PER_MIN);
		$seconds = $value_abs - $minutes * SEC_PER_MIN;

		$result .= sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
	}

	return $result;
}

/**
 * Convert time period to a human-readable format.
 * The following units will be used: years, months, days, hours, minutes, seconds and milliseconds.
 * Only the 3 most significant units will be displayed: #y #m #d, #m #d #h, #d #h #mm and so on, omitting empty ones.
 *
 * @param int  $value            Time period in seconds.
 * @param bool $ignore_millisec  Without ms (1s 200 ms = 1.2s).
 *
 * @return string
 */
function convertUnitsS($value, $ignore_millisec = false) {
	$value = (float) $value;
	$value_abs = abs($value);

	$parts = [];
	$start = null;

	$value_abs_int = floor($value_abs);

	if (($v = floor($value_abs_int / SEC_PER_YEAR)) > 0) {
		$parts['years'] = $v;
		$value_abs_int -= $v * SEC_PER_YEAR;
		$start = 0;
	}

	$v = floor($value_abs_int / SEC_PER_MONTH);
	if ($v == 12) {
		$parts['years'] = $start === null ? 1 : $parts['years'] + 1;
		$start = 0;
	}
	elseif ($start === null || ceil(log10($parts['years'])) <= PRS_FLOAT_DIG) {
		if ($v > 0) {
			$parts['months'] = $v;
			$value_abs_int -= $v * SEC_PER_MONTH;
			$start = $start === null ? 1 : $start;
		}

		$level = 2;
		foreach ([
			'days' => SEC_PER_DAY,
			'hours' => SEC_PER_HOUR,
			'minutes' => SEC_PER_MIN
		] as $part => $sec_per_part) {
			$v = floor($value_abs_int / $sec_per_part);
			if ($v > 0) {
				$parts[$part] = $v;
				$value_abs_int -= $v * $sec_per_part;
				$start = $start === null ? $level : $start;
			}

			if ($start !== null && $level - $start >= 2) {
				break;
			}

			$level++;
		}

		if ($start === null || $start >= 3) {
			if ($ignore_millisec) {
				$v = $value_abs_int + round(fmod($value_abs, 1), PRS_UNITS_ROUNDOFF_SUFFIXED);

				if ($v > 0) {
					$parts['seconds'] = $v;
				}
			}
			else {
				$parts['seconds'] = $value_abs_int;

				if ($start === null || $start >= 4) {
					$v = fmod($value_abs, 1) * 1000;

					if ($v > 0) {
						$parts['milliseconds'] = formatFloat($v, ['decimals' => PRS_UNITS_ROUNDOFF_SUFFIXED]);
					}
				}
			}
		}
	}

	$units = [
		'years' => _x('y', 'year short'),
		'months' => _x('M', 'month short'),
		'days' => _x('d', 'day short'),
		'hours' => _x('h', 'hour short'),
		'minutes' => _x('m', 'minute short'),
		'seconds' => _x('s', 'second short'),
		'milliseconds' => _x('ms', 'millisecond short')
	];

	$result = [];

	foreach (array_filter($parts) as $part_unit => $part_value) {
		$result[] = formatFloat($part_value, ['decimals' => PRS_UNITS_ROUNDOFF_SUFFIXED]).$units[$part_unit];
	}

	return $result ? ($value < 0 ? '-' : '').implode(' ', $result) : '0';
}

/**
 * Convert time period to a human-readable format.
 * The following units will be used: weeks, days, hours, minutes and seconds.
 * Only the 3 most significant units will be displayed: #w #d #h, #d #h #m or #h #m #s, omitting empty ones.
 *
 * @param int $value  Time period in seconds.
 *
 * @return string
 */
function convertSecondsToTimeUnits(int $value): string {
	$parts = [];
	$start = null;

	if (($v = floor($value / SEC_PER_WEEK)) > 0) {
		$parts['weeks'] = $v;
		$value -= $v * SEC_PER_WEEK;
		$start = 0;
	}

	$level = 1;

	foreach ([
		'days' => SEC_PER_DAY,
		'hours' => SEC_PER_HOUR,
		'minutes' => SEC_PER_MIN
	] as $part => $sec_per_part) {
		$v = floor($value / $sec_per_part);

		if ($v > 0) {
			$parts[$part] = $v;
			$value -= $v * $sec_per_part;
			$start = $start === null ? $level : $start;
		}

		if ($start !== null && $level - $start >= 2) {
			break;
		}

		$level++;
	}

	if ($start === null || $start >= 2) {
		$v = $value + round(fmod($value, 1), PRS_UNITS_ROUNDOFF_SUFFIXED);

		if ($v > 0) {
			$parts['seconds'] = $v;
		}
	}

	$units = [
		'weeks' => _x('w', 'week short'),
		'days' => _x('d', 'day short'),
		'hours' => _x('h', 'hour short'),
		'minutes' => _x('m', 'minute short'),
		'seconds' => _x('s', 'second short')
	];

	$result = [];

	foreach ($parts as $part_unit => $part_value) {
		$result[] = $part_value.$units[$part_unit];
	}

	return $result ? implode(' ', $result) : '0';
}

/**
 * Converts a raw value to a user-friendly representation based on unit and other parameters.
 * Example: 6442450944 B => 6 GB.
 *
 * @see convertUnitsRaw
 *
 * @param array $options
 *
 * @return string
 */
function convertUnits(array $options): string {
	[
		'value' => $value,
		'units' => $units
	] = convertUnitsRaw($options);

	$result = $value;

	if ($units !== '') {
		$result .= ' '.$units;
	}

	return $result;
}

/**
 * Converts a raw value to a user-friendly representation based on unit and other parameters.
 * Example: 6442450944 B => 6 GB.
 *
 * @param array $options
 *
 * $options = [
 *     'value' =>               (string)    Value to convert.
 *     'units' =>               (string)    Units to base the conversion on. Default: ''.
 *     'convert' =>             (int)       Default: ITEM_CONVERT_WITH_UNITS. Set to ITEM_CONVERT_NO_UNITS to
 *                                          force-convert a value with empty units.
 *     'power' =>               (int)       Convert to the specified power of "unit_base" (0 => '', 1 => K, 2 => M, ..).
 *                                          By default, the power will be calculated automatically.
 *     'unit_base' =>           (string)    1000 or 1024. By default, will only use 1024 for "B" and "Bps" units.
 *     'ignore_milliseconds' => (bool)      Ignore milliseconds in time conversion ("s" units).
 *     'precision' =>           (int)       Max number of significant digits to take into account.
 *                                          Default: PRS_FLOAT_DIG.
 *     'decimals' =>            (int|null)  Max number of first non-zero decimals to display. If null is specified,
 *                                          PRS_UNITS_ROUNDOFF_SUFFIXED or PRS_UNITS_ROUNDOFF_UNSUFFIXED will be used,
 *                                          depending on whether the units have been prefixed.
 *     'decimals_exact' =>      (bool)      Display exactly this number of decimals instead of first non-zeros.
 *                                          Default: false.
 *     'small_scientific' =>    (bool)      Allow scientific notation for small numbers. Default: true.
 *     'zero_as_zero' =>        (bool)      Return zero as '0', regardless of other options. Default: true.
 * ]
 *
 * @return array
 */
function convertUnitsRaw(array $options): array {
	static $power_table = ['', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];

	$options += [
		'value' => '',
		'units' => '',
		'convert' => ITEM_CONVERT_WITH_UNITS,
		'power' => null,
		'unit_base' => null,
		'ignore_milliseconds' => false,
		'precision' => PRS_FLOAT_DIG,
		'decimals' => null,
		'decimals_exact' => false,
		'small_scientific' => true,
		'zero_as_zero' => true
	];

	$value = $options['value'] !== null ? $options['value'] : '';

	if (!is_numeric($value)) {
		return [
			'value' => $value,
			'units' => '',
			'is_numeric' => false
		];
	}

	$units = $options['units'] !== null ? $options['units'] : '';

	if ($units === 'unixtime') {
		return [
			'value' => prs_date2str(DATE_TIME_FORMAT_SECONDS, $value),
			'units' => '',
			'is_numeric' => false
		];
	}

	if ($units === 'uptime') {
		return [
			'value' => convertUnitsUptime($value),
			'units' => '',
			'is_numeric' => false
		];
	}

	if ($units === 's') {
		return [
			'value' => convertUnitsS($value, $options['ignore_milliseconds']),
			'units' => '',
			'is_numeric' => false
		];
	}

	$blacklist = ['%', 'ms', 'rpm', 'RPM'];

	if ($units !== '' && $units[0] === '!') {
		$units = substr($units, 1);
		$blacklist[] = $units;
	}

	$value = (float) $value;
	$value_abs = abs($value);

	$do_convert = $units !== '' || $options['convert'] == ITEM_CONVERT_NO_UNITS;

	if (in_array($units, $blacklist) || !$do_convert || $value_abs < 1) {
		return [
			'value' => formatFloat($value, [
				'precision' => $options['precision'],
				'decimals' => $options['decimals'] !== null ? $options['decimals'] : PRS_UNITS_ROUNDOFF_UNSUFFIXED,
				'decimals_exact' => $options['decimals_exact'],
				'small_scientific' => $options['small_scientific'],
				'zero_as_zero' => $options['zero_as_zero']
			]),
			'units' => $units,
			'is_numeric' => true
		];
	}

	$unit_base = $options['unit_base'];

	if ($unit_base != 1000 && $unit_base != PRS_KIBIBYTE) {
		$unit_base = isBinaryUnits($units) ? PRS_KIBIBYTE : 1000;
	}

	if ($options['power'] === null) {
		$result = null;
		$unit_prefix = null;

		foreach ($power_table as $power => $prefix) {
			$result = formatFloat($value / pow($unit_base, $power), [
				'precision' => $options['precision'],
				'decimals' => $options['decimals'] !== null
					? $options['decimals']
					: ($prefix === '' ? PRS_UNITS_ROUNDOFF_UNSUFFIXED : PRS_UNITS_ROUNDOFF_SUFFIXED),
				'decimals_exact' => $options['decimals_exact'],
				'small_scientific' => $options['small_scientific'],
				'zero_as_zero' => $options['zero_as_zero']
			]);

			$unit_prefix = $prefix;

			if (abs($result) < $unit_base) {
				break;
			}
		}
	}
	else {
		$unit_power = array_key_exists($options['power'], $power_table) ? $options['power'] : count($power_table) - 1;
		$unit_prefix = $power_table[$unit_power];

		$result = formatFloat($value / pow($unit_base, $unit_power), [
			'precision' => $options['precision'],
			'decimals' => $options['decimals'] !== null
				? $options['decimals']
				: ($unit_prefix === '' ? PRS_UNITS_ROUNDOFF_UNSUFFIXED : PRS_UNITS_ROUNDOFF_SUFFIXED),
			'decimals_exact' => $options['decimals_exact'],
			'small_scientific' => $options['small_scientific'],
			'zero_as_zero' => $options['zero_as_zero']
		]);
	}

	$result_units = ($result == 0 ? '' : $unit_prefix).$units;

	return [
		'value' => $result,
		'units' => $result_units,
		'is_numeric' => true
	];
}

/**
 * Validate and convert time to seconds.
 * Examples: '100' => '100'; '10m' => '600'; '-10m' => '-600'; '3d' => '259200'.
 *
 * @param string $time       Decimal integer with optional time suffix.
 * @param bool   $with_year  Additionally parse year suffixes.
 *
 * @return int|null  Decimal integer seconds or null on error.
 */
function timeUnitToSeconds($time, $with_year = false) {
	$suffixes = $with_year ? PRS_TIME_SUFFIXES_WITH_YEAR : PRS_TIME_SUFFIXES;

	if (!preg_match('/^'.PRS_PREG_INT.'(?<suffix>['.$suffixes.'])?$/', $time, $matches)) {
		return null;
	}

	$suffix = array_key_exists('suffix', $matches) ? $matches['suffix'] : 's';

	return $matches['int'] * PRS_TIME_SUFFIX_MULTIPLIERS[$suffix];
}

/************* ZBX MISC *************/

/**
 * Check if every character in given string value is a decimal digit.
 *
 * @param string | int   $x Value to check.
 *
 * @return boolean
 */
function prs_ctype_digit($x) {
	return ctype_digit(strval($x));
}

/**
 * Returns true if the value is an empty string, empty array or null.
 *
 * @deprecated use strict comparison instead
 *
 * @param $value
 *
 * @return bool
 */
function prs_empty($value) {
	if ($value === null) {
		return true;
	}
	if (is_array($value) && empty($value)) {
		return true;
	}
	if (is_string($value) && $value === '') {
		return true;
	}

	return false;
}

function prs_is_int($var) {
	if (is_array($var)) {
		return false;
	}

	if (is_int($var)) {
		return true;
	}

	if (is_string($var)) {
		if (function_exists('ctype_digit') && ctype_digit($var) || strcmp(intval($var), $var) == 0) {
			return true;
		}
	}
	else {
		if ($var > 0 && prs_ctype_digit($var)) {
			return true;
		}
	}

	return preg_match("/^\-?\d{1,20}+$/", $var);
}

/**
 * Look for two arrays field value and create 3 array lists, one with arrays where field value exists only in first array
 * second with arrays where field values are only in second array and both where field values are in both arrays.
 *
 * @param array  $primary
 * @param array  $secondary
 * @param string $field field that is searched in arrays
 *
 * @return array
 */
function prs_array_diff(array $primary, array $secondary, $field) {
	$fields1 = prs_objectValues($primary, $field);
	$fields2 = prs_objectValues($secondary, $field);

	$first = array_diff($fields1, $fields2);
	$first = prs_toHash($first);

	$second = array_diff($fields2, $fields1);
	$second = prs_toHash($second);

	$result = [
		'first' => [],
		'second' => [],
		'both' => []
	];

	foreach ($primary as $array) {
		if (!isset($array[$field])) {
			$result['first'][] = $array;
		}
		elseif (isset($first[$array[$field]])) {
			$result['first'][] = $array;
		}
		else {
			$result['both'][$array[$field]] = $array;
		}
	}

	foreach ($secondary as $array) {
		if (!isset($array[$field])) {
			$result['second'][] = $array;
		}
		elseif (isset($second[$array[$field]])) {
			$result['second'][] = $array;
		}
	}

	return $result;
}

function prs_array_push(&$array, $add) {
	foreach ($array as $key => $value) {
		foreach ($add as $newKey => $newValue) {
			$array[$key][$newKey] = $newValue;
		}
	}
}

/**
 * Find if array has any duplicate values and return an array with info about them.
 * In case of no duplicates, empty array is returned.
 * Example of usage:
 *     $result = prs_arrayFindDuplicates(
 *         array('a', 'b', 'c', 'c', 'd', 'd', 'd', 'e')
 *     );
 *     array(
 *         'd' => 3,
 *         'c' => 2,
 *     )
 *
 * @param array $array
 *
 * @return array
 */
function prs_arrayFindDuplicates(array $array) {
	$countValues = array_count_values($array); // counting occurrences of every value in array
	foreach ($countValues as $value => $count) {
		if ($count <= 1) {
			unset($countValues[$value]);
		}
	}
	arsort($countValues); // sorting, so that the most duplicates would be at the top

	return $countValues;
}

/************* STRING *************/
function prs_nl2br($str) {
	$str_res = [];
	foreach (explode("\n", $str) as $str_line) {
		array_push($str_res, $str_line, BR());
	}
	array_pop($str_res);

	return $str_res;
}

function prs_formatDomId($value) {
	return str_replace(['[', ']'], ['_', ''], $value);
}

/************* SORT *************/
function natksort(&$array) {
	$keys = array_keys($array);
	natcasesort($keys);

	$new_array = [];

	foreach ($keys as $k) {
		$new_array[$k] = $array[$k];
	}

	$array = $new_array;

	return true;
}

// recursively sort an array by key
function prs_rksort(&$array, $flags = null) {
	if (is_array($array)) {
		foreach ($array as $id => $data) {
			prs_rksort($array[$id]);
		}
		ksort($array, $flags);
	}

	return $array;
}

/**
 * Sorts the data using a natural sort algorithm.
 *
 * Not suitable for sorting macros, use order_macros() instead.
 *
 * @param $data
 * @param null $sortfield
 * @param string $sortorder
 *
 * @return bool
 *
 * @see order_macros()
 */
function order_result(&$data, $sortfield = null, $sortorder = PRS_SORT_UP) {
	if (empty($data)) {
		return false;
	}

	if (is_null($sortfield)) {
		natcasesort($data);
		if ($sortorder != PRS_SORT_UP) {
			$data = array_reverse($data, true);
		}
		return true;
	}

	$sort = [];
	foreach ($data as $key => $arr) {
		if (!isset($arr[$sortfield])) {
			return false;
		}
		$sort[$key] = $arr[$sortfield];
	}
	natcasesort($sort);

	if ($sortorder != PRS_SORT_UP) {
		$sort = array_reverse($sort, true);
	}

	$tmp = $data;
	$data = [];
	foreach ($sort as $key => $val) {
		$data[$key] = $tmp[$key];
	}

	return true;
}

/**
 * Sorts the macros in the given order. Supports user and LLD macros.
 *
 * order_result() is not suitable for sorting macros, because it treats the "}" as a symbol with a lower priority
 * then any alphanumeric character, and the result will be invalid.
 *
 * E.g: order_result() will sort array('{$DD}', '{$D}', '{$D1}') as
 * array('{$D1}', '{$DD}', '{$D}') while the correct result is array('{$D}', '{$D1}', '{$DD}').
 *
 * @param array $macros
 * @param string $sortfield
 * @param string $order
 *
 * @return array
 */
function order_macros(array $macros, $sortfield, $order = PRS_SORT_UP) {
	$temp = [];
	foreach ($macros as $key => $macro) {
		$temp[$key] = substr($macro[$sortfield], 2, strlen($macro[$sortfield]) - 3);
	}
	order_result($temp, null, $order);

	$rs = [];
	foreach ($temp as $key => $macroLabel) {
		$rs[$key] = $macros[$key];
	}

	return $rs;
}

// preserve keys
function prs_array_merge() {
	$args = func_get_args();
	$result = [];
	foreach ($args as &$array) {
		if (!is_array($array)) {
			return false;
		}
		foreach ($array as $key => $value) {
			$result[$key] = $value;
		}
	}
	unset($array);

	return $result;
}

function uint_in_array($needle, $haystack) {
	foreach ($haystack as $value) {
		if (bccomp($needle, $value) == 0) {
			return true;
		}
	}

	return false;
}

function str_in_array($needle, $haystack, $strict = false) {
	if (is_array($needle)) {
		return in_array($needle, $haystack, $strict);
	}
	elseif ($strict) {
		foreach ($haystack as $value) {
			if ($needle === $value) {
				return true;
			}
		}
	}
	else {
		foreach ($haystack as $value) {
			if (strcmp($needle, $value) == 0) {
				return true;
			}
		}
	}

	return false;
}

function prs_value2array(&$values) {
	if (!is_array($values) && !is_null($values)) {
		$tmp = [];
		if (is_object($values)) {
			$tmp[] = $values;
		}
		else {
			$tmp[$values] = $values;
		}
		$values = $tmp;
	}
}

// creates chain of relation parent -> child, for all chain levels
function createParentToChildRelation(&$chain, $link, $parentField, $childField) {
	if (!isset($chain[$link[$parentField]])) {
		$chain[$link[$parentField]] = [];
	}

	$chain[$link[$parentField]][$link[$childField]] = $link[$childField];
	if (isset($chain[$link[$childField]])) {
		$chain[$link[$parentField]] = prs_array_merge($chain[$link[$parentField]], $chain[$link[$childField]]);
	}
}

// object or array of objects to hash
function prs_toHash($value, $field = null) {
	if (is_null($value)) {
		return $value;
	}
	$result = [];

	if (!is_array($value)) {
		$result = [$value => $value];
	}
	elseif (isset($value[$field])) {
		$result[$value[$field]] = $value;
	}
	else {
		foreach ($value as $val) {
			if (!is_array($val)) {
				$result[$val] = $val;
			}
			elseif (isset($val[$field])) {
				$result[$val[$field]] = $val;
			}
		}
	}

	return $result;
}

/**
 * Transforms a single or an array of values to an array of objects, where the values are stored under the $field
 * key.
 *
 * E.g:
 * prs_toObject(array(1, 2), 'hostid')            // returns array(array('hostid' => 1), array('hostid' => 2))
 * prs_toObject(3, 'hostid')                      // returns array(array('hostid' => 3))
 * prs_toObject(array('a' => 1), 'hostid', true)  // returns array('a' => array('hostid' => 1))
 *
 * @param $value
 * @param $field
 * @param $preserve_keys
 *
 * @return array
 */
function prs_toObject($value, $field, $preserve_keys = false) {
	if (is_null($value)) {
		return $value;
	}
	$result = [];

	// Value or Array to Object or Array of objects
	if (!is_array($value)) {
		$result = [[$field => $value]];
	}
	elseif (!isset($value[$field])) {
		foreach ($value as $key => $val) {
			if (!is_array($val)) {
				$result[$key] = [$field => $val];
			}
		}

		if (!$preserve_keys) {
			$result = array_values($result);
		}
	}

	return $result;
}

/**
 * Converts the given value to a numeric array:
 * - a scalar value will be converted to an array and added as the only element;
 * - an array with first element key containing only numeric characters will be converted to plain zero-based numeric array.
 * This is used for resetting nonsequential numeric arrays;
 * - an associative array will be returned in an array as the only element, except if first element key contains only numeric characters.
 *
 * @param mixed $value
 *
 * @return array
 */
function prs_toArray($value) {
	if ($value === null) {
		return $value;
	}

	if (is_array($value)) {
		// reset() is needed to move internal array pointer to the beginning of the array
		reset($value);

		if (prs_ctype_digit(key($value))) {
			$result = array_values($value);
		}
		elseif (!empty($value)) {
			$result = [$value];
		}
		else {
			$result = [];
		}
	}
	else {
		$result = [$value];
	}

	return $result;
}

/**
 * Converts value OR object OR array of objects TO an array.
 *
 * @deprecated  Use array_column() instead.
 *
 * @param $value
 * @param $field
 *
 * @return array
 */
function prs_objectValues($value, $field) {
	if (is_null($value)) {
		return $value;
	}

	if (!is_array($value)) {
		$result = [$value];
	}
	elseif (isset($value[$field])) {
		$result = [$value[$field]];
	}
	else {
		$result = [];

		foreach ($value as $val) {
			if (!is_array($val)) {
				$result[] = $val;
			}
			elseif (isset($val[$field])) {
				$result[] = $val[$field];
			}
		}
	}

	return $result;
}

function prs_cleanHashes(&$value) {
	if (is_array($value)) {
		// reset() is needed to move internal array pointer to the beginning of the array
		reset($value);
		if (prs_ctype_digit(key($value))) {
			$value = array_values($value);
		}
	}

	return $value;
}

function prs_toCSV($values) {
	$csv = '';
	$glue = '","';
	foreach ($values as $row) {
		if (!is_array($row)) {
			$row = [$row];
		}
		foreach ($row as $num => $value) {
			$row[$num] = str_replace('"', '""', $value);
		}
		$csv .= '"'.implode($glue, $row).'"'."\n";
	}

	return $csv;
}

function prs_str2links($text) {
	$result = [];

	foreach (explode("\n", $text) as $line) {
		$line = rtrim($line, "\r ");

		preg_match_all('#https?://[^\n\t\r ]+#u', $line, $matches);

		$start = 0;

		foreach ($matches[0] as $match) {
			if (($pos = mb_strpos($line, $match, $start)) !== false) {
				if ($pos != $start) {
					$result[] = mb_substr($line, $start, $pos - $start);
				}
				$result[] = new CLink($match, $match);
				$start = $pos + mb_strlen($match);
			}
		}

		if (mb_strlen($line) != $start) {
			$result[] = mb_substr($line, $start);
		}

		$result[] = BR();
	}

	array_pop($result);

	return $result;
}

function prs_subarray_push(&$mainArray, $sIndex, $element = null, $key = null) {
	if (!isset($mainArray[$sIndex])) {
		$mainArray[$sIndex] = [];
	}
	if ($key) {
		$mainArray[$sIndex][$key] = is_null($element) ? $sIndex : $element;
	}
	else {
		$mainArray[$sIndex][] = is_null($element) ? $sIndex : $element;
	}
}

/*************** PAGE SORTING ******************/

/**
 * Returns header with sorting options.
 *
 * @param string obj			Header item.
 * @param string $tabfield		Table field.
 * @param string $sortField		Sorting field.
 * @param string $sortOrder		Sorting order.
 * @param string $link			Sorting link.
 *
 * @return CColHeader
 */
function make_sorting_header($obj, $tabfield, $sortField, $sortOrder, $link = null) {
	$sortorder = ($sortField == $tabfield && $sortOrder == PRS_SORT_UP) ? PRS_SORT_DOWN : PRS_SORT_UP;

	$link = CUrlFactory::getContextUrl($link);

	$link->setArgument('sort', $tabfield);
	$link->setArgument('sortorder', $sortorder);

	prs_value2array($obj);

	$arrow = null;
	if ($tabfield == $sortField) {
		if ($sortorder == PRS_SORT_UP) {
			$arrow = (new CSpan())->addClass(PRS_STYLE_ARROW_DOWN);
		}
		else {
			$arrow = (new CSpan())->addClass(PRS_STYLE_ARROW_UP);
		}
	}

	return new CColHeader(new CLink([$obj, $arrow], $link->getUrl()));
}

/**
 * Get decimal point and thousands separator for number formatting according to the current locale.
 *
 * @return array  'decimal_point' and 'thousands_sep' values.
 */
function getNumericFormatting(): array {
	static $numeric_formatting = null;

	if ($numeric_formatting === null) {
		$numeric_formatting = array_intersect_key(localeconv(), array_flip(['decimal_point', 'thousands_sep']));
	}

	return $numeric_formatting;
}

/**
 * Format floating-point number in the best possible way for displaying.
 *
 * @param float $number   Valid number in decimal or scientific notation.
 * @param array $options  Formatting options.
 *
 * $options = [
 *     'precision' =>        (int)   Max number of significant digits to take into account. Default: PRS_FLOAT_DIG.
 *     'decimals' =>         (int)   Max number of first non-zero decimals to display. Default: 0.
 *     'decimals_exact' =>   (bool)  Display exactly this number of decimals instead of first non-zeros. Default: false.
 *     'small_scientific' => (bool)  Allow scientific notation for small numbers. Default: true.
 *     'zero_as_zero' =>     (bool)  Return zero as '0', regardless of other options. Default: true.
 * ]
 *
 * Note: $decimals must be less than $precision.
 *
 * @return string
 */
function formatFloat(float $number, array $options = []): string {
	if ($number == INF) {
		return t('zapi', 'Infinity');
	}

	if ($number == -INF) {
		return '-'.t('zapi', 'Infinity');
	}

	$defaults = [
		'precision' => PRS_FLOAT_DIG,
		'decimals' => 0,
		'decimals_exact' => false,
		'small_scientific' => true,
		'zero_as_zero' => true
	];

	[
		'precision' => $precision,
		'decimals' => $decimals,
		'decimals_exact' => $decimals_exact,
		'small_scientific' => $small_scientific,
		'zero_as_zero' => $zero_as_zero
	] = $options + $defaults;

	if ($zero_as_zero && $number == 0) {
		return '0';
	}

	$number_original = $number;

	$exponent = (int) explode('E', sprintf('%.'.($precision - 1).'E', $number))[1];

	if ($exponent < 0) {
		for ($i = 1; $i >= 0; $i--) {
			$round_precision = $decimals - $exponent - $i;

			// PHP rounding bug when precision is set more than 294.
			if ($round_precision > 294) {
				$decimal_shift = pow(10, $round_precision - 294);
				$test = round($number * $decimal_shift, 294) / $decimal_shift;
			}
			else {
				$test = round($number, $round_precision);
			}

			$test_number = sprintf('%.'.($precision - 1).'E', $test);
			$test_digits = $precision == 1
				? 1
				: strlen(rtrim(explode('E', $test_number)[0], '0')) - ($test_number[0] === '-' ? 2 : 1);

			if (!$small_scientific || $test_digits - $exponent < $precision) {
				break;
			}
		}
		$number = $test_number;
		$digits = $test_digits;
	}
	else {
		if ($exponent >= $precision) {
			if ($exponent >= min(PHP_FLOAT_DIG, $precision + 3)
					|| round($number, $precision - $exponent - 1) != $number) {
				$number = round($number, $decimals - $exponent);
			}
		}
		else {
			$number = round($number, min($decimals, $precision - $exponent - 1));
		}

		$number = sprintf('%.'.($precision - 1).'E', $number);
		$digits = $precision == 1 ? 1 : strlen(rtrim(explode('E', $number)[0], '0')) - ($number[0] === '-' ? 2 : 1);
	}

	if ($zero_as_zero && $number == 0) {
		return '0';
	}

	[
		'decimal_point' => $decimal_point,
		'thousands_sep' => $thousands_sep
	] = getNumericFormatting();

	$exponent = (int) explode('E', sprintf('%.'.($precision - 1).'E', $number))[1];

	if ($exponent < 0) {
		if (!$small_scientific
				|| $digits - $exponent <= ($decimals_exact ? min($decimals + 1, $precision) : $precision)) {
			return number_format($number, $decimals_exact ? $decimals : $digits - $exponent - 1, $decimal_point,
				$thousands_sep
			);
		}
		else {
			return str_replace('.', $decimal_point,
				sprintf('%.'.($decimals_exact ? $decimals : min($digits - 1, $decimals)).'E', $number)
			);
		}
	}
	elseif ($exponent >= min(PHP_FLOAT_DIG, $precision + 3)
			|| ($exponent >= $precision && $number != $number_original)) {
		return str_replace('.', $decimal_point,
			sprintf('%.'.($decimals_exact ? $decimals : min($digits - 1, $decimals)).'E', $number)
		);
	}
	else {
		return number_format($number, $decimals_exact ? $decimals : max(0, min($digits - $exponent - 1, $decimals)),
			$decimal_point, $thousands_sep
		);
	}
}

/**
* Truncate float to the amount of significant digits, to allow safe float comparison.
*
* @param float $number
*
* @return float
*/
function truncateFloat(float $number): float {
	if (is_infinite($number)) {
		return $number;
	}

	return (float) sprintf('%.'.(PRS_FLOAT_DIG - 1).'E', $number);
}

/**
 * Get number of digits after the decimal dot.
 *
 * @param float $number  Valid number in decimal or scientific notation.
 *
 * @return int
 */
function getNumDecimals(float $number): int {
	[$mantissa, $exponent] = explode('E', sprintf('%.'.(PRS_FLOAT_DIG - 1).'E', $number));

	$significant_size = strlen(rtrim($mantissa, '0')) - ($number < 0 ? 2 : 1);

	return max(0, $significant_size - 1 - $exponent);
}

/**
 * Converts number to letter representation.
 * From A to Z, then from AA to ZZ etc.
 * Example: 0 => A, 25 => Z, 26 => AA, 27 => AB, 52 => BA, ...
 *
 * Keep in sync with JS num2letter().
 *
 * @param int $number
 *
 * @return string
 */
function num2letter($number) {
	$start = ord('A');
	$base = 26;
	$str = '';
	$level = 0;

	do {
		if ($level++ > 0) {
			$number--;
		}
		$remainder = $number % $base;
		$number = ($number - $remainder) / $base;
		$str = chr($start + $remainder).$str;
	} while (0 != $number);

	return $str;
}

/**
 * Renders an "access denied" message and stops the execution of the script.
 *
 * The $mode parameters controls the layout of the message for logged in users:
 * - ACCESS_DENY_OBJECT     - render the message when denying access to a specific object
 * - ACCESS_DENY_PAGE       - render a complete access denied page
 *
 * If visitor is without any access permission then layout of the message is same as in ACCESS_DENY_PAGE mode.
 *
 * @param int $mode
 */
function access_deny($mode = ACCESS_DENY_OBJECT) {
	// deny access to an object
	if ($mode == ACCESS_DENY_OBJECT && CWebUser::isLoggedIn()) {
		show_error_message(t('zapi', 'No permissions to referred object or it does not exist!'));

		require_once dirname(__FILE__).'/page_header.php';
		(new CHtmlPage())->show();
		require_once dirname(__FILE__).'/page_footer.php';
	}
	// deny access to a page
	else {
		// url to redirect the user to after he logs in
		$url = (new CUrl(!empty($_REQUEST['request']) ? $_REQUEST['request'] : ''))
			->removeArgument(CCsrfTokenHelper::CSRF_TOKEN_NAME);

		if (CAuthenticationHelper::getPublic(CAuthenticationHelper::HTTP_LOGIN_FORM) == PRS_AUTH_FORM_HTTP
				&& CAuthenticationHelper::getPublic(CAuthenticationHelper::HTTP_AUTH_ENABLED) == PRS_AUTH_HTTP_ENABLED
				&& (!CWebUser::isLoggedIn() || CWebUser::isGuest())) {
			$redirect_to = (new CUrl('index_http.php'))->setArgument('request', $url->toString());
			redirect($redirect_to->toString());
		}

		$url = urlencode($url->toString());

		// if the user is logged in - render the access denied message
		if (CWebUser::isLoggedIn()) {
			$data = [
				'header' => t('zapi', 'Access denied'),
				'messages' => [
					t('zapi', 'You are logged in as "{name}".', ['name' => CWebUser::$data['username']]).' '.t('zapi', 'You have no permissions to access this page.'),
					t('zapi', 'If you think this message is wrong, please consult your administrators about getting the necessary permissions.')
				],
				'buttons' => []
			];

			// display the login button only for guest users
			if (CWebUser::isGuest()) {
				$data['buttons'][] = (new CButton('login', t('zapi', 'Login')))
					->setAttribute('data-url', $url)
					->onClick('document.location = "index.php?request=" + this.dataset.url;');
			}

			$data['buttons'][] = (new CButton('back', t('zapi', 'Go to "{name}"', ['name' => CMenuHelper::getFirstLabel()])))
				->setAttribute('data-url', CMenuHelper::getFirstUrl())
				->onClick('document.location = this.dataset.url');
		}
		// if the user is not logged in - offer to login
		else {
			$data = [
				'header' => t('zapi', 'You are not logged in'),
				'messages' => [
					t('zapi', 'You must login to view this page.'),
					t('zapi', 'If you think this message is wrong, please consult your administrators about getting the necessary permissions.')
				],
				'buttons' => [
					(new CButton('login', t('zapi', 'Login')))
						->setAttribute('data-url', $url)
						->onClick('document.location = "index.php?request=" + this.dataset.url;')
				]
			];
		}

		$data['theme'] = getUserTheme(CWebUser::$data);

		if (detect_page_type() == PAGE_TYPE_JS) {
			echo (new CView('layout.json', ['main_block' => json_encode(['error' => $data['header']])]))->getOutput();
		}
		else {
			echo (new CView('general.warning', $data))->getOutput();
		}
		session_write_close();
		exit();
	}
}

function detect_page_type($default = PAGE_TYPE_HTML) {
	if (isset($_REQUEST['output'])) {
		switch (strtolower($_REQUEST['output'])) {
			case 'text':
				return PAGE_TYPE_TEXT;
			case 'ajax':
				return PAGE_TYPE_JS;
			case 'json':
				return PAGE_TYPE_JSON;
			case 'json-rpc':
				return PAGE_TYPE_JSON_RPC;
			case 'html':
				return PAGE_TYPE_HTML_BLOCK;
			case 'img':
				return PAGE_TYPE_IMAGE;
			case 'css':
				return PAGE_TYPE_CSS;
		}
	}

	return $default;
}

/**
 * Create a message box.
 *
 * @param string      $class                  CSS class of the message box. Possible values:
 *                                            PRS_STYLE_MSG_GOOD, PRS_STYLE_MSG_BAD, PRS_STYLE_MSG_WARNING.
 * @param array       $messages               An array of messages.
 * @param string      $messages[]['message']  Message text.
 * @param string|null $title                  (optional) Message box title.
 * @param bool        $show_close_box         (optional) Show or hide close button in error message box.
 * @param bool        $show_details           (optional) Show or hide message details.
 *
 * @return CTag
 */
function makeMessageBox(string $class, array $messages, string $title = null, bool $show_close_box = true,
		bool $show_details = false): CTag {
	$msg_details = null;
	$link_details = null;

	if ($messages) {
		if ($title !== null) {
			$link_details = (new CLinkAction())
				->addItem(t('zapi', 'Details'))
				->addItem(' ') // space
				->addItem((new CSpan())
					->setId('details-arrow')
					->addClass($show_details ? PRS_STYLE_ARROW_UP : PRS_STYLE_ARROW_DOWN)
				)
				->setAttribute('aria-expanded', $show_details ? 'true' : 'false')
				->onClick('javascript: '.
					'showHide(jQuery(this).siblings(\'.'.PRS_STYLE_MSG_DETAILS.'\')'.
						'.find(\'.'.PRS_STYLE_MSG_DETAILS_BORDER.'\'));'.
					'jQuery("#details-arrow", $(this)).toggleClass("'.PRS_STYLE_ARROW_UP.' '.PRS_STYLE_ARROW_DOWN.'");'.
					'jQuery(this).attr(\'aria-expanded\', jQuery(this).find(\'.'.PRS_STYLE_ARROW_DOWN.'\').length == 0)'
				);
		}

		$list = (new CList())->addClass(PRS_STYLE_LIST_DASHED);
		if ($title !== null) {
			$list->addClass(PRS_STYLE_MSG_DETAILS_BORDER);

			if (!$show_details) {
				$list->setAttribute('style', 'display: none;');
			}
		}

		foreach ($messages as $message) {
			$list->addItem($message['message']);
		}

		$msg_details = (new CDiv())
			->addClass(PRS_STYLE_MSG_DETAILS)
			->addItem($list);
	}

	if ($title !== null) {
		$title = new CSpan($title);
	}

	$aria_labels = [
		PRS_STYLE_MSG_GOOD => t('zapi', 'Success message'),
		PRS_STYLE_MSG_BAD => t('zapi', 'Error message'),
		PRS_STYLE_MSG_WARNING => t('zapi', 'Warning message')
	];

	// Details link should be in front of title.
	$msg_box = (new CTag('output', true, [$link_details, $title, $msg_details]))
		->addClass($class)
		->setAttribute('role', 'contentinfo')
		->setAttribute('aria-label', $aria_labels[$class]);

	if ($show_close_box) {
		$msg_box->addItem((new CSimpleButton())
			->addClass(PRS_STYLE_OVERLAY_CLOSE_BTN)
			->onClick('jQuery(this).closest(\'.'.$class.'\').remove();')
			->setTitle(t('zapi', 'Close')));
	}

	return $msg_box;
}

/**
 * Filters messages that can be displayed to user based on CSettingsHelper::SHOW_TECHNICAL_ERRORS and user settings.
 *
 * @return array
 */
function filter_messages(): array {
	if (!CSettingsHelper::getPublic(CSettingsHelper::SHOW_TECHNICAL_ERRORS)) {

		$type = CMessageHelper::getType();
		$title = CMessageHelper::getTitle();
		$messages = CMessageHelper::getMessages();
		CMessageHelper::clear();

		if ($title !== null) {
			if ($type === CMessageHelper::MESSAGE_TYPE_SUCCESS) {
				CMessageHelper::setSuccessTitle($title);
			}
			else {
				CMessageHelper::setErrorTitle($title);
			}
		}

		$generic_exists = false;
		foreach ($messages as $message) {
			if ($message['type'] === CMessageHelper::MESSAGE_TYPE_ERROR	&& $message['is_technical_error']) {
				if (!$generic_exists) {
					CMessageHelper::addError(t('zapi', 'System error occurred. Please contact Perseus administrator.'));
					$generic_exists = true;
				}
			}
			else {
				CMessageHelper::addMessage($message);
			}
		}
	}

	return CMessageHelper::getMessages();
}

/**
 * Returns a message box if there are messages. Otherwise, null.
 *
 * @param  bool    $good            Parameter passed to makeMessageBox to specify message box style.
 * @param  string  $title           Message box title.
 * @param  bool    $show_close_box  Show or hide close button in error message box.
 *
 * @return CTag|null
 */
function getMessages(bool $good = false, string $title = null, bool $show_close_box = true): ?CTag {
	$messages = get_and_clear_messages();

	$message_box = ($title || $messages)
		? makeMessageBox($good ? PRS_STYLE_MSG_GOOD : PRS_STYLE_MSG_BAD, $messages, $title, $show_close_box, !$good)
		: null;

	return $message_box;
}

function show_messages($good = null, $okmsg = null, $errmsg = null) {
	global $page, $PRS_MESSAGES_PREPARED;

	if (defined('PRS_API_REQUEST')) {
		return null;
	}

	$messages = get_and_clear_messages();

	if ($good === null) {
		$has_errors = false;
		$has_warnings = false;

		foreach ($messages as $message) {
			$has_errors = ($has_errors || ($message['type'] === 'error'));
			$has_warnings = ($has_warnings || ($message['type'] === 'warning'));
		}

		if ($has_errors) {
			$class = PRS_STYLE_MSG_BAD;
			$good = false;
		}
		elseif ($has_warnings) {
			$class = PRS_STYLE_MSG_WARNING;
			$good = true;
		}
		else {
			$class = PRS_STYLE_MSG_GOOD;
			$good = true;
		}
	}
	else {
		$class = $good ? PRS_STYLE_MSG_GOOD : PRS_STYLE_MSG_BAD;
	}

	$title = $good ? $okmsg : $errmsg;

	if ($title === null && !$messages) {
		return;
	}

	$page_type = (is_array($page) && array_key_exists('type', $page)) ? $page['type'] : PAGE_TYPE_HTML;

	switch ($page_type) {
		case PAGE_TYPE_IMAGE:
			$image_messages = [];

			if ($title !== null) {
				$image_messages[] = [
					'text' => $title,
					'color' => (!$good) ? ['R' => 255, 'G' => 0, 'B' => 0] : ['R' => 34, 'G' => 51, 'B' => 68]
				];
			}

			foreach ($messages as $message) {
				$image_messages[] = [
					'text' => $message['message'],
					'color' => ($message['type'] === 'error')
						? ['R' => 255, 'G' => 55, 'B' => 55]
						: ['R' => 155, 'G' => 155, 'B' => 55]
				];
			}

			// Draw an image with the messages.
			$image_font_size = 8;

			// Calculate the size of the text.
			$width = 0;
			$height = 0;

			foreach ($image_messages as &$message) {
				$size = imageTextSize($image_font_size, 0, $message['text']);
				$message['height'] = $size['height'] - $size['baseline'];

				// Calculate the total size of the image.
				$width = max($width, $size['width']);
				$height += $size['height'] + 1;
			}
			unset($message);

			// Add padding.
			$width += 2;
			$height += 2;

			// Create the image.
			$canvas = imagecreate($width, $height);
			imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 255, 255, 255));

			// Draw messages.
			$y = 1;
			foreach ($image_messages as $message) {
				$y += $message['height'];
				imageText($canvas, $image_font_size, 0, 1, $y,
					imagecolorallocate($canvas, $message['color']['R'], $message['color']['G'], $message['color']['B']),
					$message['text']
				);
			}

			imageOut($canvas);
			imagedestroy($canvas);
			break;

		default:
			if (!is_array($PRS_MESSAGES_PREPARED)) {
				$PRS_MESSAGES_PREPARED = [];
			}

			// Prepare messages for inclusion within the layout engine.
			$PRS_MESSAGES_PREPARED[] = [
				'class' => $class,
				'messages' => $messages,
				'title' => $title,
				'show_close_box' => true,
				'show_details' => ($class === PRS_STYLE_MSG_BAD)
			];

			break;
	}
}

/**
 * Get prepared HTML messages generated by the current request and, optionally, passed by the previous request.
 *
 * @param array $options['with_auth_warning']      Include unsuccessful authentication warning message.
 * @param array $options['with_session_messages']  Include messages passed by the previous request.
 * @param array $options['with_current_messages']  Include messages generated by the current request.
 *
 * @return string|null  One or several HTML message boxes.
 */
function get_prepared_messages(array $options = []): ?string {
	global $PRS_MESSAGES_PREPARED;

	if (!is_array($PRS_MESSAGES_PREPARED)) {
		$PRS_MESSAGES_PREPARED = [];
	}

	$options += [
		'with_auth_warning' => false,
		'with_session_messages' => false,
		'with_current_messages' => false
	];

	// Process messages of the current request.

	if ($options['with_current_messages']) {
		show_messages(
			null,
			CMessageHelper::getTitle(),
			CMessageHelper::getTitle()
		);

		$messages_current = $PRS_MESSAGES_PREPARED;
		$restore_messages = [];
		$restore_messages_prepared = [];
	}
	else {
		$messages_current = [];
		$restore_messages = CMessageHelper::getMessages();
		$restore_messages_prepared = $PRS_MESSAGES_PREPARED;
		CMessageHelper::clear();
	}

	$PRS_MESSAGES_PREPARED = [];

	// Process authentication warning if user had unsuccessful authentication attempts.

	if ($options['with_auth_warning'] && ($failed_attempts = CProfile::get('web.login.attempt.failed', 0))) {
		$attempt_ip = CProfile::get('web.login.attempt.ip', '');
		$attempt_date = CProfile::get('web.login.attempt.clock', 0);

		error(_n('%4$s failed login attempt logged. Last failed attempt was from %1$s on %2$s at %3$s.',
			'%4$s failed login attempts logged. Last failed attempt was from %1$s on %2$s at %3$s.',
			$attempt_ip,
			prs_date2str(DATE_FORMAT, $attempt_date),
			prs_date2str(TIME_FORMAT, $attempt_date),
			$failed_attempts
		));

		show_messages(
			false, // Failed login can be only error message.
			CMessageHelper::getTitle(),
			CMessageHelper::getTitle()
		);

		CProfile::update('web.login.attempt.failed', 0, PROFILE_TYPE_INT);
	}

	$messages_authentication = $PRS_MESSAGES_PREPARED;
	$PRS_MESSAGES_PREPARED = [];

	// Process messages passed by the previous request.

	if ($options['with_session_messages']) {
		CMessageHelper::restoreScheduleMessages($messages_current);

		if (CMessageHelper::getTitle() !== null) {
			show_messages(
				CMessageHelper::getType() === CMessageHelper::MESSAGE_TYPE_SUCCESS,
				CMessageHelper::getTitle(),
				CMessageHelper::getTitle()
			);
		}
	}
	$messages_session = $PRS_MESSAGES_PREPARED;

	// Create message boxes for all requested messages types in the correct order.

	$html = '';
	foreach (array_merge($messages_authentication, $messages_session, $messages_current) as $box) {
		$html .= makeMessageBox($box['class'], $box['messages'], $box['title'], $box['show_close_box'],
			$box['show_details']
		)->toString();
	}

	foreach ($restore_messages as $message) {
		CMessageHelper::addMessage($message);
	}

	$PRS_MESSAGES_PREPARED = $restore_messages_prepared;

	return ($html === '') ? null : $html;
}

function show_message(string $msg): void {
	show_messages(true, $msg, '');
}

function show_error_message(string $msg): void {
	show_messages(false, '', $msg);
}

function info($msgs): void {
	prs_value2array($msgs);

	foreach ($msgs as $msg) {
		CMessageHelper::addSuccess($msg);
	}
}

/**
 * Add warning messages to the global message array.
 *
 * @param array|string $messages
 */
function warning($messages): void {
	prs_value2array($messages);

	foreach ($messages as $message) {
		CMessageHelper::addWarning($message);
	}
}

/**
 * Add an error to global message array.
 *
 * @param string|array $msgs                Error message text.
 * @param bool         $is_technical_error
 */
function error($msgs, bool $is_technical_error = false): void {
	$msgs = prs_toArray($msgs);

	foreach ($msgs as $msg) {
		CMessageHelper::addError($msg, $is_technical_error);
	}
}

function get_and_clear_messages(): array {
	$messages = filter_messages();
	CMessageHelper::clear();

	return $messages;
}

function fatal_error($msg) {
	require_once dirname(__FILE__).'/page_header.php';
	show_error_message($msg);
	require_once dirname(__FILE__).'/page_footer.php';
}

function parse_period($str) {
	$out = null;
	$time_periods_parser = new CTimePeriodsParser();

	if ($time_periods_parser->parse($str) != CParser::PARSE_SUCCESS) {
		return null;
	}

	foreach ($time_periods_parser->getPeriods() as $period) {
		if (!preg_match('/^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$/', $period, $matches)) {
			return null;
		}

		for ($i = $matches[1]; $i <= $matches[2]; $i++) {
			if (!isset($out[$i])) {
				$out[$i] = [];
			}
			array_push($out[$i], [
				'start_h' => $matches[3],
				'start_m' => $matches[4],
				'end_h' => $matches[5],
				'end_m' => $matches[6]
			]);
		}
	}

	return $out;
}

/**
 * Set image header.
 *
 * @param integer $format    One of IMAGE_FORMAT_* constants. If not set global $IMAGE_FORMAT_DEFAULT will be used.
 */
function set_image_header($format = null) {
	global $IMAGE_FORMAT_DEFAULT;

	switch ($format !== null ? $format : $IMAGE_FORMAT_DEFAULT) {
		case IMAGE_FORMAT_JPEG:
			header('Content-type: image/jpeg');
			break;

		case IMAGE_FORMAT_GIF:
			header('Content-type: image/gif');
			break;

		case IMAGE_FORMAT_TEXT:
			header('Content-type: text/html');
			break;

		default:
			header('Content-type: image/png');
	}

	header('Expires: Mon, 17 Aug 1998 12:51:50 GMT');
}

function imageOut(&$image, $format = null) {
	global $page, $IMAGE_FORMAT_DEFAULT;

	if (is_null($format)) {
		$format = $IMAGE_FORMAT_DEFAULT;
	}

	ob_start();

	if (IMAGE_FORMAT_JPEG == $format) {
		imagejpeg($image);
	}
	else {
		imagepng($image);
	}

	$imageSource = ob_get_contents();
	ob_end_clean();

	if ($page['type'] != PAGE_TYPE_IMAGE) {
		$imageId = md5(strlen($imageSource));
		CSessionHelper::set('image_id', [$imageId => $imageSource]);
	}

	switch ($page['type']) {
		case PAGE_TYPE_IMAGE:
			echo $imageSource;
			break;
		case PAGE_TYPE_JSON:
			echo json_encode(['result' => $imageId]);
			break;
		case PAGE_TYPE_TEXT:
		default:
			echo $imageId;
	}
}

/**
 * Check if we have error messages to display.
 *
 * @return bool
 */
function hasErrorMessages() {
	return CMessageHelper::getType() === CMessageHelper::MESSAGE_TYPE_ERROR;
}

/**
 * Clears table rows selection's cookies.
 *
 * @param string $name     entity name, used as sessionStorage suffix
 * @param array  $keepids  checked rows ids
 */
function uncheckTableRows($name = null, $keepids = []) {
	$key = 'cb_'.basename($_SERVER['SCRIPT_NAME'], '.php').($name !== null ? '_'.$name : '');

	if ($keepids) {
		$keepids = array_fill_keys($keepids, '');

		insert_js('sessionStorage.setItem('.json_encode($key).', JSON.stringify('.json_encode($keepids).'));');
	}
	else {
		insert_js('sessionStorage.removeItem('.json_encode($key).');');
	}
}

/**
 * Trim each element of the script path. For example, " a / b / c d " => "a/b/c d"
 *
 * @param string $name
 *
 * @return string
 */
function trimPath($name) {
	$path = splitPath($name);
	$path = array_map('trim', $path);
	$path = str_replace(['\\', '/'], ['\\\\', '\\/'], $path);
	return implode('/', $path);
}

/**
 * Splitting string using slashes with escape backslash support and non-pair backslash cleanup.
 *
 * @param string $path
 *
 * @return array
 */
function splitPath($path) {
	$path_items = [];
	$path_item = '';

	for ($i = 0; isset($path[$i]); $i++) {
		switch ($path[$i]) {
			case '/':
				$path_items[] = $path_item;
				$path_item = '';
				break;

			case '\\':
				if (isset($path[++$i])) {
					$path_item .= $path[$i];
				}
				break;

			default:
				$path_item .= $path[$i];
		}
	}

	$path_items[] = $path_item;

	return $path_items;
}

/**
 * Allocate color for an image.
 *
 * @param resource $image
 * @param string   $color  a hexadecimal color identifier like "1F2C33"
 * @param int      $alpha
 *
 * @return int
 */
function get_color($image, $color, $alpha = 0) {
	$red = hexdec('0x'.substr($color, 0, 2));
	$green = hexdec('0x'.substr($color, 2, 2));
	$blue = hexdec('0x'.substr($color, 4, 2));

	return imagecolorexactalpha($image, $red, $green, $blue, $alpha);
}

/**
 * Get graphic theme based on user configuration.
 *
 * @return array
 */
function getUserGraphTheme() {
	$themes = DB::find('graph_theme', [
		'theme' => getUserTheme(CWebUser::$data)
	]);

	if ($themes) {
		return $themes[0];
	}

	return [
		'theme' => 'blue-theme',
		'textcolor' => '1F2C33',
		'highlightcolor' => 'E33734',
		'backgroundcolor' => 'FFFFFF',
		'graphcolor' => 'FFFFFF',
		'gridcolor' => 'CCD5D9',
		'maingridcolor' => 'ACBBC2',
		'gridbordercolor' => 'ACBBC2',
		'nonworktimecolor' => 'EBEBEB',
		'leftpercentilecolor' => '429E47',
		'righttpercentilecolor' => 'E33734',
		'colorpalette' => '1A7C11,F63100,2774A4,A54F10,FC6EA3,6C59DC,AC8C14,611F27,F230E0,5CCD18,BB2A02,5A2B57,'.
			'89ABF8,7EC25C,274482,2B5429,8048B4,FD5434,790E1F,87AC4D,E89DF4'
	];
}

/**
 * Custom error handler for PHP errors.
 *
 * @param int    $errno    Level of the error raised.
 * @param string $errstr   Error message.
 * @param string $errfile  Filename that the error was raised in.
 * @param int    $errline  Line number the error was raised in.
 *
 * @return bool
 */
function prs_err_handler($errno, $errstr, $errfile, $errline) {
	// Suppress errors when calling with error control operator @function_name().
	if ((error_reporting()
			& ~(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR)) == 0) {
		return true;
	}

	// Don't show the call to this handler function.
	error($errstr.' ['.CProfiler::getInstance()->formatCallStack().']', true);

	return false;
}

/**
 * Creates an array with all possible variations of time units.
 * For example: '14d' => ['1209600', '1209600s', '20160m', '336h', '14d', '2w']
 *
 * @param string|array $values
 *
 * @return array
 */
function getTimeUnitFilters($values) {
	if (is_array($values)) {
		$res = [];

		foreach ($values as $value) {
			$res = array_merge($res, getTimeUnitFilters($value));
		}

		return array_unique($res, SORT_STRING);
	}

	$simple_interval_parser = new \app\customs\zapi\common\parsers\CSimpleIntervalParser();

	if ($simple_interval_parser->parse($values) != \app\customs\zapi\common\parsers\CParser::PARSE_SUCCESS) {
		return [$values];
	}

	$sec = timeUnitToSeconds($values);

	$res = [$sec, $sec.'s'];

	if ($sec % SEC_PER_MIN == 0) {
		$res[] = floor($sec / SEC_PER_MIN).'m';
	}

	if ($sec % SEC_PER_HOUR == 0) {
		$res[] = floor($sec / SEC_PER_HOUR).'h';
	}

	if ($sec % SEC_PER_DAY == 0) {
		$res[] = floor($sec / SEC_PER_DAY).'d';
	}

	if ($sec % SEC_PER_WEEK == 0) {
		$res[] = floor($sec / SEC_PER_WEEK).'w';
	}

	return $res;
}

/**
 * Creates SQL filter to search all possible variations of time units.
 *
 * @param string       $field_name
 * @param string|array $values
 *
 * @return string
 */
function makeUpdateIntervalFilter($field_name, $values) {
	$filters = [];

	foreach (getTimeUnitFilters($values) as $filter) {
		$filter = str_replace("!", "!!", $filter);
		$filter = str_replace("%", "!%", $filter);
		$filter = str_replace("_", "!_", $filter);

		$filters[] = $field_name.' LIKE '. ZSqlHelper::zbxDbstr($filter).' ESCAPE '.ZSqlHelper::zbxDbstr('!');
		$filters[] = $field_name.' LIKE '.ZSqlHelper::zbxDbstr($filter.';%').' ESCAPE '.ZSqlHelper::zbxDbstr('!');
	}

	$res = $filters ? implode(' OR ', $filters) : '';

	if (count($filters) > 1) {
		$res = '('.$res.')';
	}

	return $res;
}

/**
 * Update profile with new time selector range.
 *
 * @param array       $options
 * @param string      $options['profileIdx']
 * @param int         $options['profileIdx2']
 * @param string|null $options['from']
 * @param string|null $options['to']
 */
function updateTimeSelectorPeriod(array $options) {
	if ($options['from'] !== null && $options['to'] !== null) {
		CProfile::update($options['profileIdx'].'.from', $options['from'], PROFILE_TYPE_STR, $options['profileIdx2']);
		CProfile::update($options['profileIdx'].'.to', $options['to'], PROFILE_TYPE_STR, $options['profileIdx2']);
	}
}

/**
 * Get profile stored 'from' and 'to'. If profileIdx is null then default values will be returned. If one of fields
 * not exist in $options array 'from' and 'to' value will be read from user profile. Calculates from_ts, to_ts.
 *
 * @param array $options  Array with period fields data: profileIdx, profileIdx2, from, to.
 *
 * @return array
 */
function getTimeSelectorPeriod(array $options) {
	$profileIdx = array_key_exists('profileIdx', $options) ? $options['profileIdx'] : null;
	$profileIdx2 = array_key_exists('profileIdx2', $options) ? $options['profileIdx2'] : null;

	if ($profileIdx === null) {
		$options['from'] = 'now-'.CSettingsHelper::get(CSettingsHelper::PERIOD_DEFAULT);
		$options['to'] = 'now';
	}
	elseif (!array_key_exists('from', $options) || !array_key_exists('to', $options)
			|| $options['from'] === null || $options['to'] === null) {
		$options['from'] = CProfile::get($profileIdx.'.from',
			'now-'.CSettingsHelper::get(CSettingsHelper::PERIOD_DEFAULT),
			$profileIdx2
		);
		$options['to'] = CProfile::get($profileIdx.'.to', 'now', $profileIdx2);
	}

	$range_time_parser = new CRangeTimeParser();

	$range_time_parser->parse($options['from']);
	$options['from_ts'] = $range_time_parser->getDateTime(true)->getTimestamp();
	$range_time_parser->parse($options['to']);
	$options['to_ts'] = $range_time_parser->getDateTime(false)->getTimestamp();

	return $options;
}

/**
 * Get array of action statuses available for defined time range. For incorrect "from" or "to" all actions will be set
 * to false.
 *
 * @param string $from      Relative or absolute time, cannot be null.
 * @param string $to        Relative or absolute time, cannot be null.
 *
 * @return array
 */
function getTimeselectorActions($from, $to): array {
	$ts_now = time();
	$parser = new CRangeTimeParser();
	$ts_from = ($parser->parse($from) !== CParser::PARSE_FAIL) ? $parser->getDateTime(true)->getTimestamp() : null;
	$ts_to = ($parser->parse($to) !== CParser::PARSE_FAIL) ? $parser->getDateTime(false)->getTimestamp() : null;
	$valid = ($ts_from !== null && $ts_to !== null);
	$parser->parse('now-'.CSettingsHelper::get(CSettingsHelper::MAX_PERIOD));
	$max_period = 1 + $ts_now - $parser->getDateTime(true)->getTimestamp();

	return [
		'can_zoomout' => ($valid && ($ts_to - $ts_from + 1 < $max_period)),
		'can_decrement' => ($valid && ($ts_from > 0)),
		'can_increment' => ($valid && ($ts_to < $ts_now - PRS_MIN_PERIOD))
	];
}

/**
 * Convert relative date range string to translated string. Function does not check is passed date range correct.
 *
 * @param string $from     Start date of date range.
 * @param string $to       End date of date range.
 *
 * @return string
 */
function relativeDateToText($from, $to) {
	$key = $from.':'.$to;
	$ranges = [
		'now-1d/d:now-1d/d' => t('zapi', 'Yesterday'),
		'now-2d/d:now-2d/d' => t('zapi', 'Day before yesterday'),
		'now-1w/d:now-1w/d' => t('zapi', 'This day last week'),
		'now-1w/w:now-1w/w' => t('zapi', 'Previous week'),
		'now-1M/M:now-1M/M' => t('zapi', 'Previous month'),
		'now-1y/y:now-1y/y' => t('zapi', 'Previous year'),
		'now/d:now/d' => t('zapi', 'Today'),
		'now/d:now' => t('zapi', 'Today so far'),
		'now/w:now/w' => t('zapi', 'This week'),
		'now/w:now' => t('zapi', 'This week so far'),
		'now/M:now/M' => t('zapi', 'This month'),
		'now/M:now' => t('zapi', 'This month so far'),
		'now/y:now/y' => t('zapi', 'This year'),
		'now/y:now' => t('zapi', 'This year so far')
	];

	if (array_key_exists($key, $ranges)) {
		return $ranges[$key];
	}

	if ($to === 'now') {
		$relative_time_parser = new CRelativeTimeParser();

		if ($relative_time_parser->parse($from) == CParser::PARSE_SUCCESS) {
			$tokens = $relative_time_parser->getTokens();

			if (count($tokens) == 1 && $tokens[0]['type'] == CRelativeTimeParser::PRS_TOKEN_OFFSET
					&& $tokens[0]['sign'] === '-') {
				$suffix = $tokens[0]['suffix'];
				$value = (int) $tokens[0]['value'];

				switch ($suffix) {
					case 's':
						if ($value < 60 || $value % 60 != 0) {
							return _n('Last %1$d second', 'Last %1$d seconds', $value);
						}
						$value /= 60;
						// break; is not missing here.

					case 'm':
						if ($value < 60 || $value % 60 != 0) {
							return _n('Last %1$d minute', 'Last %1$d minutes', $value);
						}
						$value /= 60;
						// break; is not missing here.

					case 'h':
						if ($value < 24 || $value % 24 != 0) {
							return _n('Last %1$d hour', 'Last %1$d hours', $value);
						}
						$value /= 24;
						// break; is not missing here.

					case 'd':
						return _n('Last %1$d day', 'Last %1$d days', $value);

					case 'M':
						return _n('Last %1$d month', 'Last %1$d months', $value);

					case 'y':
						return _n('Last %1$d year', 'Last %1$d years', $value);
				}
			}
		}
	}

	return $from.' – '.$to;
}

/**
 * Get human readable time period.
 *
 * @param int $seconds
 *
 * @return string
 */
function secondsToPeriod(int $seconds): string {
	$hours = floor($seconds / 3600);
	$seconds -= $hours * 3600;

	$minutes = floor($seconds / 60);
	$seconds -= $minutes * 60;

	$period = ($hours > 0) ? _n('%1$s hour', '%1$s hours', $hours) : '';

	if ($minutes > 0) {
		if ($period !== '') {
			$period .= ', ';
		}
		$period .= _n('%1$s minute', '%1$s minutes', $minutes);
	}

	if ($seconds > 0 || $period === '') {
		if ($period !== '') {
			$period .= ', ';
		}
		$period .= _n('%1$s second', '%1$s seconds', $seconds);
	}

	return $period;
}

/**
 * Generates UUID version 4.
 *
 * @param string $seed   String to be hashed as md5 and used as UUID body.
 *
 * @return string
 */
function generateUuidV4($seed = '') {
	$data = ($seed === '') ? random_bytes(16) : hex2bin(md5($seed));

	// Set head of 7th byte to 0100 (0100xxxx)
	$data[6] = chr(ord($data[6]) & 0x0f | 0x40);

	// Set head of 9th byte to 10 (10xxxxxx)
	$data[8] = chr(ord($data[8]) & 0x3f | 0x80);

	return bin2hex($data);
}

/**
 * Function returns predefined Leaflet Tile providers with parameters.
 *
 * @return array
 */
function getTileProviders(): array {
	return [
		'OpenStreetMap.Mapnik' => [
			'name' => 'OpenStreetMap Mapnik',
			'geomaps_tile_url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
			'geomaps_max_zoom' => '19',
			'geomaps_attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
		],
		'OpenTopoMap' => [
			'name' => 'OpenTopoMap',
			'geomaps_tile_url' => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
			'geomaps_max_zoom' => '17',
			'geomaps_attribution' => 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)'
		],
		'Stamen.TonerLite' => [
			'name' => 'Stamen Toner Lite',
			'geomaps_tile_url' => 'https://stamen-tiles-{s}.a.ssl.fastly.net/toner-lite/{z}/{x}/{y}{r}.png',
			'geomaps_max_zoom' => '20',
			'geomaps_attribution' => 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a> &mdash; Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
		],
		'Stamen.Terrain' => [
			'name' => 'Stamen Terrain',
			'geomaps_tile_url' => 'https://stamen-tiles-{s}.a.ssl.fastly.net/terrain/{z}/{x}/{y}{r}.png',
			'geomaps_max_zoom' => '18',
			'geomaps_attribution' => 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a> &mdash; Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
		],
		'USGS.USTopo' => [
			'name' => 'USGS US Topo',
			'geomaps_tile_url' => 'https://basemap.nationalmap.gov/arcgis/rest/services/USGSTopo/MapServer/tile/{z}/{y}/{x}',
			'geomaps_max_zoom' => '20',
			'geomaps_attribution' => 'Tiles courtesy of the <a href="https://usgs.gov/">U.S. Geological Survey</a>'
		],
		'USGS.USImagery' => [
			'name' => 'USGS US Imagery',
			'geomaps_tile_url' => 'https://basemap.nationalmap.gov/arcgis/rest/services/USGSImageryOnly/MapServer/tile/{z}/{y}/{x}',
			'geomaps_max_zoom' => '20',
			'geomaps_attribution' => 'Tiles courtesy of the <a href="https://usgs.gov/">U.S. Geological Survey</a>'
		]
	];
}



/**
 * Checks whether all $db_fields keys exists as $args keys.
 *
 * If $db_fields element value is given and corresponding $args is not then it is assigned to $args element.
 *
 * @param $dbFields
 * @param $args
 *
 * @return bool
 */
function check_db_fields($dbFields, &$args, $acceptNull = false)
{
	if (!is_array($args)) {
		return false;
	}

	foreach ($dbFields as $field => $def) {
		if (!isset($args[$field])) {
			if (!$acceptNull && is_null($def)) {
				return false;
			} else {
				$args[$field] = $def;
			}
		}
	}

	return true;
}


function validatePortNumberOrMacro($port)
{
	return (validatePortNumber($port) || validateUserMacro($port));
}

function validatePortNumber($port)
{
	return validateNumber($port, PRS_MIN_PORT_NUMBER, PRS_MAX_PORT_NUMBER);
}

function validateNumber($value, $min = null, $max = null)
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

function validateUserMacro($value)
{
	return ((new CUserMacroParser())->parse($value) == CParser::PARSE_SUCCESS);
}

/**
 * Returns true if both IDs are equal.
 *
 * @param $id1
 * @param $id2
 *
 * @return bool
 */
function idcmp($id1, $id2) {
    return (string) $id1 === (string) $id2;
}

function getThemes(): array {
	return [
		'blue-theme' => t('zapi', 'Blue'),
		'dark-theme' => t('zapi', 'Dark'),
		'hc-light' => t('zapi', 'High-contrast light'),
		'hc-dark' => t('zapi', 'High-contrast dark')
	];
}

function getLocales(): array {
	return [
		'en_GB' => ['name' => t('zapi', 'English (en_GB)'),	'display' => true],
		'en_US' => ['name' => t('zapi', 'English (en_US)'),	'display' => true],
		'bg_BG' => ['name' => t('zapi', 'Bulgarian (bg_BG)'),	'display' => false],
		'ca_ES' => ['name' => t('zapi', 'Catalan (ca_ES)'),	'display' => true],
		'zh_CN' => ['name' => t('zapi', 'Chinese (zh_CN)'),	'display' => true],
		'zh_TW' => ['name' => t('zapi', 'Chinese (zh_TW)'),	'display' => false],
		'cs_CZ' => ['name' => t('zapi', 'Czech (cs_CZ)'),	'display' => true],
		'nl_NL' => ['name' => t('zapi', 'Dutch (nl_NL)'),	'display' => false],
		'fi_FI' => ['name' => t('zapi', 'Finnish (fi_FI)'),	'display' => false],
		'fr_FR' => ['name' => t('zapi', 'French (fr_FR)'),	'display' => true],
		'ka_GE' => ['name' => t('zapi', 'Georgian (ka_GE)'),	'display' => false],
		'de_DE' => ['name' => t('zapi', 'German (de_DE)'),	'display' => true],
		'el_GR' => ['name' => t('zapi', 'Greek (el_GR)'),	'display' => false],
		'he_IL' => ['name' => t('zapi', 'Hebrew (he_IL)'),	'display' => true],
		'hu_HU' => ['name' => t('zapi', 'Hungarian (hu_HU)'),	'display' => false],
		'id_ID' => ['name' => t('zapi', 'Indonesian (id_ID)'),	'display' => false],
		'it_IT' => ['name' => t('zapi', 'Italian (it_IT)'),	'display' => true],
		'ko_KR' => ['name' => t('zapi', 'Korean (ko_KR)'),	'display' => true],
		'ja_JP' => ['name' => t('zapi', 'Japanese (ja_JP)'),	'display' => true],
		'lv_LV' => ['name' => t('zapi', 'Latvian (lv_LV)'),	'display' => false],
		'lt_LT' => ['name' => t('zapi', 'Lithuanian (lt_LT)'),	'display' => false],
		'nb_NO' => ['name' => t('zapi', 'Norwegian (nb_NO)'),	'display' => true],
		'fa_IR' => ['name' => t('zapi', 'Persian (fa_IR)'),	'display' => false],
		'pl_PL' => ['name' => t('zapi', 'Polish (pl_PL)'),	'display' => true],
		'pt_BR' => ['name' => t('zapi', 'Portuguese (pt_BR)'),	'display' => true],
		'pt_PT' => ['name' => t('zapi', 'Portuguese (pt_PT)'),	'display' => true],
		'ro_RO' => ['name' => t('zapi', 'Romanian (ro_RO)'),	'display' => false],
		'ru_RU' => ['name' => t('zapi', 'Russian (ru_RU)'),	'display' => true],
		'sk_SK' => ['name' => t('zapi', 'Slovak (sk_SK)'),	'display' => true],
		'es_ES' => ['name' => t('zapi', 'Spanish (es_ES)'),	'display' => false],
		'sv_SE' => ['name' => t('zapi', 'Swedish (sv_SE)'),	'display' => false],
		'tr_TR' => ['name' => t('zapi', 'Turkish (tr_TR)'),	'display' => true],
		'uk_UA' => ['name' => t('zapi', 'Ukrainian (uk_UA)'),	'display' => true],
		'vi_VN' => ['name' => t('zapi', 'Vietnamese (vi_VN)'),	'display' => true]
	];
}


/**
 * format
 *
 * @param  array  $data
 * @param  string $label
 * @param  string $value
 * @return array
 */
function one2twoAssociative(array $data, $label = 'label', $value = 'value')
{
	$rows = [];
	foreach ($data as $id => $name) {
		$rows[] = [
			$label => $name,
			$value => $id,
		];
	}
	return $rows;
}

/**
 * Return list of functions that can be used without /host/key reference.
 *
 * @return array
 */
function getStandaloneFunctions(): array {
	return ['date', 'dayofmonth', 'dayofweek', 'time', 'now'];
}

/**
 * Returns a list of functions that return a constant or random number.
 *
 * @return array
 */
function getFunctionsConstants(): array {
	return ['e', 'pi', 'rand'];
}


/**
 * Purpose: Replaces host in trigger expression.
 * nodata(/localhost/agent.ping, 5m)  =>  nodata(/localhost6/agent.ping, 5m)
 *
 * @param string $expression	full expression with host names and item keys
 * @param string $src_host
 * @param string $dst_host
 *
 * @return string
 */
function triggerExpressionReplaceHost(string $expression, string $src_host, string $dst_host): string {
	$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);

	if ($expression_parser->parse($expression) == CParser::PARSE_SUCCESS) {
		$hist_functions = $expression_parser->getResult()->getTokensOfTypes(
			[CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
		);
		$hist_function = end($hist_functions);
		do {
			$query_parameter = $hist_function['data']['parameters'][0];
			if ($query_parameter['data']['host'] === $src_host) {
				$expression = substr_replace($expression, '/'.$dst_host.'/'.$query_parameter['data']['item'],
					$query_parameter['pos'], $query_parameter['length']
				);
			}
		}
		while ($hist_function = prev($hist_functions));
	}

	return $expression;
}