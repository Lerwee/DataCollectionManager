<?php
namespace app\customs\zapi\models\search\group;

use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\models\search\BaseSearch;
use app\modules\libzbx\models\zbx\Hstgrp;
use yii\db\Expression;
use yii\db\Query;

class TemplateGroupSearch extends BaseSearch
{
    public $groupids    = null;
    public $templateids = null;
    public $graphids    = null;
    public $triggerids  = null;

    public $with_templates                    = false;
    public $with_items                        = false;
    public $with_item_prototypes              = false;
    public $with_simple_graph_items           = false;
    public $with_simple_graph_item_prototypes = false;
    public $with_triggers                     = false;
    public $with_httptests                    = false;
    public $with_monitored_httptests          = false;
    public $with_graphs                       = false;
    public $with_graph_prototypes             = false;

    public $selectTemplates = null;

    public $limitSelects = null;

    public $preservekeys = false;

    public $is_all = false;

    public $sortColumns = ['groupid', 'name'];

    // filter ['groupid', 'name', 'flags', 'uuid']
    // search ['name']

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
            'from'   => ['g' => Hstgrp::tableName()],
            'where'  => [],
            'order'  => [],
        ];

        if (! $this->countOutput && $this->output === API_OUTPUT_EXTEND) {
            $this->output = ['groupid', 'name', 'uuid'];
        }

        $query = new Query();
        $query->select($sqlParts['select']);
        $query->where(['g.type' => HOST_GROUP_TYPE_TEMPLATE_GROUP]);

        // groupids
        if ($this->groupids !== null) {
            $sqlParts['where']['groupid'] = ZSqlHelper::dbConditionInt('g.groupid', filter_integer((array) $this->groupids));
        }

        // templateids
        if ($this->templateids !== null) {
            $sqlParts['from']['hg']   = 'hosts_groups';
            $sqlParts['where'][]      = ZSqlHelper::dbConditionInt('hg.hostid', filter_integer((array) $this->templateids));
            $sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
        }

        // triggerids
        if ($this->triggerids !== null) {
            $sqlParts['from']['hg']   = 'hosts_groups';
            $sqlParts['from']['f']    = 'functions';
            $sqlParts['from']['i']    = 'items';
            $sqlParts['where'][]      = ZSqlHelper::dbConditionInt('f.triggerid', filter_integer((array) $this->triggerids));
            $sqlParts['where']['fi']  = 'f.itemid=i.itemid';
            $sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
            $sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
        }

        // graphids
        if ($this->graphids !== null) {
            $sqlParts['from']['gi']   = 'graphs_items';
            $sqlParts['from']['i']    = 'items';
            $sqlParts['from']['hg']   = 'hosts_groups';
            $sqlParts['where'][]      = ZSqlHelper::dbConditionInt('gi.graphid', filter_integer((array) $this->graphids));
            $sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
            $sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
            $sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
        }

        $sub_sql_common = [];

        // with_templates
        if ($this->with_templates) {
            $sub_sql_common['from']['h']     = 'hosts';
            $sub_sql_common['where']['hg-h'] = 'hg.hostid=h.hostid';
            $sub_sql_common['where'][]       = ['h.status' => HOST_STATUS_TEMPLATE];
        }

        $sub_sql_parts = $sub_sql_common;

        // with_items, with_simple_graph_items
        if ($this->with_items) {
            $sub_sql_parts['from']['i']     = 'items';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where'][]       = ['i.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]];
        } elseif ($this->with_simple_graph_items) {
            $sub_sql_parts['from']['i']     = 'items';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where'][]       = ['i.value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]];
            $sub_sql_parts['where'][]       = ['i.status' => ITEM_STATUS_ACTIVE];
            $sub_sql_parts['where'][]       = ['i.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]];
        }

        // with_triggers
        if ($this->with_triggers) {
            $sub_sql_parts['from']['i']     = 'items';
            $sub_sql_parts['from']['f']     = 'functions';
            $sub_sql_parts['from']['t']     = 'triggers';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where']['i-f']  = 'i.itemid=f.itemid';
            $sub_sql_parts['where']['f-t']  = 'f.triggerid=t.triggerid';
            $sub_sql_parts['where'][]       = ['t.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]];
        }

        // with_httptests
        if ($this->with_httptests) {
            $sub_sql_parts['from']['ht']     = 'httptest';
            $sub_sql_parts['where']['hg-ht'] = 'hg.hostid=ht.hostid';
        }

        // with_graphs
        if ($this->with_graphs) {
            $sub_sql_parts['from']['i']      = 'items';
            $sub_sql_parts['from']['gi']     = 'graphs_items';
            $sub_sql_parts['from']['gr']     = 'graphs';
            $sub_sql_parts['where']['hg-i']  = 'hg.hostid=i.hostid';
            $sub_sql_parts['where']['i-gi']  = 'i.itemid=gi.itemid';
            $sub_sql_parts['where']['gi-gr'] = 'gi.graphid=gr.graphid';
            $sub_sql_parts['where'][]        = ['gr.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]];
        }

        if ($sub_sql_parts) {
            $sub_sql_parts['from']['hg']    = 'hosts_groups';
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
            $sub_sql_parts['from']['i']     = 'items';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where'][]       = ['i.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE];
        } elseif ($this->with_simple_graph_item_prototypes) {
            $sub_sql_parts['from']['i']     = 'items';
            $sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
            $sub_sql_parts['where'][]       = ['i.value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]];
            $sub_sql_parts['where'][]       = ['i.status' => ITEM_STATUS_ACTIVE];
            $sub_sql_parts['where'][]       = ['i.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE];
        }

        // with_graph_prototypes
        if ($this->with_graph_prototypes) {
            $sub_sql_parts['from']['i']      = 'items';
            $sub_sql_parts['from']['gi']     = 'graphs_items';
            $sub_sql_parts['from']['gr']     = 'graphs';
            $sub_sql_parts['where']['hg-i']  = 'hg.hostid=i.hostid';
            $sub_sql_parts['where']['i-gi']  = 'i.itemid=gi.itemid';
            $sub_sql_parts['where']['gi-gr'] = 'gi.graphid=gr.graphid';
            $sub_sql_parts['where'][]        = ['gr.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE];
        }

        if ($sub_sql_parts) {
            $sub_sql_parts['from']['hg']    = 'hosts_groups';
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
                'search'                 => $this->search,
                'startSearch'            => $this->startSearch,
                'excludeSearch'          => $this->excludeSearch,
                'searchWildcardsEnabled' => $this->searchWildcardsEnabled,
                'searchByAny'            => $this->searchByAny,
            ], $query);
        }

        foreach ($sqlParts['where'] as $condition) {
            $query->andWhere($condition);
        }

        $query->from($sqlParts['from']);

        // limit
        $this->applyQueryOutputOptions($query, Hstgrp::tableName(), 'g', $this->countOutput ? 'count' : $this->output);
        $this->applyQuerySortOptions($query, Hstgrp::tableName(), 'g', $this->sortfield, $this->sortorder);

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

        // adding templates
        if ($this->selectTemplates !== null) {
            if ($this->selectTemplates !== API_OUTPUT_COUNT) {
                $templates   = [];
                $relationMap = $this->createRelationMap($result, 'groupid', 'hostid', 'hosts_groups');
                $related_ids = $relationMap->getRelatedIds();

                if ($related_ids) {
                    $templates = TemplateHelper::getTemplates([
                        'output'       => $this->selectTemplates,
                        'hostids'      => $related_ids,
                        'preservekeys' => true,
                    ]);
                    if ($this->limitSelects !== null) {
                        order_result($templates, 'host');
                    }
                }

                $result = $relationMap->mapMany($result, $templates, 'templates', $this->limitSelects);
            } else {
                $templates = TemplateHelper::getTemplates([
                    'groupids'    => $groupIds,
                    'countOutput' => true,
                    'groupCount'  => true,
                ]);
                $templates = prs_toHash($templates, 'groupid');
                foreach ($result as $groupid => $group) {
                    $result[$groupid]['templates'] = array_key_exists($groupid, $templates)
                    ? $templates[$groupid]['rowscount']
                    : 0;
                }
            }
        }

        return $result;
    }
}
