<?php
namespace app\customs\zapi\common\parsers;

/**
 * A parser for IPv4 address.
 */
class CIPv4Parser extends CParser {

	/**
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		if (!preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)\.([0-9]+)/', substr($source, $pos), $matches)) {
			return self::PARSE_FAIL;
		}

		for ($i = 1; $i <= 4; $i++) {
			if (strlen($matches[$i]) > 3 || $matches[$i] > 255) {
				return self::PARSE_FAIL;
			}
		}

		$this->match = $matches[0];
		$this->length = strlen($this->match);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}
}
