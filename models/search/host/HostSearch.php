<?php

namespace app\customs\zapi\models\search\host;

use app\common\helpers\SqlHelper;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\TagHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\common\helpers\ZSqlHelper; 
use app\modules\libzbx\models\zbx\Hosts;
use yii\db\Expression;
use yii\db\Query;

class HostSearch extends BaseHostSearch
{
    public $groupids = null;
    public $hostids = null;
    public $proxyids = null;
    public $templateids = null;
    public $interfaceids = null;
    public $itemids = null;
    public $triggerids = null;
    public $maintenanceids = null;
    public $graphids = null;
    public $dserviceids = null;
    public $httptestids = null;
    public $monitored_hosts = null;
    public $templated_hosts = null;
    public $proxy_hosts = null;
    public $with_items = null;
    public $with_item_prototypes = null;
    public $with_simple_graph_items = null;
    public $with_simple_graph_item_prototypes = null;
    public $with_monitored_items = null;
    public $with_triggers = null;
    public $with_monitored_triggers = null;
    public $with_httptests = null;
    public $with_monitored_httptests = null;
    public $with_graphs = null;
    public $with_graph_prototypes = null;
    public $withProblemsSuppressed = null;
    public $editable = null;
    public $nopermissions = null;
    // filter
    public $evaltype = null;
    public $tags = null;
    public $severities = null;
    public $inheritedTags = null;
    public $filter = null;
    public $search = null;
    public $searchInventory = null;
    public $searchByAny = false;
    public $startSearch = null;
    public $excludeSearch = null;
    public $searchWildcardsEnabled = null;
    // output
    public $output = null;
    public $selectGroups = null;
    public $selectHostGroups = null;
    public $selectParentTemplates = null;
    public $selectItems = null;
    public $selectDiscoveries = null;
    public $selectTriggers = null;
    public $selectGraphs = null;
    public $selectMacros = null;
    public $selectDashboards = null;
    public $selectInterfaces = null;
    public $selectInventory = null;
    public $selectHttpTests = null;
    public $selectDiscoveryRule = null;
    public $selectHostDiscovery = null;
    public $selectTags = null;
    public $selectInheritedTags = null;
    public $selectValueMaps = null;
    public $countOutput = null;
    public $groupCount = null;
    public $preservekeys = null;
    public $sortfield = null;
    public $sortorder = null;
    public $limit = null;
    public $limitSelects = null;

    public $keyword = null;

    protected $sortColumns = ['hostid', 'name', 'status'];


    /**
	 * Searches
	 *
	 * @param  array $params
	 * @return ActiveDataProvider
	 */
	public function search(array $params = [])
	{
		$this->setAttributes($params);
 
        $tables = $where = $groupBy = [];

        $query = new Query();
		$query->select(['h.hostid']);
		$query->where(['h.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]]);

        if (null !== $this->hostids) {
            $query->andWhere(SqlHelper::whereIn('h.hostid', filter_integer((array) $this->hostids)));
        }

        if (null !== $this->groupids) {
            $tables['hg'] = 'hosts_groups';
            $where['hgh'] = 'hg.hostid=h.hostid';
            $query->andWhere(SqlHelper::whereIn('hg.groupid', filter_integer((array) $this->groupids)));

            if ($this->groupCount) {
                $groupBy['groupid'] = 'hg.groupid';
            }
        }

        if (null !== $this->proxyids) {
            $query->andWhere(SqlHelper::whereIn('h.proxy_hostid', filter_integer((array) $this->hostids)));
        }

        if (null !== $this->templateids) {
            $tables['ht'] = 'hosts_templates';
            $where['hht'] = 'h.hostid=ht.hostid';
            $query->andWhere(SqlHelper::whereIn('ht.templateid', filter_integer((array) $this->templateids)));

            if ($this->groupCount) {
                $groupBy['templateid'] = 'ht.templateid';
            }
        }

        if (null !== $this->interfaceids) {
            $query->leftJoin(['hi' => 'interface'], 'hi.hostid=h.hostid');
            $query->andWhere(SqlHelper::whereIn('hi.interfaceid', filter_integer((array) $this->interfaceid)));
        }

        if (null !== $this->itemids) {
            $tables['i'] = 'items';
            $where['hi'] = 'h.hostid=i.hostid';
            $query->andWhere(SqlHelper::whereIn('i.itemid', filter_integer((array) $this->itemids)));
        }

        if (null !== $this->triggerids) {
            $tables['f'] = 'functions';
            $tables['i'] = 'items';
			$where['hi'] = 'h.hostid=i.hostid';
			$where['fi'] = 'f.itemid=i.itemid';
            $query->andWhere(SqlHelper::whereIn('f.triggerid', filter_integer((array) $this->triggerids)));
        }

        if (null !== $this->httptestids) {
            $tables['ht'] = 'httptest';
			$where['aht'] = 'ht.hostid=h.hostid';
            $query->andWhere(SqlHelper::whereIn('i.httptestid', filter_integer((array) $this->httptestids)));
        }

        if (null !== $this->graphids) {
			$tables['gi'] = 'graphs_items';
			$tables['i'] = 'items i';
			$where['igi'] = 'i.itemid=gi.itemid';
			$where['hi'] = 'h.hostid=i.hostid';
            $query->andWhere(SqlHelper::whereIn('gi.graphid', filter_integer((array) $this->graphids)));
		}

        if (null !== $this->dserviceids) {
			$tables['ds'] = 'dservices';
			$tables['i'] = 'interface i';
			$where['dsh'] = 'ds.ip=i.ip';
			$where['hi'] = 'h.hostid=i.hostid';
            $query->andWhere(SqlHelper::whereIn('ds.dserviceid', filter_integer((array) $this->dserviceids)));

            if ($this->groupCount) {
                $groupBy['dserviceid'] = 'ds.dserviceid';
            }
		}

        if (null !== $this->maintenanceids) {
			$tables['mh'] = 'maintenances_hosts';
			$where['hmh'] = 'h.hostid=mh.hostid';
            $query->andWhere(SqlHelper::whereIn('mh.maintenanceid', filter_integer((array) $this->maintenanceids)));
            
			if ($this->groupCount) {
                $groupBy['maintenanceid'] = 'mh.maintenanceid';
			}
		}

        // monitored_hosts, templated_hosts
        if (null !== $this->monitored_hosts) {
            $query->andWhere(['h.status' => HOST_STATUS_MONITORED]);
		}
		elseif (null !== $this->templated_hosts) {
            $query->andWhere(['h.status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE]]);
		}
        elseif (null !== $this->proxy_hosts) {
            $query->andWhere(['h.status' => [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE]]);
		}
		else {
            $query->andWhere(['h.status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]]);
		}

        $this->queryWith($query);

        $options = get_object_vars($this);

        if (is_array($this->search)) {
            ZSqlHelper::zbxDbSearch('hosts h', $options, $query);
            if (ZSqlHelper::zbxDbSearch('interface hi', $options, $query)) {
                $query->leftJoin(['hi' => 'interface'], 'hi.hostid=h.hostid');
            }
        }

        if ($this->searchInventory !== null) {
            $tables['hii'] = 'host_inventory';
            $where['hii'] = 'h.hostid=hii.hostid';
            ZSqlHelper::zbxDbSearch('host_inventory hii', $options, $query);
        }


        if ($this->filter !== null) {
            if ($condition = ZSqlHelper::dbFilter('hosts',$this->filter, 'h', $this->searchByAny)) {
                $query->andWhere($condition);
            }

            if (array_key_exists('hostid', $this->filter)) {
				unset($this->filter['hostid']);
			}

            if ($condition = ZSqlHelper::dbFilter('interface',$this->filter, 'hi', $this->searchByAny)) {
                $query->andWhere($condition);
                $query->leftJoin(['hi' => 'interface'],'h.hostid=hi.hostid');
            }

            if (array_key_exists('active_available', $this->filter)
					&& $this->filter['active_available'] !== null) {
                    
                    $filter = ['filter' => [
						'active_available' => $this->filter['active_available']
					]] + $options;
                    if ($condition = ZSqlHelper::dbFilter('host_rtdata',$filter, 'hr', $this->searchByAny)) {
                        $query->andWhere($condition);
                    }
			}
        }

        // tags
		if ($this->tags !== null && $this->tags) {
			if ($this->inheritedTags) {
                $query->leftJoin(['ht2' => 'hosts_templates'],'h.hostid=hi.hostid');
                $query->andWhere(TagHelper::setInheritedHostTagsWhereCondition($this->tags, $this->evaltype));
			}
			else {
                $query->andWhere(TagHelper::setWhereCondition($this->tags, $this->evaltype, 'h', 'host_tag', 'hostid'));
			}
		}

        if ($this->limit !== null && $this->limit > 0) {
            $query->limit($this->limit);
        }

        foreach ($where as $condition) {
            $query->andWhere($condition);
        }

        if ($this->keyword !== null && $this->keyword !== '') {
            $query->andWhere([
                'OR',
                [DB_LIKE, 'h.name', $this->keyword],
                [DB_LIKE, 'h.host', $this->keyword],
            ]);
        }

        $query->from($tables + ['h' => Hosts::tableName()]);
        $query->groupBy($groupBy);

        /*
		 * Cleaning the output from write-only properties.
		 */
		$write_only_keys = ['tls_psk_identity', 'tls_psk', 'name_upper'];

		if ($this->output === API_OUTPUT_EXTEND) {
			$all_keys = array_keys(DB::getSchema(Hosts::tableName())['fields']);
			$all_keys[] = 'inventory_mode';
			$all_keys[] = 'active_available';
			$this->output = array_diff($all_keys, $write_only_keys);
		}
		/*
		* For internal calls of API method, is possible to get the write-only fields if they were specified in output.
		* Specify write-only fields in output only if they will not appear in debug mode.
		*/
		elseif (is_array($this->output) && 'api' == 'api') {
			$this->output = array_diff($this->output, $write_only_keys);
		}

        $options['output'] = $this->output;

        $this->applyQueryFilterOptions($query, 'hosts', 'h');
		$this->applyQueryOutputOptions($query, 'hosts', 'h', $this->countOutput ? 'count' : $this->output, $options);
		$this->applyQuerySortOptions($query, 'hosts', 'h', $this->sortfield, $this->sortorder);

        $provider = new ActiveDataProvider([
            'query' => $query
        ]);
        if ($this->is_all) {
            $provider->setPagination(false);
        }

        if($this->countOutput) {
            return $provider;
        }

        if ($this->preservekeys) {
            $query->indexBy('hostid');
        }
        $models = $this->format($provider->getModels());
        $provider->setModels($models);
        return $provider;
    }

    protected function format(array $hosts)
    {
        if ($hosts) {
            if ($this->outputIsRequested('discover', $this->output)) {
                foreach($hosts as &$host) {
                    unset($host['discover']);
                }
            }
            unset($host);
			$hosts = $this->addRelatedObjects($hosts);
			$hosts = $this->unsetExtraFields($hosts, ['name_upper'], $this->output);
		}
        return $hosts;
    }

    /**
     * @param  Query $query
     */
    protected function queryWith(&$query)
    {
        $nullExpression = new Expression('NULL');
        $subQuery = new Query();
        $subQuery->select($nullExpression);
    
        // with_items, with_simple_graph_items, with_monitored_items
        if ($this->with_items !== null || $this->with_simple_graph_items !== null || $this->with_monitored_items !== null) {
            $sQuery = clone $subQuery;
            $sQuery->from(['i' => 'items'])
                ->where('h.hostid=i.hostid')
                ->andWhere(['i.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]]);

            if ($this->with_monitored_items !== null) {
                $sQuery->andWhere(['i.status' => ITEM_STATUS_ACTIVE]);
            } elseif($this->with_simple_graph_items !== null) {
                $subQuery->andWhere(['i.status' => ITEM_STATUS_ACTIVE])
                    ->andWhere(['i.value_type' =>[ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]]);
            }

            $query->andWhere(['EXISTS', $sQuery]);
        }

        // with_item_prototypes, with_simple_graph_item_prototypes
        if ($this->with_item_prototypes !== null || $this->with_simple_graph_item_prototypes !== null) {
            $sQuery = clone $subQuery;
            $sQuery->from(['i' => 'items'])
                ->where('h.hostid=i.hostid')
                ->andWhere(['i.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE]);

            if ($this->with_simple_graph_item_prototypes !== null) {
                $sQuery->andWhere(['i.status' => ITEM_STATUS_ACTIVE])
                    ->andWhere(['i.value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]]);
            }

            $query->andWhere(['EXISTS', $sQuery]);
        }

        // with_triggers, with_monitored_triggers
        if ($this->with_triggers !== null) {
            $sQuery = clone $subQuery;
            $sQuery->from([
                'i' => 'items',
                'f' => 'functions',
                't' => 'triggers',
                ])
                ->where('h.hostid=i.hostid')
                ->andWhere('i.itemid=f.itemid')
                ->andWhere('f.triggerid=t.triggerid')
                ->andWhere(['t.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]]);

                $query->andWhere(['EXISTS', $sQuery]);
        } elseif ($this->with_monitored_triggers !== null) {
            $sQuery = clone $subQuery;
            $sQuery->from([
                'i' => 'items',
                'f' => 'functions',
                't' => 'triggers',
                ])
                ->where('h.hostid=i.hostid')
                ->andWhere('i.itemid=f.itemid')
                ->andWhere('f.triggerid=t.triggerid')
                ->andWhere(['i.status' => ITEM_STATUS_ACTIVE])
                ->andWhere(['t.status' => TRIGGER_STATUS_ENABLED])
                ->andWhere(['t.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]]);

                $query->andWhere(['EXISTS', $sQuery]);
        }

        // with_httptests, with_monitored_httptests
        if ($this->with_httptests !== null) {
            $query->andWhere(['EXISTS', 'SELECT NULL FROM httptest ht WHERE ht.hostid=h.hostid']);
        } elseif ($this->with_monitored_httptests !== null) {
            $query->andWhere(['EXISTS', 'SELECT NULL FROM httptest ht WHERE ht.hostid=h.hostid AND ht.status=' . HTTPTEST_STATUS_ACTIVE]);
        }

        // with_graphs
        if ($this->with_graphs !== null) {
            $sQuery = clone $subQuery;
            $sQuery->from([
                'i' => 'items',
                'gi' => 'graphs_items',
                'g' => 'graphs',
                ])
                ->where('h.hostid=i.hostid')
                ->andWhere('i.itemid=gi.itemid')
                ->andWhere('gi.graphid=g.graphid')
                ->andWhere(['g.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]]);

            $query->andWhere(['EXISTS', $sQuery]);
        }

        // with_graph_prototypes
        if ($this->with_graph_prototypes !== null) {
            $sQuery = clone $subQuery;
            $sQuery->from([
                'i' => 'items',
                'gi' => 'graphs_items',
                'g' => 'graphs',
                ])
                ->where('h.hostid=i.hostid')
                ->andWhere('i.itemid=gi.itemid')
                ->andWhere('gi.graphid=g.graphid')
                ->andWhere(['g.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE]);

            $query->andWhere(['EXISTS', $sQuery]);
        }
    }

    /**
     * @param  Query  $query
     * @param  string $tableName
     * @param  string $tableAlias
     */
    protected function applyQueryFilterOptions(Query &$query, $tableName, $tableAlias) {
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
					$inventory_mode_where[] = SqlHelper::whereIn('hinv.inventory_mode', $inventory_mode_query);
				}

                $query->andWhere((count($inventory_mode_where) > 1)
                ? '('.implode(' OR ', $inventory_mode_where).')'
                : $inventory_mode_where[0]);
			}
		}
	}

    /**
     * {@inheritDoc}
     */
    protected function applyQueryOutputOptions(Query &$query, $tableName, $tableAlias, $outPut = 'extent', array $options = []) {
		parent::applyQueryOutputOptions($query, $tableName, $tableAlias,  $outPut, $options);

        if($this->countOutput) {
            return;
        }
         
		if ($out = $this->outputIsRequested('inventory_mode', $this->output) || ($this->filter && array_key_exists('inventory_mode', $this->filter)) ) {
            $out && $query->addSelect([ZSqlHelper::dbConditionCoalesce('hinv.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode')]);
            $query->leftJoin(['hinv' => 'host_inventory'], 'hinv.hostid=h.hostid');
		}


		if ($out = $this->outputIsRequested('active_available', $this->output) || (is_array($this->filter) && array_key_exists('active_available', $this->filter))) {
            $out && $query->addSelect(['hr.active_available']);
            $query->leftJoin(['hr' => 'host_rtdata'], 'hr.hostid=h.hostid');
		}
	}


    /**
	 * Retrieves and adds additional requested data to the result set.
     *
	 * @param array  $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $result) {
		$result = parent::addRelatedObjects($result);

		// adding groups
		$this->addRelatedGroups($result, 'selectGroups');
		$this->addRelatedGroups($result, 'selectHostGroups');

		$hostids = array_keys($result);

		if ($this->selectInventory !== null) {
			$inventory = HostHelper::getInventories([
				'output' => $this->selectInventory,
				'filter' => ['hostid' => $hostids],
				'preservekeys' => true
			]);

			$inventory = $this->unsetExtraFields($inventory, ['hostid', 'inventory_mode'], []);
			$relation_map = $this->createRelationMap($result, 'hostid', 'hostid');
			$result = $relation_map->mapOne($result, $inventory, 'inventory');
		}

		if ($this->selectInterfaces !== null) {
			if ($this->selectInterfaces != API_OUTPUT_COUNT) {
				$interfaces = HostHelper::getInterfaces([
					'output' => $this->outputExtend($this->selectInterfaces, ['hostid', 'interfaceid']),
					'hostids' => $hostids,
					'nopermissions' => true,
					'preservekeys' => true
				]);

				// we need to order interfaces for proper linkage and viewing
				order_result($interfaces, 'interfaceid', PRS_SORT_UP);

				$relationMap = $this->createRelationMap($interfaces, 'hostid', 'interfaceid');

				$interfaces = $this->unsetExtraFields($interfaces, ['hostid', 'interfaceid'], $this->selectInterfaces);
				$result = $relationMap->mapMany($result, $interfaces, 'interfaces', $this->limitSelects);
			}
			else {
				$interfaces = HostHelper::getInterfaces([
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);

				$interfaces = prs_toHash($interfaces, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['interfaces'] = array_key_exists($hostid, $interfaces)
						? $interfaces[$hostid]['rowscount']
						: '0';
				}
			}
		}

		if ($this->selectDashboards !== null) {
			[$hosts_templates, $templateIds] = HostHelper::getParentTemplates($hostids);

			if ($this->selectDashboards != API_OUTPUT_COUNT) {
				$dashboards = TemplateHelper::getDashboards([
					'output' => $this->outputExtend($this->selectDashboards, ['templateid']),
					'templateids' => $templateIds
				]);

				if (!is_null($this->limitSelects)) {
					order_result($dashboards, 'name');
				}

				foreach ($result as &$host) {
					foreach ($hosts_templates[$host['hostid']] as $templateid) {
						foreach ($dashboards as $dashboard) {
							if ($dashboard['templateid'] == $templateid) {
								$host['dashboards'][] = $dashboard;
							}
						}
					}
				}
				unset($host);
			}
			else {
				$dashboards = TemplateHelper::getDashboards([
					'templateids' => $templateIds,
					'countOutput' => true,
					'groupCount' => true
				]);

				foreach ($result as $hostid => $host) {
					$result[$hostid]['dashboards'] = 0;

					foreach ($dashboards as $dashboard) {
						if (in_array($dashboard['templateid'], $hosts_templates[$hostid])) {
							$result[$hostid]['dashboards'] += $dashboard['rowscount'];
						}
					}

					$result[$hostid]['dashboards'] = (string) $result[$hostid]['dashboards'];
				}
			}
		}

		if ($this->selectDiscoveryRule !== null && $this->selectDiscoveryRule != API_OUTPUT_COUNT) {
			// discovered items
            $query = new Query();
            $query->from([
                'hd' => 'host_discovery',
                'hd2' => 'host_discovery',
            ]);
            $query->select(['hd.hostid','hd2.parent_itemid']);
            $query->where('hd.parent_hostid=hd2.hostid')
                ->andWhere(SqlHelper::whereIn('hd.hostid', $hostids));
            $discoveryRules = $query->all();


			$relationMap = $this->createRelationMap($discoveryRules, 'hostid', 'parent_itemid');
			$discoveryRules = DiscoverRuleHelper::getDiscoverRules([
				'output' => $this->selectDiscoveryRule,
				'itemids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		if ($this->selectHostDiscovery !== null) {
			$hostDiscoveries = DB::makeQuery('host_discovery', [
				'output' => $this->outputExtend($this->selectHostDiscovery, ['hostid']),
				'filter' => ['hostid' => $hostids],
				'preservekeys' => true
			])->all();
			$relationMap = $this->createRelationMap($hostDiscoveries, 'hostid', 'hostid');

			$hostDiscoveries = $this->unsetExtraFields($hostDiscoveries, ['hostid'], $this->selectHostDiscovery);
			$result = $relationMap->mapOne($result, $hostDiscoveries, 'hostDiscovery');
		}

		if ($this->selectTags !== null) {
			foreach ($result as &$row) {
				$row['tags'] = [];
			}
			unset($row);

			if ($this->selectTags === API_OUTPUT_EXTEND) {
				$output = ['hosttagid', 'hostid', 'tag', 'value', 'automatic'];
			} else {
				$output = array_unique(array_merge(['hosttagid', 'hostid'], $this->selectTags));
			}

			$sql_options = [
				'output' => $output,
				'filter' => ['hostid' => $hostids]
			];

            
			$db_tags = DB::makeQuery('host_tag', $sql_options)->all();

			foreach ($db_tags as $db_tag) {
				$hostid = $db_tag['hostid'];

				unset($db_tag['hosttagid'], $db_tag['hostid']);

				$result[$hostid]['tags'][] = $db_tag;
			}
		}

		if ($this->selectInheritedTags !== null && $this->selectInheritedTags != API_OUTPUT_COUNT) {
			[$hosts_templates, $templateids] = HostHelper::getParentTemplates($hostids);

			$templates = TemplateHelper::getTemplates([
				'output' => [],
				'selectTags' => ['tag', 'value'],
				'templateids' => $templateids,
				'preservekeys' => true,
				'nopermissions' => true
			]);

			// Set "inheritedTags" for each host.
			foreach ($result as &$host) {
				$tags = [];

				// Get IDs and template tag values from previously stored variables.
				foreach ($hosts_templates[$host['hostid']] as $templateid) {
					foreach ($templates[$templateid]['tags'] as $tag) {
						foreach ($tags as $_tag) {
							// Skip tags with same name and value.
							if ($_tag['tag'] === $tag['tag'] && $_tag['value'] === $tag['value']) {
								continue 2;
							}
						}
						$tags[] = $tag;
					}
				}

				$host['inheritedTags'] = $this->unsetExtraFields($tags, ['tag', 'value'], $this->selectInheritedTags);
			}
		}

		return $result;
	}

	/**
	 * Adds related host groups requested by "select*" options to the resulting object set.
	 *
	 * @param array  $result   [IN/OUT] Result output.
	 * @param string $option   [IN] Possible values:
	 *                                - "selectGroups" (deprecated);
	 *                                - "selectHostGroups" (or any other value).
	 */
	private function addRelatedGroups(array &$result, string $option): void {
		if (!$this->hasProperty($option) || $this->{$option} === null || $this->{$option} === API_OUTPUT_COUNT) {
			return;
		}
		$relationMap = $this->createRelationMap($result, 'hostid', 'groupid', 'hosts_groups');
		$groups = GroupHelper::getHostGroups([
			'output' => $this->{$option},
			'groupids' => $relationMap->getRelatedIds(),
			'preservekeys' => true
        ]);

		$output_tag = $option === 'selectGroups' ? 'groups' : 'hostgroups';
		$result = $relationMap->mapMany($result, $groups, $output_tag);
	}
}