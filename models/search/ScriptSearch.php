<?php

namespace app\customs\zapi\models\search;

use app\common\helpers\ArrayHelper;
use app\common\helpers\SqlHelper;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Scripts;
use app\modules\libzbx\models\zbx\HostsGroups;
use app\modules\libzbx\models\zbx\Hstgrp;
use app\modules\libzbx\models\zbx\ScriptParam;
use yii\db\Exception;
use yii\db\Query;

/**
 * Class ScriptSearch
 * @package app\customs\zapi\models\search
 */
class ScriptSearch extends BaseSearch
{
    public $scriptids;
    public $hostids;
    public $groupids;
    public $usrgrpids;
    public $selectGroups;
    public $selectHosts;
    public $selectHostGroups;

    public $selectActions;


    private $action_fields = ['actionid', 'name', 'eventsource', 'status', 'esc_period', 'pause_suppressed',
        'notify_if_canceled', 'pause_symptoms'
    ];
    public $is_all = false;
    protected $sortColumns = ['scriptid', 'name'];

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [[
                'scriptids', 'hostids', 'groupids', 'usrgrpids', 'selectHosts',
                'selectGroups', 'selectHostGroups', 'selectActions', 'is_all'
            ], 'safe']
        ]);
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
        $where = [];
        $query = (new Query())
            ->select(['s.scriptid']);

        $provider = new ActiveDataProvider([
            'query' => $query
        ]);
        if ($this->is_all) {
            $provider->setPagination(false);
        }

        $host_groups = null;
        $host_groups_by_hostids = null;
        $host_groups_by_groupids = null;
        if (!is_null($this->hostids)) {
            $host_groups_by_hostids = (new Query())->from([
                'g' => Hstgrp::tableName(),
                'hg' => HostsGroups::tableName()
            ])->select([
                '{{g}}.[[groupid]]',
                '{{g}}.[[name]]',
            ])->andWhere([
                '{{hg}}.hostid' => $this->hostids
            ])->all() ?: [];
        }

        if (!is_null($this->groupids)) {
            $host_groups_by_groupids = (new Query())->from([
                'g' => Hstgrp::tableName(),
                'hg' => HostsGroups::tableName()
            ])->select([
                '{{g}}.[[groupid]]',
                '{{g}}.[[name]]',
            ])->andWhere([
                '{{g}}.groupid' => $this->groupids
            ])->all() ?: [];
        }
        if ($host_groups_by_groupids !== null && $host_groups_by_hostids !== null) {
            $host_groups = array_intersect_key($host_groups_by_hostids, $host_groups_by_groupids);
        } elseif ($host_groups_by_hostids !== null) {
            $host_groups = $host_groups_by_hostids;
        } elseif ($host_groups_by_groupids !== null) {
            $host_groups = $host_groups_by_groupids;
        }

        if (!is_null($host_groups)) {
            $where[] = '(' . SqlHelper::whereIn('s.groupid', array_keys($host_groups)) . ' OR s.groupid IS NULL)';
        }

        //usrgrpids
        if (!is_null($this->usrgrpids)) {
            $where[] = '(s.usrgrpid IS NULL OR ' . SqlHelper::whereIn('s.usrgrpid', $this->usrgrpids) . ')';
        }

        // scriptids
        if (!is_null($this->scriptids)) {
            $where[] = ['s.scriptid' => filter_integer((array)$this->scriptids)];
        }

        // search
        if (is_array($this->search)) {
            ZSqlHelper::zbxDbSearch('scripts s', [
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
            if (array_key_exists('scope', $this->filter) && $this->filter['scope'] !== null) {
                $where[] = ['s.scope' => $this->filter['scope']];
            }
        }

        foreach ($where as $condition) {
            $query->andWhere($condition);
        }
        $query->from($fromTables + ['s' => Scripts::tableName()]);
        $this->applyQueryOutputOptions($query, Scripts::tableName(), 's', $this->countOutput ? 'count' : $this->output);
        $this->applyQuerySortOptions($query, Scripts::tableName(), 's', $this->sortfield, $this->sortorder);
        $models = $provider->getModels();
        $models = $this->format($models);
        if ($this->preservekeys) {
            $models = ArrayHelper::index($models, 'scriptid');
        } else {
            $models = array_values($models);
        }
        $provider->setModels($models);
        return $provider;
    }

    protected function format($models): array
    {
        $models = $this->addRelations($models);
        $models = $this->unsetExtraFields($models, []);

        $groupNames = [];
        if ($models && !empty(current($models)['groupid'])) {
            $groupids = array_filter(array_column($models, 'groupid'));
            $groupNames = Hstgrp::find()->select('groupid,name')->andWhere(['groupid' => $groupids])->indexBy('groupid')->asArray()->all();
        }

        foreach ($models as &$model) {
            if ($groupNames) {
                $model['group_name'] = !empty($model['groupid']) && !empty($groupNames[$model['groupid']]) ? $groupNames[$model['groupid']]['name'] : '';
            }
        }
        return $models;
    }

    protected function applyQueryOutputOptions(Query &$query, string $tableName, string $tableAlias, $outPut = 'extent', array $options = [])
    {
        parent::applyQueryOutputOptions($query, $tableName, $tableAlias, $outPut, $options);
        if ($outPut != 'count') {
            if (
                $this->selectGroups !== null || $this->selectHostGroups !== null
                || $this->selectHosts !== null
            ) {
                $query->addSelect('s.groupid');
            }
        }
    }

    public function addRelations(array $result): array
    {
        $result = ArrayHelper::index($result, 'scriptid');
        $scriptids = array_keys($result);
        // Adding actions.
        if ($this->selectActions !== null && $this->selectActions !== API_OUTPUT_COUNT) {
            foreach ($result as $scriptid => &$row) {
                $row['actions'] = [];
            }
            unset($row);

            $action_scriptids = [];

            if ($this->outputIsRequested('scope', $this->output)) {
                foreach ($result as $scriptid => $row) {
                    if ($row['scope'] == PRS_SCRIPT_SCOPE_ACTION) {
                        $action_scriptids[] = $scriptid;
                    }
                }
            } else {
                $db_scripts = [];
                foreach ($result as $row) {
                    $db_scripts[] = [
                        'scope' => $row['scope']
                    ];
                }
                $db_scripts = $this->extendFromObjects($result, $db_scripts, ['scope']);

                foreach ($db_scripts as $scriptid => $db_script) {
                    if ($db_script['scope'] == PRS_SCRIPT_SCOPE_ACTION) {
                        $action_scriptids[] = $scriptid;
                    }
                }

                // Remove scope from output, since it's not requested.
                $result = $this->unsetExtraFields($result, ['scope']);
            }

            if ($action_scriptids) {
                if ($this->selectActions === API_OUTPUT_EXTEND) {
                    $action_fields = array_map(function ($field) {
                        return 'a.' . $field;
                    }, $this->action_fields);
                    $action_fields = implode(',', $action_fields);
                } elseif (is_array($this->selectActions)) {
                    $action_fields = $this->selectActions;

                    if (!in_array('actionid', $this->selectActions)) {
                        $action_fields[] = 'actionid';
                    }

                    $action_fields = array_map(function ($field) {
                        return 'a.' . $field;
                    }, $action_fields);
                    $action_fields = implode(',', $action_fields);
                }

                $action_sql = 'SELECT DISTINCT oc.scriptid,' . $action_fields .
                    ' FROM actions a,operations o,opcommand oc' .
                    ' WHERE a.actionid=o.actionid' .
                    ' AND o.operationid=oc.operationid' .
                    ' AND ' . SqlHelper::whereIn('oc.scriptid', $action_scriptids);
                $db_script_actions = \Yii::$app->db->createCommand($action_sql)->queryAll();

                foreach ($result as $scriptid => &$row) {
                    if ($db_script_actions) {
                        foreach ($db_script_actions as $db_script_action) {
                            if (bccomp($db_script_action['scriptid'], $scriptid) == 0) {
                                unset($db_script_action['scriptid']);
                                $row['actions'][] = $db_script_action;
                            }
                        }

                        $row['actions'] = $this->unsetExtraFields(
                            $row['actions'],
                            ['actionid'],
                            $this->selectActions
                        );
                    }
                }
                unset($row);
            }
        }

        if ($this->outputIsRequested('parameters', $this->output)) {
            foreach ($result as $scriptid => $script) {
                $result[$scriptid]['parameters'] = [];
            }

            $db_parameters = ScriptParam::find()->andWhere(['scriptid' => array_keys($result)])
                ->select(['script_paramid', 'scriptid', 'name', 'value'])
                ->asArray()->all();
            if ($db_parameters) {
                foreach ($db_parameters as $db_param) {
                    $result[$db_param['scriptid']]['parameters'][] = [
                        'name' => $db_param['name'],
                        'value' => $db_param['value']
                    ];
                }
            }
        }

        return $this->addRelatedGroupsAndHosts($result);
    }

    /**
     * For each object in $objects the method copies fields listed in $fields that are not present in the target
     * object from the source object.
     *
     * Matching objects in both arrays must have the same keys.
     *
     * @param array $objects
     * @param array $sourceObjects
     *
     * @return array
     */
    protected function extendFromObjects(array $objects, array $sourceObjects, array $fields)
    {
        $fields = array_flip($fields);

        foreach ($objects as $key => &$object) {
            if (isset($sourceObjects[$key])) {
                $object += array_intersect_key($sourceObjects[$key], $fields);
            }
        }
        unset($object);

        return $objects;
    }


    private function addRelatedGroupsAndHosts(array $result, array $hostids = null)
    {
        $is_groups_select = $this->selectGroups !== null;
        $is_hostgroups_select = $this->selectHostGroups !== null;
        $is_hosts_select = $this->selectHosts !== null;

        if (!$is_groups_select && !$is_hostgroups_select && !$is_hosts_select) {
            return $result;
        }
        $groupids = [];
        foreach ($result as $script) {
            if ($script['groupid']) {
                $groupids[] = $groupids;
            }
        }

        if ($this->selectGroups === API_OUTPUT_EXTEND || $this->selectHostGroups === API_OUTPUT_EXTEND) {
            $select_groups = API_OUTPUT_EXTEND;
        } else {
            $select_groups = array_unique(array_merge(
                is_array($this->selectGroups) ? $this->selectGroups : [],
                is_array($this->selectHostGroups) ? $this->selectHostGroups : []
            ));
        }

        $select_groups = $this->outputExtend($select_groups, ['groupid', 'name']);
        $select_groups = array_intersect($select_groups, ['groupid', 'name', 'flags', 'uuid', 'type']);

        $host_groups = Hstgrp::find()->select($select_groups)->indexBy('groupid')->asArray()->all();

        $nested = [];
        foreach ($host_groups as $groupid => $group) {
            $name = $group['name'];

            while (($pos = strrpos($name, '/')) !== false) {
                $name = substr($name, 0, $pos);
                $nested[$name][$groupid] = true;
            }
        }

        $hstgrp_branch = [];
        foreach ($host_groups as $groupid => $group) {
            $hstgrp_branch[$groupid] = [$groupid => true];
            if (array_key_exists($group['name'], $nested)) {
                $hstgrp_branch[$groupid] += $nested[$group['name']];
            }
        }

        if ($is_hosts_select) {
            $query = HostsGroups::find()->select(['hostid', 'groupid'])
                ->andWhere(SqlHelper::whereIn('groupid', array_keys($host_groups)));

            if ($hostids !== null) {
                $query->andWhere(SqlHelper::whereIn('hostid', $hostids));
            }

            $db_group_hosts = $query->asArray()->all();

            $all_hostids = [];
            $group_to_hosts = [];
            foreach ($db_group_hosts as $row) {
                if (!array_key_exists($row['groupid'], $group_to_hosts)) {
                    $group_to_hosts[$row['groupid']] = [];
                }

                $group_to_hosts[$row['groupid']][$row['hostid']] = true;
                $all_hostids[] = $row['hostid'];
            }
            $used_hosts = Hosts::find()->select($this->selectHosts)
                ->andWhere(SqlHelper::whereIn('hostid', $all_hostids))
                ->indexBy('hostid')
                ->asArray()
                ->all();
        }

        foreach ($result as &$script) {
            $script_groups = [];
            if ($script['groupid'] == 0) {
                $script_groups = $host_groups;
            } else {
                $script_groups = array_intersect_key($host_groups, $hstgrp_branch[$script['groupid']]);
            }
            if ($is_groups_select) {
                $script['groups'] = array_values($this->unsetExtraFields(
                    $script_groups,
                    ['groupid', 'name', 'flags', 'uuid'],
                    $this->selectGroups
                ));
            }

            if ($is_hostgroups_select) {
                $script['hostgroups'] = array_values($this->unsetExtraFields(
                    $script_groups,
                    ['groupid', 'name', 'flags', 'uuid'],
                    $this->selectHostGroups
                ));
            }

            if ($is_hosts_select) {
                $script['hosts'] = [];
                foreach (array_keys($script_groups) as $script_groupid) {
                    if (array_key_exists($script_groupid, $group_to_hosts)) {
                        $script['hosts'] += array_intersect_key($used_hosts, $group_to_hosts[$script_groupid]);
                    }
                }
                $script['hosts'] = array_values($script['hosts']);
            }
        }
        unset($script);

        return $result;
    }


}
