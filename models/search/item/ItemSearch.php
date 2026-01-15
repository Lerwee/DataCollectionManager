<?php

namespace app\customs\zapi\models\search\item;

use app\common\helpers\ArrayHelper;
use app\common\helpers\SqlHelper;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\TagHelper;
use app\customs\zapi\common\helpers\TriggerHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\modules\libzbx\models\Items;
use yii\db\Exception;
use yii\db\Query;

/**
 * Class ItemSearch
 * @package app\customs\zapi\models\search
 */
class ItemSearch extends BaseItemSearch
{
    public $groupids = null;
    public $templateids = null;
    public $hostids = null;
    public $proxyids = null;
    public $itemids = null;
    public $interfaceids = null;
    public $graphids = null;
    public $triggerids = null;
    public $webitems = null;
    public $inherited = null;
    public $templated = null;
    public $monitored = null;
    public $editable = false;
    public $nopermissions = null;
    public $group = null;
    public $host = null;
    public $with_triggers = null;
    public $evaltype = TAG_EVAL_TYPE_AND_OR;
    public $tags = null;
    public $tag_values = null;
    // filter
    public $filter = [];
    public $search = null;
    public $searchByAny = null;
    public $startSearch = false;
    public $excludeSearch = false;
    public $searchWildcardsEnabled = null;

    public $visible = false;

    protected $sortColumns = ['itemid', 'name', 'key_', 'delay', 'history', 'trends', 'type', 'status'];

    public function rules()
    {
        return [
            [[
                'groupids', 'templateids', 'hostids', 'proxyids', 'itemids', 'interfaceids', 'graphids', 'triggerids', 'webitems',
                'inherited', 'templated', 'monitored', 'editable', 'nopermissions', 'group', 'host', 'with_triggers', 'evaltype',
                'tags', 'tag_values', 'filter', 'search', 'searchByAny', 'startSearch', 'excludeSearch',
                'searchWildcardsEnabled', 'output', 'countOutput', 'groupCount', 'selectHosts', 'selectInterfaces', 'selectTags', 'selectTriggers',
                'selectGraphs', 'selectDiscoveryRule', 'selectItemDiscovery', 'selectPreprocessing', 'selectValueMap',
                'sortfield', 'sortorder', 'is_all', 'preservekeys'
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
        $flag = array_key_exists('flags', $this->filter) && (is_null($this->filter['flags']) || !prs_empty($this->filter['flags']));
        $query = (new Query())
            ->select(['i.itemid'])
            ->andFilterWhere(!is_null($this->webitems) ? [] : ['<>', 'i.type', ITEM_TYPE_HTTPTEST])
            ->andFilterWhere(!$flag ? ['i.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]] : []);

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

        if (!is_null($this->interfaceids)) {
            $where[] = ['i.interfaceid' => filter_integer((array)$this->interfaceids)];

            if ($this->groupCount) {
                $groupBy['i'] = 'i.interfaceid';
            }
        }

        if (!is_null($this->groupids)) {
            $fromTables['hg'] = 'hosts_groups';
            $query->andWhere(['hg.groupid' => filter_integer((array)$this->groupids)]);
            $where['hgi'] = 'hg.hostid=i.hostid';

            if ($this->groupCount) {
                $groupBy['hg'] = 'hg.groupid';
            }
        }

        if (!is_null($this->proxyids)) {
            $fromTables['h'] = 'hosts';
            $where[] = ['h.proxy_hostid' => filter_integer((array)$this->proxyids)];
            $where['hi'] = 'h.hostid=i.hostid';

            if ($this->groupCount) {
                $groupBy['h'] = 'h.proxy_hostid';
            }
        }

        if (!is_null($this->triggerids)) {
            $fromTables['f'] = 'functions';
            $where[] = ['f.triggerid' => filter_integer((array)$this->triggerids)];
            $where['if'] = 'i.itemid=f.itemid';
        }

        if (!empty($this->tags) && is_array($this->tags)) {
            $where[] = TagHelper::setWhereCondition($this->tags, $this->evaltype, 'i', 'item_tag', 'itemid');
        }

        if (!empty($this->tag_values)) {
            $subQuery = new \yii\db\Query();
            $itemIds = $subQuery->select('itemid')->from('item_tag')->where(['value' => $this->tag_values])->column();
            $query->andWhere(SqlHelper::whereIn('i.itemid', array_unique($itemIds)));
        }

        if (!is_null($this->graphids)) {
            $fromTables['gi'] = 'graphs_items';
            $where[] = ['gi.graphid' => filter_integer((array)$this->graphids)];
            $where['igi'] = 'i.itemid=gi.itemid';
        }

        if (!is_null($this->proxyids)) {
            $fromTables['h'] = 'hosts';
            $where[] = ['h.proxy_hostid' => filter_integer((array)$this->proxyids)];
            $where['hi'] = 'h.hostid=i.hostid';
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

            if (array_key_exists('history', $this->filter) && $this->filter['history'] !== null) {
                $this->filter['history'] = getTimeUnitFilters($this->filter['history']);
            }

            if (array_key_exists('trends', $this->filter) && $this->filter['trends'] !== null) {
                $this->filter['trends'] = getTimeUnitFilters($this->filter['trends']);
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

        // group
        if (!is_null($this->group)) {
            $fromTables['g'] = 'hstgrp';
            $fromTables['hg'] = 'hosts_groups';
            $where['ghg'] = 'g.groupid=hg.groupid';
            $where['hgi'] = 'hg.hostid=i.hostid';
            $where[] = ['g.name' => $this->group];
        }

        // host
        if (!is_null($this->host)) {
            $fromTables['h'] = 'hosts';
            $where['hi'] = 'h.hostid=i.hostid';
            $where[] = ['h.host' => $this->host];
        }

        // with_triggers
        if (!is_null($this->with_triggers)) {
            if ($this->with_triggers == 1) {
                $where[] = 'EXISTS (' .
                    'SELECT NULL' .
                    ' FROM functions ff,triggers t' .
                    ' WHERE i.itemid=ff.itemid' .
                    ' AND ff.triggerid=t.triggerid' .
                    ' AND t.flags IN (' . PRS_FLAG_DISCOVERY_NORMAL . ',' . PRS_FLAG_DISCOVERY_CREATED . ')' .
                    ')';
            } else {
                $where[] = 'NOT EXISTS (' .
                    'SELECT NULL' .
                    ' FROM functions ff,triggers t' .
                    ' WHERE i.itemid=ff.itemid' .
                    ' AND ff.triggerid=t.triggerid' .
                    ' AND t.flags IN (' . PRS_FLAG_DISCOVERY_NORMAL . ',' . PRS_FLAG_DISCOVERY_CREATED . ')' .
                    ')';
            }
        }
        foreach ($where as $condition) {
            $query->andWhere($condition);
        }
        $query->from($fromTables + ['i' => Items::tableName()]);
        $query->groupBy($groupBy);
        $this->applyQueryOutputOptions($query,  Items::tableName(), 'i', $this->countOutput ? 'count' : $this->output);
        $this->applyQuerySortOptions($query,  Items::tableName(), 'i', $this->sortfield, $this->sortorder);
        if ($this->countOutput) {
            return $provider;
        }
        $models = $this->format($provider->getModels());
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
        $models = $this->addRelatedObjects($models);
        foreach ($models as &$item) {
            if (array_key_exists('query_fields', $item)) {
                $query_fields = ($item['query_fields'] !== '') ? json_decode($item['query_fields'], true) : [];
                $item['query_fields'] = json_last_error() ? [] : $query_fields;
            }

            if (array_key_exists('headers', $item)) {
                $item['headers'] = ItemHelper::headersStringToArray($item['headers']);
            }
        }
        unset($item);
        return $this->visible ? $this->visible($models) : $models;
    }


    protected function addRelatedObjects(array $models)
    {
        $models = ArrayHelper::index($models, 'itemid');
        $itemIds = array_keys($models);
        $models = parent::addRelations($models);

        // adding interfaces
        if ($this->selectInterfaces !== null && $this->selectInterfaces != API_OUTPUT_COUNT) {
            //
        }

        // adding triggers
        if (!is_null($this->selectTriggers)) {
            if ($this->selectTriggers != API_OUTPUT_COUNT) {
                $itemTriggerMap = $this->setRelationMap($itemIds, 'functions', 'itemid', 'triggers', 'triggerid', $this->selectTriggers);
                foreach ($models as &$model) {
                    $triggers = $itemTriggerMap[$model['itemid']] ?? [];
                    if (!is_null($this->limitSelects)) {
                        order_result($triggers, 'description');
                    }
                    $model['triggers'] = is_numeric($this->limitSelects) ? array_slice($triggers, 0, $this->limitSelects) : $triggers;;
                }
            } else {
                $itemTriggerCount = (new Query())->select(['total', 'itemid'])
                    ->from('triggers')
                    ->where(['itemid' => $itemIds])
                    ->groupBy('itemid')
                    ->indexBy('itemid')
                    ->all();
                foreach ($models as &$model) {
                    $model['triggers'] = $itemTriggerCount[$model['itemid']]['total'] ?? 0;
                }
            }
        }

        // adding graphs
        if (!is_null($this->selectGraphs)) {
            if ($this->selectGraphs != API_OUTPUT_COUNT) {
                $itemGraphMap = $this->setRelationMap($itemIds, 'items', 'itemid', 'graphs', 'graphid', $this->selectGraphs);
                foreach ($models as &$model) {
                    $graphs = $itemGraphMap[$model['itemid']] ?? [];
                    if (!is_null($this->limitSelects)) {
                        order_result($graphs, 'name');
                    }
                    $model['triggers'] = is_numeric($this->limitSelects) ? array_slice($graphs, 0, $this->limitSelects) : $graphs;
                }
            } else {
                $itemGraphCount = (new Query())->select(['total', 'itemid'])
                    ->from('graphs')
                    ->where(['itemid' => $itemIds])
                    ->groupBy('itemid')
                    ->indexBy('itemid')
                    ->all();
                foreach ($models as &$model) {
                    $model['graphs'] = $itemGraphCount[$model['itemid']]['total'] ?? 0;
                }
            }
        }

        // adding discoveryrule
        if ($this->selectDiscoveryRule !== null && $this->selectDiscoveryRule != API_OUTPUT_COUNT) {
            $discoveryRules = [];
            $relationMaps = [];
            $dbRules = (new Query())->select(['id1.itemid', 'id2.parent_itemid'])
                ->from(['id1' => 'item_discovery', 'id2' => 'item_discovery', 'i' => 'items'])
                ->where(['id1.itemid' => $itemIds])
                ->andWhere('id1.parent_itemid=id2.itemid')
                ->andWhere('i.itemid=id1.itemid')
                ->andWhere(['i.flags' => PRS_FLAG_DISCOVERY_CREATED])
                ->all();
            foreach ($dbRules as $dbRule) {
                $relationMaps[$dbRule['itemid']] = $dbRule['parent_itemid'];
            }
            $dbRules = (new Query())->select(['id.itemid', 'id.parent_itemid'])
                ->from(['id' => 'item_discovery', 'i' => 'items'])
                ->where(['id.itemid' => $itemIds])
                ->andWhere('i.itemid=id.itemid')
                ->andWhere(['i.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE])
                ->all();
            foreach ($dbRules as $dbRule) {
                $relationMaps[$dbRule['itemid']] = $dbRule['parent_itemid'];
            }

            if ($relationMaps) {
                $discoveryRules = Items::find()
                    ->select((!$this->selectDiscoveryRule || $this->selectDiscoveryRule == API_OUTPUT_EXTEND) ? '*' : $this->selectDiscoveryRule)
                    ->where(['itemid' => $relationMaps])
                    ->indexBy('itemid')
                    ->asArray()->all();
            }
            foreach ($models as &$model) {
                $parentId = $relationMaps[$model['itemid']] ?? 0;
                $model['discoveryRule'] = $discoveryRules[$parentId] ?? [];
            }
            unset($model);
        }


        // adding item discovery
        if ($this->selectItemDiscovery !== null) {
            $itemDiscoveries = (new Query())->select($this->outputExtend($this->selectItemDiscovery, ['itemdiscoveryid', 'itemid']))
                ->from(['item_discovery'])
                ->where(['itemid' => $itemIds])
                ->indexBy('itemid')
                ->all();

            $itemDiscoveries = $this->unsetExtraFields($itemDiscoveries, ['itemid', 'itemdiscoveryid'],
                $this->selectItemDiscovery
            );
            foreach ($models as &$model) {
                $model['itemDiscovery'] = $itemDiscoveries[$model['itemid']] ?? [];
            }
            unset($model);
        }
        
        // Adding item tags.
        if ($this->selectTags !== null) {
            $this->selectTags = ($this->selectTags !== API_OUTPUT_EXTEND)
                ? (array)$this->selectTags
                : ['tag', 'value'];

            $this->selectTags = array_intersect(['tag', 'value'], (array)$this->selectTags);
            $tags = (new Query())->select(array_merge((array)$this->selectTags, ['itemid']))
                ->from('item_tag')
                ->where(['itemid' => $itemIds])
                ->all();
            $tags = ArrayHelper::index($tags, null, 'itemid');

            foreach ($models as &$model) {
                $model['tags'] = array_map(function ($tag) {
                    unset($tag['itemid']);
                    return $tag;
                }, $tags[$model['itemid']] ?? []);
            }
            unset($model);
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

            if ($this->selectHosts !== null) {
                $query->addSelect(['i.hostid']);
            }

            if ($this->selectInterfaces !== null) {
                $query->addSelect(['i.interfaceid']);
            }

            if ($this->selectValueMap !== null) {
                $query->addSelect(['i.valuemapid']);
            }

            if ($this->outputIsRequested('lastclock', $outPut)
                || $this->outputIsRequested('lastns', $outPut)
                || $this->outputIsRequested('lastvalue', $outPut)
                || $this->outputIsRequested('prevvalue', $outPut)) {
                $query->addSelect(['i.value_type']);
            }
        }
    }

    /**
     * @param $models
     * @return array
     * @throws Exception
     */
    protected function visible($models): array
    {
        $models = ItemHelper::expandItemNamesWithMasterItems($models, 'items');
        $parentTemplates = ItemHelper::getItemParentTemplates($models, PRS_FLAG_DISCOVERY_NORMAL);
        $linkTemplate = [];
        foreach ($parentTemplates['links'] as $itemId => $link) {
            $link['name'] = $parentTemplates['templates'][$link['hostid']]['name'];
            $linkTemplate[$itemId] = $link;
        }
        $itemTriggerIds = [];
        foreach ($models as $model) {
            $itemTriggerIds = array_merge($itemTriggerIds, ArrayHelper::getColumn($model['triggers'], 'triggerid'));
        }
        $itemTriggers = TriggerHelper::getTriggers([
            'triggerids' => $itemTriggerIds,
            'output' => ['triggerid', 'description', 'expression', 'recovery_mode', 'recovery_expression', 'priority',
                'status', 'state', 'error', 'templateid', 'flags'
            ],
            'selectHosts' => ['hostid', 'name', 'host'],
            'preservekeys' => true
        ]);
        $triggerParentTemplates = TriggerHelper::getTriggerParentTemplates($itemTriggers, PRS_FLAG_DISCOVERY_NORMAL);
        $triggerTemplates = [];
        foreach ($triggerParentTemplates['links'] as $triggerId => $link) {
            $hosts = [];
            foreach ($link['hostids'] as $hostId) {
                $hosts[] = $triggerParentTemplates['templates'][$hostId];
            }
            $triggerTemplates[$triggerId] = ['triggerid' => $link['triggerid'], 'hosts' => $hosts];
        }
        foreach ($itemTriggers as $triggerId => &$trigger) {
            $trigger['parent_template'] = $triggerTemplates[$triggerId] ?? [];
        }
        unset($trigger);

        foreach ($models as &$model) {
            $model['parent_template'] = $linkTemplate[$model['itemid']] ?? [];
            $triggerIds = ArrayHelper::getColumn($model['triggers'], 'triggerid');
            $model['triggers'] = array_values(array_intersect_key($itemTriggers, array_flip($triggerIds)));
            $hostStatus = $model['hosts'][0]['status'] ?? 1;
            $model['check_able'] = (int)(in_array($model['type'], ItemHelper::checkNowAllowedTypes()) && $hostStatus == HOST_STATUS_MONITORED && $model['status'] == ITEM_STATUS_ACTIVE);
        }
        unset($model);
        return $models;
    }
}