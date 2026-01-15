<?php
namespace app\customs\zapi\common\validators\z;

/**
 * An interface for validators that must support partial array validation.
 *
 * Class CPartialValidatorInterface
 */
interface CPartialValidatorInterface {

	/**
	 * Validates a partial array. Some data may be missing from the given $array, then it will be taken from the
	 * full array.
	 *
	 * @abstract
	 *
	 * @param array $array
	 * @param array $fullArray
	 *
	 * @return bool
	 */
	public function validatePartial(array $array, array $fullArray);

}
