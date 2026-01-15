<?php
namespace app\customs\zapi\common\import\converters;

use Closure;
use Exception;


/**
 * Add tags with default values.
 */
class CDefaultImportConverter extends CConverter {

	protected $rules;

	public function __construct(array $schema) {
		$this->rules = $schema;
	}

	public function convert($data) {
		foreach ($this->rules['rules'] as $tag => $tag_rules) {
			if (!array_key_exists($tag, $data['perseus_export'])) {
				continue;
			}

			$data['perseus_export'][$tag] = $this->addDefaultValue($data['perseus_export'][$tag], $tag_rules);
		}

		return $data;
	}

	/**
	 * Add default values in place of missed tags.
	 *
	 * @param mixed $data   Import data.
	 * @param array $rules  XML rules.
	 *
	 * @return mixed
	 */
	protected function addDefaultValue($data, array $rules) {
		if ($rules['type'] & XML_ARRAY) {
			foreach ($rules['rules'] as $tag => $tag_rules) {
				while ($tag_rules['type'] & XML_MULTIPLE) {
					$matched_multiple_rule = null;

					foreach ($tag_rules['rules'] as $multiple_rule) {
						if ($this->multipleRuleMatched($multiple_rule, $data)) {
							$matched_multiple_rule =
								$multiple_rule + array_intersect_key($tag_rules, array_flip(['default']));
							break;
						}
					}

					if ($matched_multiple_rule === null) {
						// For use by developers. Do not translate.
						throw new Exception('Incorrect XML_MULTIPLE validation rules.');
					}

					$tag_rules = $matched_multiple_rule;
				}

				if ($tag_rules['type'] & XML_IGNORE_TAG) {
					unset($data[$tag]);

					continue;
				}

				if (array_key_exists($tag, $data)) {
					$data[$tag] = $this->addDefaultValue($data[$tag], $tag_rules);
				}
				elseif (array_key_exists('ex_default', $tag_rules)) {
					$data[$tag] = (string) call_user_func($tag_rules['ex_default'], $data);
				}
				elseif (array_key_exists('default', $tag_rules)) {
					$data[$tag] = (string) $tag_rules['default'];
				}
				else {
					$data[$tag] = (($tag_rules['type'] & XML_STRING) ? '' : []);
				}
			}
		}
		elseif ($rules['type'] & XML_INDEXED_ARRAY) {
			$prefix = $rules['prefix'];

			foreach ($data as $key => $value) {
				$data[$key] = $this->addDefaultValue($value, $rules['rules'][$prefix]);
			}
		}

		return $data;
	}

	private function multipleRuleMatched(array $multiple_rule, array $data): bool {
		if (array_key_exists('else', $multiple_rule)) {
			return true;
		}
		elseif (is_array($multiple_rule['if'])) {
			return array_key_exists($data[$multiple_rule['if']['tag']], $multiple_rule['if']['in']);
		}
		elseif ($multiple_rule['if'] instanceof Closure) {
			return call_user_func($multiple_rule['if'], $data);
		}

		return false;
	}
}
