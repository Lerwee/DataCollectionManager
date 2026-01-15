<?php

namespace app\customs\zapi\services;

use app\common\helpers\ArrayHelper;
use app\common\helpers\AuthHelper;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\TagHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\macros\CMacrosResolverHelper;
use app\customs\zapi\forms\EventGetForm;
use app\customs\zapi\forms\EventAcknowledgeForm;
use app\customs\zapi\components\API;
use app\customs\zapi\components\RelationMap;
use app\customs\zapi\models\search\EventSearch;
use app\modules\libzbx\models\Triggers;
use app\modules\libzbx\models\zbx\Events;
use app\modules\libzbx\models\zbx\EventTag;
use Exception;

class EventService extends BaseService
{
    protected $tableName = 'events';
    protected $tableAlias = 'e';
    protected $sortColumns = ['eventid', 'objectid', 'clock'];

    public function getEvent($params)
    {
        return $this->success(['rows' => array_values($this->get($params))]);
    }

    public function get($options = [])
    {
        $options = prs_array_merge(EventGetForm::getDefaults(), $options);
        EventGetForm::validate($options);
        $options['value'] !== null && prs_value2array($options['value']);
        $isTrigger = ($options['source'] == EVENT_SOURCE_TRIGGERS && $options['object'] == EVENT_OBJECT_TRIGGER) || ($options['source'] == EVENT_SOURCE_SERVICE && $options['object'] == EVENT_OBJECT_SERVICE);
        if ($isTrigger) {
            $options['value'] === null && $options['value'] = ($options['problem_time_from'] !== null && $options['problem_time_till'] !== null) ? [TRIGGER_VALUE_TRUE] : [TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE];
            $problems = in_array(TRIGGER_VALUE_TRUE, $options['value']) ? $this->getEvents(['value' => [TRIGGER_VALUE_TRUE]] + $options) : [];
            $recovery = in_array(TRIGGER_VALUE_FALSE, $options['value']) ? $this->getEvents(['value' => [TRIGGER_VALUE_FALSE]] + $options) : [];
            if ($options['countOutput']) {
                $problems = $problems === [] ? 0 : $problems; $recovery = $recovery === [] ? 0 : $recovery;
                if ($options['groupCount']) {
                    $problems = prs_toHash($problems, 'objectid'); $recovery = prs_toHash($recovery, 'objectid');
                    foreach ($problems as $oid => &$p) if (isset($recovery[$oid])) { $p['rowscount'] += $recovery['rowscount']; unset($recovery[$oid]); } unset($p);
                    $result = array_values($problems + $recovery);
                } else $result = $problems + $recovery;
            } else {
                $result = self::sortResult($problems + $recovery, $options['sortfield'], $options['sortorder']);
                $options['limit'] !== null && $result = array_slice($result, 0, $options['limit'], true);
            }
        } else $result = $this->getEvents($options);
        if ($options['countOutput']) return is_array($result) ? $result : (string)$result;
        $result && $result = $this->unsetExtraFields($this->addRelatedObjects($options, $result), ['object', 'objectid'], $options['output']);
        return $options['preservekeys'] ? $result : prs_cleanHashes($result);
    }

    private function getEvents(array $options)
    {
        $sqlParts = [
            'select' => [$this->fieldId('eventid')],
            'from' => ['e' => 'events e'],
            'where' => [],
            'order' => [],
            'group' => [],
            'limit' => null
        ];

        // source and object
        $sqlParts['where'][] = 'e.source=' . SqlHelper::dbEscapeString($options['source']);
        $sqlParts['where'][] = 'e.object=' . SqlHelper::dbEscapeString($options['object']);

        if (($options['source'] == EVENT_SOURCE_TRIGGERS && $options['object'] == EVENT_OBJECT_TRIGGER)
            || ($options['source'] == EVENT_SOURCE_SERVICE && $options['object'] == EVENT_OBJECT_SERVICE)
        ) {
            if ($options['problem_time_from'] !== null && $options['problem_time_till'] !== null) {
                if ($options['value'][0] == TRIGGER_VALUE_TRUE) {
                    $sqlParts['where'][] =
                        'e.clock<=' . SqlHelper::dbEscapeString($options['problem_time_till']) . ' AND (' .
                        'NOT EXISTS (' .
                        'SELECT NULL' .
                        ' FROM event_recovery er' .
                        ' WHERE e.eventid=er.eventid' .
                        ')' .
                        ' OR EXISTS (' .
                        'SELECT NULL' .
                        ' FROM event_recovery er,events e2' .
                        ' WHERE e.eventid=er.eventid' .
                        ' AND er.r_eventid=e2.eventid' .
                        ' AND e2.clock>=' . SqlHelper::dbEscapeString($options['problem_time_from']) .
                        ')' .
                        ')';
                } else {
                    $sqlParts['where'][] =
                        'e.clock>=' . SqlHelper::dbEscapeString($options['problem_time_from']) .
                        ' AND EXISTS (' .
                        'SELECT NULL' .
                        ' FROM event_recovery er,events e2' .
                        ' WHERE e.eventid=er.r_eventid' .
                        ' AND er.eventid=e2.eventid' .
                        ' AND e2.clock<=' . SqlHelper::dbEscapeString($options['problem_time_till']) .
                        ')';
                }
            }
        }

        // eventids
        if (!is_null($options['eventids'])) {
            prs_value2array($options['eventids']);
            $sqlParts['where'][] = SqlHelper::whereIn('e.eventid', $options['eventids']);
        }

        // objectids
        if ($options['objectids'] !== null && in_array($options['object'], [
                EVENT_OBJECT_TRIGGER,
                EVENT_OBJECT_ITEM,
                EVENT_OBJECT_LLDRULE,
                EVENT_OBJECT_SERVICE
            ])) {
            prs_value2array($options['objectids']);
            $sqlParts['where'][] = SqlHelper::whereIn('e.objectid', $options['objectids']);

            if ($options['groupCount']) {
                $sqlParts['group']['objectid'] = 'e.objectid';
            }
        }

        // groupids
        if ($options['groupids'] !== null) {
            prs_value2array($options['groupids']);

            // triggers
            if ($options['object'] == EVENT_OBJECT_TRIGGER) {
                $sqlParts['from']['f'] = 'functions f';
                $sqlParts['from']['i'] = 'items i';
                $sqlParts['from']['hg'] = 'hosts_groups hg';
                $sqlParts['where']['e-f'] = 'e.objectid=f.triggerid';
                $sqlParts['where']['f-i'] = 'f.itemid=i.itemid';
                $sqlParts['where']['i-hg'] = 'i.hostid=hg.hostid';
                $sqlParts['where']['hg'] = SqlHelper::whereIn('hg.groupid', $options['groupids']);
            } // lld rules and items
            elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
                $sqlParts['from']['i'] = 'items i';
                $sqlParts['from']['hg'] = 'hosts_groups hg';
                $sqlParts['where']['e-i'] = 'e.objectid=i.itemid';
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
                $sqlParts['where']['e-f'] = 'e.objectid=f.triggerid';
                $sqlParts['where']['f-i'] = 'f.itemid=i.itemid';
                $sqlParts['where']['i'] = SqlHelper::whereIn('i.hostid', $options['hostids']);
            } // lld rules and items
            elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
                $sqlParts['from']['i'] = 'items i';
                $sqlParts['where']['e-i'] = 'e.objectid=i.itemid';
                $sqlParts['where']['i'] = SqlHelper::whereIn('i.hostid', $options['hostids']);
            }
        }

        // severities
        if ($options['severities'] !== null) {
            // triggers
            if ($options['object'] == EVENT_OBJECT_TRIGGER || $options['object'] == EVENT_OBJECT_SERVICE) {
                prs_value2array($options['severities']);
                $sqlParts['where'][] = SqlHelper::whereIn('e.severity', $options['severities']);
            }
            // ignore this filter for items and lld rules
        }

        // acknowledged
        if (!is_null($options['acknowledged'])) {
            $acknowledged = $options['acknowledged'] ? EVENT_ACKNOWLEDGED : EVENT_NOT_ACKNOWLEDGED;
            $sqlParts['where'][] = 'e.acknowledged=' . $acknowledged;
        }

        // suppressed
        if ($options['suppressed'] !== null) {
            $sqlParts['where'][] = (!$options['suppressed'] ? 'NOT ' : '') .
                'EXISTS (' .
                'SELECT NULL' .
                ' FROM event_suppress es' .
                ' WHERE es.eventid=e.eventid' .
                ')';
        }

        // symptom
        if ($options['symptom'] !== null) {
            $sqlParts['where'][] = (!$options['symptom'] ? 'NOT ' : '') .
                'EXISTS (' .
                'SELECT NULL' .
                ' FROM event_symptom es' .
                ' WHERE es.eventid=e.eventid' .
                ')';
        }

        // tags
        if ($options['tags'] !== null && $options['tags']) {
            $sqlParts['where'][] = TagHelper::setWhereCondition($options['tags'], $options['evaltype'], 'e', 'event_tag', 'eventid');
        }

        // time_from
        if ($options['time_from'] !== null) {
            $sqlParts['where'][] = 'e.clock>=' . SqlHelper::dbEscapeString($options['time_from']);
        }

        // time_till
        if ($options['time_till'] !== null) {
            $sqlParts['where'][] = 'e.clock<=' . SqlHelper::dbEscapeString($options['time_till']);
        }

        // eventid_from
        if ($options['eventid_from'] !== null) {
            $sqlParts['where'][] = 'e.eventid>=' . SqlHelper::dbEscapeString($options['eventid_from']);
        }

        // eventid_till
        if ($options['eventid_till'] !== null) {
            $sqlParts['where'][] = 'e.eventid<=' . SqlHelper::dbEscapeString($options['eventid_till']);
        }

        // value
        if ($options['value'] !== null) {
            $sqlParts['where'][] = SqlHelper::whereIn('e.value', $options['value']);
        }

        // search
        if (is_array($options['search'])) {
            $this->dbSearch('events e', $options, $sqlParts);
        }

        // filter
        if (is_array($options['filter'])) {
            $this->dbFilter('events e', $options, $sqlParts);

            // Filter symptom events for given cause.
            if (array_key_exists('cause_eventid', $options['filter']) && $options['filter']['cause_eventid'] !== null) {
                prs_value2array($options['filter']['cause_eventid']);

                $sqlParts['from']['event_symptom'] = 'event_symptom es';
                $sqlParts['where']['ese'] = 'es.eventid=e.eventid';
                $sqlParts['where']['es'] = SqlHelper::whereIn('es.cause_eventid', $options['filter']['cause_eventid']);
            }
        }

        // limit
        if (prs_ctype_digit($options['limit']) && $options['limit']) {
            $sqlParts['limit'] = $options['limit'];
        }

        $result = [];

        $sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
        $sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);

        $sql = $this->dbAddLimit(self::createSelectQueryFromParts($sqlParts), $options['limit']);

        $events = Events::getDb()->createCommand($sql)->queryAll();
        foreach ($events as $event) {
            if ($options['countOutput']) {
                if ($options['groupCount']) {
                    $result[] = $event;
                } else {
                    $result = $event['rowscount'];
                }
            } else {
                $result[$event['eventid']] = $event;
            }
        }
        return $result;
    }

    /**
     * Validates the input parameters for the get() method.
     *
     * @param array $options
     * @throws APIException     if the input is invalid
     * @deprecated Use EventGetForm::validate() instead
     */
    protected function validateGet(array $options)
    {
        EventGetForm::validate($options);
    }

    public function updateAcknowledge($params)
    {
        try {
            $result = $this->acknowledge($params);
            return $this->success(['rows' => array_values($result)]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750204, $e->getMessage());
        }
    }

    /**
     * Acknowledges the given events and closes them if necessary.
     *
     * @param array $data And array of operation data.
     * @param mixed $data ['eventids']        An event ID or an array of event IDs.
     * @param string $data ['cause_eventid']     Cause event ID. Used if $data['action'] yields 0x100.
     * @param string $data ['message']            Message if PRS_PROBLEM_UPDATE_SEVERITY flag is passed.
     * @param string $data ['severity']        New severity level if PRS_PROBLEM_UPDATE_SEVERITY flag is passed.
     * @param string $data ['suppress_until']    Suppress until time if PRS_PROBLEM_UPDATE_SUPPRESS flag is passed.
     * @param int $data ['action']            Flags of performed operations combined:
     *                                         - 0x01  - PRS_PROBLEM_UPDATE_CLOSE
     *                                         - 0x02  - PRS_PROBLEM_UPDATE_ACKNOWLEDGE
     *                                         - 0x04  - PRS_PROBLEM_UPDATE_MESSAGE
     *                                         - 0x08  - PRS_PROBLEM_UPDATE_SEVERITY
     *                                         - 0x10  - PRS_PROBLEM_UPDATE_UNACKNOWLEDGE
     *                                         - 0x20  - PRS_PROBLEM_UPDATE_SUPPRESS
     *                                         - 0x40  - PRS_PROBLEM_UPDATE_UNSUPPRESS
     *                                           - 0x80  - PRS_PROBLEM_UPDATE_RANK_TO_CAUSE
     *                                           - 0x100 - PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM
     *
     * @return array
     */
    public function acknowledge(array $data)
    {
        // $data = '{"action":4,"eventids":[21],"message":"12"}';

        $time = time();
        $this->validateAcknowledge($data, $time);

        $data['eventids'] = prs_toArray($data['eventids']);
        $data['eventids'] = array_keys(array_flip($data['eventids']));

        $has_close_action = EventAcknowledgeForm::hasCloseAction($data);
        $has_suppress_action = EventAcknowledgeForm::hasSuppressAction($data);
        $has_unsuppress_action = EventAcknowledgeForm::hasUnsuppressAction($data);
        $has_change_rank_to_symptom_action = EventAcknowledgeForm::hasRankToSymptomAction($data);

        // Validation of event permissions has already been done in validateAcknowledge().
        $events = $this->get([
            'output' => ['objectid', 'acknowledged', 'severity', 'r_eventid', 'cause_eventid'],
            // "acknowledges" used in CEvent::isEventClosed().
            'select_acknowledges' => $has_close_action || $has_suppress_action || $has_unsuppress_action
                ? ['action']
                : null,
            // "suppression_data" used in CEvent::isEventSuppressed().
            'selectSuppressionData' => $has_unsuppress_action ? ['maintenanceid'] : null,
            'eventids' => $data['eventids'],
            'source' => EVENT_SOURCE_TRIGGERS,
            'object' => EVENT_OBJECT_TRIGGER,
            'value' => TRIGGER_VALUE_TRUE,
            'preservekeys' => true,
            'nopermissions' => true
        ]);

        // Get current data of the new cause event and get symptom events of the given cause events.
        if ($has_change_rank_to_symptom_action) {
            $update_symptom_eventids = $this->validateEventRankChangeToSymptom($data['eventids'], $data['cause_eventid']);
        }

        $ack_eventids = [];
        $unack_eventids = [];
        $sev_change_eventids = [];
        $acknowledges = [];
        $suppress_eventids = [];
        $unsuppress_eventids = [];
        $tasks_update_event_rank_cause = [];
        $tasks_update_event_rank_symptom = [];
        $n = 0;

        foreach ($events as $eventid => $event) {
            $action = PRS_PROBLEM_UPDATE_NONE;
            $old_severity = 0;
            $new_severity = 0;
            $message = '';
            $suppress_until = 0;

            // Perform PRS_PROBLEM_UPDATE_CLOSE action flag.
            if ($has_close_action && !$this->isEventClosed($event)) {
                $action |= PRS_PROBLEM_UPDATE_CLOSE;
            }

            // Perform PRS_PROBLEM_UPDATE_ACKNOWLEDGE action flag.
            if (($data['action'] & PRS_PROBLEM_UPDATE_ACKNOWLEDGE) == PRS_PROBLEM_UPDATE_ACKNOWLEDGE
                && $event['acknowledged'] == EVENT_NOT_ACKNOWLEDGED
            ) {
                $action |= PRS_PROBLEM_UPDATE_ACKNOWLEDGE;
                $ack_eventids[] = $eventid;
            }

            // Perform PRS_PROBLEM_UPDATE_UNACKNOWLEDGE action flag.
            if (($data['action'] & PRS_PROBLEM_UPDATE_UNACKNOWLEDGE) == PRS_PROBLEM_UPDATE_UNACKNOWLEDGE
                && $event['acknowledged'] == EVENT_ACKNOWLEDGED
            ) {
                $action |= PRS_PROBLEM_UPDATE_UNACKNOWLEDGE;
                $unack_eventids[] = $eventid;
            }

            // Perform PRS_PROBLEM_UPDATE_MESSAGE action flag.
            if (($data['action'] & PRS_PROBLEM_UPDATE_MESSAGE) == PRS_PROBLEM_UPDATE_MESSAGE) {
                $action |= PRS_PROBLEM_UPDATE_MESSAGE;
                $message = $data['message'];
            }

            // Perform PRS_PROBLEM_UPDATE_SEVERITY action flag.
            if (($data['action'] & PRS_PROBLEM_UPDATE_SEVERITY) == PRS_PROBLEM_UPDATE_SEVERITY
                && $data['severity'] != $event['severity']
            ) {
                $action |= PRS_PROBLEM_UPDATE_SEVERITY;
                $old_severity = $event['severity'];
                $new_severity = $data['severity'];
                $sev_change_eventids[] = $eventid;
            }

            // Perform PRS_PROBLEM_UPDATE_SUPPRESS action flag.
            if ($has_suppress_action && !$this->isEventClosed($event)) {
                $action |= PRS_PROBLEM_UPDATE_SUPPRESS;
                $suppress_until = $data['suppress_until'];
                $suppress_eventids[] = $eventid;
            }

            // Perform PRS_PROBLEM_UPDATE_UNSUPPRESS action flag.
            if ($has_unsuppress_action && $this->isEventSuppressed($event) && !$this->isEventClosed($event)) {
                $action |= PRS_PROBLEM_UPDATE_UNSUPPRESS;
                $unsuppress_eventids[] = $eventid;
            }

            // Perform PRS_PROBLEM_UPDATE_RANK_TO_CAUSE action flag.
            if (($data['action'] & PRS_PROBLEM_UPDATE_RANK_TO_CAUSE) == PRS_PROBLEM_UPDATE_RANK_TO_CAUSE
                && $event['cause_eventid'] != 0
            ) {
                $action |= PRS_PROBLEM_UPDATE_RANK_TO_CAUSE;
                $tasks_update_event_rank_cause[$n] = ['eventid' => $eventid];
            }

            // Perform PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM action flag.
            if (
                $has_change_rank_to_symptom_action && $update_symptom_eventids
                && in_array($eventid, $update_symptom_eventids)
            ) {
                $action |= PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM;
                $tasks_update_event_rank_symptom[$n] = [
                    'eventid' => $eventid,
                    'cause_eventid' => $data['cause_eventid']
                ];
            }

            // For some of selected events action might not be performed, as event is already with given change.
            if ($action != PRS_PROBLEM_UPDATE_NONE) {
                $acknowledges[$n] = [
                    'userid' => 1,
                    'eventid' => $eventid,
                    'clock' => $time,
                    'message' => $message,
                    'action' => $action,
                    'old_severity' => $old_severity,
                    'new_severity' => $new_severity,
                    'suppress_until' => $suppress_until
                ];
                $n++;
            }
        }

        // Make changes in problem and events tables.
        if ($acknowledges) {
            // Unacknowledge problems and events.
            if ($unack_eventids) {
                DB::update('problem', [
                    'values' => ['acknowledged' => EVENT_NOT_ACKNOWLEDGED],
                    'where' => ['eventid' => $unack_eventids]
                ]);

                DB::update('events', [
                    'values' => ['acknowledged' => EVENT_NOT_ACKNOWLEDGED],
                    'where' => ['eventid' => $unack_eventids]
                ]);
            }

            // Acknowledge problems and events.
            if ($ack_eventids) {
                DB::update('problem', [
                    'values' => ['acknowledged' => EVENT_ACKNOWLEDGED],
                    'where' => ['eventid' => $ack_eventids]
                ]);

                DB::update('events', [
                    'values' => ['acknowledged' => EVENT_ACKNOWLEDGED],
                    'where' => ['eventid' => $ack_eventids]
                ]);
            }

            // Change severity.
            if ($sev_change_eventids) {
                DB::update('problem', [
                    'values' => ['severity' => $data['severity']],
                    'where' => ['eventid' => $sev_change_eventids]
                ]);

                DB::update('events', [
                    'values' => ['severity' => $data['severity']],
                    'where' => ['eventid' => $sev_change_eventids]
                ]);
            }

            // Store operation history data.
            $acknowledgeids = DB::insertBatch('acknowledges', $acknowledges);

            // Create tasks to close problems manually.
            $tasks = [];
            $task_close = [];

            foreach ($acknowledgeids as $k => $id) {
                $acknowledgement = $acknowledges[$k];

                if (($acknowledgement['action'] & PRS_PROBLEM_UPDATE_CLOSE) == PRS_PROBLEM_UPDATE_CLOSE) {
                    $tasks[$k] = [
                        'type' => PRS_TM_TASK_CLOSE_PROBLEM,
                        'status' => PRS_TM_STATUS_NEW,
                        'clock' => $time
                    ];

                    $task_close[$k] = [
                        'acknowledgeid' => $id
                    ];
                }
            }

            if ($tasks) {
                $taskids = DB::insertBatch('task', $tasks);
                $task_close = array_replace_recursive($task_close, prs_toObject($taskids, 'taskid', true));
                DB::insertBatch('task_close_problem', $task_close, false);
            }

            // Create tasks for suppress/unsuppress actions.
            $tasks = [];
            $task_suppress = [];

            foreach ($acknowledgeids as $k => $id) {
                $acknowledgement = $acknowledges[$k];

                // Create tasks to suppress problems manually.
                if (($acknowledgement['action'] & PRS_PROBLEM_UPDATE_SUPPRESS) == PRS_PROBLEM_UPDATE_SUPPRESS) {
                    $tasks[$k] = [
                        'type' => PRS_TM_TASK_DATA,
                        'status' => PRS_TM_STATUS_NEW,
                        'clock' => $time
                    ];

                    $task_suppress[$k] = [
                        'taskid' => $id,
                        'type' => PRS_TM_DATA_TYPE_TEMP_SUPPRESSION,
                        'data' => json_encode([
                            'eventid' => strval($suppress_eventids[$k]),
                            'action' => PRS_PROTO_VALUE_SUPPRESSION_SUPPRESS,
                            'userid' => $acknowledgement['userid'],
                            'suppress_until' => $suppress_until
                        ])
                    ];
                }

                // Create tasks to unsuppress problems manually.
                if (($acknowledgement['action'] & PRS_PROBLEM_UPDATE_UNSUPPRESS) == PRS_PROBLEM_UPDATE_UNSUPPRESS) {
                    $tasks[$k] = [
                        'type' => PRS_TM_TASK_DATA,
                        'status' => PRS_TM_STATUS_NEW,
                        'clock' => $time
                    ];

                    $task_suppress[$k] = [
                        'taskid' => $id,
                        'type' => PRS_TM_DATA_TYPE_TEMP_SUPPRESSION,
                        'data' => json_encode([
                            'eventid' => strval($unsuppress_eventids[$k]),
                            'action' => PRS_PROTO_VALUE_SUPPRESSION_UNSUPPRESS,
                            'userid' => $acknowledgement['userid']
                        ])
                    ];
                }
            }

            if ($tasks) {
                $taskids = DB::insertBatch('task', $tasks);
                $task_suppress = array_replace_recursive($task_suppress, prs_toObject($taskids, 'taskid', true));
                DB::insertBatch('task_data', $task_suppress, false);
            }

            // Create tasks to perform server-side acknowledgement operations.
            $tasks = [];
            $tasks_ack = [];

            foreach ($acknowledgeids as $k => $id) {
                $acknowledgement = $acknowledges[$k];

                // Acknowledge task should be created for each acknowledge operation, regardless of it's action.
                $tasks[$k] = [
                    'type' => PRS_TM_TASK_ACKNOWLEDGE,
                    'status' => PRS_TM_STATUS_NEW,
                    'clock' => $time
                ];

                $tasks_ack[$k] = [
                    'acknowledgeid' => $id
                ];
            }

            if ($tasks) {
                $taskids = DB::insertBatch('task', $tasks);
                $tasks_ack = array_replace_recursive($tasks_ack, prs_toObject($taskids, 'taskid', true));
                DB::insertBatch('task_acknowledge', $tasks_ack, false);
            }

            // Create tasks for event rank change actions - convert symptoms to cause.
            $tasks = [];
            $task_update_event_rank = [];

            foreach ($acknowledgeids as $k => $id) {
                $acknowledgement = $acknowledges[$k];

                if (($acknowledgement['action']
                        & PRS_PROBLEM_UPDATE_RANK_TO_CAUSE) == PRS_PROBLEM_UPDATE_RANK_TO_CAUSE) {
                    $tasks[$k] = [
                        'type' => PRS_TM_TASK_DATA,
                        'status' => PRS_TM_STATUS_NEW,
                        'clock' => $time
                    ];

                    $task_update_event_rank[$k] = [
                        'taskid' => $id,
                        'type' => PRS_TM_DATA_TYPE_RANK_EVENT,
                        'data' => json_encode([
                            'acknowledgeid' => $id,
                            'action' => $acknowledgement['action'],
                            'eventid' => $tasks_update_event_rank_cause[$k]['eventid'],
                            'userid' => $acknowledgement['userid']
                        ])
                    ];
                }
            }

            if ($tasks) {
                $taskids = DB::insertBatch('task', $tasks);
                $task_update_event_rank = array_replace_recursive(
                    $task_update_event_rank,
                    prs_toObject($taskids, 'taskid', true)
                );
                DB::insertBatch('task_data', $task_update_event_rank, false);

                $upd_acknowledges = [];

                foreach ($acknowledgeids as $k => $id) {
                    $acknowledgement = $acknowledges[$k];

                    if (($acknowledgement['action']
                            & PRS_PROBLEM_UPDATE_RANK_TO_CAUSE) == PRS_PROBLEM_UPDATE_RANK_TO_CAUSE) {
                        $upd_acknowledges[] = [
                            'values' => ['taskid' => $taskids[$k]],
                            'where' => ['acknowledgeid' => $id]
                        ];
                    }
                }

                DB::update('acknowledges', $upd_acknowledges);
            }

            /*
             * Create tasks for event rank change actions - convert cause to symptoms or update symptoms by changing
             * cause to a different cause.
             */
            $tasks = [];
            $task_update_event_rank = [];

            foreach ($acknowledgeids as $k => $id) {
                $acknowledgement = $acknowledges[$k];

                if (($acknowledgement['action']
                        & PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM) == PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM) {
                    $tasks[$k] = [
                        'type' => PRS_TM_TASK_DATA,
                        'status' => PRS_TM_STATUS_NEW,
                        'clock' => $time
                    ];

                    $task_update_event_rank[$k] = [
                        'taskid' => $id,
                        'type' => PRS_TM_DATA_TYPE_RANK_EVENT,
                        'data' => json_encode([
                            'acknowledgeid' => $id,
                            'action' => $acknowledgement['action'],
                            'eventid' => $tasks_update_event_rank_symptom[$k]['eventid'],
                            'cause_eventid' => $tasks_update_event_rank_symptom[$k]['cause_eventid'],
                            'userid' => $acknowledgement['userid']
                        ])
                    ];
                }
            }

            if ($tasks) {
                $taskids = DB::insertBatch('task', $tasks);
                $task_update_event_rank = array_replace_recursive(
                    $task_update_event_rank,
                    prs_toObject($taskids, 'taskid', true)
                );
                DB::insertBatch('task_data', $task_update_event_rank, false);

                $upd_acknowledges = [];

                foreach ($acknowledgeids as $k => $id) {
                    $acknowledgement = $acknowledges[$k];

                    if (($acknowledgement['action']
                            & PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM) == PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM) {
                        $upd_acknowledges[] = [
                            'values' => ['taskid' => $taskids[$k]],
                            'where' => ['acknowledgeid' => $id]
                        ];
                    }
                }

                DB::update('acknowledges', $upd_acknowledges);
            }
        }

        return ['eventids' => $data['eventids']];
    }

    /**
     * Validates the input parameters for the acknowledge() method.
     *
     * @param array $data And array of operation data.
     * @param string|array $data ['eventids']        An event ID or an array of event IDs.
     * @param string $data ['cause_eventid']   Cause event ID. Used if $data['action'] yields 0x100.
     * @param string $data ['message']         Message if PRS_PROBLEM_UPDATE_SEVERITY flag is passed.
     * @param string $data ['severity']        New severity level if PRS_PROBLEM_UPDATE_SEVERITY flag is passed.
     * @param int $data ['suppress_until']  Suppress until time if PRS_PROBLEM_UPDATE_SUPPRESS flag is passed.
     * @param int $data ['action']          Flags of performed operations combined:
     *                                                - 0x01  - PRS_PROBLEM_UPDATE_CLOSE
     *                                                - 0x02  - PRS_PROBLEM_UPDATE_ACKNOWLEDGE
     *                                                - 0x04  - PRS_PROBLEM_UPDATE_MESSAGE
     *                                                - 0x08  - PRS_PROBLEM_UPDATE_SEVERITY
     *                                                - 0x10  - PRS_PROBLEM_UPDATE_UNACKNOWLEDGE
     *                                                - 0x20  - PRS_PROBLEM_UPDATE_SUPPRESS
     *                                                - 0x40  - PRS_PROBLEM_UPDATE_UNSUPPRESS
     *                                                - 0x80  - PRS_PROBLEM_UPDATE_RANK_TO_CAUSE
     *                                                - 0x100 - PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM
     *
     * @throws APIException                          If the input is invalid.
     */
    protected function validateAcknowledge(array $data, int $time)
    {
        // 使用表单模型验证基本参数
        EventAcknowledgeForm::validate($data, $time);

        $has_close_action = EventAcknowledgeForm::hasCloseAction($data);
        $has_message_action = EventAcknowledgeForm::hasMessageAction($data);
        $has_severity_action = EventAcknowledgeForm::hasSeverityAction($data);
        $has_change_rank_to_symptom_action = EventAcknowledgeForm::hasRankToSymptomAction($data);

        // Add the new cause ID to validate if the event exists and is still a problem.
        $eventids = array_fill_keys($data['eventids'], true);
        if ($has_change_rank_to_symptom_action && $data['cause_eventid'] !== null) {
            $eventids[$data['cause_eventid']] = true;
        }

        $events = $this->get([
            'output' => ['r_eventid'],
            'selectRelatedObject' => $has_close_action ? ['manual_close'] : null,
            'eventids' => array_keys($eventids),
            'source' => EVENT_SOURCE_TRIGGERS,
            'object' => EVENT_OBJECT_TRIGGER,
            'value' => TRIGGER_VALUE_TRUE
        ]);

        /*
         * If at least one of following is given, API call should not be processed:
         *   - eventid for OK event
         *   - eventid with source, that is not trigger
         *   - no read rights for related trigger
         *   - unexisting eventid
         */
        if (count($eventids) != count($events)) {
            self::exception(PRS_API_ERROR_PERMISSIONS, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        $editable_events_count = $this->get([
            'countOutput' => true,
            'eventids' => array_keys($eventids),
            'source' => EVENT_SOURCE_TRIGGERS,
            'object' => EVENT_OBJECT_TRIGGER,
            'editable' => true
        ]);

        if ($has_close_action) {
            $this->checkCanBeManuallyClosed($events, $editable_events_count);
        }

        if ($has_message_action) {
            EventAcknowledgeForm::validateMessage($data);
        }

        if ($has_severity_action) {
            $this->checkCanChangeSeverity($events, $editable_events_count, $data['severity']);
        }
    }

    /**
     * Checks if events can be closed manually.
     *
     * @param array $events Array of event objects.
     * @param int $editable_events_count Count of editable events.
     *
     * @throws APIException                 Throws an exception:
     *                                        - If at least one event is not editable;
     *                                        - If any of given event can be closed manually according the triggers
     *                                          configuration.
     */
    protected function checkCanBeManuallyClosed(array $events, $editable_events_count)
    {
        if (count($events) != $editable_events_count) {
            self::exception(PRS_API_ERROR_PERMISSIONS, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        foreach ($events as $event) {
            if ($event['relatedObject']['manual_close'] != PRS_TRIGGER_MANUAL_CLOSE_ALLOWED) {
                self::exception(
                    PRS_API_ERROR_PERMISSIONS,
                    t('zapi', 'Cannot close problem: {error}.', ['error' => t('zapi', 'trigger does not allow manual closing')])
                );
            }
        }
    }

    /**
     * Checks if severity can be changed for all given events.
     *
     * @param array $events Array of event objects.
     * @param int $editable_events_count Count of editable events.
     * @param int $severity New severity.
     *
     * @throws APIException                 Throws an exception:
     *                                        - If unknown severity is given;
     *                                        - If at least one event is not editable.
     */
    protected function checkCanChangeSeverity(array $events, $editable_events_count, $severity)
    {
        if (count($events) != $editable_events_count) {
            self::exception(PRS_API_ERROR_PERMISSIONS, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        $validator = new CLimitedSetValidator([
            'values' => [
                TRIGGER_SEVERITY_NOT_CLASSIFIED,
                TRIGGER_SEVERITY_INFORMATION,
                TRIGGER_SEVERITY_WARNING,
                TRIGGER_SEVERITY_AVERAGE,
                TRIGGER_SEVERITY_HIGH,
                TRIGGER_SEVERITY_DISASTER
            ]
        ]);

        if (!$validator->validate($severity)) {
            self::exception(
                PRS_API_ERROR_PARAMETERS,
                t('zapi',
                    'Incorrect value for field "{field}": {error}.',
                    [
                        'field' => 'severity',
                        'error' => t('zapi', 'unexpected value "{value}"', ['value' => $severity])
                    ]
                )
            );
        }
    }

    /**
     * Checks if time is valid future time.
     *
     * @param array $data Input data.
     * @param array $data ['suppress_until']  Suppress until unix time. O for Indefinite time.
     * @param int $time Current unix time.
     * @deprecated Use EventAcknowledgeForm::validateSuppressTime() instead
     */
    protected function CheckIfValidTime(array $data, $time)
    {
        // 已移至 EventAcknowledgeForm::validateSuppressTime()
    }

    /**
     * Checks if unsuppress action can be executed for given event.
     *
     * @param array $event Event object.
     * @param array $event ['suppression_data']                     List of problem suppression data.
     * @param array $event ['suppression_data'][]['maintenanceid']  Problem maintenanceid.
     *
     * @return bool
     */
    protected function isEventSuppressed(array $event)
    {
        foreach ($event['suppression_data'] as $suppression) {
            if ($suppression['maintenanceid'] == 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if event is closed.
     *
     * @param array $event Event object.
     * @param string $event ['r_eventid']                 OK event id. 0 if not resolved.
     * @param array $event ['acknowledges']              List of problem updates.
     * @param int $event ['acknowledges'][]['action']  Action performed in update.
     *
     * @return bool
     */
    protected function isEventClosed(array $event)
    {
        if (bccomp($event['r_eventid'], '0') == 1) {
            return true;
        } else {

            foreach ($event['acknowledges'] ?? [] as $acknowledge) {
                if (($acknowledge['action'] & PRS_PROBLEM_UPDATE_CLOSE) == PRS_PROBLEM_UPDATE_CLOSE) {
                    // If at least one manual close update was found, event is closing.
                    return true;
                }
            }
        }

        return false;
    }

    protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts)
    {
        $sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

        if (!$options['countOutput']) {
            // Select fields from event_recovery table using LEFT JOIN.
            if ($this->outputIsRequested('r_eventid', $options['output'])) {
                $sqlParts['select']['r_eventid'] = 'er1.r_eventid';
                $sqlParts['left_join'][] = ['alias' => 'er1', 'table' => 'event_recovery', 'using' => 'eventid'];
                $sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
            }

            // Select fields from event_recovery table using LEFT JOIN.
            $left_join_recovery = false;
            foreach (['c_eventid', 'correlationid', 'userid'] as $field) {
                if ($this->outputIsRequested($field, $options['output'])) {
                    $sqlParts['select'][$field] = 'er2.' . $field;
                    $left_join_recovery = true;
                }
            }

            if ($left_join_recovery) {
                $sqlParts['left_join'][] = ['alias' => 'er2', 'table' => 'event_recovery', 'using' => 'r_eventid'];
                $sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
            }

            if ($options['selectRelatedObject'] !== null || $options['selectHosts'] !== null) {
                $sqlParts = $this->addQuerySelect('e.object', $sqlParts);
                $sqlParts = $this->addQuerySelect('e.objectid', $sqlParts);
            }

            $left_join_symptom = false;
            if ($this->outputIsRequested('cause_eventid', $options['output'])) {
                $sqlParts['select']['cause_eventid'] = 'es1.cause_eventid';
                $left_join_symptom = true;
            }

            if ($left_join_symptom) {
                $sqlParts['left_join'][] = ['alias' => 'es1', 'table' => 'event_symptom', 'using' => 'eventid'];
                $sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
            }
        }

        return $sqlParts;
    }

    protected function addRelatedObjects(array $options, array $result)
    {
        $result = parent::addRelatedObjects($options, $result);

        $eventids = array_keys($result);

        // Adding operational data.
        if ($this->outputIsRequested('opdata', $options['output'])) {
            $events = Events::getDb()->createCommand('SELECT e.eventid,e.clock,e.ns,t.triggerid,t.expression,t.opdata' .
                ' FROM events e' .
                ' JOIN triggers t ON t.triggerid=e.objectid' .
                ' WHERE ' . SqlHelper::whereIn('e.eventid', $eventids))->queryAll();

            $events = ArrayHelper::index($events, 'eventid');

            foreach ($result as $eventid => $event) {
                $result[$eventid]['opdata'] =
                    (array_key_exists($eventid, $events) && $events[$eventid]['opdata'] !== '')
                        ? CMacrosResolverHelper::resolveTriggerOpdata($events[$eventid], ['events' => true])
                        : '';
            }
        }

        // adding hosts
        if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {

            $hosts = [];
            $relationMap = new RelationMap();

            // trigger events
            if ($options['object'] == EVENT_OBJECT_TRIGGER) {
                $query = DBselect(
                    'SELECT e.eventid,i.hostid' .
                    ' FROM events e,functions f,items i' .
                    ' WHERE ' . SqlHelper::whereIn('e.eventid', $eventids) .
                    ' AND e.objectid=f.triggerid' .
                    ' AND f.itemid=i.itemid' .
                    ' AND e.object=' . SqlHelper::dbEscapeString($options['object']) .
                    ' AND e.source=' . SqlHelper::dbEscapeString($options['source'])
                );
            } // item and LLD rule events
            elseif ($options['object'] == EVENT_OBJECT_ITEM || $options['object'] == EVENT_OBJECT_LLDRULE) {
                $query = DBselect(
                    'SELECT e.eventid,i.hostid' .
                    ' FROM events e,items i' .
                    ' WHERE ' . SqlHelper::whereIn('e.eventid', $eventids) .
                    ' AND e.objectid=i.itemid' .
                    ' AND e.object=' . SqlHelper::dbEscapeString($options['object']) .
                    ' AND e.source=' . SqlHelper::dbEscapeString($options['source'])
                );
            }

            while ($relation = DBfetch($query)) {
                $relationMap->addRelation($relation['eventid'], $relation['hostid']);
            }

            $related_ids = $relationMap->getRelatedIds();

            if ($related_ids) {
                $hosts = API::Host()->get([
                    'output' => $options['selectHosts'],
                    'hostids' => $related_ids,
                    'nopermissions' => true,
                    'preservekeys' => true
                ]);
            }

            $result = $relationMap->mapMany($result, $hosts, 'hosts');
        }

        // adding the related object
        if (
            $options['selectRelatedObject'] !== null && $options['selectRelatedObject'] != API_OUTPUT_COUNT
            && $options['object'] != EVENT_OBJECT_AUTOREGHOST
        ) {

            $relationMap = new RelationMap();
            foreach ($result as $event) {
                $relationMap->addRelation($event['eventid'], $event['objectid']);
            }
            $objects = [];
            switch ($options['object']) {
                case EVENT_OBJECT_TRIGGER:
                    $objects = Triggers::find()
                        ->select($options['selectRelatedObject'])
                        ->addSelect('triggerid')
                        ->andWhere(['triggerid' => $relationMap->getRelatedIds()])
                        ->indexBy('triggerid')->asArray()->all();
                    break;
                case EVENT_OBJECT_DHOST:
                    $api = API::DHost();
                    break;
                case EVENT_OBJECT_DSERVICE:
                    $api = API::DService();
                    break;
                case EVENT_OBJECT_ITEM:
                    $api = API::Item();
                    break;
                case EVENT_OBJECT_LLDRULE:
                    $api = API::DiscoveryRule();
                    break;
                case EVENT_OBJECT_SERVICE:
                    $api = API::Service();
                    break;
            }

//            $objects = $api->get([
//                'output' => $options['selectRelatedObject'],
////                $api->pkOption() => $relationMap->getRelatedIds(),
//                'nopermissions' => true,
//                'preservekeys' => true
//            ]);
            $result = $relationMap->mapOne($result, $objects, 'relatedObject');
        }

        // adding alerts
        if ($options['select_alerts'] !== null && $options['select_alerts'] != API_OUTPUT_COUNT) {
            $alerts = [];
            $relationMap = $this->createRelationMap($result, 'eventid', 'alertid', 'alerts');
            $related_ids = $relationMap->getRelatedIds();

            if ($related_ids) {
                $alerts = API::Alert()->get([
                    'output' => $options['select_alerts'],
                    'selectMediatypes' => API_OUTPUT_EXTEND,
                    'alertids' => $related_ids,
                    'nopermissions' => true,
                    'preservekeys' => true,
                    'sortfield' => 'clock',
                    'sortorder' => PRS_SORT_DOWN
                ]);
            }

            $result = $relationMap->mapMany($result, $alerts, 'alerts');
        }

        // adding acknowledges
        /*         if ($options['select_acknowledges'] !== null) {
                    if ($options['select_acknowledges'] != API_OUTPUT_COUNT) {
                        // create the base query
                        $sqlParts = API::getApiService()->createSelectQueryParts('acknowledges', 'a', [
                            'output' => $this->outputExtend(
                                $options['select_acknowledges'],
                                ['acknowledgeid', 'eventid', 'clock', 'userid']
                            ),
                            'filter' => ['eventid' => $eventids]
                        ]);
                        $sqlParts['order'][] = 'a.clock DESC';

                        $acknowledges = DBFetchArrayAssoc(
                            DBselect(self::createSelectQueryFromParts($sqlParts)),
                            'acknowledgeid'
                        );

                        // if the user data is requested via extended output or specified fields, join the users table
                        $userFields = ['username', 'name', 'surname'];
                        $requestUserData = [];
                        foreach ($userFields as $userField) {
                            if ($this->outputIsRequested($userField, $options['select_acknowledges'])) {
                                $requestUserData[] = $userField;
                            }
                        }

                        if ($requestUserData) {
                            $users = API::User()->get([
                                'output' => $requestUserData,
                                'userids' => prs_objectValues($acknowledges, 'userid'),
                                'preservekeys' => true
                            ]);

                            foreach ($acknowledges as &$acknowledge) {
                                if (array_key_exists($acknowledge['userid'], $users)) {
                                    $acknowledge = array_merge($acknowledge, $users[$acknowledge['userid']]);
                                }
                            }
                            unset($acknowledge);
                        }

                        $relationMap = $this->createRelationMap($acknowledges, 'eventid', 'acknowledgeid');
                        $acknowledges = $this->unsetExtraFields(
                            $acknowledges,
                            ['eventid', 'acknowledgeid', 'clock', 'userid'],
                            $options['select_acknowledges']
                        );
                        $result = $relationMap->mapMany($result, $acknowledges, 'acknowledges');
                    } else {
                        $acknowledges = DBFetchArrayAssoc(
                            DBselect(
                                'SELECT COUNT(a.acknowledgeid) AS rowscount,a.eventid' .
                                ' FROM acknowledges a' .
                                ' WHERE ' . SqlHelper::whereIn('a.eventid', $eventids) .
                                ' GROUP BY a.eventid'
                            ),
                            'eventid'
                        );

                        foreach ($result as $eventid => $event) {
                            $result[$eventid]['acknowledges'] = array_key_exists($eventid, $acknowledges)
                                ? $acknowledges[$eventid]['rowscount']
                                : '0';
                        }
                    }
                } */

        // Adding suppression data.
        if ($options['selectSuppressionData'] !== null && $options['selectSuppressionData'] != API_OUTPUT_COUNT) {
            $suppression_data = API::getApiService()->select('event_suppress', [
                'output' => $this->outputExtend($options['selectSuppressionData'], ['eventid', 'maintenanceid']),
                'filter' => ['eventid' => $eventids],
                'preservekeys' => true
            ]);
            $relation_map = $this->createRelationMap($suppression_data, 'eventid', 'event_suppressid');
            $suppression_data = $this->unsetExtraFields($suppression_data, ['event_suppressid', 'eventid'], []);
            $result = $relation_map->mapMany($result, $suppression_data, 'suppression_data');
        }

        // Adding suppressed value.
        if ($this->outputIsRequested('suppressed', $options['output'])) {
            $suppressed_eventids = [];
            foreach ($result as &$event) {
                if (array_key_exists('suppression_data', $event)) {
                    $event['suppressed'] = $event['suppression_data']
                        ? (string)PRS_PROBLEM_SUPPRESSED_TRUE
                        : (string)PRS_PROBLEM_SUPPRESSED_FALSE;
                } else {
                    $suppressed_eventids[] = $event['eventid'];
                }
            }
            unset($event);

            if ($suppressed_eventids) {
                $suppressed_events = API::getApiService()->select('event_suppress', [
                    'output' => ['eventid'],
                    'filter' => ['eventid' => $suppressed_eventids]
                ]);
                $suppressed_eventids = array_flip(prs_objectValues($suppressed_events, 'eventid'));
                foreach ($result as &$event) {
                    $event['suppressed'] = array_key_exists($event['eventid'], $suppressed_eventids)
                        ? (string)PRS_PROBLEM_SUPPRESSED_TRUE
                        : (string)PRS_PROBLEM_SUPPRESSED_FALSE;
                }
                unset($event);
            }
        }

        // Remove "maintenanceid" field if it's not requested.
        if (
            $options['selectSuppressionData'] !== null && $options['selectSuppressionData'] != API_OUTPUT_COUNT
            && !$this->outputIsRequested('maintenanceid', $options['selectSuppressionData'])
        ) {
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
            $tags = DBselect(DB::makeSql('event_tag', $tags_options));

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

            $tags = EventTag::find()->andWhere(['eventid' => $eventids])->asArray()->all();

            foreach ($result as &$event) {
                $event['tags'] = [];
                if ($tags) {
                    foreach ($tags as $tag) {
                        if ($tag['eventid'] == $event['eventid']) {
                            $event['tags'][] = $tag;
                        }
                    }
                }
            }
            unset($event);
        }

        return $result;
    }

    /**
     * Returns the list of unique tag filters.
     *
     * @param array $usrgrpids
     *
     * @return array
     */
    public static function getTagFilters(array $usrgrpids)
    {
        $tag_filters = uniqTagFilters(
            DB::select('tag_filter', [
                'output' => ['groupid', 'tag', 'value'],
                'filter' => ['usrgrpid' => $usrgrpids]
            ])
        );

        $result = [];

        foreach ($tag_filters as $tag_filter) {
            $result[$tag_filter['groupid']][] = [
                'tag' => $tag_filter['tag'],
                'value' => $tag_filter['value']
            ];
        }

        return $result;
    }

    /**
     * Add sql parts related to tag-based permissions.
     *
     * @param array $usrgrpids
     * @param array $sqlParts
     * @param int $value
     *
     * @return array
     */
    protected static function addTagFilterSqlParts(array $usrgrpids, array $sqlParts, $value)
    {
        $tag_filters = self::getTagFilters($usrgrpids);

        if (!$tag_filters) {
            return $sqlParts;
        }

        $sqlParts['from']['f'] = 'functions f';
        $sqlParts['from']['i'] = 'items i';
        $sqlParts['from']['hg'] = 'hosts_groups hg';
        $sqlParts['where']['e-f'] = 'e.objectid=f.triggerid';
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
                $conditions[] = dbConditionString('et.tag', $tags);
            }
            $parenthesis = $tags || count($tag_values) > 1;

            foreach ($tag_values as $tag => $values) {
                $condition = 'et.tag=' . SqlHelper::dbEscapeString($tag) . ' AND ' . dbConditionString('et.value', $values);
                $conditions[] = $parenthesis ? '(' . $condition . ')' : $condition;
            }

            $conditions = (count($conditions) > 1) ? '(' . implode(' OR ', $conditions) . ')' : $conditions[0];

            $tag_conditions[] = 'hg.groupid=' . SqlHelper::dbEscapeString($groupid) . ' AND ' . $conditions;
        }

        if ($tag_conditions) {
            if ($value == TRIGGER_VALUE_TRUE) {
                $sqlParts['from']['et'] = 'event_tag et';
                $sqlParts['where']['e-et'] = 'e.eventid=et.eventid';
            } else {
                $sqlParts['from']['er'] = 'event_recovery er';
                $sqlParts['from']['et'] = 'event_tag et';
                $sqlParts['where']['e-er'] = 'e.eventid=er.r_eventid';
                $sqlParts['where']['er-et'] = 'er.eventid=et.eventid';
            }

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

    /**
     * Returns sorted array of events.
     *
     * @param array $events
     * @param string|array $sortfield
     * @param string|array $sortorder
     *
     * @return array
     */
    private static function sortResult(array $result, $sortfield, $sortorder)
    {
        if ($sortfield === '' || $sortfield === []) {
            return $result;
        }

        $fields = [];

        foreach ((array)$sortfield as $i => $field) {
            if (is_string($sortorder) && $sortorder === PRS_SORT_DOWN) {
                $order = PRS_SORT_DOWN;
            } elseif (is_array($sortorder) && array_key_exists($i, $sortorder) && $sortorder[$i] === PRS_SORT_DOWN) {
                $order = PRS_SORT_DOWN;
            } else {
                $order = PRS_SORT_UP;
            }

            $fields[] = ['field' => $field, 'order' => $order];
        }

        CArrayHelper::sort($result, $fields);

        return $result;
    }

    /**
     * Validate if the given events can change the rank by moving to a new cause. Linking a cause event with its symptoms
     * (or only cause or only symptoms) to another different cause or symptom is allowed and will switch to the new cause as
     * a result. Linking a cause to one of its own symptoms is also allowed and will simply switch the cause and symptom as
     * a result. Linking a symptom to same cause is not allowed and that event ID is skipped. Linking a symptom to symptom
     * of same cause is also not allowed and is skipped.
     *
     * @param array $eventids Array of event IDs that should be converted to symptom events.
     * @param string $cause_eventid Event ID that will be the new cause ID for given $eventids.
     *
     * @return array                  Returns event IDs that are allowed to change rank.
     */
    public function validateEventRankChangeToSymptom(array $eventids, string $cause_eventid): array
    {
        $eventids = array_fill_keys($eventids, true);
        $all_eventids = $eventids;
        $all_eventids[$cause_eventid] = true;

        // Get all the events that were given in the request to check permissions.
        $events = Events::find()->select(['eventid', 'cause_eventid'])->andWhere(['eventid' => array_keys($all_eventids)])->index('eventid')->asArray()->all();

        // Early return. In case one of the events are missing, no rank change can occur.
        if (count($events) != count($all_eventids)) {
            return [];
        }

        // Early return. No matter if cause or symptom, source and destination cannot be the same.
        if (count($eventids) == 1 && bccomp(key($eventids), $cause_eventid) == 0) {
            return [];
        }

        $dst_event = $events[$cause_eventid];

        foreach (array_keys($eventids) as $eventid) {
            $event = $events[$eventid];

            // Given cause is being moved.
            if ($event['cause_eventid'] == 0) {
                // Destination is cause. Cause is moved to same cause. Skip this event ID.
                if ($dst_event['cause_eventid'] == 0 && bccomp($eventid, $dst_event['eventid']) == 0) {
                    unset($eventids[$eventid]);
                }
            } // Given symptom is moved.
            else {
                // Destination is cause.
                if ($dst_event['cause_eventid'] == 0) {
                    // Symptom current cause is the same as new cause. Skip this event ID.
                    if (bccomp($event['cause_eventid'], $dst_event['eventid']) == 0) {
                        unset($eventids[$eventid]);
                    }
                } // Destination is symptom.
                else {
                    // Symptom destination is self. Skip this Event ID.
                    if (bccomp($eventid, $dst_event['eventid']) == 0) {
                        unset($eventids[$eventid]);
                    }

                    // If given symptom cause is not also in the list, skip this Event ID.
                    if (!array_key_exists($event['cause_eventid'], $eventids)) {
                        unset($eventids[$eventid]);
                    }
                }
            }
        }

        return array_keys($eventids);
    }
}
