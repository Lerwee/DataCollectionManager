<?php

namespace app\customs\zapi\models\search\item;

use app\common\helpers\ArrayHelper;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\TriggerHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\modules\libzbx\models\Items;
use yii\db\Exception;
use yii\db\Query;

/**
 * Class ItemPrototypeSearch
 * @package app\customs\zapi\models\search\item
 */
class ItemPrototypeSearch extends BaseItemSearch
{
    public $groupids = null;
    public $templateids = null;
    public $hostids = null;
    public $itemids = null;
    public $discoveryids = null;
    public $graphids = null;
    public $triggerids = null;
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

    public $visible = false;

    protected $sortColumns = ['itemid', 'name', 'key_', 'delay', 'history', 'trends', 'type', 'status'];

    public function rules(): array
    {
        return [
            [[
                'groupids', 'templateids', 'hostids', 'itemids',
                'discoveryids', 'graphids', 'triggerids', 'inherited', 'templated',
                'monitored', 'editable', 'nopermissions',
                // filter
                'filter', 'search', 'searchByAny', 'startSearch', 'excludeSearch', 'searchWildcardsEnabled',
                // output
                'output', 'selectHosts', 'selectTriggers', 'selectGraphs', 'selectDiscoveryRule', 'selectPreprocessing', 'selectTags',
                'selectValueMap', 'countOutput', 'groupCount', 'preservekeys', 'sortfield', 'sortorder', 'limit', 'limitSelects'
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
            ->andWhere(['i.flags' => [PRS_FLAG_DISCOVERY_PROTOTYPE]]);

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

        // discoveryids
        if (!is_null($this->discoveryids)) {
            $fromTables['id'] = 'item_discovery';
            $query->andWhere(['id.parent_itemid' => filter_integer((array)$this->discoveryids)]);
            $where['idi'] = 'i.itemid=id.itemid';

            if ($this->groupCount) {
                $groupBy['id'] = 'id.parent_itemid';
            }
        }

        // triggerids
        if (!is_null($this->triggerids)) {
            $fromTables['f'] = 'functions';
            $query->andWhere(['f.triggerid' => filter_integer((array)$this->triggerids)]);
            $where['if'] = 'i.itemid=f.itemid';
        }

        if (!is_null($this->graphids)) {
            $fromTables['gi'] = 'graphs_items';
            $where[] = ['gi.graphid' => filter_integer((array)$this->graphids)];
            $where['igi'] = 'i.itemid=gi.itemid';
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
        $query->from($fromTables + ['i' => Items::tableName()]);
        $query->groupBy($groupBy);
        $this->applyQueryOutputOptions($query,  Items::tableName(), 'i', $this->countOutput ? 'count' : $this->output);
        $this->applyQuerySortOptions($query,  Items::tableName(), 'i', $this->sortfield, $this->sortorder);

        if ($this->countOutput) {
            return $provider;
        }
        $models = $provider->getModels();
        if (count($query->from) > 1) {
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
            $list = (new Query())->select(['itemid', 'parent_itemid'])
                ->from('item_discovery')
                ->where(['itemid' => $itemIds])
                ->all();

            $relationMaps = [];
            foreach ($list as $item) {
                $relationMaps[$item['itemid']] = $item['parent_itemid'];
            }
            $discoveryRules = [];
            if ($relationMaps) {
                $discoveryRules = Items::find()
                    ->select($this->selectDiscoveryRule ?: '*')
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
                $model['tags'] = $tags[$model['itemid']] ?? [];
            }
            unset($model);
        }

        return $models;
    }

    /**
     * @param $models
     * @return array
     * @throws Exception
     */
    protected function visible($models): array
    {
        $models = ItemHelper::expandItemNamesWithMasterItems($models, 'itemprototypes');
        $parentTemplates = ItemHelper::getItemParentTemplates($models, PRS_FLAG_DISCOVERY_PROTOTYPE);
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