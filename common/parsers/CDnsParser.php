<?php
namespace app\customs\zapi\common\parsers;

/**
 * A parser for DNS address.
 */
class CDnsParser extends CParser {

	/**
	 * @var CUserMacroParser
	 */
	private $user_macro_parser;

	/**
	 * @var CLLDMacroParser
	 */
	private $lld_macro_parser;

	/**
	 * @var CLLDMacroFunctionParser
	 */
	private $lld_macro_function_parser;

	/**
	 * @var CMacroParser
	 */
	private $macro_parser;

	/**
	 * Supported options:
	 *   'usermacros' => true  Enabled support of user macros;
	 *   'lldmacros' => true   Enabled support of LLD macros;
	 *   'macros' => true      Enabled support of all macros;
	 *   'macros' => []        Allows array with list of macros. Empty array or false means no macros supported.
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false,
		'macros' => []
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		if (array_key_exists('usermacros', $options)) {
			$this->options['usermacros'] = $options['usermacros'];
		}
		if (array_key_exists('lldmacros', $options)) {
			$this->options['lldmacros'] = $options['lldmacros'];
		}
		if (array_key_exists('macros', $options)) {
			$this->options['macros'] = $options['macros'];
		}

		if ($this->options['usermacros']) {
			$this->user_macro_parser = new CUserMacroParser();
		}
		if ($this->options['lldmacros']) {
			$this->lld_macro_parser = new CLLDMacroParser();
			$this->lld_macro_function_parser = new CLLDMacroFunctionParser();
		}
		if ($this->options['macros']) {
			$this->macro_parser = new CMacroParser(['macros' => $this->options['macros']]);
		}
	}

	/**
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		while (isset($source[$p])) {
			if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*(\.[A-Za-z0-9_-]+)*\.?/', substr($source, $p), $matches)) {
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
			elseif ($this->options['macros'] && $this->macro_parser->parse($source, $p) != self::PARSE_FAIL) {
				$p += $this->macro_parser->getLength();
			}
			else {
				break;
			}
		}

		$length = $p - $pos;

		if ($length == 0 || $length > 255) {
			return self::PARSE_FAIL;
		}

		$this->length = $length;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}
}
