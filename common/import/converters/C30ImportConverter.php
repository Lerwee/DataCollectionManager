<?php
namespace app\customs\zapi\common\import\converters;


/**
 * Converter for converting import data from 3.0 to 3.2
 */
class C30ImportConverter extends CConverter {

	public function convert($data) {
		$data['perseus_export']['version'] = '3.2';

		if (array_key_exists('hosts', $data['perseus_export'])) {
			$data['perseus_export']['hosts'] = $this->convertHosts($data['perseus_export']['hosts']);
		}
		if (array_key_exists('templates', $data['perseus_export'])) {
			$data['perseus_export']['templates'] = $this->convertHosts($data['perseus_export']['templates']);
		}
		if (array_key_exists('triggers', $data['perseus_export'])) {
			$data['perseus_export']['triggers'] = $this->convertTriggers($data['perseus_export']['triggers']);
		}
		if (array_key_exists('maps', $data['perseus_export'])) {
			$data['perseus_export']['maps'] = $this->convertMaps($data['perseus_export']['maps']);
		}

		return $data;
	}

	/**
	 * Convert hosts.
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	protected function convertHosts(array $hosts) {
		foreach ($hosts as &$host) {
			if (array_key_exists('discovery_rules', $host)) {
				$host['discovery_rules'] = $this->convertDiscoveryRules($host['discovery_rules']);
			}
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Convert discovery rule elements.
	 *
	 * @param array $discovery_rules
	 *
	 * @return array
	 */
	protected function convertDiscoveryRules(array $discovery_rules) {
		foreach ($discovery_rules as &$discovery_rule) {
			$discovery_rule['trigger_prototypes'] =
				$this->convertTriggers($discovery_rule['trigger_prototypes']);
		}
		unset($discovery_rule);

		return $discovery_rules;
	}

	/**
	 * Convert triggers and trigger prototypes.
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	protected function convertTriggers(array $triggers) {
		foreach ($triggers as &$trigger) {
			$trigger['recovery_mode'] = (string) PRS_RECOVERY_MODE_EXPRESSION;
			$trigger['recovery_expression'] = '';
			$trigger['correlation_mode'] = (string) PRS_TRIGGER_CORRELATION_NONE;
			$trigger['correlation_tag'] = '';
			$trigger['tags'] = [];
			$trigger['manual_close'] = (string) PRS_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED;

			if (array_key_exists('dependencies', $trigger)) {
				$trigger['dependencies'] =
					$this->convertTriggerDependencies($trigger['dependencies']);
			}
		}
		unset($trigger);

		return $triggers;
	}

	/**
	 * Convert trigger and trigger prototype dependencies.
	 *
	 * @param array $dependencies
	 *
	 * @return array
	 */
	protected function convertTriggerDependencies(array $dependencies) {
		foreach ($dependencies as &$dependency) {
			$dependency['recovery_expression'] = '';
		}
		unset($dependency);

		return $dependencies;
	}

	/**
	 * Convert maps.
	 *
	 * @param array $maps
	 *
	 * @return array
	 */
	protected function convertMaps(array $maps) {
		foreach ($maps as &$map) {
			$map['selements'] = $this->convertMapElements($map['selements']);
			$map['links'] = $this->convertMapLinks($map['links']);
		}
		unset($map);

		return $maps;
	}

	/**
	 * Convert map elements.
	 *
	 * @param array $selements
	 *
	 * @return array
	 */
	protected function convertMapElements(array $selements) {
		foreach ($selements as &$selement) {
			if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
				$selement['element']['recovery_expression'] = '';
			}
		}
		unset($selement);

		return $selements;
	}

	/**
	 * Convert map links.
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	protected function convertMapLinks(array $links) {
		foreach ($links as &$link) {
			$link['linktriggers'] = $this->convertMapLinkTriggers($link['linktriggers']);
		}
		unset($link);

		return $links;
	}

	/**
	 * Convert map links.
	 *
	 * @param array $linktriggers
	 *
	 * @return array
	 */
	protected function convertMapLinkTriggers(array $linktriggers) {
		foreach ($linktriggers as &$linktrigger) {
			$linktrigger['trigger']['recovery_expression'] = '';
		}
		unset($linktrigger);

		return $linktriggers;
	}
}
