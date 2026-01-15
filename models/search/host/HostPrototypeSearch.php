<?php

namespace app\customs\zapi\models\search\host;

use app\common\helpers\SqlHelper;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\components\RelationMap;
use app\modules\libzbx\models\zbx\Hosts;
use yii\db\Expression;
use yii\db\Query;


class HostPrototypeSearch extends BaseHostSearch
{
	public $outputFields = [
		'hostid',
		'host',
		'name',
		'status',
		'templateid',
		'inventory_mode',
		'discover',
		'custom_interfaces',
		'uuid'
	];

	public $hostids = null;
	public $discoveryids = null;
	public $filter = null;
	public $search = null;
	public $searchByAny = false;
	public $startSearch = false;
	public $excludeSearch = false;
	public $searchWildcardsEnabled = false;
	public $output = null;
	public $countOutput = false;
	public $groupCount = false;
	public $selectGroupLinks = null;
	public $selectGroupPrototypes = null;
	public $selectDiscoveryRule = null;
	public $selectParentHost = null;
	public $selectInterfaces = null;
	public $selectTemplates = null;
	public $selectMacros = null;
	public $selectTags = null;
	public $sortfield = false;
	public $sortorder = false;
	public $limit = null;
	public $inherited = null;
	public $editable = false;
	public $preservekeys = false;
	public $nopermissions = false;

	/**
	 * Searches
	 *
	 * @param  array $params
	 * @return ActiveDataProvider
	 */
	public function search(array $params = [])
	{
		$this->setAttributes($params);

		if ($this->output === API_OUTPUT_EXTEND) {
			$this->output = $this->outputFields;
		}

		$query = new Query();
		$query->select(['h.hostid']);
		$query->where(['h.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE]);

		$provider = new ActiveDataProvider([
			'query' => $query
		]);
		if ($this->is_all) {
			$provider->setPagination(false);
		}

		$this->applyQueryFilterOptions($query,  Hosts::tableName(), 'h');
		$this->applyQueryOutputOptions($query,  Hosts::tableName(), 'h', $this->countOutput ? 'count' : $this->output);
		$this->applyQuerySortOptions($query,  Hosts::tableName(), 'h', $this->sortfield, $this->sortorder);
		if ($this->countOutput) {
			return $provider;
		}
		$query->indexBy('hostid');

		$models = $this->format($provider->getModels());
		$provider->setModels($this->preservekeys ? $models : array_values($models));
		return $provider;
	}

	public function format(array $models)
	{
		if ($models) {
			$models = $this->addRelatedObjects($models);
			$models = $this->unsetExtraFields($models, ['triggerid'], $this->output);
		}
		return $models;
	}

	/**
	 * @param Query $query
	 * @param string $tableName
	 * @param string $tableAlias
	 * @param string|array $outPut
	 * @param array $options
	 * @throws Exception
	 */
	protected function applyQueryOutputOptions(Query &$query, string $tableName, string $tableAlias, $outPut = 'extend', array $options = [])
	{
		parent::applyQueryOutputOptions($query, $tableName, $tableAlias, $outPut, $options);
		if ($this->countOutput) {
			return;
		}
		if ($getInventoryMode = $this->outputIsRequested('inventory_mode', $outPut)) {
			$f = ZSqlHelper::dbConditionCoalesce('hinv.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode');
			$query->addSelect(new Expression($f));
		}
		if ($getInventoryMode || ($this->filter && array_key_exists('inventory_mode', $this->filter))) {
			$query->leftJoin(['hinv' => 'host_inventory'], $this->fieldId('hostid', $tableAlias) . '=hinv.hostid');
		}
	}

	/**
	 * @param  Query  $query
	 * @param  string $tableName
	 * @param  string $tableAlias
	 */
	protected function applyQueryFilterOptions(Query &$query, string $tableName, string $tableAlias)
	{
		$groupBy = [];
		// do not return host prototypes from discovered hosts
		$query->from([
			'hd' => 'host_discovery',
			'i' => 'items',
			'ph' => 'hosts',
			$tableAlias => $tableName
		]);

		$query->andWhere($this->fieldId('hostid', $tableAlias) . '=hd.hostid')
			->andWhere('hd.parent_itemid=i.itemid')
			->andWhere('i.hostid=ph.hostid');
		$query->andWhere([
			'ph.flags' => PRS_FLAG_DISCOVERY_NORMAL
		]);

		if ($this->hostids !== null) {
			$query->andWhere(SqlHelper::whereIn('{{h}}.hostid', filter_integer((array)$this->hostids)));
		}

		// discoveryids
		if ($this->discoveryids !== null) {
			$query->andWhere(SqlHelper::whereIn('{{hd}}.parent_itemid', filter_integer((array)$this->discoveryids)));
			if ($this->groupCount) {
				$groupBy['hd'] = 'hd.parent_itemid';
			}
		}

		// inherited
		if ($this->inherited !== null) {
			$query->andWhere($this->inherited ? 'h.templateid IS NOT NULL' : 'h.templateid IS NULL');
		}

		if ($this->filter && array_key_exists('inventory_mode', $this->filter)) {
			if ($this->filter['inventory_mode'] !== null) {
				$inventory_mode_query = (array) $this->filter['inventory_mode'];

				$inventory_mode_where = [];
				$null_position = array_search(HOST_INVENTORY_DISABLED, $inventory_mode_query);

				if ($null_position !== false) {
					unset($inventory_mode_query[$null_position]);
					$inventory_mode_where[] = 'hinv.inventory_mode IS NULL';
				}

				if ($null_position === false || $inventory_mode_query) {
					$inventory_mode_where[] = SqlHelper::whereIn('{{hinv}}.inventory_mode', $inventory_mode_query);
				}
				$query->andWHere(implode(' OR ', $inventory_mode_where));
			}
		}
		$query->groupBy($groupBy);
	}

	/**
	 * Retrieves and adds additional requested data to the result set.
	 *
	 * @param array $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $result)
	{
		$result = parent::addRelatedObjects($result);

		$hostIds = array_keys($result);
		if ($this->selectDiscoveryRule !== null) {
			$relationMap = $this->createRelationMap($result, 'hostid', 'parent_itemid', 'host_discovery');
			$discoveryRules = DiscoverRuleHelper::getDiscoverRules([
				'output' => $this->selectDiscoveryRule,
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		$this->addRelatedGroupLinks($result, $this->selectGroupLinks, 'groupLinks');
		$this->addRelatedGroupLinks($result, $this->selectGroupPrototypes, 'groupPrototypes');

		if ($this->selectParentHost !== null) {
			$hosts = [];
			$relationMap = new RelationMap();

			$query = new Query();
			$query->from([
				'hd' => 'host_discovery',
				'i' => 'items',
			])
				->where('hd.parent_itemid=i.itemid')
				->andWhere(SqlHelper::whereIn('hd.hostid', array_keys($hostIds)));

			$query->select(['parent_hostid' => 'i.hostid', 'hd.hostid']);

			$dbRules = $query->all();
			foreach ($dbRules as $dbRule) {
				$relationMap->addRelation($dbRule['hostid'], $dbRule['parent_hostid']);
			}

			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$hosts = HostHelper::getHosts([
					'output' => $this->selectParentHost,
					'hostids' => $related_ids,
					'templated_hosts' => true,
					'nopermissions' => true,
					'preservekeys' => true
				]);
			}

			$result = $relationMap->mapOne($result, $hosts, 'parentHost');
		}

		if ($this->selectTemplates !== null) {
			if ($this->selectTemplates != API_OUTPUT_COUNT) {
				$templates = [];
				$relationMap = $this->createRelationMap($result, 'hostid', 'templateid', 'hosts_templates');
				$related_ids = $relationMap->getRelatedIds();
				if ($related_ids) {
					$templates = TemplateHelper::getTemplates([
						'output' => $this->selectTemplates,
						'templateids' => $related_ids,
						'preservekeys' => true
					]);
				}

				$result = $relationMap->mapMany($result, $templates, 'templates');
			} else {
				$templates = TemplateHelper::getTemplates([
					'hostids' => $hostIds,
					'countOutput' => true,
					'groupCount' => true
				]);
				$templates = prs_toHash($templates, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['templates'] = array_key_exists($hostid, $templates)
						? $templates[$hostid]['rowscount']
						: '0';
				}
			}
		}

		if ($this->selectTags !== null) {
			foreach ($result as &$row) {
				$row['tags'] = [];
			}
			unset($row);

			if ($this->selectTags === API_OUTPUT_EXTEND) {
				$output = ['hosttagid', 'hostid', 'tag', 'value'];
			} else {
				$output = array_unique(array_merge(['hosttagid', 'hostid'], $this->selectTags));
			}

			$db_tags = DB::makeQuery('host_tag', [
				'output' => $output,
				'filter' => ['hostid' => $hostIds]
			])->all();

			foreach ($db_tags as $db_tag) {
				$hostid = $db_tag['hostid'];

				unset($db_tag['hosttagid'], $db_tag['hostid']);

				$result[$hostid]['tags'][] = $db_tag;
			}
		}

		if ($this->selectInterfaces !== null) {
			$interfaces = HostHelper::getInterfaces([
				'output' => $this->outputExtend($this->selectInterfaces, ['hostid', 'interfaceid']),
				'hostids' => $hostIds,
				'sortfield' => 'interfaceid',
				'nopermissions' => true,
				'preservekeys' => true
			]);

			foreach (array_keys($result) as $hostid) {
				$result[$hostid]['interfaces'] = [];
			}

			foreach ($interfaces as $interface) {
				$hostid = $interface['hostid'];
				unset($interface['hostid'], $interface['interfaceid']);
				$result[$hostid]['interfaces'][] = $interface;
			}
		}

		return $result;
	}

	/**
	 * @param array $result
	 * @param array $options
	 * @param string $options
	 */
	private function addRelatedGroupLinks(array &$result, ?array $options = null, string $index = 'groupLinks'): void
	{
		if ($options === null) {
			return;
		}

		foreach ($result as &$host_prototype) {
			$host_prototype[$index] = [];
		}
		unset($host_prototype);

		if ($options === API_OUTPUT_EXTEND) {
			$output = $index ==  'groupPrototypes' ?  ['group_prototypeid', 'hostid', 'name'] : ['hostid', 'groupid'];
		} else {
			$output = array_unique(array_merge(['hostid'], $options));
		}

		$query = new Query();
		$query->from('group_prototype')
			->select($output)
			->where(SqlHelper::whereIn('hostid', array_keys($result)))
			->andWhere($index == 'groupPrototypes' ? new Expression('groupid IS NULL') :  new Expression('groupid IS NOT NULL'));


		$db_group_prototypes = $query->all();

		foreach ($db_group_prototypes as $db_group_prototype) {
			$hostid = $db_group_prototype['hostid'];

			unset($db_group_prototype['hostid']);

			$result[$hostid][$index][] = $db_group_prototype;
		}
	}
}
