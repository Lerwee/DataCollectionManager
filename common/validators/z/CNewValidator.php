<?php
namespace app\customs\zapi\common\validators\z;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\CCuid;
use app\customs\zapi\common\parsers\CAbsoluteTimeParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CRangeTimeParser;
use app\customs\zapi\common\parsers\CSimpleIntervalParser;
use app\customs\zapi\common\parsers\CTimePeriodsParser;
use app\customs\zapi\common\parsers\CValidationRule;

class CNewValidator {

	private $rules;
	private $input = [];
	private $output = [];
	private $errors = [];
	private $errorsFatal = [];

	/**
	 * Parser for validation rules.
	 *
	 * @var CValidationRule
	 */
	private $validationRuleParser;

	/**
	 * Parser for range date/time.
	 *
	 * @var CRangeTimeParser
	 */
	private $range_time_parser;

	/**
	 * A parser for a list of time periods separated by a semicolon.
	 *
	 * @var CTimePeriodsParser
	 */
	private $time_periods_parser;

	public function __construct(array $input, array $rules) {
		$this->input = $input;
		$this->rules = $rules;
		$this->validationRuleParser = new CValidationRule();

		$this->validate();
	}

	/**
	 * Returns true if the given $value is valid, or set's an error and returns false otherwise.
	 */
	private function validate() {
		foreach ($this->rules as $field => $rule) {
			$this->validateField($field, $rule);

			if (array_key_exists($field, $this->input)) {
				$this->output[$field] = $this->input[$field];
			}
		}
	}

	private function validateField($field, $rules): bool {
		$rules = $this->validationRuleParser->parse($rules);

		if ($rules === false) {
			$this->addError(true, $this->validationRuleParser->getError());

			return false;
		}

		$fatal = array_key_exists('fatal', $rules);
		$flags = array_key_exists('flags', $rules) ? $rules['flags'] : 0x00;

		if (!array_key_exists($field, $this->input)) {
			if (array_key_exists('required', $rules)) {
                $this->addError($fatal, t('zapi', 'Field "{name}" is mandatory', ['name' => $field]));
				return false;
			}

			return true;
		}

		unset($rules['fatal'], $rules['flags'], $rules['required']);

		foreach ($rules as $rule => $params) {
			$value = $this->input[$field];

			switch ($rule) {
				case 'not_empty':
					if ($value === '') {
                        $this->addError($fatal, t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  $field,
                            'error' => t('zapi', 'cannot be empty')
                        ]));

						return false;
					}
					break;

				case 'json':
					if (!is_string($value) || json_decode($value) === null) {
                        $this->addError($fatal, t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  $field,
                            'error' => t('zapi', 'SON string is expected')
                        ]));
						return false;
					}
					break;

				case 'in':
					if ((!is_string($value) && !is_numeric($value)) || !in_array($value, $params)) {
                        $message = is_scalar($value)
                            ? t('zapi', 'Incorrect value "{value}" for "{attribute}" field.', [
                                'attribute' =>  $field,
                                'value' => $value
                            ])
                            : t('zapi', 'Incorrect value for "{attribute}" field.', ['attribute' => $field]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'int32':
					if ((!is_string($value) && !is_numeric($value)) || !self::is_int32($value)) {
                        $message = is_scalar($value)
                            ? t('zapi', 'Incorrect value "{value}" for "{attribute}" field.', [
                                'attribute' =>  $field,
                                'value' => $value
                            ])
                            : t('zapi', 'Incorrect value for "{attribute}" field.', ['attribute' => $field]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'uint64':
					if ((!is_string($value) && !is_numeric($value)) || !self::is_uint64($value)) {
                        $message = is_scalar($value)
                            ? t('zapi', 'Incorrect value "{value}" for "{attribute}" field.', [
                                'attribute' =>  $field,
                                'value' => $value
                            ])
                            : t('zapi', 'Incorrect value for "{attribute}" field.', ['attribute' => $field]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'id':
					if ((!is_string($value) && !is_numeric($value)) || !self::is_id($value)) {
                        $message = is_scalar($value)
                            ? t('zapi', 'Incorrect value "{value}" for "{attribute}" field.', [
                                'attribute' =>  $field,
                                'value' => $value
                            ])
                            : t('zapi', 'Incorrect value for "{attribute}" field.', ['attribute' => $field]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'array_id':
					if (!is_array($value) || !$this->is_array_id($value)) {
                        $message = is_scalar($value)
                            ? t('zapi', 'Incorrect value "{value}" for "{attribute}" field.', [
                                'attribute' =>  $field,
                                'value' => $value
                            ])
                            : t('zapi', 'Incorrect value for "{attribute}" field.', ['attribute' => $field]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'array':
					if (!is_array($value)) {
                        $message = is_scalar($value)
                            ? t('zapi', 'Incorrect value "{value}" for "{attribute}" field.', [
                                'attribute' =>  $field,
                                'value' => $value
                            ])
                            : t('zapi', 'Incorrect value for "{attribute}" field.', ['attribute' => $field]);
                        $this->addError($fatal, $message);

						return false;
					}
					break;

				case 'array_db':
					if (!is_array($value) || !$this->is_array_db($value, $params['table'], $params['field'], $flags)) {
                        $message = is_scalar($value)
                            ? t('zapi', 'Incorrect value "{value}" for "{attribute}" field.', [
                                'attribute' =>  $field,
                                'value' => $value
                            ])
                            : t('zapi', 'Incorrect value for "{attribute}" field.', ['attribute' => $field]);
                        $this->addError($fatal, $message);

						return false;
					}
					break;

				case 'ge':
					if ((!is_string($value) && !is_numeric($value)) || !self::is_int32($value) || $value < $params) {
                        $message = t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  $field,
                            'error' => t('zapi', 'value must be no less than "{value}"', ['value' => $params])
                        ]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'le':
					if ((!is_string($value) && !is_numeric($value)) || !self::is_int32($value) || $value > $params) {
                        $message = t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  $field,
                            'error' => t('zapi', 'value must be no greater than "{value}"', ['value' => $params])
                        ]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'db':
					$table_fields = DB::getSchema($params['table'])['fields'];

					if ((!is_string($value) && !is_numeric($value))
							|| !$this->check_db_value($table_fields[$params['field']], $value, $flags)) {
                        $message = is_scalar($value)
                            ? t('zapi', 'Incorrect value "{value}" for "{attribute}" field.', [
                                'attribute' =>  $field,
                                'value' => $value
                            ])
                            : t('zapi', 'Incorrect value for "{attribute}" field.', ['attribute' => $field]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'range_time':
					if ($this->range_time_parser === null) {
						$this->range_time_parser = new CRangeTimeParser();
					}

					if (!is_string($value) || $this->range_time_parser->parse($value) != CParser::PARSE_SUCCESS) {
                        $message = t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  $field,
                            'error' => t('zapi', 'a time is expected')
                        ]);
                        $this->addError($fatal, $message);

						return false;
					}
					break;

				case 'abs_date':
					$absolute_time_parser = new CAbsoluteTimeParser();

					$has_errors = !is_string($value)
						|| $absolute_time_parser->parse($value) != CParser::PARSE_SUCCESS
						|| $absolute_time_parser->getDateTime(true)->format('H:i:s') !== '00:00:00';

					if ($has_errors) {
                        $message = t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  $field,
                            'error' => t('zapi', 'a date is expected')
                        ]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'abs_time':
					$absolute_time_parser = new CAbsoluteTimeParser();

					$has_errors = !is_string($value) || $absolute_time_parser->parse($value) != CParser::PARSE_SUCCESS;

					if ($has_errors) {
                        $message = t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  $field,
                            'error' => t('zapi', 'a time is expected')
                        ]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'time_periods':
					if ($this->time_periods_parser === null) {
						$this->time_periods_parser = new CTimePeriodsParser(['usermacros' => true]);
					}

					if (!is_string($value) || $this->time_periods_parser->parse($value) != CParser::PARSE_SUCCESS) {
                        $message = t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  $field,
                            'error' => t('zapi', 'a time period is expected')
                        ]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'time_unit':
					if (is_string($value) || is_numeric($value)) {
						$result = $this->isTimeUnit($value, $params);
						$error_message = $result['is_valid'] ? null : $result['error'];
					}
					else {
						$error_message = t('zapi', 'a time unit is expected');
					}

					if ($error_message !== null) {
                        $message = t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  $field,
                            'error' => $error_message
                        ]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'rgb':
					if (!is_string($value) || preg_match('/^[A-F0-9]{6}$/', $value) == 0) {
                        $message = t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  $field,
                            'error' => t('zapi', 'a hexadecimal color code (6 symbols) is expected')
                        ]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'string':
					if (!is_string($value) && !is_numeric($value)) {
                        $message = t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  $field,
                            'error' => t('zapi', 'a character string is expected')
                        ]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'bool':
					if (!is_bool($value)) {
                        $message = t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  $field,
                            'error' => t('zapi', 'a boolean value is expected')
                        ]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				case 'cuid':
					if (!self::isCuid($value)) {
                        $message = t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  $field,
                            'error' => t('zapi', 'CUID is expected')
                        ]);
                        $this->addError($fatal, $message);
						return false;
					}
					break;

				default:
					// Do not translate.
					$this->addError($fatal, 'Invalid validation rule "'.$rule.'".');

					return false;
			}
		}

		return true;
	}

	public static function is_id($value) {
		if (!preg_match('/^'.PRS_PREG_INT.'$/', $value)) {
			return false;
		}

		return (bccomp($value, '0') >= 0 && bccomp($value, PRS_DB_MAX_ID) <= 0);
	}

	public static function is_int32($value) {
		if (!preg_match('/^'.PRS_PREG_INT.'$/', $value)) {
			return false;
		}

		return ($value >= PRS_MIN_INT32 && $value <= PRS_MAX_INT32);
	}

	public static function is_uint64($value) {
		if (!preg_match('/^'.PRS_PREG_INT.'$/', $value)) {
			return false;
		}

		return ($value >= 0 && bccomp($value, PRS_MAX_UINT64) <= 0);
	}

	public static function isCuid($value): bool {
		if (!is_string($value)) {
			return false;
		}

		if (!CCuid::checkLength($value)) {
			return false;
		}

		if (!CCuid::isCuid($value)) {
			return false;
		}

		return true;
	}

	/**
	 * Validate value against DB schema.
	 *
	 * @param array  $field_schema            Array of DB schema.
	 * @param string $field_schema['type']    Type of DB field.
	 * @param string $field_schema['length']  Length of DB field.
	 * @param string $value                   [IN/OUT] IN - input value, OUT - changed value according to flags.
	 * @param int    $flags                   Validation flags.
	 *
	 * @return bool
	 */
	private function check_db_value($field_schema, &$value, $flags) {
		switch ($field_schema['type']) {
			case DB::FIELD_TYPE_ID:
				return self::is_id($value);

			case DB::FIELD_TYPE_INT:
				return self::is_int32($value);

			case DB::FIELD_TYPE_CHAR:
				if ($flags & P_CRLF) {
					$value = CRLFtoLF($value);
				}

				return (mb_strlen($value) <= $field_schema['length']);

			case DB::FIELD_TYPE_NCLOB:
			case DB::FIELD_TYPE_TEXT:
				if ($flags & P_CRLF) {
					$value = CRLFtoLF($value);
				}

				// TODO: check length
				return true;

			default:
				return false;
		}
	}

	private function is_array_id(array $values) {
		foreach ($values as $value) {
			if (!is_string($value) || !self::is_id($value)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate array of string values against DB schema.
	 *
	 * @param array $values  [IN/OUT] IN - input values, OUT - changed values according to flags.
	 * @param string $table  DB table name.
	 * @param string $field  DB field name.
	 * @param int $flags     Validation flags.
	 *
	 * @return bool
	 */
	private function is_array_db(array &$values, $table, $field, $flags) {
		$table_schema = DB::getSchema($table);

		foreach ($values as &$value) {
			if (!is_string($value) || !$this->check_db_value($table_schema['fields'][$field], $value, $flags)) {
				return false;
			}
		}
		unset($value);

		return true;
	}

	/**
	 * Validate a configuration value. Use simple interval parser to parse the string, convert to seconds and check
	 * if the value is in between given min and max values. In some cases it's possible to enter 0, or even 0s or 0d.
	 * If the value is incorrect, set an error.
	 *
	 * @param string $value                  Value to parse and validate.
	 * @param bool   $options['with_year']   Set to "true" to allow month and year unit support.
	 * @param bool   $options['allow_zero']  Set to "true" to allow value to be zero.
	 * @param string $options['min']         Lower bound.
	 * @param string $options['max']         Upper bound.
	 *
	 * @return array  An array with parameter 'is_valid' containing validation result. If validation fails, additionally
	 *                returned parameter 'error' containing error message.
	 */
	private function isTimeUnit($value, $params) {
		$simple_interval_parser = new CSimpleIntervalParser(
			array_key_exists('with_year', $params) ? ['with_year' => true] : []
		);
		$value = (string) $value;

		if ($simple_interval_parser->parse($value) == CParser::PARSE_SUCCESS) {
			if (!$params) {
				return ['is_valid' => true];
			}

			if ($value[0] !== '{') {
				$value = timeUnitToSeconds($value,
					array_key_exists('with_year', $params) ? $params['with_year'] : false
				);

				if (array_key_exists('ranges', $params)) {
					$in_range = false;
					$message_ranges = [];

					foreach ($params['ranges'] as $range) {
						if ($range['from'] <= $value && $value <= $range['to']) {
							$in_range = true;
							break;
						}

						$message_ranges[] = ($range['from'] == $range['to'])
							?  $range['from']
							:  $range['from'].'-'.$range['to'];
					}

					if (!$in_range) {
						return [
							'is_valid' => false,
							'error' => _s('value must be one of %1$s', implode(', ', $message_ranges))
						];
					}
				}
			}
		}
		else {
			return ['is_valid' => false, 'error' => _('a time unit is expected')];
		}

		return ['is_valid' => true];
	}

	/**
	 * Add validation error.
	 *
	 * @return string
	 */
	public function addError($fatal, $error) {
		if ($fatal) {
			$this->errorsFatal[] = $error;
		}
		else {
			$this->errors[] = $error;
		}
	}

	/**
	 * Get valid fields.
	 *
	 * @return array of fields passed validation
	 */
	public function getValidInput() {
		return $this->output;
	}

	/**
	 * Returns array of error messages.
	 *
	 * @return array
	 */
	public function getAllErrors() {
		return array_merge($this->errorsFatal, $this->errors);
	}

	/**
	 * Returns true if validation failed with errors.
	 *
	 * @return bool
	 */
	public function isError() {
		return (bool) $this->errors;
	}

	/**
	 * Returns true if validation failed with fatal errors.
	 *
	 * @return bool
	 */
	public function isErrorFatal() {
		return (bool) $this->errorsFatal;
	}
}
