<?php declare(strict_types = 0);
namespace app\customs\zapi\common\validators\z;


/**
 * Class for validating math functions.
 */
class CMathFunctionValidator extends CValidator {

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'parameters' => []  Number of required parameters of known math functions.
	 *
	 * @var array
	 */
	private $options = [
		'parameters' => []
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;
	}

	/**
	 * Validate math function.
	 *
	 * @param array $token  A token of CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION type.
	 *
	 * @return bool
	 */
	public function validate($token) {
		if (!array_key_exists($token['data']['function'], $this->options['parameters'])) {
            $this->setError(t('zapi', 'unknown function "{function}"', ['function' => $token['data']['function']]));

			return false;
		}

		$num_parameters = count($token['data']['parameters']);

		foreach ($this->options['parameters'][$token['data']['function']] as $rule) {
			$is_valid = true;

			if (array_key_exists('count', $rule)) {
				$is_valid = $num_parameters == $rule['count'];
			}

			if ($is_valid && array_key_exists('min', $rule)) {
				$is_valid = $num_parameters >= $rule['min'];

				if ($is_valid && array_key_exists('step', $rule)) {
					$is_valid = ($num_parameters - $rule['min']) % $rule['step'] == 0;
				}
			}

			if ($is_valid && array_key_exists('max', $rule)) {
				$is_valid = $num_parameters <= $rule['max'];
			}

			if ($is_valid) {
				return true;
			}
		}

        $this->setError(t('zapi', 'invalid number of parameters in function "{function}"', ['function' => $token['data']['function']]));

		return false;
	}
}
