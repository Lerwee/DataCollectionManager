<?php

namespace app\customs\zapi\models\search\trigger;

use app\common\helpers\ArrayHelper;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\CSettingsHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\TagHelper;
use app\customs\zapi\common\helpers\TriggerHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\common\macros\CMacrosResolverHelper;
use app\customs\zapi\components\RelationMap;
use app\customs\zapi\models\search\item\ItemSearch;
use app\modules\libzbx\models\Items;
use yii\db\Exception;
use yii\db\Query;

/**
 * Class BaseTriggerSearch
 * @package app\customs\zapi\models\search\trigger
 */
class TriggerSearch extends BaseTriggerSearch
{
    protected $sortColumns = ['triggerid', 'description', 'status', 'priority', 'lastchange', 'hostname'];

    /**
     * @param array $params
     * @param array $options
     * @return ActiveDataProvider
     * @throws Exception
     */
    public function search(array $params = [], array $options = []): ActiveDataProvider
    {
        $this->load($params, '');
        $query = (new Query())->select(['t.triggerid']);
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
            if ($this->groupCount) {
                $groupBy['i'] = 'i.hostid';
            }
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

        // functions
        if ($this->functions !== null) {
            $fromTable['f'] = 'functions';
            $where['ft'] = 'f.triggerid=t.triggerid';
            $where[] = ['f.name' => $this->functions];
        }

        // monitored
        if ($this->monitored !== null) {
            $subQuery = (new Query())->select([null])->from(['f' => 'functions', 'i' => 'items', 'h' => 'items'])
                ->where('t.triggerid=f.triggerid')
                ->andWhere('f.itemid=i.itemid')
                ->andWhere('i.hostid=h.hostid')
                ->andWhere(['or', ['<>', 'i.status', ITEM_STATUS_ACTIVE], ['<>', 'h.status', HOST_STATUS_MONITORED]]);
            $where['monitored'] = ['NOT EXISTS', $subQuery];
            $where['status'] = ['t.status' => TRIGGER_STATUS_ENABLED];
        }

        // active
        if ($this->active !== null) {
            $subQuery = (new Query())->select([null])->from(['f' => 'functions', 'i' => 'items', 'h' => 'items'])
                ->where('t.triggerid=f.triggerid')
                ->andWhere('f.itemid=i.itemid')
                ->andWhere('i.hostid=h.hostid')
                ->andWhere(['<>', 'h.status', HOST_STATUS_MONITORED]);
            $where['active'] = ['NOT EXISTS', $subQuery];
            $where['status'] = ['t.status' => TRIGGER_STATUS_ENABLED];
        }

        // maintenance
        if ($this->maintenance !== null) {
            $subQuery = (new Query())->select([null])->from(['f' => 'functions', 'i' => 'items', 'h' => 'items'])
                ->where('t.triggerid=f.triggerid')
                ->andWhere('f.itemid=i.itemid')
                ->andWhere('i.hostid=h.hostid')
                ->andWhere(['h.maintenance_status' => HOST_MAINTENANCE_STATUS_ON]);
            $where[] = [$this->maintenance == 0 ? 'NOT EXISTS' : 'EXISTS', $subQuery];
            $where[] = ['t.status' => TRIGGER_STATUS_ENABLED];
        }

        // lastChangeSince
        if ($this->lastChangeSince !== null) {
            $where['lastchangesince'] = ['>', 't.lastchange', $this->lastChangeSince];
        }

        // lastChangeTill
        if ($this->lastChangeTill !== null) {
            $where['lastchangetill'] = ['<', 't.lastchange', $this->lastChangeTill];
        }

        // withUnacknowledgedEvents
        if ($this->withUnacknowledgedEvents !== null) {
            $subQuery = (new Query())->select([null])->from(['e' => 'events'])
                ->where('t.triggerid=e.objectid')
                ->andWhere(['e.source' => EVENT_SOURCE_TRIGGERS])
                ->andWhere(['e.object' => EVENT_OBJECT_TRIGGER])
                ->andWhere(['e.value' => TRIGGER_VALUE_TRUE])
                ->andWhere(['e.acknowledged' => EVENT_NOT_ACKNOWLEDGED]);
            $where['unack'] = ['EXISTS', $subQuery];
        }

        // withAcknowledgedEvents
        if ($this->withAcknowledgedEvents !== null) {
            $subQuery = (new Query())->select([null])->from(['e' => 'events'])
                ->where('e.objectid=t.triggerid')
                ->andWhere(['e.source' => EVENT_SOURCE_TRIGGERS])
                ->andWhere(['e.object' => EVENT_OBJECT_TRIGGER])
                ->andWhere(['e.value' => TRIGGER_VALUE_TRUE])
                ->andWhere(['e.acknowledged' => EVENT_NOT_ACKNOWLEDGED]);
            $where['ack'] = ['NOT EXISTS', $subQuery];
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

        // dependent
        if ($this->dependent !== null) {
            $subQuery = (new Query())->select([null])->from(['td' => 'trigger_depends'])
                ->where('td.triggerid_down=t.triggerid');
            if ($this->dependent) {
                $where[] = ['EXISTS', $subQuery];
            } else {
                $where[] = ['NOT EXISTS', $subQuery];
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
            if (!array_key_exists('flags', $this->filter)) {
                $this->filter['flags'] = [
                    PRS_FLAG_DISCOVERY_NORMAL,
                    PRS_FLAG_DISCOVERY_CREATED
                ];
            }
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
            $where['ft'] = 'f.triggerid=t.triggerid';
            $where['fi'] = 'f.itemid=i.itemid';
            $where['hi'] = 'h.hostid=i.hostid';
            $where['host'] = ['h.host' => $this->host];
        }

        // only_true
        if ($this->only_true !== null) {
            $where['ot'] = [
                'or',
                ['t.value' => TRIGGER_VALUE_TRUE],
                ['and', ['t.value' => TRIGGER_VALUE_FALSE], ['>', 't.lastchange', time() - timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::OK_PERIOD))]],
            ];
        }

        // min_severity
        if ($this->min_severity !== null) {
            $where[] = ['>=', 't.priority', $this->min_severity];
        }

        // tags
        if ($this->tags !== null && is_array($this->tags)) {
            $where[] = TagHelper::setWhereCondition($this->tags, $this->evaltype, 't',
                'trigger_tag', 'triggerid'
            );
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

        $result = $this->unsetExtraFields($models, ['state', 'expression'], $this->output);

        // Triggers share table with trigger prototypes. Therefore remove trigger unrelated fields.
        if ($this->outputIsRequested('discover', $this->output)) {
            foreach ($result as &$row) {
                unset($row['discover']);
            }
            unset($row);
        }

        return $result;
    }


    protected function visible(array $triggers)
    {
        $prefetched_triggers = ArrayHelper::index($triggers, 'triggerid');
        $triggerIds = ArrayHelper::getColumn($triggers, 'triggerid');

        $options = [
            'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'error', 'templateid', 'state',
                'recovery_mode', 'recovery_expression', 'value', 'opdata'
            ],
            'selectHosts' => ['hostid', 'host', 'name', 'status'],
            'selectDependencies' => ['triggerid', 'description'],
            'selectDiscoveryRule' => ['itemid', 'name'],
            'selectTriggerDiscovery' => ['ts_delete'],
            'selectTags' => ['tag', 'value'],
            'expandExpression' => true,
            'triggerids' => $triggerIds,
            'preservekeys' => true
        ];

        $triggers = TriggerHelper::getTriggers($options);

        $items = ItemHelper::getItems([
            'output' => ['itemid'],
            'selectTriggers' => ['triggerid'],
            'selectItemDiscovery' => ['ts_delete'],
            'triggerids' => $triggerIds,
            'filter' => ['flags' => PRS_FLAG_DISCOVERY_CREATED]
        ]);

        foreach ($items as $item) {
            $ts_delete = $item['itemDiscovery']['ts_delete'];

            if ($ts_delete == 0) {
                continue;
            }

            foreach (array_column($item['triggers'], 'triggerid') as $triggerid) {
                if (!array_key_exists($triggerid, $triggers)) {
                    continue;
                }

                if (!array_key_exists('ts_delete', $triggers[$triggerid]['triggerDiscovery'])) {
                    $triggers[$triggerid]['triggerDiscovery']['ts_delete'] = $ts_delete;
                } else {
                    $trigger_ts_delete = $triggers[$triggerid]['triggerDiscovery']['ts_delete'];
                    $triggers[$triggerid]['triggerDiscovery']['ts_delete'] = ($trigger_ts_delete > 0)
                        ? min($ts_delete, $trigger_ts_delete)
                        : $ts_delete;
                }
            }
        }

        // We must maintain sort order that is applied on prefetched_triggers array.
        foreach ($triggers as $triggerid => $trigger) {
            $prefetched_triggers[$triggerid] = $trigger;
        }
        $triggers = $prefetched_triggers;

        $dep_triggerids = [];
        foreach ($triggers as $trigger) {
            foreach ($trigger['dependencies'] as $dep_trigger) {
                $dep_triggerids[$dep_trigger['triggerid']] = true;
            }
        }

        $dep_triggers = [];
        if ($dep_triggerids) {
            $dep_triggers = TriggerHelper::getTriggers([
                'output' => ['triggerid', 'description', 'status', 'flags'],
                'selectHosts' => ['hostid', 'name'],
                'triggerids' => array_keys($dep_triggerids),
                'templated' => ($this->filter_value != -1) ? false : null,
                'preservekeys' => true
            ]);
            foreach ($triggers as &$trigger) {
                ArrayHelper::multisort($trigger['dependencies'], 'description', SORT_ASC);
            }
            unset($trigger);

            foreach ($dep_triggers as &$dependencyTrigger) {
                ArrayHelper::multisort($dependencyTrigger['hosts'], 'name', SORT_ASC);
            }
            unset($dependencyTrigger);
        }
        $parentTemplates = TriggerHelper::getTriggerParentTemplates($triggers, PRS_FLAG_DISCOVERY_NORMAL);

        foreach ($triggers as &$trigger) {
            $deps = [];
            foreach ($trigger['dependencies'] as $dependTrigger) {
                $deps[] = $dep_triggers[$dependTrigger['triggerid']] ?? [];
            }
            $trigger['dependencies'] = $deps;
            //上级模板
            $triggerId = $trigger['triggerid'];
            if (!array_key_exists($triggerId, $parentTemplates['links'])) {
                $trigger['parent_templates'] = [];
            } else {
                while (array_key_exists($parentTemplates['links'][$triggerId]['triggerid'], $parentTemplates['links'])) {
                    $triggerId = $parentTemplates['links'][$triggerId]['triggerid'];
                }
                $parentHostIds = $parentTemplates['links'][$triggerId]['hostids'] ?? [];
                $parentHosts = array_intersect_key($parentTemplates['templates'], array_flip($parentHostIds));
                $trigger['parent_templates'] = array_values($parentHosts);
            }
        }
        unset($trigger);
        return array_values($triggers);
    }

    protected function addRelatedObjects(array $result): array
    {
        $result = parent::addRelatedObjects($result);

        if (!$result) {
            return $result;
        }

        $triggerids = ArrayHelper::getColumn($result, 'triggerid');

        // adding trigger dependencies
        if ($this->selectDependencies !== null && $this->selectDependencies != API_OUTPUT_COUNT) {
            $dependencies = [];
            $relationMap = new RelationMap();
            $rows = (new Query())->select(['td.triggerid_up', 'td.triggerid_down'])
                ->from(['td' => 'trigger_depends'])
                ->where(['td.triggerid_down' => $triggerids])
                ->all();
            foreach ($rows as $relation) {
                $relationMap->addRelation($relation['triggerid_down'], $relation['triggerid_up']);
            }

            $related_ids = $relationMap->getRelatedIds();

            if ($related_ids) {
                $dependencies = TriggerHelper::getTriggers([
                    'output' => $this->selectDependencies,
                    'triggerids' => $related_ids,
                    'preservekeys' => true,
                    'expandExpression' => $this->expandExpression
                ]);
            }
            $result = $relationMap->mapMany($result, $dependencies, 'dependencies');
        }

        // adding items
        if ($this->selectItems !== null && $this->selectItems != API_OUTPUT_COUNT) {
            $relationMap = $this->createRelationMap($result, 'triggerid', 'itemid', 'functions');
            $itemModel = new ItemSearch();
            $provider = $itemModel->search([
                'output' => $this->selectItems,
                'itemids' => $relationMap->getRelatedIds(),
                'webitems' => true,
                'nopermissions' => true,
                'preservekeys' => true
            ]);
            $provider->setPagination(false);
            $items = $provider->getModels();

            $result = $relationMap->mapMany($result, $items, 'items');
        }

        // adding discoveryrule
        if ($this->selectDiscoveryRule !== null && $this->selectDiscoveryRule != API_OUTPUT_COUNT) {
            $discoveryRules = [];
            $relationMap = new RelationMap();
            $dbRules = (new Query())->select(['id.parent_itemid', 'td.triggerid'])
                ->from(['td' => 'trigger_discovery', 'id' => 'item_discovery', 'f' => 'functions'])
                ->where(['td.triggerid' => $triggerids])
                ->andWhere('td.parent_triggerid=f.triggerid')
                ->andWhere('f.itemid=id.itemid')
                ->all();
            foreach ($dbRules as $rule) {
                $relationMap->addRelation($rule['triggerid'], $rule['parent_itemid']);
            }

            $related_ids = $relationMap->getRelatedIds();

            if ($related_ids) {
                $discoveryRules = Items::find()
                    ->select($this->selectDiscoveryRule ?: '*')
                    ->where(['itemid' => $related_ids])
                    ->indexBy('itemid')
                    ->asArray()->all();
            }

            $result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
        }

        // adding last event
        if ($this->selectLastEvent !== null) {
            foreach ($result as $triggerId => $trigger) {
                $result[$triggerId]['lastEvent'] = [];
            }

            if (is_array($this->selectLastEvent)) {
                $pkFieldId = $this->pk('events');
                $outputFields = [
                    'objectid' => $this->fieldId('objectid', 'e'),
                    'ns' => $this->fieldId('ns', 'e'),
                    $pkFieldId => $this->fieldId($pkFieldId, 'e')
                ];

                foreach ($this->selectLastEvent as $field) {
                    if ($this->hasField($field, 'events')) {
                        $outputFields[$field] = $this->fieldId($field, 'e');
                    }
                }

                $outputFields = implode(',', $outputFields);
            } else {
                $outputFields = 'e.*';
            }

            $subQuery = (new Query())->select(['e2.source', 'e2.object', 'e2.objectid', 'clock' => 'MAX(clock)'])
                ->from(['e2' => 'events'])
                ->where(['e2.source' => EVENT_SOURCE_TRIGGERS])
                ->andWhere(['e2.object' => EVENT_OBJECT_TRIGGER])
                ->andWhere(['e2.objectid' => $triggerids])
                ->groupBy(['e2.source', 'e2.object', 'e2.objectid']);
            $dbEvents = (new Query())->select($outputFields)
                ->from([
                    'e' => 'events',
                    'e3' => $subQuery
                ])
                ->where('e3.source=e.source')
                ->andWhere('e3.object=e.object')
                ->andWhere('e3.objectid=e.objectid')
                ->andWhere('e3.clock=e.clock')
                ->all();

            // in case there are multiple records with same 'clock' for one trigger, we'll get different 'ns'
            $lastEvents = [];

            foreach ($dbEvents as $dbEvent) {
                $triggerId = $dbEvent['objectid'];
                $ns = $dbEvent['ns'];

                // unset fields, that were not requested
                if (is_array($this->selectLastEvent)) {
                    if (!in_array('objectid', $this->selectLastEvent)) {
                        unset($dbEvent['objectid']);
                    }
                    if (!in_array('ns', $this->selectLastEvent)) {
                        unset($dbEvent['ns']);
                    }
                }

                $lastEvents[$triggerId][$ns] = $dbEvent;
            }

            foreach ($lastEvents as $triggerId => $events) {
                // find max 'ns' for each trigger and that will be the 'lastEvent'
                $maxNs = max(array_keys($events));
                $result[$triggerId]['lastEvent'] = $events[$maxNs];
            }
        }

        // adding trigger discovery
        if ($this->selectTriggerDiscovery !== null && $this->selectTriggerDiscovery !== API_OUTPUT_COUNT) {
            foreach ($result as &$trigger) {
                $trigger['triggerDiscovery'] = [];
            }
            unset($trigger);

            $sql_select = ['triggerid'];
            foreach (['parent_triggerid', 'ts_delete'] as $field) {
                if ($this->outputIsRequested($field, $this->selectTriggerDiscovery)) {
                    $sql_select[] = $field;
                }
            }

            $trigger_discoveries = (new Query())->select($sql_select)
                ->from('trigger_discovery')
                ->where(['triggerid' => $triggerids])
                ->all();

            foreach ($trigger_discoveries as $trigger_discovery) {
                $triggerid = $trigger_discovery['triggerid'];
                unset($trigger_discovery['triggerid']);

                $result[$triggerid]['triggerDiscovery'] = $trigger_discovery;
            }
        }

        return $result;
    }
}