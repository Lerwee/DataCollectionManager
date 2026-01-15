<?php
namespace app\customs\zapi\common\import;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\macros\CMacrosResolverHelper;
use app\modules\libzbx\models\zbx\Hostmacro;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\Items;
use app\modules\libzbx\models\zbx\Triggers;
use yii\db\Query;

/**
 * Class that handles associations for perseus elements unique fields and their database ids.
 * The purpose is to gather all elements that need ids from database and resolve them with one query.
 */
class CImportReferencer {

	/**
	 * @var array with references to interfaceid (hostid -> reference_name -> interfaceid)
	 */
	public $interfaces_cache = [];

	protected $template_groups = [];
	protected $host_groups = [];
	protected $templates = [];
	protected $hosts = [];
	protected $items = [];
	protected $valuemaps = [];
	protected $triggers = [];
	protected $graphs = [];
	protected $iconmaps = [];
	protected $images = [];
	protected $maps = [];
	protected $template_dashboards = [];
	protected $template_macros = [];
	protected $host_macros = [];
	protected $group_prototypes = [];
	protected $host_prototype_macros = [];
	protected $proxies = [];
	protected $host_prototypes = [];
	protected $httptests = [];
	protected $httpsteps = [];

	protected $db_template_groups;
	protected $db_host_groups;
	protected $db_templates;
	protected $db_hosts;
	protected $db_items;
	protected $db_valuemaps;
	protected $db_triggers;
	protected $db_graphs;
	protected $db_iconmaps;
	protected $db_images;
	protected $db_maps;
	protected $db_template_dashboards;
	protected $db_template_macros;
	protected $db_host_macros;
	protected $db_group_prototypes;
	protected $db_host_prototype_macros;
	protected $db_proxies;
	protected $db_host_prototypes;
	protected $db_httptests;
	protected $db_httpsteps;

	/**
	 * Get template group ID by group UUID.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findTemplateGroupidByUuid(string $uuid): ?string {
		if ($this->db_template_groups === null) {
			$this->selectTemplateGroups();
		}

		foreach ($this->db_template_groups as $groupid => $group) {
			if ($group['uuid'] === $uuid) {
				return $groupid;
			}
		}

		return null;
	}

	/**
	 * Get host group ID by group UUID.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findHostGroupidByUuid(string $uuid): ?string {
		if ($this->db_host_groups === null) {
			$this->selectHostGroups();
		}

		foreach ($this->db_host_groups as $groupid => $group) {
			if ($group['uuid'] === $uuid) {
				return $groupid;
			}
		}

		return null;
	}

	/**
	 * Get template group ID by group name.
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findTemplateGroupidByName(string $name): ?string {
		if ($this->db_template_groups === null) {
			$this->selectTemplateGroups();
		}

		foreach ($this->db_template_groups as $groupid => $group) {
			if ($group['name'] === $name) {
				return $groupid;
			}
		}

		return null;
	}

	/**
	 * Get host group ID by group name.
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findHostGroupidByName(string $name): ?string {
		if ($this->db_host_groups === null) {
			$this->selectHostGroups();
		}

		foreach ($this->db_host_groups as $groupid => $group) {
			if ($group['name'] === $name) {
				return $groupid;
			}
		}

		return null;
	}

	/**
	 * Get template ID by group UUID.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findTemplateidByUuid(string $uuid): ?string {
		if ($this->db_templates === null) {
			$this->selectTemplates();
		}

		foreach ($this->db_templates as $templateid => $template) {
			if ($template['uuid'] === $uuid) {
				return $templateid;
			}
		}

		return null;
	}

	/**
	 * Get template ID by template host.
	 *
	 * @param string $host
	 *
	 * @return string|null
	 */
	public function findTemplateidByHost(string $host): ?string {
		if ($this->db_templates === null) {
			$this->selectTemplates();
		}

		foreach ($this->db_templates as $templateid => $template) {
			if ($template['host'] === $host) {
				return $templateid;
			}
		}

		return null;
	}

	/**
	 * Get host ID by host.
	 *
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function findHostidByHost(string $name): ?string {
		if ($this->db_hosts === null) {
			$this->selectHosts();
		}

		foreach ($this->db_hosts as $hostid => $host) {
			if ($host['host'] === $name) {
				return $hostid;
			}
		}

		return null;
	}

	/**
	 * Get host ID or template ID by host.
	 *
	 * @param string $host
	 *
	 * @return string|null
	 */
	public function findTemplateidOrHostidByHost(string $host): ?string {
		$templateid = $this->findTemplateidByHost($host);

		if ($templateid !== null) {
			return $templateid;
		}

		return $this->findHostidByHost($host);
	}

	/**
	 * Get interface ID by host ID and interface reference.
	 *
	 * @param string $hostid
	 * @param string $interface_ref
	 *
	 * @return string|null
	 */
	public function findInterfaceidByRef(string $hostid, string $interface_ref): ?string {
		if (array_key_exists($hostid, $this->interfaces_cache)
				&& array_key_exists($interface_ref, $this->interfaces_cache[$hostid])) {
			return $this->interfaces_cache[$hostid][$interface_ref];
		}

		return null;
	}

	/**
	 * Initializes references for items.
	 */
	public function initItemsReferences(): void {
		if ($this->db_items === null) {
			$this->selectItems();
		}
	}

	/**
	 * Get item ID by uuid.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findItemidByUuid(string $uuid): ?string {
		if ($this->db_items === null) {
			$this->selectItems();
		}

		foreach ($this->db_items as $itemid => $item) {
			if ($item['uuid'] === $uuid) {
				return $itemid;
			}
		}

		return null;
	}

	/**
	 * Get item ID by host ID and item key_.
	 *
	 * @param string $hostid
	 * @param string $key
	 * @param bool   $inherited
	 *
	 * @return string|null
	 */
	public function findItemidByKey(string $hostid, string $key, bool $inherited = false): ?string {
		if ($this->db_items === null) {
			$this->selectItems();
		}

		foreach ($this->db_items as $itemid => $item) {
			if (((string)$item['hostid']) === $hostid && $item['key_'] === $key && ($inherited || $item['templateid'] == 0)) {
				return $itemid;
			}
		}

		return null;
	}

	/**
	 * Get valuemap ID by valuemap name.
	 *
	 * @param string $hostid
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findValuemapidByName(string $hostid, string $name): ?string {
		if ($this->db_valuemaps === null) {
			$this->selectValuemaps();
		}

		foreach ($this->db_valuemaps as $valuemapid => $valuemap) {
			if (((string)$valuemap['hostid']) === $hostid && $valuemap['name'] === $name) {
				return $valuemapid;
			}
		}

		return null;
	}

	/**
	 * Get image ID by image name.
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findImageidByName(string $name): ?string {
		if ($this->db_images === null) {
			$this->selectImages();
		}

		foreach ($this->db_images as $imageid => $image) {
			if ($image['name'] === $name) {
				return $imageid;
			}
		}

		return null;
	}

	/**
	 * Get trigger by trigger ID.
	 *
	 * @param string $triggerid
	 *
	 * @return array|null
	 */
	public function findTriggerById(string $triggerid): ?array {
		if ($this->db_triggers === null) {
			$this->selectTriggers();
		}

		if (array_key_exists($triggerid, $this->db_triggers)) {
			return $this->db_triggers[$triggerid];
		}

		return null;
	}

	/**
	 * Get trigger ID by trigger UUID.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findTriggeridByUuid(string $uuid): ?string {
		if ($this->db_triggers === null) {
			$this->selectTriggers();
		}

		foreach ($this->db_triggers as $triggerid => $trigger) {
			if ($trigger['uuid'] === $uuid) {
				return $triggerid;
			}
		}

		return null;
	}

	/**
	 * Get trigger ID by trigger name and expressions.
	 *
	 * @param string $name
	 * @param string $expression
	 * @param string $recovery_expression
	 * @param bool   $inherited
	 *
	 * @return string|null
	 */
	public function findTriggeridByName(string $name, string $expression, string $recovery_expression,
			bool $inherited = false): ?string {
		if ($this->db_triggers === null) {
			$this->selectTriggers();
		}

		foreach ($this->db_triggers as $triggerid => $trigger) {
			if ($trigger['description'] === $name
					&& $trigger['expression'] === $expression
					&& $trigger['recovery_expression'] === $recovery_expression
					&& ($inherited || $trigger['templateid'] == 0)) {
				return $triggerid;
			}
		}

		return null;
	}

	/**
	 * Get graph ID by UUID.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findGraphidByUuid(string $uuid): ?string {
		if ($this->db_graphs === null) {
			$this->selectGraphs();
		}

		foreach ($this->db_graphs as $graphid => $graph) {
			if ($graph['uuid'] === $uuid) {
				return $graphid;
			}
		}

		return null;
	}

	/**
	 * Get graph ID by host ID and graph name.
	 *
	 * @param string $hostid
	 * @param string $name
	 * @param bool   $inherited
	 *
	 * @return string|null
	 */
	public function findGraphidByName(string $hostid, string $name, bool $inherited = false): ?string {
		if ($this->db_graphs === null) {
			$this->selectGraphs();
		}

		foreach ($this->db_graphs as $graphid => $graph) {
			if ($graph['name'] === $name
					&& in_array($hostid, $graph['hosts'])
					&& ($inherited || $graph['templateid'] == 0)) {
				return $graphid;
			}
		}

		return null;
	}

	/**
	 * Get iconmap ID by name.
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findIconmapidByName(string $name): ?string {
		if ($this->db_iconmaps === null) {
			$this->selectIconmaps();
		}

		foreach ($this->db_iconmaps as $iconmapid => $iconmap) {
			if ($iconmap['name'] === $name) {
				return $iconmapid;
			}
		}

		return null;
	}

	/**
	 * Get map ID by name.
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findMapidByName(string $name): ?string {
		if ($this->db_maps === null) {
			$this->selectMaps();
		}

		foreach ($this->db_maps as $mapid => $map) {
			if ($map['name'] === $name) {
				return $mapid;
			}
		}

		return null;
	}

	/**
	 * Get template dashboard ID by dashboard UUID.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 *
	 * @throws APIException
	 */
	public function findTemplateDashboardidByUuid(string $uuid): ?string {
		if ($this->db_template_dashboards === null) {
			$this->selectTemplateDashboards();
		}

		foreach ($this->db_template_dashboards as $dashboardid => $dashboard) {
			if ($dashboard['uuid'] === $uuid) {
				return $dashboardid;
			}
		}

		return null;
	}

	/**
	 * Get template dashboard ID by dashboard name and template ID.
	 *
	 * @param string $name
	 * @param int    $templateid
	 *
	 * @return string|null
	 */
	public function findTemplateDashboardidByNameAndId(string $name, int $templateid): ?string {
		if ($this->db_template_dashboards === null) {
			$this->selectTemplateDashboards();
		}

		foreach ($this->db_template_dashboards as $dashboardid => $dashboard) {
			if ($dashboard['name'] === $name && $dashboard['templateid'] == $templateid) {
				return $dashboardid;
			}
		}

		return null;
	}

	/**
	 * Get macro ID by template ID and macro name.
	 *
	 * @param string $templateid
	 * @param string $macro
	 *
	 * @return string|null
	 */
	public function findTemplateMacroid(string $templateid, string $macro): ?string {
		if ($this->db_template_macros === null) {
			$this->selectTemplateMacros();
		}

		return (array_key_exists($templateid, $this->db_template_macros)
				&& array_key_exists($macro, $this->db_template_macros[$templateid]))
			? $this->db_template_macros[$templateid][$macro]
			: null;
	}

	/**
	 * Get macro ID by host ID and macro name.
	 *
	 * @param string $hostid
	 * @param string $macro
	 *
	 * @return string|null
	 */
	public function findHostMacroid(string $hostid, string $macro): ?string {
		if ($this->db_host_macros === null) {
			$this->selectHostMacros();
		}

		return (array_key_exists($hostid, $this->db_host_macros)
				&& array_key_exists($macro, $this->db_host_macros[$hostid]))
			? $this->db_host_macros[$hostid][$macro]
			: null;
	}

	/**
	 * Get group prototype ID by host prototype ID and group prototype name.
	 *
	 * @param string $hostid
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findGroupPrototypeId(string $hostid, string $name): ?string {
		if ($this->db_group_prototypes === null) {
			$this->selectGroupPrototypes();
		}

		return (array_key_exists($hostid, $this->db_group_prototypes)
				&& array_key_exists($name, $this->db_group_prototypes[$hostid]))
			? $this->db_group_prototypes[$hostid][$name]
			: null;
	}

	/**
	 * Get macro ID by host prototype ID and macro name.
	 *
	 * @param string $hostid
	 * @param string $macro
	 *
	 * @return string|null
	 */
	public function findHostPrototypeMacroid(string $hostid, string $macro): ?string {
		if ($this->db_host_prototype_macros === null) {
			$this->selectHostPrototypeMacros();
		}

		return (array_key_exists($hostid, $this->db_host_prototype_macros)
				&& array_key_exists($macro, $this->db_host_prototype_macros[$hostid]))
			? $this->db_host_prototype_macros[$hostid][$macro]
			: null;
	}

	/**
	 * Get proxy ID by name.
	 *
	 * @param string $host
	 *
	 * @return string|null
	 */
	public function findProxyidByHost(string $host): ?string {
		if ($this->db_proxies === null) {
			$this->selectProxies();
		}

		foreach ($this->db_proxies as $proxyid => $proxy) {
			if ($proxy['host'] === $host) {
				return $proxyid;
			}
		}

		return null;
	}

	/**
	 * Get host prototype ID by UUID.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findHostPrototypeidByUuid(string $uuid): ?string {
		if ($this->db_host_prototypes === null) {
			$this->selectHostPrototypes();
		}

		foreach ($this->db_host_prototypes as $host_prototypeid => $host_prototype) {
			if ($host_prototype['uuid'] === $uuid) {
				return $host_prototypeid;
			}
		}

		return null;
	}

	/**
	 * Get host prototype ID by host.
	 *
	 * @param string $parent_hostid
	 * @param string $discovery_ruleid
	 * @param string $host
	 *
	 * @return string|null
	 */
	public function findHostPrototypeidByHost(string $parent_hostid, string $discovery_ruleid, string $host): ?string {
		if ($this->db_host_prototypes === null) {
			$this->selectHostPrototypes();
		}

		foreach ($this->db_host_prototypes as $host_prototypeid => $host_prototype) {
			if ($host_prototype['parent_hostid'] === $parent_hostid
					&& $host_prototype['discovery_ruleid'] === $discovery_ruleid
					&& $host_prototype['host'] === $host) {
				return $host_prototypeid;
			}
		}

		return null;
	}

	/**
	 * Get httptest ID by web scenario UUID.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findHttpTestidByUuid(string $uuid): ?string {
		if ($this->db_httptests === null) {
			$this->selectHttpTests();
		}

		foreach ($this->db_httptests as $httptestid => $httptest) {
			if ($httptest['uuid'] === $uuid) {
				return $httptestid;
			}
		}

		return null;
	}

	/**
	 * Get httptest ID by hostid and web scenario name.
	 *
	 * @param string $hostid
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function findHttpTestidByName(string $hostid, string $name): ?string {
		if ($this->db_httptests === null) {
			$this->selectHttpTests();
		}

		foreach ($this->db_httptests as $httptestid => $httptest) {
			if ($httptest['hostid'] === $hostid && $httptest['name'] === $name) {
				return $httptestid;
			}
		}

		return null;
	}

	/**
	 * Get httpstep ID by hostid, httptestid and web scenario step name.
	 *
	 * @param string $hostid
	 * @param string $httptestid
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findHttpStepidByName(string $hostid, string $httptestid, string $name): ?string {
		if ($this->db_httpsteps === null) {
			$this->selectHttpSteps();
		}

		foreach ($this->db_httpsteps as $httpstepid => $httpstep) {
			if ($httpstep['hostid'] === $hostid && $httpstep['name'] === $name
					&& $httpstep['httptestid'] === $httptestid) {
				return $httpstepid;
			}
		}

		return null;
	}

	/**
	 * Add template group names that need association with a database group ID.
	 *
	 * @param array $groups
	 */
	public function addTemplateGroups(array $groups): void {
		$this->template_groups = $groups;
	}

	/**
	 * Add host group names that need association with a database group ID.
	 *
	 * @param array $groups
	 */
	public function addHostGroups(array $groups): void {
		$this->host_groups = $groups;
	}

	/**
	 * Add template group name association with group ID.
	 *
	 * @param string $groupid
	 * @param array  $group
	 */
	public function setDbTemplateGroup(string $groupid, array $group): void {
		$this->db_template_groups[$groupid] = [
			'uuid' => $group['uuid'],
			'name' => $group['name']
		];
	}

	/**
	 * Add host group name association with group ID.
	 *
	 * @param string $groupid
	 * @param array  $group
	 */
	public function setDbHostGroup(string $groupid, array $group): void {
		$this->db_host_groups[$groupid] = [
			'uuid' => $group['uuid'],
			'name' => $group['name']
		];
	}

	/**
	 * Add templates names that need association with a database template ID.
	 *
	 * @param array $templates
	 */
	public function addTemplates(array $templates): void {
		$this->templates = $templates;
	}

	/**
	 * Add template name association with template ID.
	 *
	 * @param string $templateid
	 * @param array  $template
	 */
	public function setDbTemplate(string $templateid, array $template): void {
		$this->db_templates[$templateid] = [
			'uuid' => $template['uuid'],
			'host' => $template['host']
		];
	}

	/**
	 * Add hosts names that need association with a database host ID.
	 *
	 * @param array $hosts
	 */
	public function addHosts(array $hosts): void {
		$this->hosts = $hosts;
	}

	/**
	 * Add host name association with host ID.
	 *
	 * @param string $hostid
	 * @param array  $host
	 */
	public function setDbHost(string $hostid, array $host): void {
		$this->db_hosts[$hostid] = [
			'host' => $host['host']
		];
	}

	/**
	 * Add item keys that need association with a database item ID.
	 *
	 * @param array $items
	 */
	public function addItems(array $items): void {
		$this->items = $items;
	}

	/**
	 * Add item key association with item ID.
	 *
	 * @param string $itemid
	 * @param array  $item
	 */
	public function setDbItem(string $itemid, array $item): void {
		$this->db_items[$itemid] = [
			'hostid' => $item['hostid'],
			'uuid' => array_key_exists('uuid', $item) ? $item['uuid'] : '',
			'key_' => $item['key_'],
			'templateid' => 0
		];
	}

	/**
	 * Add value map names that need association with a database value map ID.
	 *
	 * @param array $valuemaps
	 */
	public function addValuemaps(array $valuemaps): void {
		$this->valuemaps = $valuemaps;
	}

	/**
	 * Add trigger description/expression/recovery_expression that need association with a database trigger ID.
	 *
	 * @param array $triggers
	 */
	public function addTriggers(array $triggers): void {
		$this->triggers = $triggers;
	}

	/**
	 * Add graph names that need association with a database graph ID.
	 *
	 * @param array $graphs
	 */
	public function addGraphs(array $graphs): void {
		$this->graphs = $graphs;
	}

	/**
	 * Add trigger name/expression association with trigger ID.
	 *
	 * @param string $triggerid
	 * @param array  $trigger
	 */
	public function setDbTrigger(string $triggerid, array $trigger): void {
		$this->db_triggers[$triggerid] = [
			'uuid' => array_key_exists('uuid', $trigger) ? $trigger['uuid'] : '',
			'description' => $trigger['description'],
			'expression' => $trigger['expression'],
			'recovery_expression' => $trigger['recovery_expression'],
			'templateid' => 0
		];
	}

	/**
	 * Add icon map names that need association with a database icon map ID.
	 *
	 * @param array $iconmaps
	 */
	public function addIconmaps(array $iconmaps): void {
		$this->iconmaps = $iconmaps;
	}

	/**
	 * Add icon map names that need association with a database icon map ID.
	 *
	 * @param array $images
	 */
	public function addImages(array $images): void {
		$this->images = $images;
	}

	/**
	 * Add image name association with image ID.
	 *
	 * @param string $imageid
	 * @param array  $image
	 */
	public function setDbImage(string $imageid, array $image): void {
		$this->db_images[$imageid] = [
			'name' => $image['name']
		];
	}

	/**
	 * Add map names that need association with a database map ID.
	 *
	 * @param array $maps
	 */
	public function addMaps(array $maps) {
//		$this->maps = array_unique(array_merge($this->maps, $maps));
		$this->maps = $maps;
	}

	/**
	 * Add map name association with map ID.
	 *
	 * @param string $mapid
	 * @param array  $map
	 */
	public function setDbMap(string $mapid, array $map): void {
		$this->db_maps[$mapid] =[
			'name' => $map['name']
		];
	}

	/**
	 * Add templated dashboard names that need association with a database dashboard ID.
	 *
	 * @param array $dashboards
	 */
	public function addTemplateDashboards(array $dashboards): void {
		$this->template_dashboards = $dashboards;
	}

	/**
	 * Add user macro names that need association with a database macro ID.
	 *
	 * @param array $macros[<template uuid>]  An array of macros by template UUID.
	 */
	public function addTemplateMacros(array $macros): void {
		$this->template_macros = $macros;
	}

	/**
	 * Add user macro names that need association with a database macro ID.
	 *
	 * @param array $macros[<host name>]  An array of macros by host technical name.
	 */
	public function addHostMacros(array $macros): void {
		$this->host_macros = $macros;
	}

	/**
	 * Add group prototype names that need association with a database group prototype ID.
	 *
	 * @param array $group_prototypes
	 */
	public function addGroupPrototypes(array $group_prototypes): void {
		$this->group_prototypes = $group_prototypes;
	}

	/**
	 * Add user macro names that need association with a database macro ID.
	 *
	 * @param array $macros[<host name>]  An array of macros by host technical name.
	 */
	public function addHostPrototypeMacros(array $macros): void {
		$this->host_prototype_macros = $macros;
	}

	/**
	 * Add proxy names that need association with a database proxy ID.
	 *
	 * @param array $proxies
	 */
	public function addProxies(array $proxies): void {
		$this->proxies = $proxies;
	}

	/**
	 * Add host prototypes that need association with a database host prototype ID.
	 *
	 * @param array $hostPrototypes
	 */
	public function addHostPrototypes(array $hostPrototypes): void {
		$this->host_prototypes = $hostPrototypes;
	}

	/**
	 * Add web scenario names that need association with a database httptest ID.
	 *
	 * @param array  $httptests
	 */
	public function addHttpTests(array $httptests): void {
		$this->httptests = $httptests;
	}

	/**
	 * Add web scenario step names that need association with a database httpstep ID.
	 *
	 * @param array  $httpsteps
	 */
	public function addHttpSteps(array $httpsteps): void {
		$this->httpsteps = $httpsteps;
	}

	/**
	 * Select template group ids for previously added group names.
	 */
	protected function selectTemplateGroups(): void {
		$this->db_template_groups = [];

		if (!$this->template_groups) {
			return;
		}

		$this->db_template_groups = GroupHelper::getTemplateGroups([
			'output' => ['name', 'uuid'],
			'filter' => [
				'uuid' => array_column($this->template_groups, 'uuid'),
				'name' => array_keys($this->template_groups)
			],
			'searchByAny' => true,
			'preservekeys' => true
		]);

		$this->template_groups = [];
	}

	/**
	 * Select host group ids for previously added group names.
	 */
	protected function selectHostGroups(): void {
		$this->db_host_groups = [];

		if (!$this->host_groups) {
			return;
		}

		$this->db_host_groups = GroupHelper::getHostGroups([
			'output' => ['name', 'uuid'],
			'filter' => [
				'uuid' => array_column($this->host_groups, 'uuid'),
				'name' => array_keys($this->host_groups)
			],
			'searchByAny' => true,
			'preservekeys' => true
		]);

		$this->host_groups = [];
	}

	/**
	 * Select template ids for previously added template names.
	 */
	protected function selectTemplates(): void {
		$this->db_templates = [];

		if (!$this->templates) {
			return;
		}

		$this->db_templates = Hosts::find()
			->select(['hostid', 'host', 'uuid'])
			->where(['status' => HOST_STATUS_TEMPLATE])
			->andWhere([
				'OR',
				['uuid' => array_column($this->templates, 'uuid')],
				['host' => array_keys($this->templates)],
			])
			->indexBy('hostid')
			->asArray()
			->all();

		$this->templates = [];
	}

	/**
	 * Select host ids for previously added host names.
	 */
	protected function selectHosts(): void {
		$this->db_hosts = [];

		if (!$this->hosts) {
			return;
		}

		// Fetch only normal hosts, discovered hosts must not be imported.
		$this->db_hosts = Hosts::find()
			->select(['hostid', 'host'])
			->where(['status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE]])
			->andWhere(['host' => array_keys($this->hosts)])
			->indexBy('hostid')
			->asArray()
			->all();

		$this->hosts = [];
	}

	/**
	 * Select item ids for previously added item keys.
	 */
	protected function selectItems(): void {
		$this->db_items = [];

		if (!$this->items) {
			return;
		}

		$sql_where = [];

		foreach ($this->items as $host => $items) {
			$hostid = $this->findTemplateidOrHostidByHost($host);
			if ($hostid !== null) {
				$sql_where[] = 'hostid=' . ((int) $hostid) 
					. ' AND ('
						. SqlHelper::stringWhereIn('key_', array_keys($items))
						. ' OR ' . SqlHelper::stringWhereIn('uuid', array_column($items, 'uuid'))
					
					. ')';
			}
		}

		if ($sql_where) {
			array_unshift($sql_where, 'OR');
			$db_items = Items::find()
				->select(['itemid', 'hostid', 'key_', 'uuid', 'templateid'])
				->where($sql_where)
				->asArray()
				->all();

			foreach ($db_items as $db_item) {
				$this->db_items[$db_item['itemid']] = [
					'uuid' => $db_item['uuid'],
					'key_' => $db_item['key_'],
					'hostid' => $db_item['hostid'],
					'templateid' => $db_item['templateid']
				];
			}
		}
	}

	/**
	 * Unset item refs to make referencer select them from db again.
	 */
	public function refreshItems(): void {
		$this->db_items = null;
	}

	/**
	 * Select value map IDs for previously added value map names.
	 */
	protected function selectValuemaps(): void {
		$this->db_valuemaps = [];

		if (!$this->valuemaps) {
			return;
		}

		$sql_where = [];

		foreach ($this->valuemaps as $host => $valuemap_names) {
			$hostid = $this->findTemplateidOrHostidByHost($host);
			if ($hostid !== null) {
				$sql_where[] = '(hostid='.($hostid).' AND '.
					SqlHelper::stringWhereIn('name', array_keys($valuemap_names)).')';
			}
		} 
		if ($sql_where) {
			array_unshift($sql_where, 'OR');
			$query = new Query();
			$query->from('valuemap')
				->select(['valuemapid', 'hostid', 'name']);
			$query->where($sql_where);
			$db_valuemaps = $query->all();
			foreach ($db_valuemaps as $valuemap ) {
				$this->db_valuemaps[$valuemap['valuemapid']] = [
					'name' => $valuemap['name'],
					'hostid' => $valuemap['hostid']
				];
			}
		}

		$this->valuemaps = [];
	}

	/**
	 * Select trigger ids for previously added trigger names/expressions.
	 */
	protected function selectTriggers(): void {
		$this->db_triggers = [];

		if (!$this->triggers) {
			return;
		}

		$uuids = [];

		foreach ($this->triggers as $trigger) {
			foreach ($trigger as $expression) {
				$uuids += array_flip(array_column($expression, 'uuid'));
			}
		}

		$query = Triggers::find()
				->select(['triggerid', 'uuid', 'description', 'expression', 'recovery_expression', 'templateid']);
			$query->where([
				'flags' => [
					PRS_FLAG_DISCOVERY_NORMAL,
					PRS_FLAG_DISCOVERY_PROTOTYPE,
					PRS_FLAG_DISCOVERY_CREATED
				]
			]);
			$query->indexBy('triggerid')->asArray();

		$db_triggers = [];
		if ($uuids) {
			$_query = clone $query;
			$_query->andWhere(['uuid' => array_keys($uuids)]);
			$db_triggers = $_query->all();
		}

		$query->andWhere(['description' => array_keys($this->triggers)]);
		$db_triggers += $query->all();


		if (!$db_triggers) {
			return;
		}

		$db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($db_triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		foreach ($db_triggers as $db_trigger) {
			$uuid = $db_trigger['uuid'];
			$description = $db_trigger['description'];
			$expression = $db_trigger['expression'];
			$recovery_expression = $db_trigger['recovery_expression'];

			if (array_key_exists($uuid, $uuids)
				|| (array_key_exists($description, $this->triggers)
					&& array_key_exists($expression, $this->triggers[$description])
					&& array_key_exists($recovery_expression, $this->triggers[$description][$expression]))) {
				$this->db_triggers[$db_trigger['triggerid']] = $db_trigger;
			}
		}
	}

	/**
	 * Unset trigger refs to make referencer select them from db again.
	 */
	public function refreshTriggers(): void {
		$this->db_triggers = null;
	}

	/**
	 * Select graph IDs for previously added graph names.
	 */
	protected function selectGraphs(): void {
		$this->db_graphs = [];

		if (!$this->graphs) {
			return;
		}

		$graph_uuids = [];
		$graph_names = [];

		foreach ($this->graphs as $graph) {
			$graph_uuids += array_flip(array_column($graph, 'uuid'));
			$graph_names += array_flip(array_keys($graph));
		}

		// TODO:
		$db_graphs = [];

		foreach ($db_graphs as $graph) {
			$graph['hosts'] = array_column($graph['hosts'], 'hostid');
			$this->db_graphs[$graph['graphid']] = $graph;
		}
	}

	/**
	 * Unset graph refs to make referencer select them from DB again.
	 */
	public function refreshGraphs(): void {
		$this->db_graphs = null;
	}

	/**
	 * Select icon map ids for previously added icon maps names.
	 */
	protected function selectIconmaps(): void {
		$this->db_iconmaps = [];

		if (!$this->iconmaps) {
			return;
		}

		// TODO:
		$db_iconmaps = [];

		foreach ($db_iconmaps as $iconmapid => $iconmap) {
			$this->db_iconmaps[$iconmapid] = $iconmap;
		}

		$this->iconmaps = [];
	}

	/**
	 * Select icon map ids for previously added icon maps names.
	 */
	protected function selectImages(): void {
		$this->db_images = [];

		if (!$this->images) {
			return;
		}

		// TODO:
		$db_images = [];

		foreach ($db_images as $imageid => $image) {
			$this->db_images[$imageid] = $image;
		}

		$this->images = [];
	}

	/**
	 * Select map ids for previously added maps names.
	 */
	protected function selectMaps(): void {
		$this->db_maps = [];

		if (!$this->maps) {
			return;
		}

		// TODO:
		$db_maps = [];

		foreach ($db_maps as $mapid => $map) {
			$this->db_maps[$mapid] = $map;
		}

		$this->maps = [];
	}

	/**
	 * Select template dashboard IDs for previously added dashboard names and template IDs.
	 *
	 * @throws APIException
	 */
	protected function selectTemplateDashboards(): void {
		$this->db_template_dashboards = [];

		if (!$this->template_dashboards) {
			return;
		}
		
		// TODO:
		$this->db_template_dashboards = [];

		$this->template_dashboards = [];
	}

	/**
	 * Select user macro ids for previously added macro names.
	 */
	protected function selectTemplateMacros(): void {
		$this->db_template_macros = [];

		$sql_where = [];

		foreach ($this->template_macros as $uuid => $macros) {
			$sql_where[] = '(h.uuid='.SqlHelper::dbEscapeString($uuid).' AND '.SqlHelper::stringWhereIn('{{hm}}.macro', $macros).')';
		}

		if ($sql_where) {
			$query = Hostmacro::find()
				->alias('hm')
				->select(['hm.hostmacroid','hm.hostid','hm.macro']);
			$query->innerJoin(['h' => Hosts::tableName()], 'hm.hostid=h.hostid');
			$query->where(implode(' OR ', $sql_where));
			$db_macros = $query->asArray()->all();
			foreach ($db_macros as $db_macro) {
				$this->db_template_macros[$db_macro['hostid']][$db_macro['macro']] = $db_macro['hostmacroid'];
			}
		}

		$this->template_macros = [];
	}

	/**
	 * Select user macro ids for previously added macro names.
	 */
	protected function selectHostMacros(): void {
		$this->db_host_macros = [];

		$sql_where = [];

		foreach ($this->template_macros as $host => $macros) {
			$sql_where[] = '(h.host='.SqlHelper::dbEscapeString($host).' AND '.SqlHelper::stringWhereIn('{{hm}}.macro', $macros).')';
		}

		if ($sql_where) {
			$query = Hostmacro::find()
				->alias('hm')
				->select(['hm.hostmacroid','hm.hostid','hm.macro']);
			$query->innerJoin(['h' => Hosts::tableName()], 'hm.hostid=h.hostid');
			$query->where(implode(' OR ', $sql_where));
			$db_macros = $query->asArray()->all();
			foreach ($db_macros as $db_macro) {
				$this->db_host_macros[$db_macro['hostid']][$db_macro['macro']] = $db_macro['hostmacroid'];
			}
		}

		$this->host_macros = [];
	}

	/**
	 * Select group prototype IDs for previously added group prototype names.
	 */
	protected function selectGroupPrototypes(): void {
		$this->db_group_prototypes = [];

		$sql_where = [];

		foreach ($this->group_prototypes as $type => $hosts) {
			foreach ($hosts as $host => $lld_rules) {
				foreach ($lld_rules as $lld_rule_key => $host_prototypes) {
					foreach ($host_prototypes as $host_prototype_id => $gp_names) {
						$sql_where[] = '('.
						SqlHelper::stringWhereIn('{{h}}.host', [$host]).
							' AND '.SqlHelper::stringWhereIn('{{i}}.key_', [$lld_rule_key]).
							' AND '.SqlHelper::stringWhereIn('{{hp}}.'.$type, [$host_prototype_id]).
							' AND '.SqlHelper::stringWhereIn('{{gp}}.name', $gp_names).
						')';
					}
				}
			}
		}

		if ($sql_where) {
			$query = new Query();
			$query->from([
				'gp' => 'group_prototype',
				'hp' => 'hosts',
				'hd' => 'host_discovery',
				'i' => 'items',
				'h' => 'hosts',
			])
			->select(['gp.group_prototypeid','gp.hostid','gp.name'])
			->where('gp.hostid=hp.hostid')
			->andWhere('hp.hostid=hd.hostid')
			->andWhere('hd.parent_itemid=i.itemid')
			->andWhere('i.hostid=h.hostid');
			$query->andWhere(implode(' OR ', $sql_where));
			$rows = $query->all();

			foreach ($rows as $row) {
				$this->db_group_prototypes[$row['hostid']][$row['name']] = $row['group_prototypeid'];
			}
		}

		$this->group_prototypes = [];
	}

	/**
	 * Select user macro ids for previously added macro names.
	 */
	protected function selectHostPrototypeMacros(): void {
		$this->db_host_prototype_macros = [];

		$sql_where = [];

		foreach ($this->host_prototype_macros as $type => $hosts) {
			foreach ($hosts as $host => $discovery_rules) {
				foreach ($discovery_rules as $discovery_rule_key => $host_prototypes) {
					foreach ($host_prototypes as $host_prototype_id => $macros) {
						$sql_where[] = '('.
							'h.host='.SqlHelper::dbEscapeString($host).
							' AND dr.key_='.SqlHelper::dbEscapeString($discovery_rule_key).
							' AND hp.'.($type === 'uuid' ? 'uuid' : 'host').'='.SqlHelper::dbEscapeString($host_prototype_id).
							' AND '.SqlHelper::stringWhereIn('{{hm}}.macro', $macros).
						')';
					}
				}
			}
		}

		if ($sql_where) {
			$query = new Query();
			$query->from([
				'hm' => 'hostmacro',
				'hp' => 'hosts',
				'hd' => 'host_discovery',
				'dr' => 'items',
				'h' => 'hosts',
			])
			->select(['hm.hostmacroid','hm.hostid','hm.macro'])
			->where('hm.hostid=hp.hostid')
			->andWhere('hp.hostid=hd.hostid')
			->andWhere('hd.parent_itemid=dr.itemid')
			->andWhere('dr.hostid=h.hostid');
			$query->andWhere(implode(' OR ', $sql_where));
			$db_macros = $query->all();

			foreach($db_macros as $db_macro ) {
				$this->db_host_prototype_macros[$db_macro['hostid']][$db_macro['macro']] = $db_macro['hostmacroid'];
			}
		}

		$this->host_prototype_macros = [];
	}

	/**
	 * Select proxy ids for previously added proxy names.
	 */
	protected function selectProxies(): void {
		$this->db_proxies = [];

		if (!$this->proxies) {
			return;
		}

		$query = Hosts::find()
			->select(['hostid', 'host'])
			->where(['status' => [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE]])
			->andWhere(['host' => array_keys($this->proxies)]);
		$query->indexBy('hostid')
			->asArray();

		$this->db_proxies = $query->all();

		$this->proxies = [];
	}

	/**
	 * Select host prototype ids for previously added host prototypes names.
	 */
	protected function selectHostPrototypes(): void {
		$this->db_host_prototypes = [];

		$sql_where = [];

		foreach ($this->host_prototypes as $type => $hosts) {
			foreach ($hosts as $host => $discovery_rules) {
				foreach ($discovery_rules as $discovery_rule_id => $host_prototype_ids) {
					$sql_where[] = '('.
						'h.host='.SqlHelper::dbEscapeString($host).
						' AND dr.'.($type === 'uuid' ? 'uuid' : 'key_').'='.SqlHelper::dbEscapeString($discovery_rule_id).
						' AND '.SqlHelper::stringWhereIn('{{hp}}.'.($type === 'uuid' ? 'uuid' : 'host'), $host_prototype_ids).
					')';
				}
			}
		}

		if ($sql_where) {
			$query = new Query();
			$query->from([
				'hp' => 'hosts',
				'hd' => 'host_discovery',
				'dr' => 'items',
				'h' => 'hosts',
			])
			->select(['hp.host','hp.uuid','hp.hostid','hd.parent_itemid', 'parent_hostid' => 'dr.hostid'])
			->where('hp.hostid=hd.hostid')
			->andWhere('hd.parent_itemid=dr.itemid')
			->andWhere('dr.hostid=h.hostid');
			$query->andWhere(implode(' OR ', $sql_where));
			$db_host_prototypes = $query->all();
			foreach ($db_host_prototypes as $db_host_prototype) {
				$this->db_host_prototypes[$db_host_prototype['hostid']] = [
					'uuid' => $db_host_prototype['uuid'],
					'host' => $db_host_prototype['host'],
					'parent_hostid' => $db_host_prototype['parent_hostid'],
					'discovery_ruleid' => $db_host_prototype['parent_itemid']
				];
			}
		}

		$this->host_prototypes = [];
	}

	/**
	 * Select httptestids for previously added web scenario names.
	 */
	protected function selectHttpTests(): void {
		$this->db_httptests = [];

		if (!$this->httptests) {
			return;
		}

		$sql_where = [];

		foreach ($this->httptests as $host => $httptests) {
			$hostid = $this->findTemplateidOrHostidByHost($host);

			if ($hostid !== false) {
				$sql_where[] = '(ht.hostid='.SqlHelper::dbEscapeString($hostid)
					.' AND ht.templateid IS NULL'
					.' AND ('
						.SqlHelper::dbEscapeString('{ht}.name', array_keys($httptests))
						.' OR '.SqlHelper::dbEscapeString('{ht}.uuid', array_column($httptests, 'uuid'))
					.'))';
			}
		}

		if ($sql_where) {
			$query = new Query();
			$query->from(['ht' => 'httptest'])
				->select(['ht.hostid','ht.name','ht.httptestid','ht.uuid'])
				->where(implode(' OR ', $sql_where));

			$db_httptests = $query->all();

			foreach ($db_httptests as $db_httptest) {
				$this->db_httptests[$db_httptest['httptestid']] = [
					'uuid' => $db_httptest['uuid'],
					'name' => $db_httptest['name'],
					'hostid' => $db_httptest['hostid']
				];
			}
		}
	}

	/**
	 * Unset web scenario refs to make referencer select them from db again.
	 */
	public function refreshHttpTests(): void {
		$this->db_httptests = null;
	}

	/**
	 * Select httpstepids for previously added web scenario step names.
	 */
	protected function selectHttpSteps(): void {
		$this->db_httpsteps = [];

		if (!$this->httpsteps) {
			return;
		}

		$sql_where = [];

		foreach ($this->httpsteps as $host => $httptests) {
			$hostid = $this->findTemplateidOrHostidByHost($host);

			if ($hostid !== null) {
				foreach ($httptests as $httpstep_names) {
					$sql_where[] = SqlHelper::dbEscapeString('{{hs}}.name', array_keys($httpstep_names));
				}
			}
		}

		if ($sql_where) {
			$query = new Query();
			$query->from(['ht' => 'httptest', 'hs' => 'httpstep'])
				->select(['ht.hostid','ht.name','ht.httptestid','ht.uuid'])
				->where('ht.httptestid=hs.httptestid')
				->andWhere(implode(' OR ', $sql_where));
			$db_httpsteps = $query->all();
			foreach ($db_httpsteps as $db_httpstep) {
				$this->db_httpsteps[$db_httpstep['httpstepid']] = [
					'name' => $db_httpstep['name'],
					'hostid' => $db_httpstep['hostid'],
					'httptestid' => $db_httpstep['httptestid']
				];
			}
		}
	}
}
