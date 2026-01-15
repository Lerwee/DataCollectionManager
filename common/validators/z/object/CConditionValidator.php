<?php
namespace app\customs\zapi\common\validators\z\object;

use app\customs\zapi\common\parsers\CConditionFormula;
use app\customs\zapi\common\validators\z\CValidator;

class CConditionValidator extends CValidator {

	/**
	 * Error message if the formula is not given.
	 *
	 * @var string
	 */
	public $messageMissingFormula;

	/**
	 * Error message if the formula is invalid.
	 *
	 * @var string
	 */
	public $messageInvalidFormula;

	/**
	 * Error message if the formula contains a condition that is not defined in the "conditions" array.
	 *
	 * @var string
	 */
	public $messageMissingCondition;

	/**
	 * Error message if the "conditions" array contains a condition that is not used in the formula.
	 *
	 * @var string
	 */
	public $messageUnusedCondition;

	/**
	 * Error message if several triggers are compared with "and".
	 *
	 * @var string
	 */
	public $messageAndWithSeveralTriggers;

	/**
	 * Validates the given condition formula and checks if the given conditions match the formula.
	 *
	 * @param array $object
	 *
	 * @return bool
	 */
	public function validate($object) {
		if ($object['evaltype'] == CONDITION_EVAL_TYPE_AND) {
			// get triggers count in formula
			$trigger_count = 0;
			foreach ($object['conditions'] as $condition) {
				if (array_key_exists('conditiontype', $condition) && array_key_exists('operator', $condition)
						&& $condition['conditiontype'] == PRS_CONDITION_TYPE_TRIGGER
						&& $condition['operator'] == CONDITION_OPERATOR_EQUAL) {
					$trigger_count++;
				}
			}

			// check if multiple triggers are compared with AND
			if ($trigger_count > 1) {
				$this->error($this->messageAndWithSeveralTriggers);

				return false;
			}
		}

		// validate only custom expressions
		if ($object['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
			return true;
		}

		if (!array_key_exists('formula', $object)) {
			$this->error($this->messageMissingFormula);

			return false;
		}

		// check if the formula is valid
		$parser = new CConditionFormula();
		if (!$parser->parse($object['formula'])) {
			$this->error($this->messageInvalidFormula, $object['formula'], $parser->error);

			return false;
		}

		// check that all conditions used in the formula are defined in the "conditions" array
		$conditions = prs_toHash($object['conditions'], 'formulaid');
		$constants = array_unique(prs_objectValues($parser->constants, 'value'));
		foreach ($constants as $constant) {
			if (!array_key_exists($constant, $conditions)) {
				$this->error($this->messageMissingCondition, $constant, $object['formula']);

				return false;
			}

			unset($conditions[$constant]);
		}

		// check that the "conditions" array has no unused conditions
		if ($conditions) {
			$condition = reset($conditions);
			$this->error($this->messageUnusedCondition, $condition['formulaid'], $object['formula']);

			return false;
		}

		return true;
	}
}
