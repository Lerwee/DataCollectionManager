<?php declare(strict_types = 0);
namespace app\customs\zapi\common\parsers;

/**
 *  A parser for function macros like "{?<trigger expression>}".
 */
class C10ExpressionMacroParser extends CParser {

	/**
	 * @var C10TriggerExpression
	 */
	protected $trigger_expression_parser;

	/**
	 * Set up necessary parsers.
	 */
	public function __construct() {
		$this->trigger_expression_parser = new C10TriggerExpression([
			'host_macro' => ['{HOST.HOST}']
		]);
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

		if (!isset($source[$p]) || substr($source, $p, 2) !== '{?') {
			$this->errorPos($source, $p);

			return CParser::PARSE_FAIL;
		}
		$p += 2;

		$this->trigger_expression_parser->parse(substr($source, $p));

		if ($this->trigger_expression_parser->error_type !== C10TriggerExpression::ERROR_UNPARSED_CONTENT) {
			$this->errorPos($source, $p + $this->trigger_expression_parser->error_pos);

			return CParser::PARSE_FAIL;
		}
		$p += $this->trigger_expression_parser->error_pos;

		if (!isset($source[$p]) || $source[$p] !== '}') {
			$this->errorPos($source, $p);

			return CParser::PARSE_FAIL;
		}
		$p++;

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? CParser::PARSE_SUCCESS_CONT : CParser::PARSE_SUCCESS);
	}
}
