<?php

namespace app\customs\zapi\common\managers\base;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\managers\GraphManager;
use app\customs\zapi\common\managers\GraphPrototypeManager;
use app\modules\libzbx\models\zbx\Graphs;
use app\modules\libzbx\models\zbx\GraphsItems;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\HostsTemplates;
use app\modules\libzbx\models\zbx\Items;
use yii\db\Expression;
use yii\db\Query;

class GraphGeneral extends BaseManager
{
    /**
     * Inherit template graphs from template to host.
     *
     * @param array $data
     */
    public function syncTemplates(array $data): void
    {
        $output = [
            'g.graphid', 'g.name', 'g.width', 'g.height', 'g.yaxismin', 'g.yaxismax', 'g.templateid', 'g.show_work_period',
            'g.show_triggers', 'g.graphtype', 'g.show_legend', 'g.show_3d', 'g.percent_left', 'g.percent_right', 'g.ymin_type',
            'g.ymax_type', 'g.ymin_itemid', 'g.ymax_itemid'
        ];

        $query = new Query();

        $query->from([
            'g' => Graphs::tableName(),
            'gi' => GraphsItems::tableName(),
            'i' => Items::tableName()
        ]);
        $query->where('gi.graphid=g.graphid')
            ->andWhere('i.itemid=gi.itemid');

        $query->andWhere(SqlHelper::whereIn('{{i}}.hostid', $data['templateids']));

        if ($this instanceof GraphPrototypeManager) {
            $output[] = 'g.discover';
            $query->andWhere(['i.flags' => PRS_FLAG_DISCOVERY_PROTOTYPE]);
        }

        $query->indexBy('graphid');

        if ($graphs = $query->all()) {

            $graphsItems = GraphsItems::find()
                ->where(SqlHelper::whereIn('graphid', array_keys($graphs)))
                ->select(['itemid', 'drawtype', 'sortorder', 'color', 'yaxisside', 'calc_fnc', 'type', 'graphid'])
                ->asArray()
                ->all();

            $buf = [];
            foreach ($graphsItems as $graphsItem) {
                $buf[$graphsItem['graphid']][] = array_diff_key($graphsItem, ['graphid' => 1]);
            }
            unset($graphsItem);
            unset($graphsItems);

            foreach ($graphs as &$graph) {
                $graph['gitems'] = $buf[$graph['graphid']] ?? [];
            }
            unset($graph);

            $this->inherit($graphs, $data['hostids']);
        }
    }

    /**
     * Returns visible host name. Can be used for error reporting.
     *
     * @static
     *
     * @param string|int $hostId
     *
     * @return string
     */
    private static function getHostName($hostId): string
    {
        return Hosts::find()->select(['name'])->where(['hostid' => $hostId])->scalar();
    }

    /**
     * Adding graph items for selected graphs.
     *
     * @static
     *
     * @param array $graphs
     * @param bool  $with_hostid
     *
     * @return array
     */
    private static function addGraphItems(array $graphs, bool $with_hostid = false): array
    {
        $tables = [
            'gi' => GraphsItems::tableName(),
        ];
        $selects = ['gi.gitemid', 'gi.graphid', 'gi.itemid'];

        $query = new Query();
        if ($with_hostid) {
            $tables['i'] = Items::tableName();
            $selects[] = 'i.hostid';
            $query->where('gi.itemid=i.itemid');
        }
        $query->from($tables)
            ->select($selects);
        $query->andWhere(SqlHelper::whereIn('{{gi}}.graphid', array_keys($graphs)));

        $query->orderBy('{{gi}}.sortorder');


        $db_graph_items = $query->all();

        foreach ($db_graph_items as $db_graph_item) {
            $graphid = $db_graph_item['graphid'];
            unset($db_graph_item['graphid']);

            $graphs[$graphid]['gitems'][] = $db_graph_item;
        }

        return $graphs;
    }

    /**
     * Updates the children of the graph on the given hosts and propagates the inheritance to the child hosts.
     *
     * @param array      $graphs   An array of graphs to inherit. Each graph must contain all graph properties including
     *                             "gitems" property.
     * @param array|null $hostids  An array of hosts to inherit to; if set to null, the graphs will be inherited to all
     *                             linked hosts or templates.
     * @throws APIException
     */
    protected function inherit(array $graphs, array $hostids = null): void
    {
        $graphs = array_column($graphs, null, 'graphid');

        if ($hostids === null) {
            /*
			 * From the passed graphs we are able to inherit only those, which are template graphs and templates of
			 * which are linked at least to one host. There we try to find the graphs which meet these conditions.
			 */
            $query = new Query();
            $query->from([
                'gi' => GraphsItems::tableName(),
                'i' => Items::tableName(),
                'h' => Hosts::tableName(),
                'ht' => HostsTemplates::tableName(),
                'h2' => Hosts::tableName()
            ]);
            $query->where('gi.itemid=i.itemid')
                ->andWhere('i.hostid=h.hostid')
                ->andWhere('h.hostid=ht.templateid')
                ->andWhere('ht.hostid=h2.hostid');
            $query->andWhere(SqlHelper::whereIn('{{gi}}.graphid', array_keys($graphs)))
                ->andWhere(['h.status' => HOST_STATUS_TEMPLATE])
                ->andWhere(['h2.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]]);
            $query->select(['graphid' => new Expression('DISTINCT gi.graphid')])
                ->indexBy('graphid');

            $graphIds = $query->column();

            // Based on the found graphs, we leave only graphs that is possible to inherit.
            $graphs = array_intersect_key($graphs, $graphIds);

            if (!$graphs) {
                return;
            }
        }

        $same_name_graphs = [];
        $itemids = [];

        foreach ($graphs as $graphid => $graph) {
            $same_name_graphs[$graph['name']][] = $graphid;

            if ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymin_itemid'] != 0) {
                $itemids[$graph['ymin_itemid']] = true;
            }

            if ($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymax_itemid'] != 0) {
                $itemids[$graph['ymax_itemid']] = true;
            }

            foreach ($graph['gitems'] as $gitem) {
                $itemids[$gitem['itemid']] = true;
            }
        }

        $itemids = array_keys($itemids);

        $graph_templateids = [];
        $templateids = [];

        $query = new Query();
        $query->from([
            'gi' => GraphsItems::tableName(),
            'i' => Items::tableName(),
        ]);
        $query->where('gi.itemid=i.itemid')
            ->andWhere(SqlHelper::whereIn('gi.graphid', array_keys($graphs)));
        $query->select([new Expression('DISTINCT gi.graphid'), 'i.hostid']);

        $db_graph_templates = $query->all();

        foreach ($db_graph_templates as $db_graph_template) {
            $graph_templateids[$db_graph_template['graphid']] = $db_graph_template['hostid'];
            $templateids[$db_graph_template['hostid']] = true;
        }

        $templateids_hosts = [];

        $query = new Query();
        $query->from([
            'ht' => HostsTemplates::tableName(),
            'h' => Hosts::tableName(),
        ]);
        $query->where('ht.hostid=h.hostid')
            ->andWhere(SqlHelper::whereIn('ht.templateid', array_keys($templateids)))
            ->andWhere(['h.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]]);

        if ($hostids !== null) {
            $query->andWhere(SqlHelper::whereIn('ht.hostid', $hostids));
        }

        $query->select(['ht.templateid', 'ht.hostid']);

        $db_host_templates = $query->all();

        foreach ($db_host_templates as $db_host_template) {
            $templateids_hosts[$db_host_template['templateid']][$db_host_template['hostid']] = true;
        }

        foreach ($same_name_graphs as $name => $graphids) {
            if (count($graphids) > 1) {
                $_templateids = [];

                foreach ($graphids as $graphid) {
                    $_templateids[] = $graph_templateids[$graphid];
                }

                $_templateids_count = count($_templateids);

                for ($i = 0; $i < $_templateids_count - 1; $i++) {
                    for ($j = $i + 1; $j < $_templateids_count; $j++) {
                        $same_hosts = array_intersect_key(
                            $templateids_hosts[$_templateids[$i]],
                            $templateids_hosts[$_templateids[$j]]
                        );

                        if ($same_hosts) {
                            $err = t('zapi', 'Graph "{src_name}" already exists on "{dst_name}".', ['src_name' => $name, 'dst_name' => self::getHostName(key($same_hosts))]);
                            throw new ValidateException(60750101, $err);
                        }
                    }
                }
            }
        }

        /*
		 * In case when all equivalent items to graphs templates items exists on all hosts, to which they are linked,
		 * there will be collected relations between template items and these equivalents on hosts.
		 */
        $item_links = [];

        $query = new Query();
        $query->from([
            'src' => Items::tableName(),
            'dest' => Items::tableName(),
        ]);
        $query->where('src.itemid=dest.templateid')
            ->andWhere(SqlHelper::whereIn('{{src}}.itemid', $itemids));
        if ($hostids !== null) {
            $query->andWhere(SqlHelper::whereIn('{{dest}}.hostid', $hostids));
        }
        $query->select([
            'src_itemid' => 'src.itemid',
            'dest_itemid' => 'dest.itemid',
            'dest_hostid' => 'dest.hostid'
        ]);

        $db_items = $query->all();

        foreach ($db_items as $db_item) {
            $item_links[$db_item['src_itemid']][$db_item['dest_hostid']] = $db_item['dest_itemid'];
        }

        $chd_graphs_tpl = [];
        $chd_graphs_name = [];

        // Preparing list of child graphs by graph templateid.
        $query = new Query();
        $query->from([
            'g' => Graphs::tableName(),
            'gi' => GraphsItems::tableName(),
            'i' => Items::tableName()
        ]);
        $query->where('g.graphid=gi.graphid')
            ->andWhere('gi.itemid=i.itemid')
            ->andWhere(SqlHelper::whereIn('{{g}}.templateid', array_keys($graphs)));
        if ($hostids !== null) {
            $query->andWhere(SqlHelper::whereIn('{{i}}.hostid', $hostids));
        }
        $query->select(['graphid' => new Expression('DISTINCT gi.graphid'), 'g.name', 'g.templateid', 'i.hostid']);
        $query->indexBy('graphid');
        $chd_graphs = $query->all();

        if ($chd_graphs) {
            $chd_graphs = self::addGraphItems($chd_graphs);

            foreach ($chd_graphs as $chd_graph) {
                $chd_graphs_tpl[$chd_graph['hostid']][$chd_graph['templateid']] = array_intersect_key(
                    $chd_graph,
                    array_flip(['graphid', 'name', 'gitems'])
                );
            }
        }

        $hostids_by_name = [];

        // Preparing list of child graphs by graph name.
        foreach ($graph_templateids as $graphid => $templateid) {
            foreach (array_keys($templateids_hosts[$templateid]) as $hostid) {
                if (
                    !array_key_exists($hostid, $chd_graphs_tpl)
                    || !array_key_exists($graphid, $chd_graphs_tpl[$hostid])
                ) {
                    $hostids_by_name[$graphs[$graphid]['name']][] = $hostid;
                }
            }
        }

        $chd_graphs = [];

        $subSelect = new Expression('NULL');

        foreach ($hostids_by_name as $name => $_hostids) {
            $flags = $this instanceof GraphManager
                ? [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]
                : [PRS_FLAG_DISCOVERY_PROTOTYPE];

            $query = Graphs::find()
                ->alias('g')
                ->select(['g.graphid', 'g.name', 'g.templateid', 'g.flags']);
            $query->where(['g.name' => $name])
                ->andWhere(['g.flags' => $flags]);

            $subQuery = new Query();
            $subQuery->select($subSelect);
            $subQuery->from([
                'gi' => GraphsItems::tableName(),
                'i' => Items::tableName()
            ]);
            $subQuery->where('g.graphid=gi.graphid')
                ->andWhere('gi.itemid=i.itemid')
                ->andWhere(SqlHelper::whereIn('i.hostid', $_hostids));
            $query->andWhere(['EXISTS', $subQuery]);
            $query->indexBy('graphid')->asArray();

            $chd_graphs += $query->all();
        }

        if ($chd_graphs) {
            $chd_graphs = self::addGraphItems($chd_graphs);

            foreach ($chd_graphs as $chd_graph) {
                $hostid = $chd_graph['gitems'][0]['hostid'];

                if ($chd_graph['templateid'] != 0) {
                    $err = t('zapi', 'Graph "{src_name}" already exists on "{dsc_name}" (inherited from another template).', [
                        'src_name' => $chd_graph['name'],
                        'dst_name' => self::getHostName($hostid)
                    ]);
                    throw new ValidateException(60750101, $err);
                } elseif ($this instanceof GraphManager && $chd_graph['flags'] & PRS_FLAG_DISCOVERY_CREATED) {
                    $err = t('zapi', 'Graph "{src_name}" already exists on "{dsc_name}" as a graph created from graph prototype.', [
                        'src_name' => $chd_graph['name'],
                        'dst_name' => self::getHostName($hostid)
                    ]);
                    throw new ValidateException(60750101, $err);
                }

                $chd_graphs_name[$hostid][$chd_graph['name']] = array_intersect_key(
                    $chd_graph,
                    array_flip(['graphid', 'name', 'gitems'])
                );
            }
        }

        $ins_graphs = [];
        $upd_graphs = [];
        $upd_hostids_by_name = [];

        foreach ($graphs as $graphid => $graph) {
            $templateid = $graph_templateids[$graphid];

            foreach (array_keys($templateids_hosts[$templateid]) as $hostid) {
                $chd_graph = null;

                if (
                    array_key_exists($hostid, $chd_graphs_tpl)
                    && array_key_exists($graphid, $chd_graphs_tpl[$hostid])
                ) {
                    $chd_graph = $chd_graphs_tpl[$hostid][$graphid];

                    /*
					 * If template graph name was changed, we collect all that names to check whether graphs with the
					 * same name already exists on child hosts/templates.
					 */
                    if ($graph['name'] !== $chd_graph['name']) {
                        $upd_hostids_by_name[$graph['name']][] = $hostid;
                    }

                    $_graph = ['graphid' => $chd_graph['graphid'], 'templateid' => $graphid] + $graph;
                } elseif (
                    array_key_exists($hostid, $chd_graphs_name)
                    && array_key_exists($graph['name'], $chd_graphs_name[$hostid])
                ) {
                    $chd_graph = $chd_graphs_name[$hostid][$graph['name']];
                    $chd_graph_itemids = array_column($chd_graph['gitems'], 'itemid');

                    if (count($graph['gitems']) !== count($chd_graph['gitems'])) {
                        $err = t('zapi', 'Graph "{src_name}" already exists on "{dsc_name}" (items are not identical).', [
                            'src_name' => $graph['name'],
                            'dst_name' => self::getHostName($hostid)
                        ]);
                        throw new ValidateException(60750101, $err);
                    }

                    foreach ($graph['gitems'] as $gitem) {
                        $index = array_search($item_links[$gitem['itemid']][$hostid], $chd_graph_itemids);

                        if ($index === false) {
                            $err = t('zapi', 'Graph "{src_name}" already exists on "{dsc_name}" (items are not identical).', [
                                'src_name' => $graph['name'],
                                'dst_name' => self::getHostName($hostid)
                            ]);
                            throw new ValidateException(60750101, $err);
                        }

                        unset($chd_graph_itemids[$index]);
                    }

                    $_graph = ['graphid' => $chd_graph['graphid'], 'templateid' => $graphid] + $graph;
                } else {
                    $_graph = ['templateid' => $graphid] + array_diff_key($graph, ['graphid' => true]);
                }

                $_graph['uuid'] = '';

                if ($_graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $_graph['ymin_itemid'] != 0) {
                    $_graph['ymin_itemid'] = $item_links[$_graph['ymin_itemid']][$hostid];
                }

                if ($_graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $_graph['ymax_itemid'] != 0) {
                    $_graph['ymax_itemid'] = $item_links[$_graph['ymax_itemid']][$hostid];
                }

                CArrayHelper::sort($_graph['gitems'], ['sortorder']);

                foreach ($_graph['gitems'] as &$gitem) {
                    $gitem['itemid'] = $item_links[$gitem['itemid']][$hostid];

                    if ($chd_graph !== null && $chd_graph['gitems']) {
                        $gitem['gitemid'] = array_shift($chd_graph['gitems'])['gitemid'];
                    }
                }
                unset($gitem);

                if ($chd_graph !== null) {
                    $upd_graphs[] = $_graph;
                } else {
                    $ins_graphs[] = $_graph;
                }
            }
        }

        // Check if graph with a new name already exists on the child host.
        if ($upd_hostids_by_name) {

            $query = new Query();
            $query->select(['i.hostid', 'g.name']);
            $query->from([
                'g' => Graphs::tableName(),
                'gi' => GraphsItems::tableName(),
                'i' => Items::tableName()
            ]);
            $query->where('gi.graphid=g.graphid')
                ->andWhere('i.itemid=gi.itemid');
            $sql_where = ['OR'];
            foreach ($upd_hostids_by_name as $name => $_hostids) {
                $sql_where[] = ['i.hostid' => $_hostids, 'g.name' => $name];
            }
            $query->andWhere($sql_where);

            $query->limit(1);

            if ($db_graph = $query->one()) {
                $err = t('zapi', 'Graph "{src_name}" already exists on "{dsc_name}".', [
                    'src_name' => $db_graph['name'],
                    'dst_name' => self::getHostName($db_graph['hostid'])
                ]);
                throw new ValidateException(60750101, $err);
            }
        }

        if ($ins_graphs) {
            $this->createReal($ins_graphs);
        }

        if ($upd_graphs) {
            $this->updateReal($upd_graphs);
        }

        $this->inherit(array_merge($ins_graphs + $upd_graphs));
    }

    /**
     * Creates new graphs.
     *
     * @param array $graphs
     */
    protected function createReal(array &$graphs)
    {
        $graphIds = DB::insert(Graphs::tableName(), $graphs);
        $graph_items = [];

        // Collect graph_items to insert.
        foreach ($graphs as $key => $graph) {
            $sort_order = 0;

            foreach ($graph['gitems'] as $graph_item) {
                $graph_item['graphid'] = $graphIds[$key];

                if (!array_key_exists('sortorder', $graph_item)) {
                    $graph_item['sortorder'] = $sort_order;
                }

                $graph_items[] = $graph_item;

                $sort_order++;
            }
        }

        $graphs_itemsids = DB::insert(GraphsItems::tableName(), $graph_items);

        // Set id for graphs and graph items.
        $i = 0;
        foreach ($graphs as $key => &$graph) {
            $graph['graphid'] = $graphIds[$key];

            foreach ($graph['gitems'] as &$graph_item) {
                $graph_item['gitemid'] = $graphs_itemsids[$i++];
            }
            unset($graph_item);
        }
        unset($graph);
    }

    /**
     * Updates the graphs.
     *
     * @param array $graphs
     *
     * @return string
     */
    protected function updateReal(array $graphs)
    {
        $data = [];
        foreach ($graphs as $graph) {
            unset($graph['gitems']);

            $data[] = ['values' => $graph, 'where' => ['graphid' => $graph['graphid']]];
        }
        DB::update(Graphs::tableName(), $data);

        $db_graph_items = GraphsItems::find()
            ->select(['gitemid', 'itemid', 'drawtype', 'sortorder', 'color', 'yaxisside', 'calc_fnc', 'type'])
            ->where(SqlHelper::whereIn('graphid', array_column($graphs, 'graphid')))
            ->indexBy('gitemid')
            ->asArray()
            ->all();
        $ins_graph_items = [];
        $upd_graph_items = [];

        foreach ($graphs as $graph) {
            $sort_order = 0;

            foreach ($graph['gitems'] as $graph_item) {
                // Update an existing item.
                if (
                    array_key_exists('gitemid', $graph_item)
                    && array_key_exists($graph_item['gitemid'], $db_graph_items)
                ) {
                    $db_graph_item = $db_graph_items[$graph_item['gitemid']];
                    $upd_graph_item = DB::getUpdatedValues(GraphsItems::tableName(), $graph_item, $db_graph_item);

                    if ($upd_graph_item) {
                        $upd_graph_items[] = [
                            'values' => $upd_graph_item,
                            'where' => ['gitemid' => $graph_item['gitemid']]
                        ];
                    }

                    unset($db_graph_items[$graph_item['gitemid']]);
                }
                // Adding a new item.
                else {
                    $graph_item['graphid'] = $graph['graphid'];

                    if (!array_key_exists('sortorder', $graph_item)) {
                        $graph_item['sortorder'] = $sort_order;
                    }

                    $ins_graph_items[] = $graph_item;

                    $sort_order++;
                }
            }
        }

        if ($ins_graph_items) {
            DB::insert(GraphsItems::tableName(), $ins_graph_items);
        }

        if ($upd_graph_items) {
            DB::update(GraphsItems::tableName(), $upd_graph_items);
        }

        if ($db_graph_items) {
            DB::delete(GraphsItems::tableName(), ['gitemid' => array_keys($db_graph_items)]);
        }
    }
}
