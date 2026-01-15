<?php

namespace app\customs\zapi\services;

use app\common\helpers\ArrayHelper;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\CSettingsHelper;
use app\customs\zapi\common\helpers\TagHelper;
use app\customs\zapi\common\macros\CMacrosResolverHelper;
use app\customs\zapi\common\validators\EventSourceObjectValidator;
use app\customs\zapi\common\validators\LimitedSetValidator;
use app\modules\libzbx\models\zbx\Acknowledges;
use app\modules\libzbx\models\zbx\EventSuppress;
use app\modules\libzbx\models\zbx\Problem;
use Exception;

class ProblemService extends BaseService
{
    protected $tableName = 'problem';
    protected $tableAlias = 'p';
    protected $sortColumns = ['eventid'];

    public function getEvent($params)
    {
        try {
            $result = $this->get($params);
            return $this->success(['rows' => array_values($result)]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750204, $e->getMessage());
        }
    }

    /**
     * Get problem data.
     *
     * @param array $options
     *
     * @return array|int item data as array or false if error
     */
    public function get($options = [])
    {
        $options = '{"output":["eventid","objectid","clock","ns","name","severity","cause_eventid"],"source":0,"object":0,"sortfield":["eventid"],"sortorder":"DESC","preservekeys":true,"groupids":null,"hostids":null,"objectids":["22391"],"eventid_till":null,"suppressed":false,"symptom":false,"limit":1001,"recent":true,"evaltype":0}';
        $options = json_decode($options, true);
        $result = [];
        $userType = self::$userData['type'];

        $sqlParts = [
            'select' => [$this->fieldId('eventid')],
            'from' => ['p' => 'problem p'],
            'where' => [],
            'order' => [],
            'group' => [],
            'limit' => null
        ];

        $defOptions = [
            'eventids' => null,
            'groupids' => null,
            'hostids' => null,
            'objectids' => null,

            'editable' => false,
            'source' => EVENT_SOURCE_TRIGGERS,
            'object' => EVENT_OBJECT_TRIGGER,
            'severities' => null,
            'nopermissions' => null,
            // filter
            'time_from' => null,
            'time_till' => null,
            'eventid_from' => null,
            'eventid_till' => null,
            'acknowledged' => null,
            'suppressed' => null,
            'symptom' => null,
            'recent' => null,
            'any' => null,	// (internal) true if need not filtered by r_eventid
            'evaltype' => TAG_EVAL_TYPE_AND_OR,
            'tags' => null,
            'filter' => null,
            'search' => null,
            'searchByAny' => null,
            'startSearch' => false,
            'excludeSearch' => false,
            'searchWildcardsEnabled' => null,
            // output
            'output' => API_OUTPUT_EXTEND,
            'selectAcknowledges' => null,
            'selectSuppressionData' => null,
            'selectTags' => null,
            'countOutput' => false,
            'preservekeys' => false,
            'sortfield' => '',
            'sortorder' => '',
            'limit' => null
        ];
        $options = prs_array_merge($defOptions, $options);

        $this->validateGet($options);

        // source and object
        $sqlParts['where'][] = 'p.source=' . SqlHelper::dbEscapeString($options['source']);
        $sqlParts['where'][] = 'p.object=' . SqlHelper::dbEscapeString($options['object']);

        // editable + PERMISSION CHECK
        /*         if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
                    // triggers
                    if ($options['object'] == EVENT_OBJECT_TRIGGER) {
                        $user_groups = getUserGroupsByUserId(self::$userData['userid']);

                        // specific triggers
                        if ($options['objectids'] !== null) {
                            $options['objectids'] = array_keys(API::Trigger()->get([
                                'output' => [],
                                'triggerids' => $options['objectids'],
                                'editable' => $options['editable'],
                                'preservekeys' => true
                            ]));
                        }
                        // all triggers
                        else {
                            $sqlParts['where'][] = 'NOT EXISTS (' .
                                'SELECT NULL' .
                                ' FROM functions f,items i,hosts_groups hgg' .
                                    ' LEFT JOIN rights r' .
                                        ' ON r.id=hgg.groupid' .
                                            ' AND ' . SqlHelper::whereIn('r.groupid', $user_groups) .
                                ' WHERE p.objectid=f.triggerid' .
                                    ' AND f.itemid=i.itemid' .
                                    ' AND i.hostid=hgg.hostid' .
                                ' GROUP BY i.hostid' .
                                ' HAVING MAX(permission)<' . ($options['editable'] ? PERM_READ_WRITE : PERM_READ) .
                                    ' OR MIN(permission) IS NULL' .
                                    ' OR MIN(permission)=' . PERM_DENY .
                            ')';
                        }

                        if ($options['source'] == EVENT_SOURCE_TRIGGERS) {
                            $sqlParts = self::addTagFilterSqlParts($user_groups, $sqlParts);
                        }
                    } elseif ($options['object'] == EVENT_OBJECT_ITEM || $options['object'] == EVENT_OBJECT_LLDRULE) {
                        // specific items or lld rules
                        if ($options['objectids'] !== null) {
                            if ($options['object'] == EVENT_OBJECT_ITEM) {
                                $items = API::Item()->get([
                                    'output' => [],
                                    'itemids' => $options['objectids'],
                                    'editable' => $options['editable'],
                                    'preservekeys' => true
                                ]);
                                $options['objectids'] = array_keys($items);
                            } elseif ($options['object'] == EVENT_OBJECT_LLDRULE) {
                                $items = API::DiscoveryRule()->get([
                                    'output' => [],
                                    'itemids' => $options['objectids'],
                                    'editable' => $options['editable'],
                                    'preservekeys' => true
                                ]);
                                $options['objectids'] = array_keys($items);
                            }
                        }
                        // all items or lld rules
                        else {
                            $user_groups = getUserGroupsByUserId(self::$userData['userid']);

                            $sqlParts['where'][] = 'EXISTS (' .
                                'SELECT NULL' .
                                ' FROM items i,hosts_groups hgg' .
                                    ' JOIN rights r' .
                                        ' ON r.id=hgg.groupid' .
                                            ' AND ' . SqlHelper::whereIn('r.groupid', $user_groups) .
                                ' WHERE p.objectid=i.itemid' .
                                    ' AND i.hostid=hgg.hostid' .
                                ' GROUP BY hgg.hostid' .
                                ' HAVING MIN(r.permission)>' . PERM_DENY .
                                    ' AND MAX(r.permission)>=' . ($options['editable'] ? PERM_READ_WRITE : PERM_READ) .
                            ')';
                        }
                    }
                } */

        // eventids
        if ($options['eventids'] !== null) {
            prs_value2array($options['eventids']);
            $sqlParts['where'][] = SqlHelper::whereIn('p.eventid', $options['eventids']);
        }

        // objectids
        if ($options['objectids'] !== null) {
            prs_value2array($options['objectids']);
            $sqlParts['where'][] = SqlHelper::whereIn('p.objectid', $options['objectids']);
        }

        // groupids
        if ($options['groupids'] !== null) {
            prs_value2array($options['groupids']);

            // triggers
            if ($options['object'] == EVENT_OBJECT_TRIGGER) {
                $sqlParts['from']['f'] = 'functions f';
                $sqlParts['from']['i'] = 'items i';
                $sqlParts['from']['hg'] = 'hosts_groups hg';
                $sqlParts['where']['p-f'] = 'p.objectid=f.triggerid';
                $sqlParts['where']['f-i'] = 'f.itemid=i.itemid';
                $sqlParts['where']['i-hg'] = 'i.hostid=hg.hostid';
                $sqlParts['where']['hg'] = SqlHelper::whereIn('hg.groupid', $options['groupids']);
            }
            // lld rules and items
            elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
                $sqlParts['from']['i'] = 'items i';
                $sqlParts['from']['hg'] = 'hosts_groups hg';
                $sqlParts['where']['p-i'] = 'p.objectid=i.itemid';
                $sqlParts['where']['i-hg'] = 'i.hostid=hg.hostid';
                $sqlParts['where']['hg'] = SqlHelper::whereIn('hg.groupid', $options['groupids']);
            }
        }

        // hostids
        if ($options['hostids'] !== null) {
            prs_value2array($options['hostids']);

            // triggers
            if ($options['object'] == EVENT_OBJECT_TRIGGER) {
                $sqlParts['from']['f'] = 'functions f';
                $sqlParts['from']['i'] = 'items i';
                $sqlParts['where']['p-f'] = 'p.objectid=f.triggerid';
                $sqlParts['where']['f-i'] = 'f.itemid=i.itemid';
                $sqlParts['where']['i'] = SqlHelper::whereIn('i.hostid', $options['hostids']);
            }
            // lld rules and items
            elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
                $sqlParts['from']['i'] = 'items i';
                $sqlParts['where']['p-i'] = 'p.objectid=i.itemid';
                $sqlParts['where']['i'] = SqlHelper::whereIn('i.hostid', $options['hostids']);
            }
        }

        // severities
        if ($options['severities'] !== null) {
            // triggers
            if ($options['object'] == EVENT_OBJECT_TRIGGER || $options['object'] == EVENT_OBJECT_SERVICE) {
                prs_value2array($options['severities']);
                $sqlParts['where'][] = SqlHelper::whereIn('p.severity', $options['severities']);
            }
            // ignore this filter for items and lld rules
        }

        // acknowledged
        if ($options['acknowledged'] !== null) {
            $acknowledged = $options['acknowledged'] ? EVENT_ACKNOWLEDGED : EVENT_NOT_ACKNOWLEDGED;
            $sqlParts['where'][] = 'p.acknowledged=' . $acknowledged;
        }

        // suppressed
        if ($options['suppressed'] !== null) {
            $sqlParts['where'][] = (!$options['suppressed'] ? 'NOT ' : '') .
                'EXISTS (' .
                    'SELECT NULL' .
                    ' FROM event_suppress es' .
                    ' WHERE es.eventid=p.eventid' .
                ')';
        }

        // symptom
        if ($options['symptom'] !== null) {
            $sqlParts['where'][] = 'p.cause_eventid IS ' . ($options['symptom'] ? 'NOT ' : '') . ' NULL';
        }

        // tags
        if ($options['tags'] !== null && $options['tags']) {
            $sqlParts['where'][] = TagHelper::setWhereCondition(
                $options['tags'],
                $options['evaltype'],
                'p',
                'problem_tag',
                'eventid'
            );
        }

        // recent
        if ($options['recent'] !== null && $options['recent']) {
            $ok_events_from = time() - timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::OK_PERIOD));

            $sqlParts['where'][] = '(p.r_eventid IS NULL OR p.r_clock>' . $ok_events_from . ')';
        } else {
            $sqlParts['where'][] = 'p.r_eventid IS NULL';
        }

        // time_from
        if ($options['time_from'] !== null) {
            $sqlParts['where'][] = 'p.clock>=' . SqlHelper::dbEscapeString($options['time_from']);
        }

        // time_till
        if ($options['time_till'] !== null) {
            $sqlParts['where'][] = 'p.clock<=' . SqlHelper::dbEscapeString($options['time_till']);
        }

        // eventid_from
        if ($options['eventid_from'] !== null) {
            $sqlParts['where'][] = 'p.eventid>=' . SqlHelper::dbEscapeString($options['eventid_from']);
        }

        // eventid_till
        if ($options['eventid_till'] !== null) {
            $sqlParts['where'][] = 'p.eventid<=' . SqlHelper::dbEscapeString($options['eventid_till']);
        }

        // search
        if (is_array($options['search'])) {
            $this->dbSearch('problem p', $options, $sqlParts);
        }

        // filter
        if (is_array($options['filter'])) {
            $this->dbFilter('problem p', $options, $sqlParts);
        }

        // limit
        if (prs_ctype_digit($options['limit']) && $options['limit']) {
            $sqlParts['limit'] = $options['limit'];
        }

        $sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
        $sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);

        $sql = $this->dbAddLimit(self::createSelectQueryFromParts($sqlParts), $options['limit']);

        $events = Problem::getDb()->createCommand($sql)->queryAll();

        foreach ($events as $event) {
            if ($options['countOutput']) {
                $result = $event['rowscount'];
            } else {
                $result[$event['eventid']] = $event;
            }
        }

        if ($options['countOutput']) {
            return $result;
        }

        if ($result) {
            $result = $this->addRelatedObjects($options, $result);
            $result = $this->unsetExtraFields($result, ['object', 'objectid'], $options['output']);
        }

        // removing keys (hash -> array)
        if (!$options['preservekeys']) {
            $result = prs_cleanHashes($result);
        }

        return $result;
    }

    /**
     * Validates the input parameters for the get() method.
     *
     * @throws APIException  if the input is invalid
     *
     * @param array $options
     */
    protected function validateGet(array $options)
    {
        $sourceValidator = new LimitedSetValidator([
            'values' => [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE]
        ]);
        if (!$sourceValidator->validate($options['source'])) {
            self::exception(PRS_API_ERROR_PARAMETERS, _('Incorrect source value.'));
        }

        $objectValidator = new LimitedSetValidator([
            'values' => [EVENT_OBJECT_TRIGGER, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE, EVENT_OBJECT_SERVICE]
        ]);
        if (!$objectValidator->validate($options['object'])) {
            self::exception(PRS_API_ERROR_PARAMETERS, _('Incorrect object value.'));
        }

        $sourceObjectValidator = new EventSourceObjectValidator();
        if (!$sourceObjectValidator->validate(['source' => $options['source'], 'object' => $options['object']])) {
            self::exception(PRS_API_ERROR_PARAMETERS, $sourceObjectValidator->getError());
        }

        $evaltype_validator = new LimitedSetValidator([
            'values' => [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]
        ]);
        if (!$evaltype_validator->validate($options['evaltype'])) {
            self::exception(PRS_API_ERROR_PARAMETERS, _('Incorrect evaltype value.'));
        }
    }

    protected function addRelatedObjects(array $options, array $result)
    {
        $result = parent::addRelatedObjects($options, $result);

        $eventids = array_keys($result);

        // Adding operational data.
        if ($this->outputIsRequested('opdata', $options['output'])) {
            $sql =
                'SELECT p.eventid,p.clock,p.ns,t.triggerid,t.expression,t.opdata' .
                ' FROM problem p' .
                ' JOIN triggers t ON t.triggerid=p.objectid' .
                ' WHERE ' . SqlHelper::whereIn('p.eventid', $eventids);
            $problems = Problem::getDb()->createCommand($sql)->queryAll();
            $problems = ArrayHelper::index($problems, 'eventid');

            foreach ($result as $eventid => $problem) {
                $result[$eventid]['opdata'] =
                    (array_key_exists($eventid, $problems) && $problems[$eventid]['opdata'] !== '')
                        ? CMacrosResolverHelper::resolveTriggerOpdata($problems[$eventid], ['events' => true])
                        : '';
            }
        }

        // adding acknowledges
        if ($options['selectAcknowledges'] !== null) {
            if ($options['selectAcknowledges'] != API_OUTPUT_COUNT) {
                // create the base query
                $acknowledges = Acknowledges::find()->where(['eventid' => $eventids])->asArray()->all();

                $relationMap = $this->createRelationMap($acknowledges, 'eventid', 'acknowledgeid');
                $acknowledges = $this->unsetExtraFields(
                    $acknowledges,
                    ['eventid', 'acknowledgeid'],
                    $options['selectAcknowledges']
                );
                $result = $relationMap->mapMany($result, $acknowledges, 'acknowledges');
            } else {
                $acknowledges = DBFetchArrayAssoc(DBselect(
                    'SELECT a.eventid,COUNT(a.acknowledgeid) AS rowscount' .
                        ' FROM acknowledges a' .
                        ' WHERE ' . SqlHelper::whereIn('a.eventid', $eventids) .
                        ' GROUP BY a.eventid'
                ), 'eventid');

                foreach ($result as $eventid => $event) {
                    $result[$eventid]['acknowledges'] = array_key_exists($eventid, $acknowledges)
                        ? $acknowledges[$eventid]['rowscount']
                        : '0';
                }
            }
        }

        // Adding suppression data.
        if ($options['selectSuppressionData'] !== null && $options['selectSuppressionData'] != API_OUTPUT_COUNT) {
            $suppression_data = API::getApiService()->select('event_suppress', [
                'output' => $this->outputExtend($options['selectSuppressionData'], ['eventid', 'maintenanceid']),
                'filter' => ['eventid' => $eventids],
                'preservekeys' => true
            ]);
            EventSuppress::find()->andWhere(['eventid' => $eventids]);
            $relation_map = $this->createRelationMap($suppression_data, 'eventid', 'event_suppressid');
            $suppression_data = $this->unsetExtraFields($suppression_data, ['event_suppressid', 'eventid'], []);
            $result = $relation_map->mapMany($result, $suppression_data, 'suppression_data');
        }

        // Adding suppressed value.
        if ($this->outputIsRequested('suppressed', $options['output'])) {
            $suppressed_eventids = [];
            foreach ($result as &$problem) {
                if (array_key_exists('suppression_data', $problem)) {
                    $problem['suppressed'] = $problem['suppression_data']
                        ? (string) PRS_PROBLEM_SUPPRESSED_TRUE
                        : (string) PRS_PROBLEM_SUPPRESSED_FALSE;
                } else {
                    $suppressed_eventids[] = $problem['eventid'];
                }
            }
            unset($problem);

            if ($suppressed_eventids) {
                $suppressed_events = EventSuppress::find()->andWhere(['eventid' => $suppressed_eventids])->indexBy('eventid')->asArray()->all();
                foreach ($result as &$problem) {
                    $problem['suppressed'] = array_key_exists($problem['eventid'], $suppressed_events)
                        ? (string) PRS_PROBLEM_SUPPRESSED_TRUE
                        : (string) PRS_PROBLEM_SUPPRESSED_FALSE;
                }
                unset($problem);
            }
        }

        // Remove "maintenanceid" field if it's not requested.
        if ($options['selectSuppressionData'] !== null && $options['selectSuppressionData'] != API_OUTPUT_COUNT
                && !$this->outputIsRequested('maintenanceid', $options['selectSuppressionData'])) {
            foreach ($result as &$row) {
                $row['suppression_data'] = $this->unsetExtraFields($row['suppression_data'], ['maintenanceid'], []);
            }
            unset($row);
        }

        // Resolve webhook urls.
        if ($this->outputIsRequested('urls', $options['output'])) {
            $tags_options = [
                'output' => ['eventid', 'tag', 'value'],
                'filter' => ['eventid' => $eventids]
            ];
            $tags = DBselect(DB::makeSql('problem_tag', $tags_options));

            $events = [];

            foreach ($result as $event) {
                $events[$event['eventid']]['tags'] = [];
            }

            while ($tag = DBfetch($tags)) {
                $events[$tag['eventid']]['tags'][] = [
                    'tag' => $tag['tag'],
                    'value' => $tag['value']
                ];
            }

            $urls = DB::select('media_type', [
                'output' => ['event_menu_url', 'event_menu_name'],
                'filter' => [
                    'type' => MEDIA_TYPE_WEBHOOK,
                    'status' => MEDIA_TYPE_STATUS_ACTIVE,
                    'show_event_menu' => PRS_EVENT_MENU_SHOW
                ]
            ]);

            $events = CMacrosResolverHelper::resolveMediaTypeUrls($events, $urls);

            foreach ($events as $eventid => $event) {
                $result[$eventid]['urls'] = $event['urls'];
            }
        }

        // Adding event tags.
        if ($options['selectTags'] !== null && $options['selectTags'] != API_OUTPUT_COUNT) {
            if ($options['selectTags'] === API_OUTPUT_EXTEND) {
                $options['selectTags'] = ['tag', 'value'];
            }

            $tags_options = [
                'output' => $this->outputExtend($options['selectTags'], ['eventid']),
                'filter' => ['eventid' => $eventids]
            ];
            $tags = DBselect(DB::makeSql('problem_tag', $tags_options));

            foreach ($result as &$event) {
                $event['tags'] = [];
            }
            unset($event);

            while ($tag = DBfetch($tags)) {
                $event = &$result[$tag['eventid']];

                unset($tag['problemtagid'], $tag['eventid']);
                $event['tags'][] = $tag;
            }
            unset($event);
        }

        return $result;
    }

    /**
     * Add sql parts related to tag-based permissions.
     *
     * @param array $usrgrpids
     * @param array $sqlParts
     *
     * @return array
     */
    protected static function addTagFilterSqlParts(array $usrgrpids, array $sqlParts)
    {
        $tag_filters = CEvent::getTagFilters($usrgrpids);

        if (!$tag_filters) {
            return $sqlParts;
        }

        $sqlParts['from']['f'] = 'functions f';
        $sqlParts['from']['i'] = 'items i';
        $sqlParts['from']['hg'] = 'hosts_groups hg';
        $sqlParts['where']['p-f'] = 'p.objectid=f.triggerid';
        $sqlParts['where']['f-i'] = 'f.itemid=i.itemid';
        $sqlParts['where']['i-hg'] = 'i.hostid=hg.hostid';

        $tag_conditions = [];
        $full_access_groupids = [];

        foreach ($tag_filters as $groupid => $filters) {
            $tags = [];
            $tag_values = [];

            foreach ($filters as $filter) {
                if ($filter['tag'] === '') {
                    $full_access_groupids[] = $groupid;
                    continue 2;
                } elseif ($filter['value'] === '') {
                    $tags[] = $filter['tag'];
                } else {
                    $tag_values[$filter['tag']][] = $filter['value'];
                }
            }

            $conditions = [];

            if ($tags) {
                $conditions[] = SqlHelper::stringWhereIn('pt.tag', $tags);
            }
            $parenthesis = $tags || count($tag_values) > 1;

            foreach ($tag_values as $tag => $values) {
                $condition = 'pt.tag=' . SqlHelper::dbEscapeString($tag) . ' AND ' . SqlHelper::stringWhereIn('pt.value', $values);
                $conditions[] = $parenthesis ? '(' . $condition . ')' : $condition;
            }

            $conditions = (count($conditions) > 1) ? '(' . implode(' OR ', $conditions) . ')' : $conditions[0];

            $tag_conditions[] = 'hg.groupid=' . SqlHelper::dbEscapeString($groupid) . ' AND ' . $conditions;
        }

        if ($tag_conditions) {
            $sqlParts['from']['pt'] = 'problem_tag pt';
            $sqlParts['where']['p-pt'] = 'p.eventid=pt.eventid';

            if ($full_access_groupids || count($tag_conditions) > 1) {
                foreach ($tag_conditions as &$tag_condition) {
                    $tag_condition = '(' . $tag_condition . ')';
                }
                unset($tag_condition);
            }
        }

        if ($full_access_groupids) {
            $tag_conditions[] = SqlHelper::whereIn('hg.groupid', $full_access_groupids);
        }

        $sqlParts['where'][] = (count($tag_conditions) > 1)
            ? '(' . implode(' OR ', $tag_conditions) . ')'
            : $tag_conditions[0];

        return $sqlParts;
    }
}
