<?php

namespace app\customs\zapi\models\search\host;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\GraphHelper;
use app\customs\zapi\common\helpers\HttpTestHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\MacroHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\common\helpers\TriggerHelper;
use app\customs\zapi\components\RelationMap;
use app\customs\zapi\models\search\BaseSearch;
use ReflectionClass;
use yii\db\Query;

/**
 * Class BaseHostSearch
 * @package app\customs\zapi\models\search\item
 */
class BaseHostSearch extends BaseSearch
{
    public $preservekeys = false;
    // output
    public $output = API_OUTPUT_EXTEND;
    public $countOutput = false;
    public $groupCount = false;
    public $selectHosts = null;
    public $selectInterfaces = null;
    public $selectParentTemplates = null;
    public $selectItems = null;
    public $selectDiscoveries = null;
    public $selectTriggers = null;
    public $selectGraphs = null;
    public $selectMacros = null;
    public $selectHttpTests = null;
    public $selectTags = null;
    public $selectValueMaps = null;
    public $sortfield = '';
    public $sortorder = '';
    public $limitSelects = '';
    public $is_all = false;

	/**
     * {@inheritDoc}
     */
    public function rules()
    {
        $attributes = [];
        $properties = (new ReflectionClass($this))->getProperties();
        foreach ($properties as $property) {
            $property->isPublic() && array_push($attributes, $property->name);
        }
        return [
            [$attributes, 'safe']
        ];
    }


    protected function addRelatedObjects(array $result) {
		$result = parent::addRelatedObjects($result);
        $options = get_object_vars($this);
		$hostids = array_keys($result);

		// Add templates.
		if ($options['selectParentTemplates'] !== null) {
			if ($options['selectParentTemplates'] != API_OUTPUT_COUNT) {
				$templates = [];

				// Get template IDs for each host and additional field from relation table if necessary.
                $query = new Query();
                $query->from([
                    'ht' => 'hosts_templates'
                ]);
                $query->select(['ht.hostid', 'ht.templateid'])
                    ->addSelect($this->outputIsRequested('link_type', $options['selectParentTemplates']) ? 'ht.link_type' : '');
                $query->where(SqlHelper::whereIn('ht.hostid', $hostids));
				$hosts_templates = $query->all();

				if ($hosts_templates) {
					// Select also template ID if not selected. It can be removed from results if not requested.
					$template_options = $this->outputIsRequested('templateid', $options['selectParentTemplates'])
						? $options['selectParentTemplates']
						: array_merge($options['selectParentTemplates'], ['templateid']);

					/*
					 * Since templates API does not have "link_type" field, remove it from request, so that template.get
					 * validation may pass successfully.
					 */
					if ($this->outputIsRequested('link_type', $template_options) && is_array($template_options)
							&& ($key = array_search('link_type', $template_options)) !== false) {
						unset($template_options[$key]);
					}
        
					$templates = TemplateHelper::getTemplates([
						'output' => $template_options,
						'templateids' => array_column($hosts_templates, 'templateid'),
						'nopermissions' => $options['nopermissions'],
						'preservekeys' => true
					]);

					if ($options['limitSelects'] !== null) {
						order_result($templates, 'host');
					}
				}

				/*
				 * In order to correctly slice the ordered templates in case of "limitSelects", first they must be
				 * mapped for each host. Otherwise incorrect results may appear. $relation_map key is the host ID, and
				 * values are template ID and, if selected, "link_type".
				 */
				$relation_map = [];
				foreach ($hosts_templates as $host_template) {
					if (!array_key_exists($host_template['hostid'], $relation_map)) {
						$relation_map[$host_template['hostid']] = [];
					}

					$related_fields = ['templateid' => $host_template['templateid']];

					if ($this->outputIsRequested('link_type', $options['selectParentTemplates'])) {
						$related_fields['link_type'] = $host_template['link_type'];
					}

					$relation_map[$host_template['hostid']][] = $related_fields;
				}

				foreach ($result as $hostid => &$host) {
					$host['parentTemplates'] = [];

					if (array_key_exists($hostid, $relation_map)) {
						$templateids = array_column($relation_map[$hostid], 'templateid');
						$templateids = array_combine($templateids, $templateids);

						// Find the matching templates and limit the results if necessary.
						$host['parentTemplates'] = array_values(array_intersect_key($templates, $templateids));

						if ($options['limitSelects'] !== null && $options['limitSelects'] != 0) {
							$host['parentTemplates'] = array_slice($host['parentTemplates'], 0,
								$options['limitSelects']
							);
						}

						// Append the additional field from relation table.
						if ($this->outputIsRequested('link_type', $options['selectParentTemplates'])) {
							foreach ($host['parentTemplates'] as &$template) {
								foreach ($relation_map[$hostid] as $rel_template) {
									if (bccomp($template['templateid'], $rel_template['templateid']) == 0) {
										$template['link_type'] = $rel_template['link_type'];
									}
								}
							}
						}

						// Unset fields if they were not requested.
						$host['parentTemplates'] = $this->unsetExtraFields($host['parentTemplates'], ['templateid'],
							$options['selectParentTemplates']
						);
					}
				}
				unset($host);
			}
			else {
				$templates = TemplateHelper::getTemplates([
					'hostids' => $hostids,
					'countOutput' => true,
					'groupCount' => true
				]);
				$templates = prs_toHash($templates, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['parentTemplates'] = array_key_exists($hostid, $templates)
						? $templates[$hostid]['rowscount']
						: '0';
				}
			}
		}
	
		if ($options['selectItems'] !== null) {
			if ($options['selectItems'] != API_OUTPUT_COUNT) {
				$items = ItemHelper::getItems([
					'output' => $this->outputExtend($options['selectItems'], ['hostid', 'itemid']),
					'hostids' => $hostids,
					'preservekeys' => true,
				]);


				if (!is_null($options['limitSelects'])) {
					order_result($items, 'name');
				}

				$relationMap = $this->createRelationMap($items, 'hostid', 'itemid');

				$items = $this->unsetExtraFields($items, ['hostid', 'itemid'], $options['selectItems']);
				$result = $relationMap->mapMany($result, $items, 'items', $options['limitSelects']);
			}
			else {
				$items = ItemHelper::getItems([
					'hostids' => $hostids,
					'preservekeys' => true,
					'countOutput' => true,
					'groupCount' => true
				]);
			 
				$items = prs_toHash($items, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['items'] = array_key_exists($hostid, $items) ? $items[$hostid]['rowscount'] : 0;
				}
			}
		}

		if ($options['selectDiscoveries'] !== null) {
			if ($options['selectDiscoveries'] != API_OUTPUT_COUNT) {
				$items = DiscoverRuleHelper::getDiscoverRules([
					'output' => $this->outputExtend($options['selectDiscoveries'], ['hostid', 'itemid']),
					'hostids' => $hostids,
					'nopermissions' => true,
					'preservekeys' => true
				]);


				if (!is_null($options['limitSelects'])) {
					order_result($items, 'name');
				}

				$relationMap = $this->createRelationMap($items, 'hostid', 'itemid');

				$items = $this->unsetExtraFields($items, ['hostid', 'itemid'], $options['selectDiscoveries']);
				$result = $relationMap->mapMany($result, $items, 'discoveries', $options['limitSelects']);
			}
			else {
				$items = DiscoverRuleHelper::getDiscoverRules([
					'hostids' => $hostids,
					'preservekeys' => true,
					'countOutput' => true,
					'groupCount' => true
				]);
				$items = prs_toHash($items, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['discoveries'] = array_key_exists($hostid, $items)
						? $items[$hostid]['rowscount']
						: '0';
				}
			}
		}

		if ($options['selectTriggers'] !== null) {
			if ($options['selectTriggers'] != API_OUTPUT_COUNT) {
				$triggers = [];
				$relationMap = new RelationMap();
				// discovered items
				$query = new Query();
				$query->from([
					'i' => 'items',
					'f' => 'functions',
				]);
				$query->select(['i.hostid','f.triggerid']);
				$query->where('i.itemid=f.itemid')
					->andWhere(SqlHelper::whereIn('i.hostid', $hostids));
				$res = $query->all();
				foreach ($res as $relation) {
					$relationMap->addRelation($relation['hostid'], $relation['triggerid']);
				}

				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$triggers = TriggerHelper::getTriggers([
						'output' => $options['selectTriggers'],
						'triggerids' => $related_ids,
						'preservekeys' => true
					]);
					if (!is_null($options['limitSelects'])) {
						order_result($triggers, 'description');
					}
				}

				$result = $relationMap->mapMany($result, $triggers, 'triggers', $options['limitSelects']);
			}
			else {
				$triggers = TriggerHelper::getTriggers([
					'hostids' => $hostids,
					'preservekeys' => true,
					'countOutput' => true,
					'groupCount' => true
				]);
				$triggers = prs_toHash($triggers, 'hostid');

				foreach ($result as $hostid => $host) {
					$result[$hostid]['triggers'] = array_key_exists($hostid, $triggers)
						? $triggers[$hostid]['rowscount']
						: 0;
				}
			}
		}

		if ($options['selectGraphs'] !== null) {
			if ($options['selectGraphs'] != API_OUTPUT_COUNT) {
				$graphs = [];
				$relationMap = new RelationMap();
				// discovered items
				$query = new Query();
				$query->from([
					'i' => 'items',
					'gi' => 'graphs_items',
				]);
				$query->select(['i.hostid','gi.graphid']);
				$query->where('i.itemid=gi.itemid')
					->andWhere(SqlHelper::whereIn('i.hostid', $hostids));
				$res = $query->all();
				foreach ($res as $relation) {
					$relationMap->addRelation($relation['hostid'], $relation['graphid']);
				}

				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$graphs = GraphHelper::getGraphs([
						'output' => $options['selectGraphs'],
						'graphids' => $related_ids,
						'preservekeys' => true
					]);
					if (!is_null($options['limitSelects'])) {
						order_result($graphs, 'name');
					}
				}

				$result = $relationMap->mapMany($result, $graphs, 'graphs', $options['limitSelects']);
			}
			else {
				$graphs = GraphHelper::getGraphs([
					'hostids' => $hostids,
					'countOutput' => true,
					'groupCount' => true
				]);
				$graphs = prs_toHash($graphs, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['graphs'] = array_key_exists($hostid, $graphs)
						? $graphs[$hostid]['rowscount']
						: 0;
				}
			}
		}

		if ($options['selectHttpTests'] !== null) {
			if ($options['selectHttpTests'] != API_OUTPUT_COUNT) {
				$httpTests = HttpTestHelper::getHttpTests([
					'output' => $this->outputExtend($options['selectHttpTests'], ['hostid', 'httptestid']),
					'hostids' => $hostids,
					'nopermissions' => true,
					'preservekeys' => true
				]);

				if (!is_null($options['limitSelects'])) {
					order_result($httpTests, 'name');
				}

				$relationMap = $this->createRelationMap($httpTests, 'hostid', 'httptestid');

				$httpTests = $this->unsetExtraFields($httpTests, ['hostid', 'httptestid'], $options['selectHttpTests']);
				$result = $relationMap->mapMany($result, $httpTests, 'httpTests', $options['limitSelects']);
			}
			else {
				$httpTests = HttpTestHelper::getHttpTests([
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);
				$httpTests = prs_toHash($httpTests, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['httpTests'] = array_key_exists($hostid, $httpTests)
						? $httpTests[$hostid]['rowscount']
						: 0;
				}
			}
		}

		if ($options['selectValueMaps'] !== null) {
			if ($options['selectValueMaps'] === API_OUTPUT_EXTEND) {
				$options['selectValueMaps'] = ['valuemapid', 'name', 'mappings'];
			}

			foreach ($result as &$host) {
				$host['valuemaps'] = [];
			}
			unset($host);

            $query = new Query();
            $query->from('valuemap');
            $query->select(array_diff($this->outputExtend($options['selectValueMaps'], ['valuemapid', 'hostid']), ['mappings']));
            $query->where(['hostid' => $hostids]);
            $query->indexBy('valuemapid');
			$valuemaps = $query->all();

			if ($this->outputIsRequested('mappings', $options['selectValueMaps']) && $valuemaps) {
                $query = new Query();
                $query->from('valuemap_mapping');
                $query->select(['valuemapid', 'type', 'value', 'newvalue']);
                $query->where(['valuemapid' => array_keys($valuemaps)]);
                $query->orderBy('sortorder');
                $mappings = $query->all();


				foreach ($mappings as $mapping) {
					$valuemaps[$mapping['valuemapid']]['mappings'][] = [
						'type' => $mapping['type'],
						'value' => $mapping['value'],
						'newvalue' => $mapping['newvalue']
					];
				}
			}

			foreach ($valuemaps as $valuemap) {
				$result[$valuemap['hostid']]['valuemaps'][] = array_intersect_key($valuemap,
					array_flip($options['selectValueMaps'])
				);
			}
		}

		// adding macros
		if ($options['selectMacros'] !== null && $options['selectMacros'] !== API_OUTPUT_COUNT) {
			$macros = MacroHelper::getHostMacros([
				'output' => $this->outputExtend($options['selectMacros'], ['hostid', 'hostmacroid']),
				'hostids' => $hostids,
				'preservekeys' => true,
			]);
	
			$relationMap = $this->createRelationMap($macros, 'hostid', 'hostmacroid');
			$macros = $this->unsetExtraFields($macros, ['hostid', 'hostmacroid'], $options['selectMacros']);
			$result = $relationMap->mapMany($result, $macros, 'macros',
				array_key_exists('limitSelects', $options) ? $options['limitSelects'] : null
			);
		}

		return $result;
	}
}