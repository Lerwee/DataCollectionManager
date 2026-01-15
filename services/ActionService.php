<?php

namespace app\customs\zapi\services;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\CConditionHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\forms\ActionForm;
use app\modules\libzbx\models\zbx\Actions;
use app\modules\libzbx\models\zbx\Conditions;
use app\modules\libzbx\models\zbx\OpcommandGrp;
use app\modules\libzbx\models\zbx\OpcommandHst;
use app\modules\libzbx\models\zbx\Opconditions;
use app\modules\libzbx\models\zbx\Operations;
use app\modules\libzbx\models\zbx\Opgroup;
use app\modules\libzbx\models\zbx\OpmessageGrp;
use app\modules\libzbx\models\zbx\OpmessageUsr;
use app\modules\libzbx\models\zbx\Optemplate;
use yii\base\Exception;

class ActionService extends BaseService
{


    public function createAction($params)
    {
        return $this->success($this->create($params), t('act', 'Create Success'));
    }

    public function updateAction($params)
    {
        return $this->success($this->update($params), t('act', 'Update Success'));
    }

    public function create(array $actions): array
    {
        $this->validateCreate($actions);
        $ins = array_map(fn($a) => isset($a['filter']) ? ['evaltype' => $a['filter']['evaltype']] + $a : $a, $actions);
        $actionids = DB::insert('actions', $ins);
        foreach ($actions as $i => &$a) $a['actionid'] = $actionids[$i];
        unset($a);
        self::updateFilter($actions);
        self::updateOperations($actions);
        return ['actionids' => $actionids];
    }


    public function update(array $actions): array
    {
        $this->validateUpdate($actions, $db_actions);
        $upd = [];
        foreach ($actions as $a) {
            $db = $db_actions[$a['actionid']];
            if (isset($a['filter'])) { $a['evaltype'] = $a['filter']['evaltype']; $db['evaltype'] = $db['filter']['evaltype']; }
            if ($v = DB::getUpdatedValues('actions', $a, $db)) $upd[] = ['values' => $v, 'where' => ['actionid' => $a['actionid']]];
        }
        $upd && DB::update('actions', $upd);
        self::updateFilter($actions, $db_actions);
        self::updateOperations($actions, $db_actions);
        return ['actionids' => array_column($actions, 'actionid')];
    }

    private function validateCreate(array &$actions): void
    {
        if (!ValidateHelper::validateObjects($actions, ActionForm::getValidationRules('create'), ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']]], $e)) self::exception(60750001, $e);
        ActionForm::checkDuplicates($actions);
        ActionForm::checkFilter($actions);
        ActionForm::checkOperations($actions);
    }


    private function validateUpdate(array &$actions, ?array &$db_actions): void
    {
        $rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['actionid']], 'fields' => ['actionid' => ['type' => API_ID, 'flags' => API_REQUIRED]]];
        if (!ValidateHelper::validate($rules, $actions, '/', $e)) self::exception(PRS_API_ERROR_PARAMETERS, $e);
        $db_actions = Actions::find()->andWhere(['actionid' => array_column($actions, 'actionid')])->select(['actionid', 'name', 'eventsource', 'status', 'esc_period', 'pause_suppressed', 'notify_if_canceled', 'pause_symptoms'])->indexBy('actionid')->asArray()->all();
        count($actions) != count($db_actions) && self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'No permissions to referred object or it does not exist!'));
        $actions = $this->extendObjectsByKey($actions, $db_actions, 'actionid', ['name', 'eventsource']);
        if (!ValidateHelper::validateObjects($actions, ActionForm::getValidationRules('update'), ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']]], $e)) self::exception(60750001, $e);
        ActionForm::checkDuplicates($actions, $db_actions);
        foreach ($actions as $a) if ($a['eventsource'] != $db_actions[$a['actionid']]['eventsource']) self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Cannot update "{type}" for action "{action}".', ['type' => 'eventsource', 'action' => $a['name']]));
        self::addAffectedObjects($actions, $db_actions);
        ActionForm::checkFilter($actions);
        ActionForm::checkOperations($actions, $db_actions);
    }


    private static function updateFilter(array &$actions, array $db_actions = null): void
    {
        $isUpd = $db_actions !== null; $ins = $upd = $del = [];
        foreach ($actions as &$a) {
            if (!isset($a['filter']['conditions'])) continue;
            $dbc = $isUpd ? $db_actions[$a['actionid']]['filter']['conditions'] : [];
            foreach ($a['filter']['conditions'] as &$c) {
                $match = current(array_filter($dbc, fn($d) => $c['conditiontype'] == PRS_CONDITION_TYPE_SUPPRESSED ? $c['conditiontype'] == $d['conditiontype'] : ($c['conditiontype'] == PRS_CONDITION_TYPE_EVENT_TAG_VALUE ? $c['conditiontype'] == $d['conditiontype'] && $c['value2'] === $d['value2'] : $c['conditiontype'] == $d['conditiontype'] && $c['value'] == $d['value'])));
                if ($match) { $c['conditionid'] = $match['conditionid']; unset($dbc[$match['conditionid']]); if ($v = DB::getUpdatedValues('conditions', $c, $match)) $upd[] = ['values' => $v, 'where' => ['conditionid' => $match['conditionid']]]; }
                else $ins[] = ['actionid' => $a['actionid']] + $c;
            } unset($c);
            $del = array_merge($del, array_keys($dbc));
        } unset($a);
        $del && DB::delete('conditions', ['conditionid' => $del]); $upd && DB::update('conditions', $upd);
        $ids = $ins ? DB::insert('conditions', $ins) : [];
        foreach ($actions as &$a) { if (!isset($a['filter']['conditions'])) continue; foreach ($a['filter']['conditions'] as &$c) if (!isset($c['conditionid'])) $c['conditionid'] = array_shift($ids); unset($c); } unset($a);
        $updA = [];
        foreach ($actions as &$a) {
            if (!isset($a['filter'])) continue;
            $a['filter']['formula'] = $a['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION ? CConditionHelper::replaceLetterIds($a['filter']['formula'], array_column($a['filter']['conditions'], 'conditionid', 'formulaid')) : '';
            $dbF = $isUpd ? $db_actions[$a['actionid']]['filter']['formula'] : '';
            if ($a['filter']['formula'] !== $dbF) $updA[] = ['values' => ['formula' => $a['filter']['formula']], 'where' => ['actionid' => $a['actionid']]];
        } unset($a);
        $updA && DB::update('actions', $updA);
    }


    private static function updateOperations(array &$actions, array $db_actions = null): void
    {
        $is_update = ($db_actions !== null);

        $ins_operations = [];
        $upd_operations = [];
        $del_operationids = [];

        foreach ($actions as &$action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                $db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

                foreach ($action[$operation_group] as &$operation) {
                    $db_operation = current(
                        array_filter($db_operations, static function (array $db_operation) use ($operation): bool {
                            return $operation['operationtype'] == $db_operation['operationtype']
                                && $operation['recovery'] == $db_operation['recovery'];
                        })
                    );

                    if ($db_operation) {
                        $operation['operationid'] = $db_operation['operationid'];
                        unset($db_operations[$operation['operationid']]);

                        $upd_operation = DB::getUpdatedValues('operations', $operation, $db_operation);

                        if ($upd_operation) {
                            $upd_operations[] = [
                                'values' => $upd_operation,
                                'where' => ['operationid' => $db_operation['operationid']]
                            ];
                        }
                    } else {
                        $ins_operations[] = ['actionid' => $action['actionid']] + $operation;
                    }
                }
                unset($operation);

                $del_operationids = array_merge($del_operationids, array_keys($db_operations));
            }
        }
        unset($action);

        if ($del_operationids) {
            DB::delete('operations', ['operationid' => $del_operationids]);
        }

        if ($upd_operations) {
            DB::update('operations', $upd_operations);
        }

        if ($ins_operations) {
            $operationids = DB::insert('operations', $ins_operations);
        }

        foreach ($actions as &$action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                foreach ($action[$operation_group] as &$operation) {
                    if (!array_key_exists('operationid', $operation)) {
                        $operation['operationid'] = array_shift($operationids);
                    }
                }
                unset($operation);
            }
        }
        unset($action);

        self::updateOperationConditions($actions, $db_actions);
        self::updateOperationMessages($actions, $db_actions);
        self::updateOperationCommands($actions, $db_actions);
        self::updateOperationGroups($actions, $db_actions);
        self::updateOperationTemplates($actions, $db_actions);
        self::updateOperationInventories($actions, $db_actions);
    }


    /**
     * @param array $actions
     * @param array|null $db_actions
     */
    private static function updateOperationConditions(array &$actions, array $db_actions = null): void
    {
        $is_update = ($db_actions !== null);

        $ins_opconditions = [];
        $upd_opconditions = [];
        $del_opconditionids = [];

        foreach ($actions as &$action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                $db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

                foreach ($action[$operation_group] as &$operation) {
                    if (!array_key_exists('opconditions', $operation)) {
                        continue;
                    }

                    $db_operation = array_key_exists($operation['operationid'], $db_operations)
                        ? $db_operations[$operation['operationid']]
                        : [];

                    $db_opconditions = array_key_exists('opconditions', $db_operation)
                        ? array_column($db_operation['opconditions'], null, 'value')
                        : [];

                    foreach ($operation['opconditions'] as &$opcondition) {
                        if (array_key_exists($opcondition['value'], $db_opconditions)) {
                            $db_opcondition = $db_opconditions[$opcondition['value']];

                            $opcondition['opconditionid'] = $db_opcondition['opconditionid'];
                            unset($db_opconditions[$opcondition['value']]);

                            $upd_opcondition = DB::getUpdatedValues('opconditions', $opcondition, $db_opcondition);

                            if ($upd_opcondition) {
                                $upd_opconditions[] = [
                                    'values' => $upd_opcondition,
                                    'where' => ['operationid' => $operation['operationid']]
                                ];
                            }
                        } else {
                            $ins_opconditions[] = ['operationid' => $operation['operationid']] + $opcondition;
                        }
                    }
                    unset($opcondition);

                    $del_opconditionids = array_merge($del_opconditionids,
                        array_column($db_opconditions, 'opconditionid')
                    );
                }
                unset($operation);
            }
        }
        unset($action);

        if ($del_opconditionids) {
            DB::delete('opconditions', ['opconditionid' => $del_opconditionids]);
        }

        if ($upd_opconditions) {
            DB::update('opconditions', $upd_opconditions);
        }

        if ($ins_opconditions) {
            $opconditionids = DB::insert('opconditions', $ins_opconditions);
        }

        foreach ($actions as &$action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                foreach ($action[$operation_group] as &$operation) {
                    if (!array_key_exists('opconditions', $operation)) {
                        continue;
                    }

                    foreach ($operation['opconditions'] as &$opcondition) {
                        if (!array_key_exists('opconditionid', $opcondition)) {
                            $opcondition['opconditionid'] = array_shift($opconditionids);
                        }
                    }
                    unset($opcondition);
                }
                unset($operation);
            }
        }
        unset($action);
    }

    /**
     * @param array $actions
     * @param array|null $db_actions
     */
    private static function updateOperationMessages(array &$actions, array $db_actions = null): void
    {
        $is_update = ($db_actions !== null);

        $ins_opmessages = [];
        $upd_opmessages = [];

        $ins_opmessage_grps = [];
        $del_opmessage_grpids = [];

        $ins_opmessage_usrs = [];
        $del_opmessage_usrids = [];

        foreach ($actions as &$action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                $db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

                foreach ($action[$operation_group] as &$operation) {
                    $db_operation = array_key_exists($operation['operationid'], $db_operations)
                        ? $db_operations[$operation['operationid']]
                        : [];

                    switch ($operation['operationtype']) {
                        case OPERATION_TYPE_MESSAGE:
                            if (array_key_exists('opmessage_grp', $operation)) {
                                $db_opmessage_grps = array_key_exists('opmessage_grp', $db_operation)
                                    ? array_column($db_operation['opmessage_grp'], null, 'usrgrpid')
                                    : [];

                                foreach ($operation['opmessage_grp'] as &$opmessage_grp) {
                                    if (array_key_exists($opmessage_grp['usrgrpid'], $db_opmessage_grps)) {
                                        $db_opmessage_grp = $db_opmessage_grps[$opmessage_grp['usrgrpid']];
                                        $opmessage_grp['opmessage_grpid'] = $db_opmessage_grp['opmessage_grpid'];
                                        unset($db_opmessage_grps[$opmessage_grp['usrgrpid']]);
                                    } else {
                                        $ins_opmessage_grps[] =
                                            ['operationid' => $operation['operationid']] + $opmessage_grp;
                                    }
                                }
                                unset($opmessage_grp);

                                $del_opmessage_grpids = array_merge($del_opmessage_grpids,
                                    array_column($db_opmessage_grps, 'opmessage_grpid')
                                );
                            }

                            if (array_key_exists('opmessage_usr', $operation)) {
                                $db_opmessage_usrs = array_key_exists('opmessage_usr', $db_operation)
                                    ? array_column($db_operation['opmessage_usr'], null, 'userid')
                                    : [];

                                foreach ($operation['opmessage_usr'] as &$opmessage_usr) {
                                    if (array_key_exists($opmessage_usr['userid'], $db_opmessage_usrs)) {
                                        $db_opmessage_usr = $db_opmessage_usrs[$opmessage_usr['userid']];
                                        $opmessage_usr['opmessage_usrid'] = $db_opmessage_usr['opmessage_usrid'];
                                        unset($db_opmessage_usrs[$opmessage_usr['userid']]);
                                    } else {
                                        $ins_opmessage_usrs[] =
                                            ['operationid' => $operation['operationid']] + $opmessage_usr;
                                    }
                                }
                                unset($opmessage_usr);

                                $del_opmessage_usrids = array_merge($del_opmessage_usrids,
                                    array_column($db_opmessage_usrs, 'opmessage_usrid')
                                );
                            }
                        // break; is not missing here

                        case OPERATION_TYPE_RECOVERY_MESSAGE:
                        case OPERATION_TYPE_UPDATE_MESSAGE:
                            if (array_key_exists('opmessage', $db_operation)) {
                                $upd_opmessage = DB::getUpdatedValues('opmessage', $operation['opmessage'],
                                    $db_operation['opmessage']
                                );

                                if ($upd_opmessage) {
                                    $upd_opmessages[] = [
                                        'values' => $upd_opmessage,
                                        'where' => ['operationid' => $operation['operationid']]
                                    ];
                                }
                            } else {
                                $ins_opmessages[] =
                                    ['operationid' => $operation['operationid']] + $operation['opmessage'];
                            }
                            break;
                    }
                }
                unset($operation);
            }
        }
        unset($action);

        if ($upd_opmessages) {
            DB::update('opmessage', $upd_opmessages);
        }

        if ($ins_opmessages) {
            DB::insert('opmessage', $ins_opmessages, false);
        }

        if ($del_opmessage_grpids) {
            DB::delete('opmessage_grp', ['opmessage_grpid' => $del_opmessage_grpids]);
        }

        if ($ins_opmessage_grps) {
            $opmessage_grpids = DB::insert('opmessage_grp', $ins_opmessage_grps);
        }

        if ($del_opmessage_usrids) {
            DB::delete('opmessage_usr', ['opmessage_usrid' => $del_opmessage_usrids]);
        }

        if ($ins_opmessage_usrs) {
            $opmessage_usrids = DB::insert('opmessage_usr', $ins_opmessage_usrs);
        }

        foreach ($actions as &$action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                foreach ($action[$operation_group] as &$operation) {
                    if (array_key_exists('opmessage_grp', $operation)) {
                        foreach ($operation['opmessage_grp'] as &$opmessage_grp) {
                            if (!array_key_exists('opmessage_grpid', $opmessage_grp)) {
                                $opmessage_grp['opmessage_grpid'] = array_shift($opmessage_grpids);
                            }
                        }
                        unset($opmessage_grp);
                    }

                    if (array_key_exists('opmessage_usr', $operation)) {
                        foreach ($operation['opmessage_usr'] as &$opmessage_usr) {
                            if (!array_key_exists('opmessage_usrid', $opmessage_usr)) {
                                $opmessage_usr['opmessage_usrid'] = array_shift($opmessage_usrids);
                            }
                        }
                        unset($opmessage_usr);
                    }
                }
                unset($operation);
            }
        }
        unset($action);
    }

    /**
     * @param array $actions
     * @param array|null $db_actions
     */
    private static function updateOperationCommands(array &$actions, array $db_actions = null): void
    {
        $is_update = ($db_actions !== null);

        $ins_opcommands = [];
        $upd_opcommands = [];

        $ins_opcommand_grps = [];
        $del_opcommand_grpids = [];

        $ins_opcommand_hsts = [];
        $del_opcommand_hstids = [];

        foreach ($actions as &$action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                $db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

                foreach ($action[$operation_group] as &$operation) {
                    if ($operation['operationtype'] != OPERATION_TYPE_COMMAND) {
                        continue;
                    }

                    $db_operation = array_key_exists($operation['operationid'], $db_operations)
                        ? $db_operations[$operation['operationid']]
                        : [];

                    if (array_key_exists('opcommand', $db_operation)) {
                        $upd_opcommand = DB::getUpdatedValues('opcommand', $operation['opcommand'],
                            $db_operation['opcommand']
                        );

                        if ($upd_opcommand) {
                            $upd_opcommands[] = [
                                'values' => $upd_opcommand,
                                'where' => ['operationid' => $operation['operationid']]
                            ];
                        }
                    } else {
                        $ins_opcommands[] =
                            ['operationid' => $operation['operationid']] + $operation['opcommand'];
                    }

                    if (array_key_exists('opcommand_grp', $operation)) {
                        $db_opcommand_grps = array_key_exists('opcommand_grp', $db_operation)
                            ? array_column($db_operation['opcommand_grp'], null, 'groupid')
                            : [];

                        foreach ($operation['opcommand_grp'] as &$opcommand_grp) {
                            if (array_key_exists($opcommand_grp['groupid'], $db_opcommand_grps)) {
                                $db_opcommand_grp = $db_opcommand_grps[$opcommand_grp['groupid']];
                                $opcommand_grp['opcommand_grpid'] = $db_opcommand_grp['opcommand_grpid'];
                                unset($db_opcommand_grps[$opcommand_grp['groupid']]);
                            } else {
                                $ins_opcommand_grps[] =
                                    ['operationid' => $operation['operationid']] + $opcommand_grp;
                            }
                        }
                        unset($opcommand_grp);

                        $del_opcommand_grpids = array_merge($del_opcommand_grpids,
                            array_column($db_opcommand_grps, 'opcommand_grpid')
                        );
                    }

                    if (array_key_exists('opcommand_hst', $operation)) {
                        $db_opcommand_hsts = array_key_exists('opcommand_hst', $db_operation)
                            ? array_column($db_operation['opcommand_hst'], null, 'hostid')
                            : [];

                        foreach ($operation['opcommand_hst'] as &$opcommand_hst) {
                            if (array_key_exists($opcommand_hst['hostid'], $db_opcommand_hsts)) {
                                $db_opcommand_hst = $db_opcommand_hsts[$opcommand_hst['hostid']];
                                $opcommand_hst['opcommand_hstid'] = $db_opcommand_hst['opcommand_hstid'];
                                unset($db_opcommand_hsts[$opcommand_hst['hostid']]);
                            } else {
                                $ins_opcommand_hsts[] =
                                    ['operationid' => $operation['operationid']] + $opcommand_hst;
                            }
                        }
                        unset($opcommand_hst);

                        $del_opcommand_hstids = array_merge($del_opcommand_hstids,
                            array_column($db_opcommand_hsts, 'opcommand_hstid')
                        );
                    }
                }
                unset($operation);
            }
        }
        unset($action);

        if ($del_opcommand_grpids) {
            DB::delete('opcommand_grp', ['opcommand_grpid' => $del_opcommand_grpids]);
        }

        if ($del_opcommand_hstids) {
            DB::delete('opcommand_hst', ['opcommand_hstid' => $del_opcommand_hstids]);
        }

        if ($upd_opcommands) {
            DB::update('opcommand', $upd_opcommands);
        }

        if ($ins_opcommands) {
            DB::insert('opcommand', $ins_opcommands, false);
        }

        if ($ins_opcommand_grps) {
            $opcommand_grpids = DB::insert('opcommand_grp', $ins_opcommand_grps);
        }

        if ($ins_opcommand_hsts) {
            $opcommand_hstids = DB::insert('opcommand_hst', $ins_opcommand_hsts);
        }

        foreach ($actions as &$action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                foreach ($action[$operation_group] as &$operation) {
                    if (array_key_exists('opcommand_grp', $operation)) {
                        foreach ($operation['opcommand_grp'] as &$opcommand_grp) {
                            if (!array_key_exists('opcommand_grpid', $opcommand_grp)) {
                                $opcommand_grp['opcommand_grpid'] = array_shift($opcommand_grpids);
                            }
                        }
                        unset($opcommand_grp);
                    }

                    if (array_key_exists('opcommand_hst', $operation)) {
                        foreach ($operation['opcommand_hst'] as &$opcommand_hst) {
                            if (!array_key_exists('opcommand_hstid', $opcommand_hst)) {
                                $opcommand_hst['opcommand_hstid'] = array_shift($opcommand_hstids);
                            }
                        }
                        unset($opcommand_hst);
                    }
                }
                unset($operation);
            }
        }
        unset($action);
    }

    /**
     * @param array $actions
     * @param array|null $db_actions
     */
    private static function updateOperationGroups(array &$actions, array $db_actions = null): void
    {
        $is_update = ($db_actions !== null);

        $ins_opgroups = [];
        $del_opgroupids = [];

        foreach ($actions as &$action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                $db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

                foreach ($action[$operation_group] as &$operation) {
                    // Proceed only if operation type is OPERATION_TYPE_GROUP_ADD or OPERATION_TYPE_GROUP_REMOVE.
                    if (!array_key_exists('opgroup', $operation)) {
                        continue;
                    }

                    $db_operation = array_key_exists($operation['operationid'], $db_operations)
                        ? $db_operations[$operation['operationid']]
                        : [];

                    $db_opgroups = array_key_exists('opgroup', $db_operation)
                        ? array_column($db_operation['opgroup'], null, 'groupid')
                        : [];

                    foreach ($operation['opgroup'] as &$opgroup) {
                        if (array_key_exists($opgroup['groupid'], $db_opgroups)) {
                            $db_opgroup = $db_opgroups[$opgroup['groupid']];
                            $opgroup['opgroupid'] = $db_opgroup['opgroupid'];
                            unset($db_opgroups[$opgroup['groupid']]);
                        } else {
                            $ins_opgroups[] = ['operationid' => $operation['operationid']] + $opgroup;
                        }
                    }
                    unset($opgroup);

                    $del_opgroupids = array_merge($del_opgroupids, array_column($db_opgroups, 'opgroupid'));
                }
                unset($operation);
            }
        }
        unset($action);

        if ($del_opgroupids) {
            DB::delete('opgroup', ['opgroupid' => $del_opgroupids]);
        }

        if ($ins_opgroups) {
            $opgroupids = DB::insert('opgroup', $ins_opgroups);
        }

        foreach ($actions as &$action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                foreach ($action[$operation_group] as &$operation) {
                    if (!array_key_exists('opgroup', $operation)) {
                        continue;
                    }

                    foreach ($operation['opgroup'] as &$opgroup) {
                        if (!array_key_exists('opgroupid', $opgroup)) {
                            $opgroup['opgroupid'] = array_shift($opgroupids);
                        }
                    }
                    unset($opgroup);
                }
                unset($operation);
            }
        }
        unset($action);
    }

    /**
     * @param array $actions
     * @param array|null $db_actions
     */
    private static function updateOperationTemplates(array &$actions, array $db_actions = null): void
    {
        $is_update = ($db_actions !== null);

        $ins_optemplates = [];
        $del_optemplateids = [];

        foreach ($actions as &$action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                $db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

                foreach ($action[$operation_group] as &$operation) {
                    // Proceed only if operation type is OPERATION_TYPE_TEMPLATE_ADD or OPERATION_TYPE_TEMPLATE_REMOVE.
                    if (!array_key_exists('optemplate', $operation)) {
                        continue;
                    }

                    $db_operation = array_key_exists($operation['operationid'], $db_operations)
                        ? $db_operations[$operation['operationid']]
                        : [];

                    $db_optemplates = array_key_exists('optemplate', $db_operation)
                        ? array_column($db_operation['optemplate'], null, 'templateid')
                        : [];

                    foreach ($operation['optemplate'] as &$optemplate) {
                        if (array_key_exists($optemplate['templateid'], $db_optemplates)) {
                            $db_optemplate = $db_optemplates[$optemplate['templateid']];
                            $optemplate['optemplateid'] = $db_optemplate['optemplateid'];
                            unset($db_optemplates[$optemplate['templateid']]);
                        } else {
                            $ins_optemplates[] = ['operationid' => $operation['operationid']] + $optemplate;
                        }
                    }
                    unset($optemplate);

                    $del_optemplateids = array_merge($del_optemplateids, array_column($db_optemplates, 'optemplateid'));
                }
                unset($operation);
            }
        }
        unset($action);

        if ($del_optemplateids) {
            DB::delete('optemplate', ['optemplateid' => $del_optemplateids]);
        }

        if ($ins_optemplates) {
            $optemplateids = DB::insert('optemplate', $ins_optemplates);
        }

        foreach ($actions as &$action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                foreach ($action[$operation_group] as &$operation) {
                    if (!array_key_exists('optemplate', $operation)) {
                        continue;
                    }

                    foreach ($operation['optemplate'] as &$optemplate) {
                        if (!array_key_exists('optemplateid', $optemplate)) {
                            $optemplate['optemplateid'] = array_shift($optemplateids);
                        }
                    }
                    unset($optemplate);
                }
                unset($operation);
            }
        }
        unset($action);
    }

    /**
     * @param array $actions
     * @param array|null $db_actions
     */
    private static function updateOperationInventories(array $actions, array $db_actions = null): void
    {
        $is_update = ($db_actions !== null);

        $ins_opinventories = [];
        $upd_opinventories = [];

        foreach ($actions as $action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                $db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

                foreach ($action[$operation_group] as $operation) {
                    if ($operation['operationtype'] != OPERATION_TYPE_HOST_INVENTORY) {
                        continue;
                    }

                    $db_operation = array_key_exists($operation['operationid'], $db_operations)
                        ? $db_operations[$operation['operationid']]
                        : [];

                    if (array_key_exists('opinventory', $db_operation)) {
                        $upd_opinventory = DB::getUpdatedValues('opinventory', $operation['opinventory'],
                            $db_operation['opinventory']
                        );

                        if ($upd_opinventory) {
                            $upd_opinventories[] = [
                                'values' => $upd_opinventory,
                                'where' => ['operationid' => $operation['operationid']]
                            ];
                        }
                    } else {
                        $ins_opinventories[] =
                            ['operationid' => $operation['operationid']] + $operation['opinventory'];
                    }
                }
            }
        }

        if ($upd_opinventories) {
            DB::update('opinventory', $upd_opinventories);
        }

        if ($ins_opinventories) {
            DB::insert('opinventory', $ins_opinventories, false);
        }
    }


    /**
     * Add existing filter with conditions and operations to $db_actions if they are affected by the update.
     *
     * @param array $actions
     * @param array|null $db_actions
     */
    private static function addAffectedObjects(array $actions, array &$db_actions = null): void
    {
        $actionids = ['filter' => [], 'operations' => []];

        foreach ($actions as $action) {
            if (array_key_exists('filter', $action)) {
                $actionids['filter'][] = $action['actionid'];
                $db_actions[$action['actionid']]['filter'] = [];
                $db_actions[$action['actionid']]['filter']['conditions'] = [];
            }

            if (!array_intersect_key(array_flip(ActionForm::OPERATION_GROUPS), $action)) {
                continue;
            }

            $actionids['operations'][] = $action['actionid'];

            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                $db_actions[$action['actionid']][$operation_group] = [];
            }
        }

        if ($actionids['filter']) {

            $db_filters = Actions::find()
                ->select(['actionid', 'evaltype', 'formula'])
                ->andWhere(['actionid' => $actionids['filter']])
                ->asArray()->all() ?: [];

            foreach ($db_filters as $db_filter) {
                $db_actions[$db_filter['actionid']]['filter'] += array_diff_key($db_filter, array_flip(['actionid']));
            }


            $db_conditions = Conditions::find()
                ->select(['conditionid', 'actionid', 'conditiontype', 'operator', 'value', 'value2'])
                ->andWhere(['actionid' => $actionids['filter']])
                ->asArray()->all() ?: [];

            foreach ($db_conditions as $db_condition) {
                $db_actions[$db_condition['actionid']]['filter']['conditions'][$db_condition['conditionid']] =
                    array_diff_key($db_condition, array_flip(['actionid']));
            }

            foreach ($db_actions as &$db_action) {
                if ($db_action['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
                    $formula = $db_action['filter']['formula'];

                    $formulaids = CConditionHelper::getFormulaIds($formula);

                    foreach ($db_action['filter']['conditions'] as &$db_condition) {
                        $db_condition['formulaid'] = $formulaids[$db_condition['conditionid']];
                    }
                    unset($db_condition);
                }
            }
            unset($db_action);
        }

        if (!$actionids['operations']) {
            return;
        }

        $operationids = array_fill_keys([
            'opconditions', 'opmessage_grp', 'opmessage_usr', 'opcommand_grp', 'opcommand_hst', 'opgroup', 'optemplate'
        ], []);

        $sql_operations =
            'SELECT o.operationid,o.actionid,o.operationtype,o.esc_period,o.esc_step_from,o.esc_step_to,o.evaltype,' .
            'o.recovery,m.default_msg,m.subject,m.message,m.mediatypeid,c.scriptid,i.inventory_mode' .
            ' FROM operations o' .
            ' LEFT JOIN opmessage m ON m.operationid=o.operationid' .
            ' LEFT JOIN opcommand c ON c.operationid=o.operationid' .
            ' LEFT JOIN opinventory i ON i.operationid=o.operationid' .
            ' WHERE ' . ZSqlHelper::dbConditionId('o.actionid', $actionids['operations']);
        $db_operations = Operations::getDb()->createCommand($sql_operations)->queryAll();
        foreach ($db_operations as $db_operation) {
            $operation = [
                'operationid' => $db_operation['operationid'],
                'operationtype' => $db_operation['operationtype'],
                'evaltype' => $db_operation['evaltype'],
                'recovery' => $db_operation['recovery']
            ];

            $eventsource = $db_actions[$db_operation['actionid']]['eventsource'];

            if ($db_operation['recovery'] == ACTION_OPERATION
                && in_array($eventsource, [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
                $operation['esc_period'] = $db_operation['esc_period'];
                $operation['esc_step_from'] = $db_operation['esc_step_from'];
                $operation['esc_step_to'] = $db_operation['esc_step_to'];

                if ($eventsource == EVENT_SOURCE_TRIGGERS) {
                    $operation['opconditions'] = [];
                    $operationids['opconditions'][$db_operation['operationid']] = true;
                }
            }

            switch ($db_operation['operationtype']) {
                case OPERATION_TYPE_MESSAGE:
                case OPERATION_TYPE_RECOVERY_MESSAGE:
                case OPERATION_TYPE_UPDATE_MESSAGE:
                    $operation['opmessage'] = [
                        'default_msg' => $db_operation['default_msg'],
                        'subject' => $db_operation['subject'],
                        'message' => $db_operation['message'],
                        'mediatypeid' => $db_operation['mediatypeid']
                    ];

                    if ($db_operation['operationtype'] == OPERATION_TYPE_MESSAGE) {
                        $operation['opmessage_grp'] = [];
                        $operation['opmessage_usr'] = [];
                        $operationids['opmessage_grp'][$db_operation['operationid']] = true;
                        $operationids['opmessage_usr'][$db_operation['operationid']] = true;
                    }
                    break;

                case OPERATION_TYPE_COMMAND:
                    $operation['opcommand']['scriptid'] = $db_operation['scriptid'];

                    if ($eventsource != EVENT_SOURCE_SERVICE) {
                        $operation['opcommand_grp'] = [];
                        $operation['opcommand_hst'] = [];
                        $operationids['opcommand_grp'][$db_operation['operationid']] = true;
                        $operationids['opcommand_hst'][$db_operation['operationid']] = true;
                    }
                    break;

                case OPERATION_TYPE_GROUP_ADD:
                case OPERATION_TYPE_GROUP_REMOVE:
                    $operationids['opgroup'][$db_operation['operationid']] = true;
                    break;

                case OPERATION_TYPE_TEMPLATE_ADD:
                case OPERATION_TYPE_TEMPLATE_REMOVE:
                    $operationids['optemplate'][$db_operation['operationid']] = true;
                    break;

                case OPERATION_TYPE_HOST_INVENTORY:
                    $operation['opinventory']['inventory_mode'] = $db_operation['inventory_mode'];
                    break;
            }

            $operation_group = ActionForm::OPERATION_GROUPS[$db_operation['recovery']];

            $db_actions[$db_operation['actionid']][$operation_group][$db_operation['operationid']] = $operation;
        }

        $db_opdata = [];

        if ($operationids['opconditions']) {
            $db_opconditions = Opconditions::find()
                ->select(['opconditionid', 'operationid', 'conditiontype', 'operator', 'value'])
                ->andWhere(['operationid' => array_keys($operationids['opconditions'])])
                ->asArray()->all();
            foreach ($db_opconditions as $db_opcondition) {
                $db_opdata[$db_opcondition['operationid']]['opconditions'][$db_opcondition['opconditionid']] =
                    array_diff_key($db_opcondition, array_flip(['operationid']));
            }
        }

        if ($operationids['opmessage_grp']) {
            $db_opmessage_grps = OpmessageGrp::find()
                ->select(['opmessage_grpid', 'operationid', 'usrgrpid'])
                ->andWhere(['operationid' => array_keys($operationids['opmessage_grp'])])
                ->asArray()->all();

            foreach ($db_opmessage_grps as $db_opmessage_grp) {
                $db_opdata[$db_opmessage_grp['operationid']]['opmessage_grp'][$db_opmessage_grp['opmessage_grpid']] =
                    array_diff_key($db_opmessage_grp, array_flip(['operationid']));
            }
        }

        if ($operationids['opmessage_usr']) {
            $db_opmessage_usrs = OpmessageUsr::find()
                ->select(['opmessage_usrid', 'operationid', 'userid'])
                ->andWhere(['operationid' => array_keys($operationids['opmessage_usr'])])
                ->asArray()->all();

            foreach ($db_opmessage_usrs as $db_opmessage_usr) {
                $db_opdata[$db_opmessage_usr['operationid']]['opmessage_usr'][$db_opmessage_usr['opmessage_usrid']] =
                    array_diff_key($db_opmessage_usr, array_flip(['operationid']));
            }
        }

        if ($operationids['opcommand_grp']) {

            $db_opcommand_grps = OpcommandGrp::find()
                ->select(['opcommand_grpid', 'operationid', 'groupid'])
                ->andWhere(['operationid' => array_keys($operationids['opcommand_grp'])])
                ->asArray()->all();

            foreach ($db_opcommand_grps as $db_opcommand_grp) {
                $db_opdata[$db_opcommand_grp['operationid']]['opcommand_grp'][$db_opcommand_grp['opcommand_grpid']] =
                    array_diff_key($db_opcommand_grp, array_flip(['operationid']));
            }
        }

        if ($operationids['opcommand_hst']) {

            $db_opcommand_hsts = OpcommandHst::find()
                ->select(['opcommand_hstid', 'operationid', 'hostid'])
                ->andWhere(['operationid' => array_keys($operationids['opcommand_hst'])])
                ->asArray()->all();

            foreach ($db_opcommand_hsts as $db_opcommand_hst) {
                $db_opdata[$db_opcommand_hst['operationid']]['opcommand_hst'][$db_opcommand_hst['opcommand_hstid']] =
                    array_diff_key($db_opcommand_hst, array_flip(['operationid']));
            }
        }

        if ($operationids['opgroup']) {

            $db_opgroups = Opgroup::find()
                ->select(['opgroupid', 'operationid', 'groupid'])
                ->andWhere(['operationid' => array_keys($operationids['opgroup'])])
                ->asArray()->all();

            foreach ($db_opgroups as $db_opgroup) {
                $db_opdata[$db_opgroup['operationid']]['opgroup'][$db_opgroup['opgroupid']] =
                    array_diff_key($db_opgroup, array_flip(['operationid']));
            }
        }

        if ($operationids['optemplate']) {
            $db_optemplates = Optemplate::find()
                ->select(['optemplateid', 'operationid', 'templateid'])
                ->andWhere(['operationid' => array_keys($operationids['optemplate'])])
                ->asArray()->all();
            foreach ($db_optemplates as $db_optemplate) {
                $db_opdata[$db_optemplate['operationid']]['optemplate'][$db_optemplate['optemplateid']] =
                    array_diff_key($db_optemplate, array_flip(['operationid']));
            }
        }

        foreach ($db_actions as &$db_action) {
            foreach (ActionForm::OPERATION_GROUPS as $operation_group) {
                if (!array_key_exists($operation_group, $db_action)) {
                    continue;
                }

                foreach ($db_action[$operation_group] as &$db_operation) {
                    if (array_key_exists($db_operation['operationid'], $db_opdata)) {
                        $db_operation = array_merge($db_operation, $db_opdata[$db_operation['operationid']]);
                    }
                }
                unset($db_operation);
            }
        }
        unset($db_action);
    }


}
