<?php
namespace app\customs\zapi\common\parsers;

/**
 * A parser for simple intervals.
 */
class CSimpleIntervalParser extends CParser {

	private $options = [
		'usermacros' => false,
		'lldmacros' => false,
		'negative' => false,
		'with_year' => false
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
		if (array_key_exists('negative', $options)) {
			$this->options['negative'] = $options['negative'];
		}
		if (array_key_exists('with_year', $options)) {
			$this->options['with_year'] = $options['with_year'];
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
	 * Parse the given source string.
	 *
	 * 0..N[smhdwMy]|{$M}|{#M}|{{#M}.func()}
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$minus = $this->options['negative'] ? '-?' : '';
		$p = $pos;

		if (preg_match(
			'/^('.$minus.'(0|[1-9][0-9]*)['.
			($this->options['with_year'] ? PRS_TIME_SUFFIXES_WITH_YEAR : PRS_TIME_SUFFIXES).']?)/',
			substr($source, $p), $matches)
		) {
			$p += strlen($matches[0]);
		}
		elseif ($this->options['usermacros'] && $this->user_macro_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->user_macro_parser->getLength();
		}
		elseif ($this->options['lldmacros'] && $this->lld_macro_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->lld_macro_parser->getLength();
		}
		elseif ($this->options['lldmacros']
				&& $this->lld_macro_function_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->lld_macro_function_parser->getLength();
		}
		else {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}
}
