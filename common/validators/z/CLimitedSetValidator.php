<?php
namespace app\customs\zapi\common\validators\z;

class CLimitedSetValidator extends CValidator {

	/**
	 * Allowed values.
	 *
	 * @var array
	 */
	public $values = [];

	/**
	 * Error message if the value is invalid or is not of an acceptable type.
	 *
	 * @var string
	 */
	public $messageInvalid = null;

	/**
	 * Checks if the given value belongs to some set.
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		if (!is_string($value) && !is_int($value)) {
			if ($this->messageInvalid !== null) {
				$this->error($this->messageInvalid, $this->stringify($value));
			}

			return false;
		}

		$values = array_flip($this->values);

		if (!isset($values[$value])) {
			if ($this->messageInvalid !== null) {
				$this->error($this->messageInvalid, $value);
			}

			return false;
		}

		return true;
	}

}
