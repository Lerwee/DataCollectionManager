<?php

namespace app\customs\zapi\models\search\trigger;

use app\common\helpers\ArrayHelper;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\TriggerHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\common\macros\CMacrosResolverHelper;
use app\customs\zapi\components\RelationMap;
use app\customs\zapi\models\search\item\ItemSearch;
use app\modules\libzbx\models\Items;
use app\modules\libzbx\models\Triggers;
use yii\db\Exception;
use yii\db\Query;

/**
 * Class TriggerPrototypeSearch
 * @package app\customs\zapi\models\search\trigger
 */
class TriggerPrototypeSearch extends BaseTriggerSearch
{
    protected $sortColumns = ['triggerid', 'description', 'status', 'priority', 'discover'];

    /**
     * @param array $params
     * @param array $options
     * @return ActiveDataProvider
     * @throws Exception
     */
    public function search(array $params = [], array $options = []): ActiveDataProvider
    {
        $this->load($params, '');
        $query = (new Query())->select(['t.triggerid'])
            ->where(['t.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE]);
        $provider = new ActiveDataProvider([
            'query' => $query
        ]);
        if ($this->is_all) {
            $provider->setPagination(false);
        }
        $fromTable = [];
        $where = [];
        $groupBy = [];
        // groupids
        if ($this->groupids !== null) {
            $groupIds = filter_integer((array)$this->groupids);
            sort($groupIds);
            $fromTable['f'] = 'functions';
            $fromTable['i'] = 'items';
            $fromTable['hg'] = 'hosts_groups';
            $where['hgi'] = 'hg.hostid=i.hostid';
            $where['ft'] = 'f.triggerid=t.triggerid';
            $where['fi'] = 'f.itemid=i.itemid';
            $where['groupid'] = ['hg.groupid' => $groupIds];
            if ($this->groupCount) {
                $groupBy['hg'] = 'hg.groupid';
            }
        }

        if (!is_null($this->templateids) || !is_null($this->hostids)) {
            $hostIds = array_merge((array)$this->templateids, (array)$this->hostids);
            $where[] = ['i.hostid' => filter_integer($hostIds)];
            $fromTable['f'] = 'functions';
            $fromTable['i'] = 'items';
            $where['ft'] = 'f.triggerid=t.triggerid';
            $where['fi'] = 'f.itemid=i.itemid';
        }

        // triggerids
        if ($this->triggerids !== null) {
            $where['triggerid'] = ['t.triggerid' => filter_integer((array)$this->triggerids)];
        }

        // itemids
        if ($this->itemids !== null) {
            $fromTable['f'] = 'functions';
            $where['itemid'] = ['f.itemid' => filter_integer((array)$this->itemids)];
            $where['ft'] = 'f.triggerid=t.triggerid';
            if ($this->groupCount) {
                $groupBy['f'] = 'f.itemid';
            }
        }

        // discoveryids
        if ($this->discoveryids !== null) {
            $fromTable['f'] = 'functions';
            $fromTable['id'] = 'item_discovery';
            $where['fid'] = 'f.itemid=id.itemid';
            $where['ft'] = 'f.triggerid=t.triggerid';
            $where[] = ['id.parent_itemid' => $this->discoveryids];
            if ($this->groupCount) {
                $groupBy['id'] = 'id.parent_itemid';
            }
        }

        // functions
        if ($this->functions !== null) {
            $fromTable['f'] = 'functions';
            $where['ft'] = 'f.triggerid=t.triggerid';
            $where[] = ['f.name' => $this->functions];
        }

        // monitored
        if ($this->monitored !== null) {
            $subQuery = (new Query())->select([null])->from(['ff' => 'functions'])
                ->where('ff.triggerid=t.triggerid')
                ->andWhere(['EXISTS', (new Query())->select([null])->from(['ii' => 'items', 'hh' => 'hosts'])
                    ->where('ff.itemid=ii.itemid')
                    ->andWhere('hh.hostid=ii.hostid')
                    ->andWhere(['or', ['<>', 'ii.status', ITEM_STATUS_ACTIVE], ['<>', 'hh.status', HOST_STATUS_MONITORED]])
                ]);
            $where['monitored'] = ['EXISTS', $subQuery];
            $where['status'] = ['t.status' => TRIGGER_STATUS_ENABLED];
        }

        // active
        if ($this->active !== null) {
            $subQuery = (new Query())->select([null])->from(['ff' => 'functions'])
                ->where('ff.triggerid=t.triggerid')
                ->andWhere(['EXISTS', (new Query())->select([null])->from(['ii' => 'items', 'hh' => 'hosts'])
                    ->where('ff.itemid=ii.itemid')
                    ->andWhere('hh.hostid=ii.hostid')
                    ->andWhere(['<>', 'hh.status', HOST_STATUS_MONITORED])
                ]);

            $where['active'] = ['EXISTS', $subQuery];
            $where['status'] = ['t.status' => TRIGGER_STATUS_ENABLED];
        }

        // maintenance
        if ($this->maintenance !== null) {
            $subQuery = (new Query())->select([null])->from(['f' => 'functions'])
                ->where('t.triggerid=f.triggerid')
                ->andWhere(['EXISTS', (new Query())->select([null])->from(['ii' => 'items', 'hh' => 'hosts'])
                    ->where('ff.itemid=ii.itemid')
                    ->andWhere('hh.hostid=ii.hostid')
                    ->andWhere(['hh.maintenance_status' => 1])
                ]);
            $where[] = [$this->maintenance == 0 ? 'NOT EXISTS' : 'EXISTS', $subQuery];
            $where[] = ['t.status' => TRIGGER_STATUS_ENABLED];
        }

        // templated
        if ($this->templated !== null) {
            $fromTable['f'] = 'functions';
            $fromTable['i'] = 'items';
            $fromTable['h'] = 'hosts';
            $where['ft'] = 'f.triggerid=t.triggerid';
            $where['fi'] = 'f.itemid=i.itemid';
            $where['hi'] = 'h.hostid=i.hostid';
            if ($this->templated) {
                $where[] = ['h.status' => HOST_STATUS_TEMPLATE];
            } else {
                $where[] = ['<>', 'h.status', HOST_STATUS_TEMPLATE];
            }
        }

        // inherited
        if ($this->inherited !== null) {
            if ($this->inherited) {
                $where[] = 't.templateid IS NOT NULL';
            } else {
                $where[] = 't.templateid IS NULL';
            }
        }

        // search
        if (is_array($this->search)) {
            ZSqlHelper::zbxDbSearch('triggers t', [
                'search' => $this->search,
                'startSearch' => $this->startSearch,
                'excludeSearch' => $this->excludeSearch,
                'searchWildcardsEnabled' => $this->searchWildcardsEnabled,
                'searchByAny' => $this->searchByAny,
            ], $query);
        }


        // filter
        if ($this->filter === null) {
            $this->filter = [];
        }

        if (is_array($this->filter)) {
            $filter[] = ZSqlHelper::dbFilter('triggers', $this->filter, 't', (bool)$this->searchByAny);
            $filter = array_filter($filter);
            $filterStr = implode((bool)$this->searchByAny ? 'OR' : 'AND', $filter);
            $filter && $where[] = $filterStr;

            if (array_key_exists('host', $this->filter) && $this->filter['host'] !== null) {
                $fromTable['f'] = 'functions';
                $fromTable['i'] = 'items';
                $where['ft'] = 'f.triggerid=t.triggerid';
                $where['fi'] = 'f.itemid=i.itemid';
                $fromTable['hosts'] = 'hosts h';
                $where['hi'] = 'h.hostid=i.hostid';
                $where['host'] = ['h.host' => $this->filter['host']];
            }

            if (array_key_exists('hostid', $this->filter) && $this->filter['hostid'] !== null) {
                $fromTable['f'] = 'functions';
                $fromTable['i'] = 'items';
                $where['ft'] = 'f.triggerid=t.triggerid';
                $where['fi'] = 'f.itemid=i.itemid';
                $where['hostid'] = ['i.hostid', $this->filter['hostid']];
            }
        }

        // group
        if ($this->group !== null) {
            $fromTable['functions'] = 'functions f';
            $fromTable['items'] = 'items i';
            $fromTable['hosts_groups'] = 'hosts_groups hg';
            $fromTable['hstgrp'] = 'hstgrp g';
            $where['ft'] = 'f.triggerid=t.triggerid';
            $where['fi'] = 'f.itemid=i.itemid';
            $where['hgi'] = 'hg.hostid=i.hostid';
            $where['ghg'] = 'g.groupid = hg.groupid';
            $where['group'] = ['g.name' => $this->group];
        }

        // host
        if ($this->host !== null) {
            $fromTable['f'] = 'functions';
            $fromTable['i'] = 'items';
            $fromTable['h'] = 'hosts';
            $where['i'] = ['i.hostid' => $this->hostids];
            $where['ft'] = 'f.triggerid=t.triggerid';
            $where['fi'] = 'f.itemid=i.itemid';
            $where['hi'] = 'h.hostid=i.hostid';
            $where['host'] = ['h.host' => $this->host];
        }

        // min_severity
        if ($this->min_severity !== null) {
            $where[] = ['>=', 't.priority', $this->min_severity];
        }

        foreach ($where as $condition) {
            $query->andWhere($condition);
        }
        $query->groupBy($groupBy)->distinct();
        $query->from($fromTable + ['t' => 'triggers']);
        $this->applyQueryOutputOptions($query, 'triggers', 't', $this->countOutput ? 'count' : $this->output);
        $this->applyQuerySortOptions($query, 'triggers', 't', $this->sortfield, $this->sortorder);
        if ($this->countOutput) {
            return $provider;
        }
        $models = $provider->getModels();
        $models = $this->addRelatedObjects($models);
        $models = $this->format($models);
        if (!empty($options['visible'])) {
            $models = $this->visible($models);
        }
        if ($this->preservekeys) {
            $models = ArrayHelper::index($models, 'triggerid');
        } else {
            $models = array_values($models);
        }
        $provider->setModels($models);
        return $provider;
    }

    /**
     * @param array $models
     * @return array
     */
    protected function format(array $models): array
    {
        // expandDescription
        if ($this->expandDescription !== null && $models && array_key_exists('description', reset($models))) {
            $models = CMacrosResolverHelper::resolveTriggerNames($models);
        }

        // expandComment
        if ($this->expandComment !== null && $models && array_key_exists('comments', reset($models))) {
            $models = CMacrosResolverHelper::resolveTriggerDescriptions($models, ['sources' => ['comments']]);
        }

        // expand expressions
        if ($this->expandExpression !== null && $models) {
            $sources = [];
            if (array_key_exists('expression', reset($models))) {
                $sources[] = 'expression';
            }
            if (array_key_exists('recovery_expression', reset($models))) {
                $sources[] = 'recovery_expression';
            }

            if ($sources) {
                $models = CMacrosResolverHelper::resolveTriggerExpressions($models,
                    ['resolve_usermacros' => true, 'resolve_macros' => true, 'sources' => $sources]
                );
            }
        }

        return $models;
    }


    protected function visible(array $triggers)
    {
        $triggerIds = ArrayHelper::getColumn($triggers, 'triggerid');

        $options = [
            'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'templateid', 'recovery_mode',
                'recovery_expression', 'opdata', 'discover'
            ],
            'selectHosts' => ['hostid', 'host'],
            'selectDependencies' => ['triggerid', 'description'],
            'expandExpression' => 1,
            'selectTags' => ['tag', 'value'],
            'triggerids' => $triggerIds
        ];
        $triggers = TriggerHelper::getTriggerPrototypes($options);

        order_result($triggers, $this->sortfield, $this->sortorder);

        $depTriggerIds = [];
        foreach ($triggers as $trigger) {
            foreach ($trigger['dependencies'] as $depTrigger) {
                $depTriggerIds[$depTrigger['triggerid']] = true;
            }
        }

        if ($depTriggerIds) {
            $depTriggerIds = array_keys($depTriggerIds);
            $options = [
                'output' => ['triggerid', 'description', 'status', 'flags'],
                'selectHosts' => ['hostid', 'name'],
                'triggerids' => $depTriggerIds,
                'filter' => [
                    'flags' => [PRS_FLAG_DISCOVERY_NORMAL]
                ],
                'preservekeys' => true
            ];
            $dependencyTriggers = TriggerHelper::getTriggers($options);

            $options = [
                'output' => ['triggerid', 'description', 'status', 'flags'],
                'selectHosts' => ['hostid', 'name'],
                'triggerids' => $depTriggerIds,
                'preservekeys' => true
            ];
            $dependencyTriggerPrototypes = TriggerHelper::getTriggerPrototypes($options);

            $dependencyTriggers = $dependencyTriggers + $dependencyTriggerPrototypes;

            foreach ($triggers as &$trigger) {
                order_result($trigger['dependencies'], 'description', PRS_SORT_UP);
            }
            unset($trigger);

            foreach ($dependencyTriggers as &$dependencyTrigger) {
                order_result($dependencyTrigger['hosts'], 'name', PRS_SORT_UP);
            }
            unset($dependencyTrigger);
        }
        $parentTemplates = TriggerHelper::getTriggerParentTemplates($triggers, PRS_FLAG_DISCOVERY_PROTOTYPE);
        foreach ($triggers as &$trigger) {
            $deps = [];
            foreach ($trigger['dependencies'] as $dependTrigger) {
                $deps[] = $dependencyTriggers[$dependTrigger['triggerid']];
            }
            $trigger['dependencies'] = $deps;
            $parentHostIds = $parentTemplates['links'][$trigger['triggerid']]['hostids'] ?? [];
            $parentHosts = array_intersect_key($parentTemplates['templates'], array_flip($parentHostIds));
            $trigger['parent_templates'] = array_values($parentHosts);
        }
        unset($trigger);
        return $triggers;
    }

    /**
     * Retrieves and adds additional requested data (options 'selectHosts', 'selectGroups', etc.) to result set.
     *
     * @param array $result
     *
     * @return array
     */
    protected function addRelatedObjects(array $result): array
    {
        $result = ArrayHelper::index($result, 'triggerid');
        $result = parent::addRelatedObjects($result);

        $triggerPrototypeIds = ArrayHelper::getColumn($result, 'triggerid');

        // Add trigger prototype dependencies.
        if ($this->selectDependencies !== null && $this->selectDependencies != API_OUTPUT_COUNT) {
            $dependencies = [];
            $relationMap = new RelationMap();
            $rows = (new Query())->select(['td.triggerid_up', 'td.triggerid_down'])
                ->from(['td' => 'trigger_depends'])
                ->where(['td.triggerid_down' => $triggerPrototypeIds])
                ->all();

            foreach ($rows as $relation) {
                $relationMap->addRelation($relation['triggerid_down'], $relation['triggerid_up']);
            }

            $related_ids = $relationMap->getRelatedIds();

            if ($related_ids) {
                $dependencies = Triggers::find()->select($this->selectDependencies ?: '*')
                    ->where(['triggerid' => $related_ids])
                    ->indexBy('triggerid')
                    ->asArray()->all();
            }

            $result = $relationMap->mapMany($result, $dependencies, 'dependencies');
        }

        // adding items
        if ($this->selectItems !== null && $this->selectItems != API_OUTPUT_COUNT) {
            $relationMap = $this->createRelationMap($result, 'triggerid', 'itemid', 'functions');
            $items = ItemHelper::getItems([
                'output' => $this->selectItems,
                'itemids' => $relationMap->getRelatedIds(),
                'webitems' => true,
                'nopermissions' => true,
                'preservekeys' => true,
                'filter' => ['flags' => null]
            ]);
            $result = $relationMap->mapMany($result, $items, 'items');
        }

        // adding discovery rule
        if ($this->selectDiscoveryRule !== null && $this->selectDiscoveryRule != API_OUTPUT_COUNT) {
            $dbRules = (new Query())->select(['id.parent_itemid', 'f.triggerid'])
                ->from(['id' => 'item_discovery', 'f' => 'functions'])
                ->where(['f.triggerid' => $triggerPrototypeIds])
                ->andWhere('f.itemid=id.itemid')
                ->all();
            $relationMap = new RelationMap();
            foreach ($dbRules as $rule) {
                $relationMap->addRelation($rule['triggerid'], $rule['parent_itemid']);
            }

            $discoveryRules = DiscoverRuleHelper::getDiscoverRules([
                'output' => $this->selectDiscoveryRule,
                'itemids' => $relationMap->getRelatedIds(),
                'nopermissions' => true,
                'preservekeys' => true
            ]);

            $result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
        }

        return $result;
    }

}