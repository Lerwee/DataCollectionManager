<?php declare(strict_types = 0);
namespace app\customs\zapi\common\import\converters;


/**
 * Converter for converting import data from 6.0 to 6.2.
 */
class C60ImportConverter extends CConverter {

	/**
	 * Convert import data from 6.0 to 6.2 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert(array $data): array {
		$data['perseus_export']['version'] = '6.2';

		if (array_key_exists('groups', $data['perseus_export'])) {
			$data['perseus_export'] = self::convertGroups($data['perseus_export']);
		}

		return $data;
	}

	/**
	 * Convert groups.
	 *
	 * @param array $perseus_export
	 *
	 * @return array
	 */
	private static function convertGroups(array $perseus_export): array {
		$template_groups = [];
		$host_groups = [];

		if (array_key_exists('templates', $perseus_export)) {
			foreach ($perseus_export['templates'] as $template) {
				foreach ($template['groups'] as $group) {
					$template_groups[] = $group['name'];
				}

				if (array_key_exists('discovery_rules', $template)) {
					foreach ($template['discovery_rules'] as $discovery_rule) {
						if (array_key_exists('host_prototypes', $discovery_rule)) {
							foreach ($discovery_rule['host_prototypes'] as $host_prototype) {
								if (array_key_exists('group_links', $host_prototype)) {
									foreach ($host_prototype['group_links'] as $group_link) {
										$host_groups[] = $group_link['group']['name'];
									}
								}
							}
						}
					}
				}
			}
		}

		if (array_key_exists('hosts', $perseus_export)) {
			foreach ($perseus_export['hosts'] as $host) {
				foreach ($host['groups'] as $group) {
					$host_groups[] = $group['name'];
				}

				if (array_key_exists('discovery_rules', $host)) {
					foreach ($host['discovery_rules'] as $discovery_rule) {
						if (array_key_exists('host_prototypes', $discovery_rule)) {
							foreach ($discovery_rule['host_prototypes'] as $host_prototype) {
								if (array_key_exists('group_links', $host_prototype)) {
									foreach ($host_prototype['group_links'] as $group_link) {
										$host_groups[] = $group_link['group']['name'];
									}
								}
							}
						}
					}
				}
			}
		}

		foreach ($perseus_export['groups'] as $group) {
			if (in_array($group['name'], $host_groups) || !in_array($group['name'], $template_groups)) {
				$perseus_export['host_groups'][] = $group;
			}
			if (in_array($group['name'], $template_groups)) {
				$perseus_export['template_groups'][] = $group;
			}
		}

		unset($perseus_export['groups']);

		return $perseus_export;
	}
}
