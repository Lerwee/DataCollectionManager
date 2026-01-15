<?php
namespace app\customs\zapi\common\parsers;

/**
 * A parser for reference macros like $1-$9.
 */
class CReferenceParser extends CParser {

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

		if (!isset($source[$p]) || $source[$p] !== '$') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		if (!isset($source[$p]) || $source[$p] < '1' || $source[$p] > '9') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}
}
