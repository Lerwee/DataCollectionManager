<?php
namespace app\customs\zapi\models\search;

use app\common\helpers\SqlHelper;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\HttpTestHelper;
use yii\db\Query;

class HttpTestSearch extends BaseSearch
{
    public $httptestids   = null;
    public $hostids       = null;
    public $groupids      = null;
    public $templateids   = null;
    public $editable      = false;
    public $inherited     = null;
    public $templated     = null;
    public $monitored     = null;
    public $nopermissions = null;
    public $evaltype      = TAG_EVAL_TYPE_AND_OR;
    public $tags          = null;

    // output
    public $output         = API_OUTPUT_EXTEND;
    public $expandName     = null;
    public $expandStepName = null;
    public $selectHosts    = null;
    public $selectSteps    = null;
    public $selectTags     = null;

    public $is_all = false;

    public function search(array $params = [])
    {
        $this->setAttributes($params);

        $tables = $where = $groupBy = [];

        $tables['ht'] = 'httptest';

        $query = new Query();
        $query->select(['httptests' => 'ht.httptestid']);

        // httptestids
        if (! is_null($this->httptestids)) {
            $query->andWhere(SqlHelper::whereIn('ht.httptestid', filter_integer((array) $this->httptestids)));
        }

        // templateids
        // hostids
        if (! is_null($this->templateids) || ! is_null($this->hostids)) {
            $hostIds = array_merge((array) $this->templateids, (array) $this->hostids);
            $query->andWhere(SqlHelper::whereIn('ht.hostid', filter_integer($hostIds)));
            if ($this->groupCount) {
                $groupBy['hostid'] = 'ht.hostid';
            }
        }

        // tags

        // groupids
        if (! is_null($this->groupids)) {
            $tables['hg'] = 'hosts_groups';
            $where['hgh'] = 'hg.hostid=ht.hostid';
            $query->andWhere(SqlHelper::whereIn('hg.groupids', filter_integer((array) $this->groupids)));

            if ($this->groupCount) {
                $groupBy['groupid'] = 'hg.groupid';
            }
        }

        // inherited
        if (! is_null($this->inherited)) {
            $query->andWhere($this->inherited ? 'ht.templateid IS NOT NULL' : 'ht.templateid IS NULL');
        }

        // templated
        if (! is_null($this->templated)) {
            $tables['h'] = 'hosts';
            $where['h']  = 'h.hostid=ht.hostid';
            if ($this->templated) {
                $where[] = ['h.status' => HOST_STATUS_TEMPLATE];
            } else {
                $where[] = ['<>', 'h.status', HOST_STATUS_TEMPLATE];
            }
        }

        // monitored
        if (! is_null($this->monitored)) {
            $tables['h']  = 'hosts';
            $where['hht'] = 'h.hostid=ht.hostid';
            if ($this->monitored) {
                $where[] = ['h.status' => HOST_STATUS_MONITORED];
                $where[] = ['ht.status' => ITEM_STATUS_ACTIVE];
            } else {
                $where[] = [
                    'OR',
                    ['<>', 'h.status', HOST_STATUS_MONITORED],
                    ['<>', 'ht.status', ITEM_STATUS_ACTIVE],
                ];
            }
        }

        // search
        if (is_array($this->search)) {
            ZSqlHelper::zbxDbSearch('httptest ht', [
                'search'                 => $this->search,
                'startSearch'            => $this->startSearch,
                'excludeSearch'          => $this->excludeSearch,
                'searchWildcardsEnabled' => $this->searchWildcardsEnabled,
                'searchByAny'            => $this->searchByAny,
            ], $query);
        }

        // filter
        if (is_array($this->filter)) {
            if (array_key_exists('delay', $this->filter) && $this->filter['delay'] !== null) {
                $this->filter['delay'] = getTimeUnitFilters($this->filter['delay']);
            }
            $condition = ZSqlHelper::dbFilter('httptest', $this->filter, 'ht', (bool) $this->searchByAny);
            if ($condition) {
                $query->andWhere($condition);
            }
        }

        // limit
        if ($this->limit !== null && $this->limit > 0) {
            $query->limit($this->limit);
        }

        if ($this->preservekeys) {
            $query->indexBy('httptestid');
        }

        foreach ($where as $condition) {
            $query->andWhere($condition);
        }
        $query->groupBy($groupBy);
        $query->from($tables);

        $this->applyQueryOutputOptions($query, 'httptest', 'ht', $this->countOutput ? 'count' : $this->output);
        $this->applyQuerySortOptions($query, 'httptest', 'ht', $this->sortfield, $this->sortorder);

        $provider = new ActiveDataProvider([
            'query' => $query,
        ]);
        if ($this->is_all) {
            $provider->setPagination(false);
        }

        if ($this->countOutput) {
            return $provider;
        }

        $models = $this->format($provider->getModels());
        $provider->setModels($models);
        return $provider;
    }

    protected function format(array $result)
    {
        if ($result) {
            $result = $this->addRelatedObjects($result);

            // expandName
            $nameRequested = (is_array($this->output) && in_array('name', $this->output))
            || $this->output == API_OUTPUT_EXTEND;
            $expandName = $this->expandName !== null && $nameRequested;

            // expandStepName
            $stepNameRequested = $this->selectSteps == API_OUTPUT_EXTEND
                || (is_array($this->selectSteps) && in_array('name', $this->selectSteps));
            $expandStepName = $this->expandStepName !== null && $stepNameRequested;

            if ($expandName || $expandStepName) {
                $result = HttpTestHelper::resolveHttpTestMacros($result, $expandName, $expandStepName);
            }

            $result = $this->unsetExtraFields($result, ['hostid'], $this->output);

        }

        return $result;
    }

    protected function addRelatedObjects(array $result)
    {
        $result = parent::addRelatedObjects($result);

        $httpTestIds = array_keys($result);

        // adding headers and variables
        $fields = [
            PRS_HTTPFIELD_HEADER   => 'headers',
            PRS_HTTPFIELD_VARIABLE => 'variables',
        ];
        foreach ($fields as $type => $field) {
            if (! $this->outputIsRequested($field, $options['output'])) {
                unset($fields[$type]);
            }
        }

        $idWhereIn = SqlHelper::whereIn('httptestid', $httpTestIds);

        if ($fields) {
            $query = (new Query())
                ->select(['httptestid', 'name', 'value', 'type'])
                ->from('httptest_field')
                ->where($idWhereIn)
                ->andWhere(['type' => array_keys($fields)])
                ->orderBy('httptest_fieldid');
            $db_httpfields = $query->all();
            foreach ($result as &$httptest) {
                foreach ($fields as $field) {
                    $httptest[$field] = [];
                }
            }
            unset($httptest);

            foreach ($db_httpfields as $db_httpfield) {
                $result[$db_httpfield['httptestid']][$fields[$db_httpfield['type']]][] = [
                    'name'  => $db_httpfield['name'],
                    'value' => $db_httpfield['value'],
                ];
            }
        }

        // adding hosts
        if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
            $relationMap = $this->createRelationMap($result, 'httptestid', 'hostid');
            $hosts       = HostHelper::getHosts([
                'output'          => $options['selectHosts'],
                'hostid'          => $relationMap->getRelatedIds(),
                'nopermissions'   => true,
                'templated_hosts' => true,
                'preservekeys'    => true,
            ]);
            $result = $relationMap->mapMany($result, $hosts, 'hosts');
        }

        // adding steps
        if ($options['selectSteps'] !== null) {
            if ($options['selectSteps'] != API_OUTPUT_COUNT) {
                $fields = [
                    PRS_HTTPFIELD_HEADER      => 'headers',
                    PRS_HTTPFIELD_VARIABLE    => 'variables',
                    PRS_HTTPFIELD_QUERY_FIELD => 'query_fields',
                    PRS_HTTPFIELD_POST_FIELD  => 'posts',
                ];
                foreach ($fields as $type => $field) {
                    if (! $this->outputIsRequested($field, $options['selectSteps'])) {
                        unset($fields[$type]);
                    }
                }

                $query = (new Query())
                    ->select($this->outputExtend($options['selectSteps'], ['httptestid', 'httpstepid', 'post_type']))
                    ->from('httpstep')
                    ->where($idWhereIn)
                    ->andWhere(['type' => array_keys($fields)])
                    ->indexBy('httpstepid');
                $db_httpsteps = $query->all();

                $relationMap = $this->createRelationMap($db_httpsteps, 'httptestid', 'httpstepid');

                if ($fields) {
                    foreach ($db_httpsteps as &$db_httpstep) {
                        foreach ($fields as $type => $field) {
                            if ($type != PRS_HTTPFIELD_POST_FIELD || $db_httpstep['post_type'] == PRS_POSTTYPE_FORM) {
                                $db_httpstep[$field] = [];
                            }
                        }
                    }
                    unset($db_httpstep);

                    $query = (new Query())
                        ->select(['httpstepid', 'name', 'value', 'type'])
                        ->from('httpstep_field')
                        ->where(SqlHelper::whereIn('httpstepid', array_keys($db_httpsteps)))
                        ->andWhere(['type' => array_keys($fields)])
                        ->orderBy('httpstep_fieldid');

                    $db_httpstep_fields = $query->all();
                    foreach ($db_httpstep_fields as $db_httpstep_field) {
                        $db_httpstep = &$db_httpsteps[$db_httpstep_field['httpstepid']];

                        if ($db_httpstep_field['type'] != PRS_HTTPFIELD_POST_FIELD
                            || $db_httpstep['post_type'] == PRS_POSTTYPE_FORM) {
                            $db_httpstep[$fields[$db_httpstep_field['type']]][] = [
                                'name'  => $db_httpstep_field['name'],
                                'value' => $db_httpstep_field['value'],
                            ];
                        }
                    }
                    unset($db_httpstep);
                }

                $db_httpsteps = $this->unsetExtraFields($db_httpsteps, ['httptestid', 'httpstepid', 'post_type'],
                    $options['selectSteps']
                );
                $result = $relationMap->mapMany($result, $db_httpsteps, 'steps');
            } else {
                $query = (new Query())
                    ->select(['httptestid', 'stepscnt' => 'COUNT(httpstepid)'])
                    ->from('httpstep')
                    ->where($idWhereIn)
                    ->groupBy('httptestid');

                foreach ($query->each() as $dbHttpStep) {
                    $result[$dbHttpStep['httptestid']]['steps'] = $dbHttpStep['stepscnt'];
                }
            }
        }

        // Adding web scenario tags.
        if ($options['selectTags'] !== null) {
            $options['selectTags'] = ($options['selectTags'] !== API_OUTPUT_EXTEND)
            ? (array) $options['selectTags']
            : ['tag', 'value'];

            $options['selectTags'] = array_intersect(['tag', 'value'], $options['selectTags']);
            $requested_output      = array_flip($options['selectTags']);

            array_walk($result, function (&$http_test) {
                $http_test['tags'] = [];
            });

            $query = (new Query())
                ->select(array_merge($options['selectTags'], ['httptestid']))
                ->where($idWhereIn)
                ->from('httptest_tag');

            foreach ($query->each() as $db_tag) {
                $result[$db_tag['httptestid']]['tags'][] = array_intersect_key($db_tag, $requested_output);
            }
        }

        return $result;
    }

}
