<?php
namespace app\customs\zapi\common\parsers;

/**
 * A parser for function id macros.
 */
class CFunctionIdParser extends CParser {

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

		for (; isset($source[$p]) && ($source[$p] >= '0' && $source[$p] <= '9'); $p++) {
			// Code is not missing here.
		}

		if ($p == $pos + 1 || !isset($source[$p]) || $source[$p] !== '}') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		$functionid = substr($source, $pos + 1, $p - $pos - 2);

		if (bccomp(1, $functionid) > 0 ||  bccomp($functionid, PRS_DB_MAX_ID) > 0) {
			return CParser::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}
}
