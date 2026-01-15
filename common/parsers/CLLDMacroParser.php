<?php
namespace app\customs\zapi\common\parsers;

/**
 * A parser for LLD macros.
 */
class CLLDMacroParser extends CParser {

	/**
	 * @param string    $source
	 * @param int       $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		if (!isset($source[$p]) || $source[$p] !== '{') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		if (!isset($source[$p]) || $source[$p] !== '#') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		for (; isset($source[$p]) && $this->isMacroChar($source[$p]); $p++) {
		}

		if ($p == $pos + 2 || !isset($source[$p]) || $source[$p] !== '}') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}

	/**
	 * Returns true if the char is allowed in the macro, false otherwise
	 *
	 * @param string    $c
	 *
	 * @return bool
	 */
	private function isMacroChar($c) {
		return (($c >= 'A' && $c <= 'Z') || $c == '.' || $c == '_' || ($c >= '0' && $c <= '9'));
	}
}
