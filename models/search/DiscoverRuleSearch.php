<?php

namespace app\customs\zapi\models\search;

use app\common\helpers\ArrayHelper;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\CConditionHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\TriggerHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\components\RelationMap;
use app\customs\zapi\models\search\item\BaseItemSearch;
use app\modules\libzbx\models\Items;
use yii\db\Exception;
use yii\db\Query;

/**
 * Class DiscoverRuleSearch
 * @package app\customs\zapi\models\search
 */
class DiscoverRuleSearch extends BaseItemSearch
{
    public $groupids = null;
    public $templateids = null;
    public $hostids = null;
    public $itemids = null;
    public $interfaceids = null;
    public $inherited = null;
    public $templated = null;
    public $monitored = null;
    public $editable = false;
    public $nopermissions = null;
    // filter
    public $filter = null;
    public $search = null;
    public $searchByAny = null;
    public $startSearch = false;
    public $excludeSearch = false;
    public $searchWildcardsEnabled = null;
    // output
    public $output = API_OUTPUT_EXTEND;
    public $selectHosts = null;
    public $selectItems = null;
    public $selectTriggers = null;
    public $selectGraphs = null;
    public $selectHostPrototypes = null;
    public $selectFilter = null;
    public $selectLLDMacroPaths = null;
    public $selectPreprocessing = null;
    public $selectOverrides = null;
    public $countOutput = false;
    public $groupCount = false;
    public $preservekeys = false;
    public $sortfield = '';
    public $sortorder = '';
    public $limit = null;
    public $limitSelects = null;

    public $is_all = false;
    public $visible = false;


    public function rules(): array
    {
        return [
            [[
                'groupids', 'templateids', 'hostids', 'itemids', 'interfaceids', 'inherited', 'templated', 'monitored', 'editable', 'nopermissions',
                // filter'filter',
                'search', 'searchByAny', 'startSearch', 'excludeSearch', 'searchWildcardsEnabled',
                // output
                'output', 'selectHosts', 'selectItems', 'selectTriggers', 'selectGraphs', 'selectHostPrototypes', 'selectFilter',
                'selectLLDMacroPaths', 'selectPreprocessing', 'selectOverrides', 'countOutput', 'groupCount', 'preservekeys', 'sortfield',
                'sortorder', 'limit', 'limitSelects', 'is_all', 'visible'
            ], 'safe']
        ];
    }

    /**
     * @param array $params
     * @return ActiveDataProvider
     * @throws Exception
     */
    public function search(array $params = []): ActiveDataProvider
    {
        $this->load($params, '');
        $fromTables = [];
        $where = $groupBy = [];
        $query = (new Query())
            ->select(['i.itemid'])
            ->andWhere(['i.flags' => [PRS_FLAG_DISCOVERY_RULE]]);

        $provider = new ActiveDataProvider([
            'query' => $query
        ]);
        if ($this->is_all) {
            $provider->setPagination(false);
        }

        // itemids
        if (!is_null($this->itemids)) {
            $where[] = ['i.itemid' => filter_integer((array)$this->itemids)];
        }

        if (!is_null($this->templateids) || !is_null($this->hostids)) {
            $hostIds = array_merge((array)$this->templateids, (array)$this->hostids);
            $where[] = ['i.hostid' => filter_integer($hostIds)];

            if ($this->groupCount) {
                $groupBy['i'] = 'i.hostid';
            }
        }

        // interfaceids
        if (!is_null($this->interfaceids)) {
            $where[]['interfaceid'] = ['i.interfaceid' => filter_integer((array)$this->interfaceids)];
            if ($this->groupCount) {
                $groupBy['i'] = 'i.interfaceid';
            }
        }

        // groupids
        if ($this->groupids !== null) {
            $fromTables['hg'] = 'hosts_groups';
            $where[] = ['hg.groupid' => filter_integer((array)$this->groupids)];
            $where[] = 'hg.hostid=i.hostid';

            if ($this->groupCount) {
                $groupBy['hg'] = 'hg.groupid';
            }
        }

        if (!is_null($this->inherited)) {
            if ($this->inherited) {
                $where[] = 'i.templateid IS NOT NULL';
            } else {
                $where[] = 'i.templateid IS NULL';
            }
        }

        if (!is_null($this->templated)) {
            $fromTables['h'] = 'hosts';
            $where['hi'] = 'h.hostid=i.hostid';
            if ($this->templated) {
                $where[] = ['h.status' => HOST_STATUS_TEMPLATE];
            } else {
                $where[] = ['<>', 'h.status', HOST_STATUS_TEMPLATE];
            }
        }

        // monitored
        if (!is_null($this->monitored)) {
            $fromTables['h'] = 'hosts';
            $where['hi'] = 'h.hostid=i.hostid';
            if ($this->monitored) {
                $where[] = ['h.status' => HOST_STATUS_MONITORED, 'i.status' => ITEM_STATUS_ACTIVE];
            } else {
                $where[] = ['or', ['<>', 'h.status', HOST_STATUS_MONITORED], ['<>', 'i.status', ITEM_STATUS_ACTIVE]];
            }
        }

        // search
        if (is_array($this->search)) {
            if (array_key_exists('error', $this->search) && $this->search['error'] !== null) {
                ZSqlHelper::zbxDbSearch('item_rtdata ir', ['search' => ['error' => $this->search['error']]] + [
                        'search' => $this->search,
                        'startSearch' => $this->startSearch,
                        'excludeSearch' => $this->excludeSearch,
                        'searchWildcardsEnabled' => $this->searchWildcardsEnabled,
                        'searchByAny' => $this->searchByAny,
                    ], $query);
            }
            ZSqlHelper::zbxDbSearch('items i', [
                'search' => $this->search,
                'startSearch' => $this->startSearch,
                'excludeSearch' => $this->excludeSearch,
                'searchWildcardsEnabled' => $this->searchWildcardsEnabled,
                'searchByAny' => $this->searchByAny,
            ], $query);
        }

        // filter
        if (is_array($this->filter)) {
            $filter = [];
            if (array_key_exists('delay', $this->filter) && $this->filter['delay'] !== null) {
                $where[] = makeUpdateIntervalFilter('i.delay', $this->filter['delay']);
                unset($this->filter['delay']);
            }

            if (array_key_exists('lifetime', $this->filter) && $this->filter['lifetime'] !== null) {
                $this->filter['lifetime'] = getTimeUnitFilters($this->filter['lifetime']);
            }

            if (array_key_exists('state', $this->filter) && $this->filter['state'] !== null) {
                $fieldFilter = array_merge(['state' => $this->filter['state']], $this->filter);
                $filter[] = ZSqlHelper::dbFilter('item_rtdata', $fieldFilter, 'ir', (bool)$this->searchByAny);
            }

            $filter[] = ZSqlHelper::dbFilter('items', $this->filter, 'i', (bool)$this->searchByAny);
            $filter = array_filter($filter);
            $filterStr = implode((bool)$this->searchByAny ? 'OR' : 'AND', $filter);
            $filter && $where[] = $filterStr;

            if (isset($this->filter['host'])) {
                $fromTables['h'] = 'hosts';
                $where['hi'] = 'h.hostid=i.hostid';
                $where[] = ['h.host' => $this->filter['host']];
            }
        }

        foreach ($where as $condition) {
            $query->andWhere($condition);
        }
        $query->groupBy($groupBy)->distinct();
        $query->from($fromTables + ['i' => Items::tableName()]);
        $this->applyQueryOutputOptions($query, Items::tableName(), 'i', $this->countOutput ? 'count' : $this->output);
        $this->applyQuerySortOptions($query, Items::tableName(), 'i', $this->sortfield, $this->sortorder);
        if ($this->countOutput) {
            return $provider;
        }
        $models = $provider->getModels();
        if(count($query->from) > 1) {
            $models = $this->addNclobFieldValues($models, $this->output);
        }
        $models = $this->format($models);
        if ($this->preservekeys) {
            $models = ArrayHelper::index($models, 'itemid');
        } else {
            $models = array_values($models);
        }
        $provider->setModels($models);
        return $provider;
    }

    protected function format($models): array
    {
        $models = $this->addRelations($models);
        $models = $this->unsetExtraFields($models, ['name_upper']);

        foreach ($models as &$rule) {
            // unset the fields that are returned in the filter
            unset($rule['formula'], $rule['evaltype']);
            if ($this->selectFilter !== null) {
                $filter = $this->unsetExtraFields([$rule['filter']],
                    ['conditions', 'formula', 'evaltype'],
                    $this->selectFilter
                );
                $filter = reset($filter);
                if (isset($filter['conditions'])) {
                    foreach ($filter['conditions'] as &$condition) {
                        unset($condition['item_conditionid'], $condition['itemid']);
                    }
                    unset($condition);
                }

                $rule['filter'] = $filter;
            }
        }
        unset($rule);
        $models = $this->formatQueryFields($models);
        return $this->visible ? $this->visible($models) : $models;
    }

    protected function applyQueryOutputOptions(Query &$query, string $tableName, string $tableAlias, $outPut = 'extent', array $options = [])
    {
        parent::applyQueryOutputOptions($query, $tableName, $tableAlias, $outPut, $options);


        $upCasedIndex = array_search($tableAlias . '.name_upper', $query->select);
        if ($upCasedIndex !== false) {
            unset($query->select[$upCasedIndex]);
        }

        if ($outPut != 'count' && ($this->outputIsRequested('state', $outPut)
                || $this->outputIsRequested('error', $this->output))
            || (is_array($this->search) && array_key_exists('error', $this->search))
            || (is_array($this->filter) && array_key_exists('state', $this->filter))) {
            $query->leftJoin(['ir' => 'item_rtdata'], 'i.itemid = ir.itemid');
        }

        if ($outPut != 'count') {
            if ($this->outputIsRequested('state', $outPut)) {
                $query->addSelect(['ir.state']);
            }
            if ($this->outputIsRequested('error', $outPut)) {
                /*
                 * SQL func COALESCE use for template items because they don't have record
                 * in item_rtdata table and DBFetch convert null to '0'
                 */
                $query->addSelect([ZSqlHelper::dbConditionCoalesce('ir.error', '', 'error')]);
            }

            // add filter fields
            if ($this->outputIsRequested('formula', $this->selectFilter)
                || $this->outputIsRequested('eval_formula', $this->selectFilter)
                || $this->outputIsRequested('conditions', $this->selectFilter)) {
                $query->addSelect(['i.formula']);
                $query->addSelect(['i.evaltype']);
            }
            if ($this->outputIsRequested('evaltype', $this->selectFilter)) {
                $query->addSelect(['i.evaltype']);

            }

            if ($this->selectHosts !== null) {
                $query->addSelect(['i.hostid']);
            }
        }
    }

    public function addRelations(array $models): array
    {
        $result = parent::addRelations($models);
        $result = ArrayHelper::index($result, 'itemid');
        $itemIds = array_keys($result);
        // adding items
        if (!is_null($this->selectItems)) {
            if ($this->selectItems != API_OUTPUT_COUNT) {
                $items = [];
                $relationMap = $this->createRelationMap($result, 'parent_itemid', 'itemid', 'item_discovery');
                $related_ids = $relationMap->getRelatedIds();

                if ($related_ids) {
                    $items = ItemHelper::getItemPrototypes([
                        'output' => $this->selectItems,
                        'itemids' => $related_ids,
                        'nopermissions' => true,
                        'preservekeys' => true
                    ]);
                }

                $result = $relationMap->mapMany($result, $items, 'items', $this->limitSelects);
            } else {
                $items = ItemHelper::getItemPrototypes([
                    'discoveryids' => $itemIds,
                    'nopermissions' => true,
                    'countOutput' => true,
                    'groupCount' => true
                ]);

                $items = prs_toHash($items, 'parent_itemid');
                foreach ($result as $itemid => $item) {
                    $result[$itemid]['items'] = array_key_exists($itemid, $items) ? $items[$itemid]['rowscount'] : '0';
                }
            }
        }

        // adding triggers
        if (!is_null($this->selectTriggers)) {
            if ($this->selectTriggers != API_OUTPUT_COUNT) {
                $triggers = [];
                $relationMap = new RelationMap();
                $rows = (new Query())->select(['id.parent_itemid', 'f.triggerid'])
                    ->from(['id' => 'item_discovery', 'i' => 'items', 'f' => 'functions'])
                    ->where(['id.parent_itemid' => $itemIds])
                    ->andWhere('id.itemid=i.itemid')
                    ->andWhere('i.itemid=f.itemid')
                    ->all();
                foreach ($rows as $relation) {
                    $relationMap->addRelation($relation['parent_itemid'], $relation['triggerid']);
                }

                $related_ids = $relationMap->getRelatedIds();

                if ($related_ids) {
                    $triggers = TriggerHelper::getTriggerPrototypes([
                        'output' => $this->selectTriggers,
                        'triggerids' => $related_ids,
                        'preservekeys' => true
                    ]);
                }

                $result = $relationMap->mapMany($result, $triggers, 'triggers', $this->limitSelects);
            } else {
                $triggers = TriggerHelper::getTriggerPrototypes([
                    'discoveryids' => $itemIds,
                    'countOutput' => true,
                    'groupCount' => true
                ]);
                $triggers = prs_toHash($triggers, 'parent_itemid');

                foreach ($result as $itemid => $item) {
                    $result[$itemid]['triggers'] = array_key_exists($itemid, $triggers)
                        ? $triggers[$itemid]['rowscount']
                        : '0';
                }
            }
        }

        // adding graphs

        // adding hosts
        if ($this->selectHostPrototypes !== null) {
            if ($this->selectHostPrototypes !== API_OUTPUT_COUNT) {
                $hostPrototypes = [];
				$relationMap = $this->createRelationMap($result, 'parent_itemid', 'hostid', 'host_discovery');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
                    $hostPrototypes = HostHelper::getHostPrototypes([
						'output' => $this->selectHostPrototypes,
						'hostids' => $related_ids,
						'nopermissions' => true,
						'preservekeys' => true
                    ]);
				}

				$result = $relationMap->mapMany($result, $hostPrototypes, 'hostPrototypes', $this->limitSelects);
            } else {
                $hostPrototypes = HostHelper::getHostPrototypes([
					'discoveryids' => $itemIds,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
                ]);
                $hostPrototypes = prs_toHash($hostPrototypes, 'parent_itemid');

				foreach ($result as $itemid => $item) {
					$result[$itemid]['hostPrototypes'] = array_key_exists($itemid, $hostPrototypes)
						? $hostPrototypes[$itemid]['rowscount']
						: '0';
				}
            }
        }

        if ($this->selectFilter !== null) {
            $formulaRequested = $this->outputIsRequested('formula', $this->selectFilter);
            $evalFormulaRequested = $this->outputIsRequested('eval_formula', $this->selectFilter);
            $conditionsRequested = $this->outputIsRequested('conditions', $this->selectFilter);

            $filters = [];
            foreach ($result as $rule) {
                $filters[$rule['itemid']] = [
                    'evaltype' => $rule['evaltype'],
                    'formula' => $rule['formula'] ?? ''
                ];
            }

            // adding conditions
            if ($formulaRequested || $evalFormulaRequested || $conditionsRequested) {
                $conditions = (new Query())->select(['item_conditionid', 'macro', 'value', 'itemid', 'operator'])
                    ->from('item_condition')
                    ->where(['itemid' => $itemIds])
                    ->indexBy('item_conditionid')
                    ->all();
                $relationMap = $this->createRelationMap($conditions, 'itemid', 'item_conditionid');

                $filters = $relationMap->mapMany($filters, $conditions, 'conditions');

                foreach ($filters as &$filter) {
                    // in case of a custom expression - use the given formula
                    if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
                        $formula = $filter['formula'];
                    } // in other cases - generate the formula automatically
                    else {
                        // sort the conditions by macro before generating the formula
                        $conditions = prs_toHash($filter['conditions'], 'item_conditionid');
                        $conditions = order_macros($conditions, 'macro');

                        $formulaConditions = [];
                        foreach ($conditions as $condition) {
                            $formulaConditions[$condition['item_conditionid']] = $condition['macro'];
                        }
                        $formula = CConditionHelper::getFormula($formulaConditions, $filter['evaltype']);
                    }

                    // generate formulaids from the effective formula
                    $formulaIds = CConditionHelper::getFormulaIds($formula);
                    foreach ($filter['conditions'] as &$condition) {
                        $condition['formulaid'] = $formulaIds[$condition['item_conditionid']];
                    }
                    unset($condition);

                    // generated a letter based formula only for rules with custom expressions
                    if ($formulaRequested && $filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
                        $filter['formula'] = CConditionHelper::replaceNumericIds($formula, $formulaIds);
                    }

                    if ($evalFormulaRequested) {
                        $filter['eval_formula'] = CConditionHelper::replaceNumericIds($formula, $formulaIds);
                    }
                }
                unset($filter);
            }

            // add filters to the result
            foreach ($result as &$rule) {
                $rule['filter'] = $filters[$rule['itemid']];
            }
            unset($rule);
        }

        // Add LLD macro paths.
        if ($this->selectLLDMacroPaths !== null && $this->selectLLDMacroPaths != API_OUTPUT_COUNT) {
            $lld_macro_paths = (new Query())->select($this->outputExtend($this->selectLLDMacroPaths, ['itemid', 'lld_macro_pathid']))
                ->from('lld_macro_path')
                ->where(['itemid' => $itemIds])
                ->all();

            foreach ($result as &$lld_macro_path) {
                $lld_macro_path['lld_macro_paths'] = [];
            }
            unset($lld_macro_path);

            foreach ($lld_macro_paths as $lld_macro_path) {
                $itemid = $lld_macro_path['itemid'];

                if (!$this->outputIsRequested('lld_macro_pathid', $this->selectLLDMacroPaths)) {
                    unset($lld_macro_path['lld_macro_pathid']);
                }
                unset($lld_macro_path['itemid']);

                $result[$itemid]['lld_macro_paths'][] = $lld_macro_path;
            }
        }

        // add overrides
        if ($this->selectOverrides !== null && $this->selectOverrides != API_OUTPUT_COUNT) {
            $ovrd_fields = ['itemid', 'lld_overrideid'];
            $filter_requested = $this->outputIsRequested('filter', $this->selectOverrides);
            $operations_requested = $this->outputIsRequested('operations', $this->selectOverrides);

            if ($filter_requested) {
                $ovrd_fields = array_merge($ovrd_fields, ['formula', 'evaltype']);
            }

            $columns = array_diff($this->outputExtend($this->selectOverrides, $ovrd_fields), ['filter', 'operations']);
            $overrides = (new Query())->select($columns)
                ->from('lld_override')
                ->where(['itemid' => $itemIds])
                ->indexBy('lld_overrideid')
                ->all();

            if ($filter_requested && $overrides) {
                $conditions = (new Query())->select(['lld_override_conditionid', 'macro', 'value', 'lld_overrideid', 'operator'])
                    ->from('lld_override_condition')
                    ->where(['lld_overrideid' => array_keys($overrides)])
                    ->orderBy(['lld_override_conditionid' => SORT_ASC])
                    ->indexBy('lld_override_conditionid')
                    ->all();

                $relation_map = $this->createRelationMap($conditions, 'lld_overrideid', 'lld_override_conditionid');

                foreach ($overrides as &$override) {
                    $override['filter'] = [
                        'evaltype' => $override['evaltype'],
                        'formula' => $override['formula']
                    ];
                    unset($override['evaltype'], $override['formula']);
                }
                unset($override);

                $overrides = $relation_map->mapMany($overrides, $conditions, 'conditions');

                foreach ($overrides as &$override) {
                    $override['filter'] += ['conditions' => $override['conditions']];
                    unset($override['conditions']);

                    if ($override['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
                        $formula = $override['filter']['formula'];
                    } else {
                        $conditions = prs_toHash($override['filter']['conditions'], 'lld_override_conditionid');
                        $conditions = order_macros($conditions, 'macro');
                        $formula_conditions = [];

                        foreach ($conditions as $condition) {
                            $formula_conditions[$condition['lld_override_conditionid']] = $condition['macro'];
                        }

                        $formula = CConditionHelper::getFormula($formula_conditions, $override['filter']['evaltype']);
                    }

                    $formulaids = CConditionHelper::getFormulaIds($formula);

                    foreach ($override['filter']['conditions'] as &$condition) {
                        $condition['formulaid'] = $formulaids[$condition['lld_override_conditionid']];
                        unset($condition['lld_override_conditionid'], $condition['lld_overrideid']);
                    }
                    unset($condition);

                    if ($override['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
                        $override['filter']['formula'] = CConditionHelper::replaceNumericIds($formula, $formulaids);
                        $override['filter']['eval_formula'] = $override['filter']['formula'];
                    } else {
                        $override['filter']['eval_formula'] = CConditionHelper::replaceNumericIds($formula,
                            $formulaids
                        );
                    }
                }
                unset($override);
            }

            if ($operations_requested && $overrides) {
                $operations = (new Query())->select(['lld_override_operationid', 'lld_overrideid', 'operationobject', 'operator', 'value'])
                    ->from('lld_override_operation')
                    ->where(['lld_overrideid' => array_keys($overrides)])
                    ->orderBy(['lld_override_operationid' => SORT_ASC])
                    ->indexBy('lld_override_operationid')
                    ->all();

                if ($operations) {
                    $opdiscover = (new Query())->select(['lld_override_operationid', 'discover'])
                        ->from('lld_override_opdiscover')
                        ->where(['lld_override_operationid' => array_keys($operations)])
                        ->all();

                    $item_prototype_objectids = [];
                    $trigger_prototype_objectids = [];
                    $host_prototype_objectids = [];

                    foreach ($operations as $operation) {
                        switch ($operation['operationobject']) {
                            case OPERATION_OBJECT_ITEM_PROTOTYPE:
                                $item_prototype_objectids[$operation['lld_override_operationid']] = true;
                                break;

                            case OPERATION_OBJECT_TRIGGER_PROTOTYPE:
                                $trigger_prototype_objectids[$operation['lld_override_operationid']] = true;
                                break;

                            case OPERATION_OBJECT_HOST_PROTOTYPE:
                                $host_prototype_objectids[$operation['lld_override_operationid']] = true;
                                break;
                        }
                    }

                    if ($item_prototype_objectids || $trigger_prototype_objectids || $host_prototype_objectids) {
                        $opstatus = (new Query())->select(['lld_override_operationid', 'status'])
                            ->from('lld_override_opstatus')
                            ->where(['lld_override_operationid' => array_keys(
                                $item_prototype_objectids + $trigger_prototype_objectids + $host_prototype_objectids
                            )])
                            ->all();
                    }

                    if ($item_prototype_objectids) {
                        $ophistory = (new Query())->select(['lld_override_operationid', 'history'])
                            ->from('lld_override_ophistory')
                            ->where(['lld_override_operationid' => array_keys($item_prototype_objectids)])
                            ->all();
                        $optrends = (new Query())->select(['lld_override_operationid', 'trends'])
                            ->from('lld_override_optrends')
                            ->where(['lld_override_operationid' => array_keys($item_prototype_objectids)])
                            ->all();
                        $opperiod = (new Query())->select(['lld_override_operationid', 'delay'])
                            ->from('lld_override_opperiod')
                            ->where(['lld_override_operationid' => array_keys($item_prototype_objectids)])
                            ->all();
                    }

                    if ($trigger_prototype_objectids) {
                        $opseverity = (new Query())->select(['lld_override_operationid', 'severity'])
                            ->from('lld_override_opseverity')
                            ->where(['lld_override_operationid' => array_keys($trigger_prototype_objectids)])
                            ->all();
                    }

                    if ($trigger_prototype_objectids || $host_prototype_objectids || $item_prototype_objectids) {
                        $optag = (new Query())->select(['lld_override_operationid', 'tag', 'value'])
                            ->from('lld_override_optag')
                            ->where(['lld_override_operationid' => array_keys(
                                $trigger_prototype_objectids + $host_prototype_objectids + $item_prototype_objectids
                            )])
                            ->all();
                    }

                    if ($host_prototype_objectids) {
                        $optemplate = (new Query())->select(['lld_override_operationid', 'templateid'])
                            ->from('lld_override_optemplate')
                            ->where(['lld_override_operationid' => array_keys($host_prototype_objectids)])
                            ->all();
                        $opinventory = (new Query())->select(['lld_override_operationid', 'inventory_mode'])
                            ->from('lld_override_opinventory')
                            ->where(['lld_override_operationid' => array_keys($host_prototype_objectids)])
                            ->all();
                    }

                    foreach ($operations as &$operation) {
                        $lld_override_operationid = $operation['lld_override_operationid'];

                        if ($item_prototype_objectids || $trigger_prototype_objectids || $host_prototype_objectids) {
                            foreach ($opstatus as $row) {
                                if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
                                    $operation['opstatus']['status'] = $row['status'];
                                }
                            }
                        }

                        foreach ($opdiscover as $row) {
                            if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
                                $operation['opdiscover']['discover'] = $row['discover'];
                            }
                        }

                        if ($item_prototype_objectids) {
                            foreach ($ophistory as $row) {
                                if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
                                    $operation['ophistory']['history'] = $row['history'];
                                }
                            }

                            foreach ($optrends as $row) {
                                if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
                                    $operation['optrends']['trends'] = $row['trends'];
                                }
                            }

                            foreach ($opperiod as $row) {
                                if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
                                    $operation['opperiod']['delay'] = $row['delay'];
                                }
                            }
                        }

                        if ($trigger_prototype_objectids) {
                            foreach ($opseverity as $row) {
                                if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
                                    $operation['opseverity']['severity'] = $row['severity'];
                                }
                            }
                        }

                        if ($trigger_prototype_objectids || $host_prototype_objectids || $item_prototype_objectids) {
                            foreach ($optag as $row) {
                                if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
                                    $operation['optag'][] = ['tag' => $row['tag'], 'value' => $row['value']];
                                }
                            }
                        }

                        if ($host_prototype_objectids) {
                            foreach ($optemplate as $row) {
                                if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
                                    $operation['optemplate'][] = ['templateid' => $row['templateid']];
                                }
                            }

                            foreach ($opinventory as $row) {
                                if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
                                    $operation['opinventory']['inventory_mode'] = $row['inventory_mode'];
                                }
                            }
                        }
                    }
                    unset($operation);
                }

                $relation_map = $this->createRelationMap($operations, 'lld_overrideid', 'lld_override_operationid');

                $overrides = $relation_map->mapMany($overrides, $operations, 'operations');
            }

            foreach ($result as &$row) {
                $row['overrides'] = [];

                foreach ($overrides as $override) {
                    if (bccomp($override['itemid'], $row['itemid']) == 0) {
                        unset($override['itemid'], $override['lld_overrideid']);

                        if ($operations_requested) {
                            foreach ($override['operations'] as &$operation) {
                                unset($operation['lld_override_operationid'], $operation['lld_overrideid']);
                            }
                            unset($operation);
                        }

                        $row['overrides'][] = $override;
                    }
                }
            }
            unset($row);
        }

        return $result;
    }

    /**
     * @param $models
     * @return array
     * @throws Exception
     */
    protected function visible($models): array
    {
        $models = ItemHelper::expandItemNamesWithMasterItems($models, 'items');
        $parentTemplates = ItemHelper::getItemParentTemplates($models, PRS_FLAG_DISCOVERY_RULE);
        $linkTemplate = [];
        foreach ($parentTemplates['links'] as $itemId => $link) {
            $link['name'] = $parentTemplates['templates'][$link['hostid']]['name'];
            $linkTemplate[$itemId] = $link;
        }
        foreach ($models as &$model) {
            $model['parent_template'] = $linkTemplate[$model['itemid']] ?? [];
        }
        unset($model);
        return $models;
    }
}