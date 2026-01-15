<?php
namespace app\customs\zapi\common\validators\z\object;


use app\customs\zapi\common\validators\z\CPartialValidatorInterface;
use app\customs\zapi\common\validators\z\CValidator;

class CUpdateDiscoveredValidator extends CValidator implements CPartialValidatorInterface {

	/**
	 * Fields that can be updated for discovered objects. If no fields are set, updating discovered objects
	 * will be forbidden.
	 *
	 * @var array
	 */
	public $allowed = [];

	/**
	 * Error message in case updating discovered objects is totally forbidden.
	 *
	 * @var string
	 */
	public $messageAllowed;

	/**
	 * Error message in case we can update only certain fields for discovered objects.
	 *
	 * @var string
	 */
	public $messageAllowedField;

	/**
	 * Checks that only the allowed fields for discovered objects are updated.
	 *
	 * The object must have the "flags" property defined.
	 *
	 * @param array $object
	 *
	 * @return bool
	 */
	public function validate($object) {
		$allowedFields = array_flip($this->allowed);

		if ($object['flags'] == PRS_FLAG_DISCOVERY_CREATED) {
			// ignore the "flags" field
			unset($object['flags']);

			foreach ($object as $field => $value) {
				if (!isset($allowedFields[$field])) {
					// if we allow to update some fields, throw an error referencing a specific field
					// we check if there is more than 1 field, because the PK must always be present
					if (count($allowedFields) > 1) {
						$this->error($this->messageAllowedField, $field);
					}
					else {
						$this->error($this->messageAllowed);
					}

					return false;
				}
			}
		}

		return true;
	}

	public function validatePartial(array $array, array $fullArray) {
		$array['flags'] = $fullArray['flags'];

		return $this->validate($array);
	}

}
