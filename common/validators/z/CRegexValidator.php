<?php
namespace app\customs\zapi\common\validators\z;

class CRegexValidator extends CValidator
{

	/**
	 * Error message if the is not a string.
	 *
	 * @var string
	 */
	public $messageInvalid;

	/**
	 * Error message if the value is invalid
	 *
	 * @var string
	 */
	public $messageRegex;

	/**
	 * Check if regular expression is valid
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		if (!is_string($value) && !is_numeric($value)) {
			$this->error($this->messageInvalid);
			return false;
		}

		// escape '/' since Perseus server treats them as literal characters.
		$value = str_replace('/', '\/', $value);

		// validate through preg_match
		$error = false;

		set_error_handler(function ($errno, $errstr) use (&$error) {
			if ($errstr != '') {
				$error = $errstr;
			}
		});

		preg_match('/'.$value.'/', '');

		restore_error_handler();

		if ($error) {
			$this->error(
				$this->messageRegex,
				$value,
				str_replace('preg_match(): ', '', $error)
			);
			return false;
		}

		return true;
	}
}
