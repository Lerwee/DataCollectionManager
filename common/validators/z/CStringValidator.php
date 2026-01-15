<?php
namespace app\customs\zapi\common\validators\z;

class CStringValidator extends CValidator {

	/**
	 * If set to false, the string cannot be empty.
	 *
	 * @var bool
	 */
	public $empty = false;

	/**
	 * Maximum string length.
	 *
	 * @var int
	 */
	public $maxLength;

	/**
	 * Regex to match the string against.
	 *
	 * @var string
	 */
	public $regex;

	/**
	 * Error message if the string is empty.
	 *
	 * @var string
	 */
	public $messageEmpty;

	/**
	 * Error message if the string is too long.
	 *
	 * @var string
	 */
	public $messageMaxLength;

	/**
	 * Error message if the string doesn't match the regex.
	 *
	 * @var string
	 */
	public $messageRegex;

	/**
	 * Error message if not a string, integer or decimal is provided
	 *
	 * @var string
	 */
	public $messageInvalid;

	/**
	 * Checks if the given string is:
	 * - empty
	 * - not too long
	 * - matches a certain regex
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		if (!(is_string($value) || is_numeric($value))) {
			$this->error($this->messageInvalid, $this->stringify($value));

			return false;
		}

		if (prs_empty($value)) {
			if ($this->empty) {
				return true;
			}
			else {
				$this->error($this->messageEmpty);

				return false;
			}
		}

		if ($this->maxLength && mb_strlen($value) > $this->maxLength) {
			$this->error($this->messageMaxLength, $value, $this->maxLength);

			return false;
		}

		if ($this->regex && !prs_empty($value) && !preg_match($this->regex, $value)) {
			$this->error($this->messageRegex, $value);

			return false;
		}

		return true;
	}
}
