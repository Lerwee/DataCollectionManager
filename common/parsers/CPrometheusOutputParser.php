<?php
namespace app\customs\zapi\common\parsers;

/**
 * A parser for Prometheus output.
 */
class CPrometheusOutputParser extends CParser {

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
	 * Parse the given source string.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		if (!$this->parseLabelName($source, $p)) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Parse label names. It must follow the [a-zA-Z_][a-zA-Z0-9_]* regular expression. User macros and LLD macros
	 * are allowed.
	 *
	 * @param string $source  [IN]      Source string that needs to be parsed.
	 * @param int    $pos     [IN/OUT]  Position offset.
	 *
	 * @return bool
	 */
	private function parseLabelName($source, &$pos) {
		if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*/', substr($source, $pos), $matches)) {
			$pos += strlen($matches[0]);

			return true;
		}
		elseif ($this->options['usermacros'] && $this->user_macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->user_macro_parser->getLength();

			return true;
		}
		elseif ($this->options['lldmacros'] && $this->lld_macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->lld_macro_parser->getLength();

			return true;
		}
		elseif ($this->options['lldmacros']
				&& $this->lld_macro_function_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->lld_macro_function_parser->getLength();

			return true;
		}

		return false;
	}
}
