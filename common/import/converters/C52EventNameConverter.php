<?php
namespace app\customs\zapi\common\import\converters;

use app\customs\zapi\common\import\converters\C52TriggerExpressionConverter;
use app\customs\zapi\common\parsers\C10TriggerExpression;
use app\customs\zapi\common\parsers\C10ExpressionMacroParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\results\C10TriggerExprParserResult;


/**
 * Expression macros converter in event name field.
 */
class C52EventNameConverter extends C52TriggerExpressionConverter {

	public function __construct() {
		parent::__construct();

		$this->parser = new C10TriggerExpression(['host_macro' => ['{HOST.HOST}']]);
	}

	/**
	 * Convert event name.
	 *
	 * @param string $event_name  Event name to convert.
	 *
	 * @return string
	 */
	public function convert($event_name) {
		$expression_macros = $this->getExpressionMacros($event_name);

		krsort($expression_macros, SORT_NUMERIC);

		foreach ($expression_macros as $pos => $expression_macro) {
			if (($this->parser->parse(substr($expression_macro, 2, -1), 0)) !== CParser::PARSE_FAIL) {
				$functions = $this->parser->result->getTokensByType(
					C10TriggerExprParserResult::TOKEN_TYPE_FUNCTION_MACRO
				);

				foreach (array_reverse($functions) as $function) {
					if ($function['data']['host'] === '{HOST.HOST}' || $function['data']['host'] === '{HOST.HOST1}') {
						$function['data']['host'] = '';
					}
					[$new_expr] = $this->convertFunction($function['data'], '', '');
					$start = $pos + $function['pos'] + 2;
					$event_name = substr_replace($event_name, $new_expr, $start, strlen($function['value']));
				}
			}
		}

		return $event_name;
	}

	/**
	 * Extract expression macros with position in given string.
	 *
	 * @param string $value  The string to search in.
	 *
	 * @return array
	 */
	private function getExpressionMacros(string $value): array {
		$expr_macro = new C10ExpressionMacroParser();
		$expression_macros = [];
		$p = 0;

		while (isset($value[$p])) {
			if (substr($value, $p, 2) !== '{?') {
				$p++;

				continue;
			}

			if ($expr_macro->parse($value, $p) !== CParser::PARSE_FAIL) {
				$expression_macros[$p] = $expr_macro->getMatch();
				$p += $expr_macro->getLength();
			}
			else {
				$p++;
			}
		}

		return $expression_macros;
	}
}
