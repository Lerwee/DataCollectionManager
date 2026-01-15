<?php
namespace app\customs\zapi\common\parsers\results;

/**
 * Class for storing the result returned by a parser.
 */
class CParserResult {

	/**
	 * Source string.
	 *
	 * @var	string
	 */
	public $source;

	/**
	 * Parsed string.
	 *
	 * @var string
	 */
	public $match;

	/**
	 * Starting position of the matched string in the source string.
	 *
	 * @var
	 */
	public $pos;

	/**
	 * Length of the matched string in bytes.
	 *
	 * @var int
	 */
	public $length;
}
