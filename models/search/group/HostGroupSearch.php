<?php

namespace app\customs\zapi\models\search\group;

use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\models\search\BaseSearch;
use app\modules\libzbx\models\zbx\Hstgrp;
use Yii;
use yii\db\Expression;
use yii\db\Query;

class HostGroupSearch extends BaseSearch
{
    public $groupids = null;
    public $hostids = null;
    public $graphids = null;
    public $triggerids = null;
    public $maintenanceids = null;
    public $monitored_hosts;
    public $with_monitored_hosts = false;
    public $real_hosts;
    public $with_hosts = false;
    public $with_items = false;
    public $with_item_prototypes = false;
    public $with_simple_graph_items = false;
    public $with_simple_graph_item_prototypes = false;
    public $with_monitored_items = false;
    public $with_triggers = false;
    public $with_monitored_triggers = false;
    public $with_httptests = false;
    public $with_monitored_httptests = false;
    public $with_graphs = false;
    public $with_graph_prototypes = false;

    public $selectHosts = null;
    public $selectGroupDiscovery = null;
    public $selectDiscoveryRule = null;
    public $selectHostPrototype = null;

    public $limitSelects = null;

    public $preservekeys = false;

    public $is_all = false;

    // filter ['groupid', 'name', 'flags', 'uuid']
    // search ['name']

    protected $sortColumns = ['groupid', 'name'];

    /**
     * @param array $params
     * @return ActiveDataProvider
     */
    public function search(array $params)
    {
        $this->setAttributes($params);

        $output_fields = ['groupid', 'name', 'flags', 'uuid'];

        $sqlParts = [
            'select' => ['hstgrp' => 'g.groupid'],
            'from' => ['g' => Hstgrp::tableName()],
            'where' => [['g.type' => HOST_GROUP_TYPE_HOST_GROUP]],
            'order' => [],
        ];

        if (!$this->countOutput && $this->output === API_OUTPUT_EXTEND) {
            $this->output = ['groupid', 'name', 'flags', 'uuid'];
        }

        $query = new Query();
        $query->select($sqlParts['select']);

        // groupids
        if ($this->groupids !== null) {
            $sqlParts['where']['groupid'] = ZSqlHelper::dbConditionInt('g.groupid', filter_integer((array) $this->groupids));
        }

        // hostids
        if ($this->hostids !== null) {
            $sqlParts['from']['hg'] = 'hosts_groups';
            $sqlParts['where'][] = ZSqlHelper::dbConditionInt('hg.hostid', filter_integer((array) $this->hostids));
            $sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
        }

        // triggerids
        if ($this->triggerids !== null) {
            $sqlParts['from']['hg'] = 'hosts_groups';
            $sqlParts['from']['f'] = 'functions';
            $sqlParts['from']['i'] = 'items';
            $sqlParts['where'][] = ZSqlHelper::dbConditionInt('f.triggerid', filter_integer((array) $this->triggerids));
            $sqlParts['where']['fi'] = 'f.itemid=i.itemid';
            $sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
            $sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
        }

        // graphids
        if ($this->graphids !== null) {
            $sqlParts['from']['gi'] = 'graphs_items';
            $sqlParts['from']['i'] = 'items';
            $sqlParts['from']['hg'] = 'hosts_groups';
            $sqlParts['where'][] = ZSqlHelper::dbConditionInt('gi.graphid', filter_integer((array) $this->graphids));
            $sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
            $sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
            $sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
        }

        // maintenanceids
        if ($this->maintenanceids !== null) {
            $sqlParts['from']['mg'] = 'maintenances_groups';
            $sqlParts['where'][] = ZSqlHelper::dbConditionInt('mg.maintenanceid', filter_integer((array) $this->maintenanceids));
            $sqlParts['where']['hmh'] = 'g.groupid=mg.groupid';
        }

        $sub_sql_common = [];

        if ($this->real_hosts) {
            $this->with_hosts = 1;
        }
        if ($this->monitored_hosts) {
            $this->with_monitored_hosts = 1;
        }

        // with_monitored_hosts, with_hosts
        if ($this->with_monitored_hosts) {
            $sub_sql_common['from']['h'] = 'hosts';
            $sub_sql_common['where']['hg-h'] = 'hg.hostid=h.hostid';
            $sub_sql_common['where'][] = ['h.status' => HOST_STATUS_MONITORED];
        } elseif ($this->with_hosts) {
            $sub_sql_common['from']['h'] = 'hosts';
            $sub_sql_common['where']['hg-h'] = 'hg.hostid=h.hostid';
            $sub_sql_common['where'][] = ['h.status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]];
        }

        $sub_sql_parts = $sub_sql_common;

        // with_items, with_monitored_items, with_simple_graph_items
        if ($this->with_items) {
            $sub_sql_parts['from']['i'] = 'items';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where'][] = ['i.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]];
        } elseif ($this->with_monitored_items) {
            $sub_sql_parts['from']['i'] = 'items';
            $sub_sql_parts['from']['h'] = 'hosts';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where']['hg-h'] = 'hg.hostid=h.hostid';
            $sub_sql_parts['where'][] = ['h.status' => HOST_STATUS_MONITORED];
            $sub_sql_parts['where'][] = ['i.status' => ITEM_STATUS_ACTIVE];
            $sub_sql_parts['where'][] = ['i.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]];
        } elseif ($this->with_simple_graph_items) {
            $sub_sql_parts['from']['i'] = 'items';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where'][] = ['i.value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]];
            $sub_sql_parts['where'][] = ['i.status' => ITEM_STATUS_ACTIVE];
            $sub_sql_parts['where'][] = ['i.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]];
        }

        // with_triggers, with_monitored_triggers
        if ($this->with_triggers) {
            $sub_sql_parts['from']['i'] = 'items';
            $sub_sql_parts['from']['f'] = 'functions';
            $sub_sql_parts['from']['t'] = 'triggers';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where']['i-f'] = 'i.itemid=f.itemid';
            $sub_sql_parts['where']['f-t'] = 'f.triggerid=t.triggerid';
            $sub_sql_parts['where'][] = ['t.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]];
        } elseif ($this->with_monitored_triggers) {
            $sub_sql_parts['from']['i'] = 'items';
            $sub_sql_parts['from']['h'] = 'hosts';
            $sub_sql_parts['from']['f'] = 'functions';
            $sub_sql_parts['from']['t'] = 'triggers';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where']['hg-h'] = 'hg.hostid=h.hostid';
            $sub_sql_parts['where']['i-f'] = 'i.itemid=f.itemid';
            $sub_sql_parts['where']['f-t'] = 'f.triggerid=t.triggerid';
            $sub_sql_parts['where'][] = ['h.status' => HOST_STATUS_MONITORED];
            $sub_sql_parts['where'][] = ['i.status' => ITEM_STATUS_ACTIVE];
            $sub_sql_parts['where'][] = ['t.status' => TRIGGER_STATUS_ENABLED];
            $sub_sql_parts['where'][] = ['t.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]];
        }

        // with_httptests, with_monitored_httptests
        if ($this->with_httptests) {
            $sub_sql_parts['from']['ht'] = 'httptest';
            $sub_sql_parts['where']['hg-ht'] = 'hg.hostid=ht.hostid';
        } elseif ($this->with_monitored_httptests) {
            $sub_sql_parts['from']['ht'] = 'httptest';
            $sub_sql_parts['where']['hg-ht'] = 'hg.hostid=ht.hostid';
            $sub_sql_parts['where'][] = ['ht.status' => HTTPTEST_STATUS_ACTIVE];
        }

        // with_graphs
        if ($this->with_graphs) {
            $sub_sql_parts['from']['i'] = 'items';
            $sub_sql_parts['from']['gi'] = 'graphs_items';
            $sub_sql_parts['from']['gr'] = 'graphs';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where']['i-gi'] = 'i.itemid=gi.itemid';
            $sub_sql_parts['where']['gi-gr'] = 'gi.graphid=gr.graphid';
            $sub_sql_parts['where'][] = ['gr.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]];
        }

        if ($sub_sql_parts) {
            $sub_sql_parts['from']['hg'] = 'hosts_groups';
            $sub_sql_parts['where']['g-hg'] = 'g.groupid=hg.groupid';

            $subQuery = new Query();
            $subQuery->select(new Expression('NULL'))
                ->from($sub_sql_parts['from']);

            foreach ($sub_sql_parts['where'] as $where) {
                $subQuery->andWhere($where);
            }
            $query->andWhere(['EXISTS', $subQuery]);
        }

        $sub_sql_parts = $sub_sql_common;

        // with_item_prototypes, with_simple_graph_item_prototypes
        if ($this->with_item_prototypes) {
            $sub_sql_parts['from']['i'] = 'items';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where'][] = ['i.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE];
        } elseif ($this->with_simple_graph_item_prototypes) {
            $sub_sql_parts['from']['i'] = 'items';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where'][] = ['i.value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]];
            $sub_sql_parts['where'][] = ['i.status' => ITEM_STATUS_ACTIVE];
            $sub_sql_parts['where'][] = ['i.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE];
        }

        // with_graph_prototypes
        if ($this->with_graph_prototypes) {
            $sub_sql_parts['from']['i'] = 'items';
            $sub_sql_parts['from']['gi'] = 'graphs_items';
            $sub_sql_parts['from']['gr'] = 'graphs';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where']['i-gi'] = 'i.itemid=gi.itemid';
            $sub_sql_parts['where']['gi-gr'] = 'gi.graphid=gr.graphid';
            $sub_sql_parts['where'][] = ['gr.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE];
        }

        if ($sub_sql_parts) {
            $sub_sql_parts['from']['hg'] = 'hosts_groups';
            $sub_sql_parts['where']['g-hg'] = 'g.groupid=hg.groupid';

            $subQuery = new Query();
            $subQuery->select(new Expression('NULL'))
                ->from($sub_sql_parts['from']);

            foreach ($sub_sql_parts['where'] as $where) {
                $subQuery->andWhere($where);
            }
            $query->andWhere(['EXISTS', $subQuery]);
        }

        // filter
        if ($this->filter !== null) {
            if ($condition = ZSqlHelper::dbFilter('hstgrp', $this->filter, 'g', (bool) $this->searchByAny)) {
                $query->andWhere($condition);
            }
        }

        // search
        if ($this->search !== null) {
            ZSqlHelper::zbxDbSearch('hstgrp g', [
                'search' => $this->search,
                'startSearch' => $this->startSearch,
                'excludeSearch' => $this->excludeSearch,
                'searchWildcardsEnabled' => $this->searchWildcardsEnabled,
                'searchByAny' => $this->searchByAny,
            ], $query);
        }

        foreach ($sqlParts['where'] as $condition) {
            $query->andWhere($condition);
        }

        $query->from($sqlParts['from']);

        // limit
        $this->applyQueryOutputOptions($query, Hstgrp::tableName(), 'g', $this->countOutput ? 'count' : $this->output);
        $this->applyQuerySortOptions($query, Hstgrp::tableName(), 'g', $this->sortfield, $this->sortorder);

        if (count($query->from) > 1) {
            $query->distinct();
        }

        $provider = new ActiveDataProvider([
            'query' => $query,
        ]);
        if ($this->is_all) {
            $provider->setPagination(false);
        }

        if ($this->countOutput) {
            return $provider;
        }

        if ($this->preservekeys) {
            $query->indexBy('groupid');
        }
        $models = $this->format($provider->getModels());
        $provider->setModels($models);
        return $provider;
    }

    protected function format(array $models)
    {
        if ($models) {
            $models = $this->addRelatedObjects($models);
            $models = $this->unsetExtraFields($models, ['groupid'], $this->output);
        }
        return $this->preservekeys ? $models : array_values($models);
    }

    protected function addRelatedObjects(array $result)
    {
        $result = parent::addRelatedObjects($result);

        $groupIds = array_keys($result);
        sort($groupIds);

        // adding hosts
        if ($this->selectHosts !== null) {
            if ($this->selectHosts !== API_OUTPUT_COUNT) {
                $hosts = [];
                $relationMap = $this->createRelationMap($result, 'groupid', 'hostid', 'hosts_groups');
                $related_ids = $relationMap->getRelatedIds();

                if ($related_ids) {
                    $hosts = HostHelper::getHosts([
                        'output' => $this->selectHosts,
                        'hostids' => $related_ids,
                        'preservekeys' => true,
                    ]);
                    if ($this->limitSelects !== null) {
                        order_result($hosts, 'host');
                    }
                }

                $result = $relationMap->mapMany($result, $hosts, 'hosts', $this->limitSelects);
            } else {
                $hosts = HostHelper::getHosts([
                    'groupids' => $groupIds,
                    'countOutput' => true,
                    'groupCount' => true,
                ]);
                $hosts = prs_toHash($hosts, 'groupid');
                foreach ($result as $groupid => $group) {
                    $result[$groupid]['hosts'] = array_key_exists($groupid, $hosts)
                    ? $hosts[$groupid]['rowscount']
                    : 0;
                }
            }
        }

        // adding discovery rule
        if ($this->selectDiscoveryRule !== null && $this->selectDiscoveryRule != API_OUTPUT_COUNT) {
            // discovered items
            $discoveryRules = Yii::$app->db->createCommand(
                'SELECT gd.groupid,hd.parent_itemid' .
                ' FROM group_discovery gd,group_prototype gp,host_discovery hd' .
                ' WHERE ' . ZSqlHelper::dbConditionInt('gd.groupid', $groupIds) .
                ' AND gd.parent_group_prototypeid=gp.group_prototypeid' .
                ' AND gp.hostid=hd.hostid'
            )->queryAll();

            $relationMap = $this->createRelationMap($discoveryRules, 'groupid', 'parent_itemid');

            $discoveryRules = DiscoverRuleHelper::getDiscoverRules([
                'output' => $this->selectDiscoveryRule,
                'itemids' => $relationMap->getRelatedIds(),
                'preservekeys' => true,
            ]);
            $result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
        }

        // adding host prototype
        if ($this->selectHostPrototype !== null) {
            $db_links = Yii::$app->db->createCommand(
                'SELECT gd.groupid,gp.hostid' .
                ' FROM group_discovery gd,group_prototype gp' .
                ' WHERE ' . ZSqlHelper::dbConditionInt('gd.groupid', $groupIds) .
                ' AND gd.parent_group_prototypeid=gp.group_prototypeid'
            )->queryAll();

            $host_prototypes = HostHelper::getHostPrototypes([
                'output' => $this->selectHostPrototype,
                'hostids' => array_column($db_links, 'hostid'),
                'preservekeys' => true,
            ]);

            foreach ($result as &$row) {
                $row['hostPrototype'] = [];
            }
            unset($row);

            foreach ($db_links as $row) {
                if (array_key_exists($row['hostid'], $host_prototypes)) {
                    $result[$row['groupid']]['hostPrototype'] = $host_prototypes[$row['hostid']];
                }
            }
        }

        // adding group discovery
        if ($this->selectGroupDiscovery !== null) {
            $query = new Query();
            $query->from('group_discovery')
                ->select($this->outputExtend($this->selectGroupDiscovery, ['groupid']))
                ->where(ZSqlHelper::dbConditionId('groupid', $groupIds))
                ->indexBy('groupdiscoveryid');
            $groupDiscoveries = $query->all();
            $relationMap = $this->createRelationMap($groupDiscoveries, 'groupid', 'groupid');

            $groupDiscoveries = $this->unsetExtraFields($groupDiscoveries, ['groupid'], $this->selectGroupDiscovery);
            $result = $relationMap->mapOne($result, $groupDiscoveries, 'groupDiscovery');
        }

        return $result;
    }
}
