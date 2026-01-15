<?php

namespace app\customs\zapi\models\search\host;

use app\common\helpers\SqlHelper;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\TagHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\components\RelationMap;
use app\modules\libzbx\models\zbx\Hosts;
use yii\db\Expression;
use yii\db\Query;

/**
 * 模板原始搜索模型
 */
class TemplateSearch extends BaseHostSearch
{
    public $groupids     = null;
    public $templateids = null;
    public $parentTemplateids = null;
    public $hostids     = null;
    public $graphids     = null;
    public $itemids     = null;
    public $triggerids = null;
    public $with_items = null;
    public $with_triggers = null;
    public $with_graphs = null;
    public $with_httptests = null;
    public $editable     = false;
    public $nopermissions = null;
    // filter
    public $evaltype     = TAG_EVAL_TYPE_AND_OR;
    public $tags         = null;
    public $filter     = null;
    public $search     = '';
    public $searchByAny = null;
    public $startSearch = false;
    public $excludeSearch = false;
    public $searchWildcardsEnabled    = null;
    // output
    public $output     = API_OUTPUT_EXTEND;
    public $selectGroups = null;
    public $selectTemplateGroups        = null;
    public $selectHosts = null;
    public $selectTemplates = null;
    public $selectParentTemplates        = null;
    public $selectItems = null;
    public $selectDiscoveries = null;
    public $selectTriggers = null;
    public $selectGraphs = null;
    public $selectMacros = null;
    public $selectDashboards = null;
    public $selectHttpTests = null;
    public $selectTags = null;
    public $selectValueMaps = null;
    public $countOutput = false;
    public $groupCount = false;
    public $preservekeys = false;
    public $sortfield     = '';
    public $sortorder     = '';
    public $limit         = null;
    public $limitSelects = null;

    public function search(array $params = [])
    {
        $this->setAttributes($params);

        $nullExpression = new Expression('NULL');
        $fromTables = [];
        $where = $groupBy = [];
        $query = (new Query())
            ->select(['templates' => 'h.hostid'])
            ->where(['h.status' => HOST_STATUS_TEMPLATE]);

        $provider = new ActiveDataProvider([
            'query' => $query
        ]);

        if ($this->is_all) {
            $provider->setPagination(false);
        }

        // groupids
        if ($this->groupids !== null) {

            $fromTables['hg'] = 'hosts_groups';
            $query->andWhere(['hg.groupid' => filter_integer((array)$this->groupids)]);
            $where['hgi'] = 'hg.hostid=h.hostid';

            if ($this->groupCount) {
                $groupBy['hg'] = 'hg.groupid';
            }
        }

        // templateids
        if ($this->templateids !== null) {
            $query->andWhere(SqlHelper::whereIn('{{h}}.hostid', filter_integer((array)$this->templateids)));
        }

        // parentTemplateids
        if ($this->parentTemplateids !== null) {
            $fromTables['ht'] = 'hosts_templates';
            $query->andWhere(SqlHelper::whereIn('{{ht}}.templateid', filter_integer((array)$this->parentTemplateids)));
            $where['hht'] = 'h.hostid=ht.hostid';

            if ($this->groupCount) {
                $groupBy['templateid'] = 'ht.templateid';
            }
        }

        // hostids
        if ($this->hostids !== null) {
            $fromTables['ht'] = 'hosts_templates';
            $query->andWhere(SqlHelper::whereIn('{{ht}}.hostid', filter_integer((array)$this->hostids)));
            $where['hht'] = 'h.hostid=ht.templateid';

            if ($this->groupCount) {
                $groupBy['ht'] = 'ht.hostid';
            }
        }

        // itemids
        if ($this->itemids !== null) {
            $fromTables['i'] = 'items';
            $query->andWhere(SqlHelper::whereIn('{{i}}.itemid', filter_integer((array)$this->itemids)));
            $where['hi'] = 'h.hostid=i.hostid';
        }

        // triggerids
        if ($this->triggerids !== null) {
            $fromTables['f'] = 'functions';
            $fromTables['i'] = 'items';
            $query->andWhere(SqlHelper::whereIn('f.triggerid', filter_integer((array)$this->triggerids)));
            $where['hi'] = 'h.hostid=i.hostid';
            $where['fi'] = 'f.itemid=i.itemid';
        }

        // graphids
        if ($this->graphids !== null) {
            $fromTables['gi'] = 'graphs_items';
            $fromTables['i'] = 'items';
            $query->andWhere(SqlHelper::whereIn('gi.graphid', filter_integer((array)$this->graphids)));
            $where['igi'] = 'i.itemid=gi.itemid';
            $where['hi'] = 'h.hostid=i.hostid';
        }

        // with_items
        if ($this->with_items !== null) {
            $subQuery = new Query();
            $subQuery->from(['i' => 'items']);
            $subQuery->select($nullExpression)
                ->where('h.hostid=i.hostid')
                ->andWhere(['i.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]]);
            $query->andWhere(['EXISTS', $subQuery]);
        }

        // with_triggers
        if ($this->with_triggers !== null) {
            $subQuery = new Query();
            $subQuery->from([
                'i' => 'items',
                'f' => 'functions',
                't' => 'triggers',
            ]);
            $subQuery->select($nullExpression)
                ->where('i.hostid=h.hostid')
                ->where('i.itemid=f.itemid')
                ->where('f.triggerid=t.triggerid')
                ->andWhere(['t.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]]);
            $query->andWhere(['EXISTS', $subQuery]);
        }

        // with_graphs
        if ($this->with_graphs !== null) {
            $subQuery = new Query();
            $subQuery->from([
                'i' => 'items',
                'gi' => 'graphs_items',
                'g' => 'graphs',
            ]);
            $subQuery->select($nullExpression)
                ->where('i.hostid=h.hostid')
                ->where('i.itemid=gi.itemid')
                ->where('gi.graphid=g.graphid')
                ->andWhere(['g.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]]);
            $query->andWhere(['EXISTS', $subQuery]);
        }

        // with_httptests
        if ($this->with_httptests !== null) {
            $subQuery = new Query();
            $subQuery->from(['ht' => 'httptest']);
            $subQuery->select($nullExpression)
                ->where('ht.hostid=h.hostid');
            $query->andWhere(['EXISTS', $subQuery]);
        }

        // tags
        if ($this->tags !== null && $this->tags) {
            $sqlParts['where'][] = TagHelper::setWhereCondition(
                filter_integer((array)$this->tags),
                $this->evaltype,
                'h',
                'host_tag',
                'hostid'
            );
        }

        // filter
		if (is_array($this->filter)) {
            if ($filter = ZSqlHelper::dbFilter('hosts', $this->filter, 'h', (bool)$this->searchByAny)) {
                $query->andWhere($filter);
            }
		}

        // search
        if (is_array($this->search)) {
            ZSqlHelper::zbxDbSearch('hosts h', [
                'search' => $this->search,
                'startSearch' => $this->startSearch,
                'excludeSearch' => $this->excludeSearch,
                'searchWildcardsEnabled' => $this->searchWildcardsEnabled,
                'searchByAny' => $this->searchByAny,
            ], $query);
        }


		// limit
        if ($this->limit !== null && prs_ctype_digit($this->limit)) {
            $query->limit($this->limit);
        }

        foreach ($where as $condition) {
            $query->andWhere($condition);
        }
        $query->from($fromTables + ['h' => Hosts::tableName()]);
        $query->groupBy($groupBy);

		$this->applyQueryOutputOptions($query, Hosts::tableName(), 'h', $this->countOutput ? 'count' : $this->output);

		// is_array($this->search) && $upcased_index = array_search('h.name_upper', $this->search);
		// if ($upcased_index !== false) {
		// 	unset($sqlParts['select'][$upcased_index]);
		// }

        $this->applyQuerySortOptions($query, Hosts::tableName(), 'h', $this->sortfield, $this->sortorder);
 

        if ($this->countOutput) {
            return $provider;
        }
        if ($this->preservekeys) {
            $query->indexBy('hostid');
        }
        $models = $this->format($provider->getModels());
        $provider->setModels($models);
        return $provider;
    }

    protected function format(array $templates)
    {
        if ($templates) {
            foreach($templates as &$template) {
                $template['templateid'] = $template['hostid'];
                // Templates share table with hosts and host prototypes. Therefore remove template unrelated fields.
                unset($template['hostid'], $template['discover']);
            }
            unset($template);
			$templates = $this->addRelatedObjects($templates);
			$templates = $this->unsetExtraFields($templates, ['name_upper']);
		}
        return $templates;
    }

    protected function addRelatedObjects(array $result) {
		$result = parent::addRelatedObjects($result);

        $options = get_object_vars($this);
		// adding template groups
		$this->addRelatedGroups($result, 'selectGroups');
		$this->addRelatedGroups($result, 'selectTemplateGroups');

		$templateids = array_keys($result);

		if ($options['selectTemplates'] !== null) {
            
            $query = Hosts::find()
                ->asArray()
                ->where(['status' => HOST_STATUS_TEMPLATE]);
			if ($options['selectTemplates'] != API_OUTPUT_COUNT) {
				$templates = [];
				$relationMap = $this->createRelationMap($result, 'templateid', 'hostid', 'hosts_templates');
				$related_ids = $relationMap->getRelatedIds();
				if ($related_ids) {
                    $query->select($options['selectTemplates'])
                        ->addSelect(['hostid']);
                    $query->andWhere(SqlHelper::whereIn('hostid', $related_ids))
                        ->indexBy('hostid');

                    $templates = array_map(function($template){
                        $template['templateid'] = $template['hostid'];
                        unset($template['hostid']);
                        return $template;
                    }, $query->all());
					if (!is_null($options['limitSelects'])) {
						order_result($templates, 'host');
					}
				}

				$result = $relationMap->mapMany($result, $templates, 'templates', $options['limitSelects']);
			}
			else {

                $query->from([
                    'h' => 'hosts',
                    'ht' => 'hosts_templates',
                ]);
                $query->where('h.hostid=ht.hostid')
                    ->andWhere(['status' => HOST_STATUS_TEMPLATE]);
                $query->andWhere(SqlHelper::whereIn('{{ht}}.templateid', $templateids));

                $query->select([
                    'templateid' => 'h.hostid',
                    'rowscount' => 'count(1)',
                ])
                ->groupBy('h.hostid');

				$templates = $query->all();
				$templates = prs_toHash($templates, 'templateid');
				foreach ($result as $templateid => $template) {
					$result[$templateid]['templates'] = array_key_exists($templateid, $templates)
						? $templates[$templateid]['rowscount']
						: '0';
				}
			}
		}

		if ($options['selectHosts'] !== null) {
            $query = Hosts::find()
                ->asArray()
                ->where(['status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]]);
			if ($options['selectHosts'] != API_OUTPUT_COUNT) {
				$hosts = [];
				$relationMap = $this->createRelationMap($result, 'templateid', 'hostid', 'hosts_templates');
				$related_ids = $relationMap->getRelatedIds();
             
				if ($related_ids) {
                    $query->select($options['selectHosts'])
                        ->addSelect(['hostid']);

					$hosts = HostHelper::getHosts([
						'output' => $options['selectHosts'],
						'hostids' => $related_ids,
						'preservekeys' => true
					]);
					if (!is_null($options['limitSelects'])) {
						order_result($hosts, 'host');
					}
				}

				$result = $relationMap->mapMany($result, $hosts, 'hosts', $options['limitSelects']);
			}
			else {
				$hosts = HostHelper::getHosts([
					'templateids' => $templateids,
					'countOutput' => true,
					'groupCount' => true
				]);
				$hosts = prs_toHash($hosts, 'templateid');
				foreach ($result as $templateid => $template) {
					$result[$templateid]['hosts'] = array_key_exists($templateid, $hosts)
						? $hosts[$templateid]['rowscount']
						: '0';
				}
			}
		}

		if ($options['selectDashboards'] !== null) {
			if ($options['selectDashboards'] != API_OUTPUT_COUNT) {
				$dashboards = TemplateHelper::getDashboards([
					'output' => $this->outputExtend($options['selectDashboards'], ['templateid']),
					'templateids' => $templateids
				]);
				if (!is_null($options['limitSelects'])) {
					order_result($dashboards, 'name');
				}

				// Build relation map.
				$relationMap = new RelationMap();
				foreach ($dashboards as $key => $dashboard) {
					$relationMap->addRelation($dashboard['templateid'], $key);
				}

				$dashboards = $this->unsetExtraFields($dashboards, ['templateid'], $options['selectDashboards']);
				$result = $relationMap->mapMany($result, $dashboards, 'dashboards', $options['limitSelects']);
			}
			else {
				$dashboards = TemplateHelper::getDashboards([
					'templateids' => $templateids,
					'countOutput' => true,
					'groupCount' => true
				]);
				$dashboards = prs_toHash($dashboards, 'templateid');
				foreach ($result as $templateid => $template) {
					$result[$templateid]['dashboards'] = array_key_exists($templateid, $dashboards)
						? $dashboards[$templateid]['rowscount']
						: '0';
				}
			}
		}

		if ($options['selectTags'] !== null) {
			foreach ($result as &$row) {
				$row['tags'] = [];
			}
			unset($row);

			if ($options['selectTags'] === API_OUTPUT_EXTEND) {
				$output = ['hosttagid', 'hostid', 'tag', 'value'];
			}
			else {
				$output = array_unique(array_merge(['hosttagid', 'hostid'], $options['selectTags']));
			}

            $query = new Query();
            $query->from('host_tag')
                ->select($output);
            $query->where(SqlHelper::whereIn('hostid', $templateids));

            $db_tags = $query->all();
			foreach ($db_tags as $db_tag) {
				$hostid = $db_tag['hostid'];

				unset($db_tag['hosttagid'], $db_tag['hostid']);

				$result[$hostid]['tags'][] = $db_tag;
			}
		}

		return $result;
	}

	/**
	 * Adds related template groups requested by "select*" options to the resulting object set.
	 *
	 * @param array  $result  [IN/OUT] Result output.
	 * @param string $option  [IN] Possible values:
	 *                               - "selectGroups" (deprecated);
	 *                               - "selectHostGroups" (or any other value).
	 */
	private function addRelatedGroups(array &$result, string $option): void {
        $options = get_object_vars($this);
		if ($options[$option] === null || $options[$option] === API_OUTPUT_COUNT) {
			return;
		}

		$relationMap = $this->createRelationMap($result, 'hostid', 'groupid', 'hosts_groups');
        $groups = GroupHelper::getTemplateGroups([
			'output' => $options[$option],
			'groupids' => $relationMap->getRelatedIds(),
			'preservekeys' => true
		]);

		$output_tag = $option === 'selectGroups' ? 'groups' : 'templategroups';
		$result = $relationMap->mapMany($result, $groups, $output_tag);
	}

    /**
     * Validates the input parameters for the get() method.
     *
     * @param array $options
     *
     * @throws ValidateException if the input is invalid
     */
    protected function validateGet(array $options)
    {
        // Validate input parameters.
        $rules = [];
        $options_filter = array_intersect_key($options, $rules['fields']);
        $bool = ValidateHelper::validateObjects($options_filter, $rules, [], $error);
        if (!$bool) {
            return $this->error(error_code(60750001), $error);
        }
    }
}
