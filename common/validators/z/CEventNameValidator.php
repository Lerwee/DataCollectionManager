<?php
namespace app\customs\zapi\common\validators\z;


use app\customs\zapi\common\parsers\CExpressionMacroFunctionParser;
use app\customs\zapi\common\parsers\CExpressionMacroParser;
use app\customs\zapi\common\parsers\CParser;

/**
 * Validate only trigger event name field expression macros, other macros will be ignored.
 */
class CEventNameValidator extends CValidator {

	/**
	 * Returns true if the given $value is valid, or set's an error and returns false otherwise.
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		$p = 0;
		$expr_macro = new CExpressionMacroParser([
			'usermacros' => true,
			'lldmacros' => true,
			'host_macro_n' => true,
			'empty_host' => true
		]);
		$expr_func_macro = new CExpressionMacroFunctionParser([
			'usermacros' => true,
			'lldmacros' => true,
			'host_macro_n' => true,
			'empty_host' => true
		]);

		while (isset($value[$p])) {
			if (substr($value, $p, 2) !== '{?') {
				$p++;

				continue;
			}

			if ($expr_func_macro->parse($value, $p) === CParser::PARSE_FAIL) {
				if ($expr_macro->parse($value, $p) === CParser::PARSE_FAIL) {
					$this->setError($expr_macro->getError());

					return false;
				}
				else {
					$p += $expr_macro->getLength();
				}
			}
			else {
				$p += $expr_func_macro->getLength();
			}
		}

		return true;
	}
}
