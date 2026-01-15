<?php
namespace app\customs\zapi\common\parsers;

/**
 * A parser for time period.
 */
class CTimePeriodParser extends CParser {

	private $options = [
		'usermacros' => false,
		'lldmacros' => false
	];

	private $user_macro_parser;
	private $lld_macro_parser;
	private $lld_macro_function_parser;

	public function __construct($options = []) {
		if (array_key_exists('usermacros', $options)) {
			$this->options['usermacros'] = $options['usermacros'];
		}
		if (array_key_exists('lldmacros', $options)) {
			$this->options['lldmacros'] = $options['lldmacros'];
		}

		if ($this->options['usermacros']) {
			$this->user_macro_parser = new CUserMacroParser();
		}
		if ($this->options['lldmacros']) {
			$this->lld_macro_parser = new CLLDMacroParser();
			$this->lld_macro_function_parser = new CLLDMacroFunctionParser();
		}
	}

	/**
	 * Parse the given period.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		if ($this->options['usermacros'] && $this->user_macro_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->user_macro_parser->getLength();
		}
		elseif ($this->options['lldmacros'] && $this->lld_macro_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->lld_macro_parser->getLength();
		}
		elseif ($this->options['lldmacros']
				&& $this->lld_macro_function_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->lld_macro_function_parser->getLength();
		}
		elseif (!self::parseTimePeriod($source, $p)) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Parse time period.
	 *
	 * @param string	$source
	 * @param int		$pos
	 *
	 * @return bool
	 */
	private static function parseTimePeriod($source, &$pos) {
		$pattern_wdays = '(?P<w_from>[1-7])(-(?P<w_till>[1-7]))?';
		$pattern_hours = '(?P<h_from>[0-9]{1,2}):(?P<m_from>[0-9]{2})-(?P<h_till>[0-9]{1,2}):(?P<m_till>[0-9]{2})';

		if (!preg_match('/^'.$pattern_wdays.','.$pattern_hours.'/', substr($source, $pos), $matches)) {
			return false;
		}

		if (($matches['w_till'] !== '' && $matches['w_from'] > $matches['w_till'])
				|| $matches['m_from'] > 59 || $matches['m_till'] > 59) {
			return false;
		}

		$time_from = $matches['h_from'] * SEC_PER_HOUR + $matches['m_from'] * SEC_PER_MIN;
		$time_till = $matches['h_till'] * SEC_PER_HOUR + $matches['m_till'] * SEC_PER_MIN;

		if ($time_from >= $time_till || $time_till > 24 * SEC_PER_HOUR) {
			return false;
		}

		$pos += strlen($matches[0]);

		return true;
	}
}
