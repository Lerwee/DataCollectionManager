<?php
namespace app\customs\zapi\common\import\converters;

use Exception;
use Closure;


/**
 * Convert constants to their values.
 */
class CConstantImportConverter extends CConverter {

	protected $rules;

	public function __construct(array $schema) {
		$this->rules = $schema;
	}

	public function convert($data) {
		$data['perseus_export'] = $this->replaceConstant($data['perseus_export'], $this->rules);

		return $data;
	}

	/**
	 * Convert human readable import constants to values Perseus API can work with.
	 *
	 * @param mixed  $data   Import data.
	 * @param array  $rules  XML rules.
	 * @param string $path   XML path (for error reporting).
	 *
	 * @throws Exception if the element is invalid.
	 *
	 * @return mixed
	 */
	protected function replaceConstant($data, array $rules, $path = '') {
		if ($rules['type'] & XML_STRING) {
			if (!array_key_exists('in', $rules)) {
				return $data;
			}

			$flip = array_flip($rules['in']);

			if (!array_key_exists($data, $flip)) {
				throw new Exception(
					t('zapi', 'Invalid tag "{tag}": {error}.', [
						'tag' => $path,
						'error' => t('zapi', 'unexpected constant value "{value}"', [
							'value' => $data,
						])
					])
				);
			}

			$data = (string) $flip[$data];
		}
		elseif ($rules['type'] & XML_ARRAY) {
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
					continue;
				}

				if (array_key_exists($tag, $data)) {
					if (array_key_exists('ex_rules', $tag_rules)) {
						$tag_rules = call_user_func($tag_rules['ex_rules'], $data);
					}

					$data[$tag] = $this->replaceConstant($data[$tag], $tag_rules, $tag);
				}
			}
		}
		elseif ($rules['type'] & XML_INDEXED_ARRAY) {
			$prefix = $rules['prefix'];

			foreach ($data as $tag => $value) {
				$data[$tag] = $this->replaceConstant($value, $rules['rules'][$prefix]);
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
