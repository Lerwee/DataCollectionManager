<?php

namespace app\customs\zapi\services\assist;

use app\common\components\Result;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\CConditionHelper;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\helpers\ValueMapHelper;
use app\customs\zapi\common\managers\DiscoveryRuleManager;
use app\customs\zapi\common\parsers\CUpdateIntervalParser;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\LLDMacroValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\ObjectValidator;
use app\customs\zapi\common\validators\TimeUnitValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\z\CCollectionValidator;
use app\customs\zapi\common\validators\z\CLimitedSetValidator;
use app\customs\zapi\common\validators\z\CStringValidator;
use app\customs\zapi\common\validators\z\object\CConditionValidator;
use app\customs\zapi\common\validators\z\schema\CSchemaValidator;
use app\customs\zapi\services\HostPrototypeService;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\zbx\Items;
use app\modules\libzbx\models\zbx\LldOverride;
use yii\base\Exception;
use yii\db\Query;

class DiscoverRuleAssist extends BaseDiscoverRuleAssist
{
    /**
     * Define a set of supported pre-processing rules.
     *
     * @var array
     */
    const SUPPORTED_PREPROCESSING_TYPES = [PRS_PREPROC_REGSUB, PRS_PREPROC_JSONPATH,
        PRS_PREPROC_VALIDATE_NOT_REGEX, PRS_PREPROC_ERROR_FIELD_JSON, PRS_PREPROC_THROTTLE_TIMED_VALUE,
        PRS_PREPROC_SCRIPT, PRS_PREPROC_PROMETHEUS_TO_JSON, PRS_PREPROC_XPATH, PRS_PREPROC_ERROR_FIELD_XML,
        PRS_PREPROC_CSV_TO_JSON, PRS_PREPROC_STR_REPLACE, PRS_PREPROC_XML_TO_JSON, PRS_PREPROC_SNMP_WALK_VALUE,
        PRS_PREPROC_SNMP_WALK_TO_JSON
    ];

    /**
     * Define a set of supported item types.
     *
     * @var array
     */
    const SUPPORTED_ITEM_TYPES = [ITEM_TYPE_PERSEUS, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL,
        ITEM_TYPE_PERSEUS_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH,
        ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
    ];

    /**
     * Add DiscoveryRule.
     *
     * @param array $items
     *
     * @return Result
     */
    public function create(array $items): Result
    {
        try {
            $items = prs_toArray($items);
            $this->checkInput($items);
            foreach ($items as &$item) {
                if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
                    if (array_key_exists('query_fields', $item)) {
                        $item['query_fields'] = $item['query_fields'] ? json_encode($item['query_fields']) : '';
                    }

                    if (array_key_exists('headers', $item)) {
                        $item['headers'] = $this->headersArrayToString($item['headers']);
                    }

                    if (array_key_exists('request_method', $item) && $item['request_method'] == HTTPCHECK_REQUEST_HEAD
                        && !array_key_exists('retrieve_mode', $item)) {
                        $item['retrieve_mode'] = HTTPTEST_STEP_RETRIEVE_MODE_HEADERS;
                    }
                } else {
                    $item['query_fields'] = '';
                    $item['headers'] = '';
                }

                // Option 'Convert to JSON' is not supported for discovery rule.
                unset($item['itemid'], $item['output_format']);
            }
            unset($item);

            // Get only hosts not templates from items
            $hosts = Hosts::find()->select(['hostid'])
                ->where(['hostid' => prs_objectValues($items, 'hostid')])
                ->indexBy('hostid')->asArray()->all();
            foreach ($items as &$item) {
                if (array_key_exists($item['hostid'], $hosts)) {
                    $item['rtdata'] = true;
                }
            }
            unset($item);


            $this->validateCreateLLDMacroPaths($items);
            $this->validateDependentItems($items);
            $this->createReal($items);
            $this->inherit($items);
            return $this->success(['itemids' => prs_objectValues($items, 'itemid')]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            \Yii::error(parse_exception($e));
            return $this->error(60750601, $e->getMessage());
        }
    }

    /**
     * Update DiscoveryRule.
     *
     * @param array $items
     *
     * @return Result
     */
    public function update(array $items): Result
    {
        try {
            $items = prs_toArray($items);
            $db_items = DiscoverRuleHelper::getDiscoverRules([
                'output' => ['itemid', 'name', 'type', 'master_itemid', 'authtype', 'allow_traps', 'retrieve_mode'],
                'selectFilter' => ['evaltype', 'formula', 'conditions'],
                'itemids' => prs_objectValues($items, 'itemid'),
                'preservekeys' => true
            ]);

            $this->checkInput($items, true, $db_items);
            $this->validateUpdateLLDMacroPaths($items);

            $items = $this->extendFromObjects(prs_toHash($items, 'itemid'), $db_items, ['flags', 'type', 'authtype',
                'master_itemid'
            ]);
            $this->validateDependentItems($items);

            $defaults = DB::getDefaults('items');
            $clean = [
                ITEM_TYPE_HTTPAGENT => [
                    'url' => '',
                    'query_fields' => '',
                    'timeout' => $defaults['timeout'],
                    'status_codes' => $defaults['status_codes'],
                    'follow_redirects' => $defaults['follow_redirects'],
                    'request_method' => $defaults['request_method'],
                    'allow_traps' => $defaults['allow_traps'],
                    'post_type' => $defaults['post_type'],
                    'http_proxy' => '',
                    'headers' => '',
                    'retrieve_mode' => $defaults['retrieve_mode'],
                    'output_format' => $defaults['output_format'],
                    'ssl_key_password' => '',
                    'verify_peer' => $defaults['verify_peer'],
                    'verify_host' => $defaults['verify_host'],
                    'ssl_cert_file' => '',
                    'ssl_key_file' => '',
                    'posts' => ''
                ]
            ];

            // set the default values required for updating
            foreach ($items as &$item) {
                $type_change = (array_key_exists('type', $item) && $item['type'] != $db_items[$item['itemid']]['type']);

                if (isset($item['filter'])) {
                    foreach ($item['filter']['conditions'] as &$condition) {
                        $condition += [
                            'operator' => DB::getDefault('item_condition', 'operator')
                        ];
                    }
                    unset($condition);
                }

                if ($type_change && $db_items[$item['itemid']]['type'] == ITEM_TYPE_HTTPAGENT) {
                    $item = array_merge($item, $clean[ITEM_TYPE_HTTPAGENT]);

                    if ($item['type'] != ITEM_TYPE_SSH) {
                        $item['authtype'] = $defaults['authtype'];
                        $item['username'] = '';
                        $item['password'] = '';
                    }

                    if ($item['type'] != ITEM_TYPE_TRAPPER) {
                        $item['trapper_hosts'] = '';
                    }
                }

                if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
                    // Clean username and password when authtype is set to PRS_HTTP_AUTH_NONE.
                    if ($item['authtype'] == PRS_HTTP_AUTH_NONE) {
                        $item['username'] = '';
                        $item['password'] = '';
                    }

                    if (array_key_exists('allow_traps', $item) && $item['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_OFF
                        && $item['allow_traps'] != $db_items[$item['itemid']]['allow_traps']) {
                        $item['trapper_hosts'] = '';
                    }

                    if (array_key_exists('query_fields', $item) && is_array($item['query_fields'])) {
                        $item['query_fields'] = $item['query_fields'] ? json_encode($item['query_fields']) : '';
                    }

                    if (array_key_exists('headers', $item) && is_array($item['headers'])) {
                        $item['headers'] = $this->headersArrayToString($item['headers']);
                    }

                    if (array_key_exists('request_method', $item) && $item['request_method'] == HTTPCHECK_REQUEST_HEAD
                        && !array_key_exists('retrieve_mode', $item)
                        && $db_items[$item['itemid']]['retrieve_mode'] != HTTPTEST_STEP_RETRIEVE_MODE_HEADERS) {
                        $item['retrieve_mode'] = HTTPTEST_STEP_RETRIEVE_MODE_HEADERS;
                    }
                } else {
                    $item['query_fields'] = '';
                    $item['headers'] = '';
                }

                if ($type_change && $db_items[$item['itemid']]['type'] == ITEM_TYPE_SCRIPT) {
                    if ($item['type'] != ITEM_TYPE_SSH && $item['type'] != ITEM_TYPE_DB_MONITOR
                        && $item['type'] != ITEM_TYPE_TELNET && $item['type'] != ITEM_TYPE_CALCULATED) {
                        $item['params'] = '';
                    }

                    if ($item['type'] != ITEM_TYPE_HTTPAGENT) {
                        $item['timeout'] = $defaults['timeout'];
                    }
                }

                // Option 'Convert to JSON' is not supported for discovery rule.
                unset($item['output_format']);
            }
            unset($item);

            // update
            $this->updateReal($items);
            $this->inherit($items);
            return $this->success(['itemids' => prs_objectValues($items, 'itemid')]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750602, $e->getMessage());
        }
    }

    /**
     * Delete DiscoveryRules.
     *
     * @param array $ruleids
     *
     * @return Result
     */
    public function delete(array $ruleids): Result
    {
        try {
            $this->validateDelete($ruleids);
            DiscoveryRuleManager::delete($ruleids);
            return $this->success(['ruleids' => $ruleids]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750603, $e->getMessage());
        }
    }

    /**
     * @param array $ruleids
     * @throws ValidateException
     * @throws \yii\db\Exception
     */
    private function validateDelete(array &$ruleids)
    {
        $ruleids = filter_integer($ruleids);

        $db_rules = DiscoverRuleHelper::getDiscoverRules([
            'output' => ['templateid'],
            'itemids' => $ruleids,
            'editable' => true,
            'preservekeys' => true
        ]);

        foreach ($ruleids as $ruleid) {
            if (!array_key_exists($ruleid, $db_rules)) {
                throw new ValidateException(60750004);
            }

            $db_rule = $db_rules[$ruleid];

            if ($db_rule['templateid'] != 0) {
                self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Cannot delete templated items.'));
            }
        }
    }

    protected function createReal(array &$items)
    {
        $items_rtdata = [];
        $create_items = [];

        // create items without formulas, they will be updated when items and conditions are saved
        foreach ($items as $key => $item) {
            if (array_key_exists('filter', $item)) {
                $item['evaltype'] = $item['filter']['evaltype'];
                unset($item['filter']);
            }

            if (array_key_exists('rtdata', $item)) {
                $items_rtdata[$key] = [];
                unset($item['rtdata']);
            }

            $create_items[] = $item;
        }
        $create_items = DB::save('items', $create_items);

        foreach ($items_rtdata as $key => &$value) {
            $value['itemid'] = $create_items[$key]['itemid'];
        }
        unset($value);

        DB::insert('item_rtdata', $items_rtdata, false);

        $conditions = [];
        $itemids = [];

        foreach ($items as $key => &$item) {
            $item['itemid'] = $create_items[$key]['itemid'];
            $itemids[$key] = $item['itemid'];

            // conditions
            if (isset($item['filter'])) {
                foreach ($item['filter']['conditions'] as $condition) {
                    $condition['itemid'] = $item['itemid'];

                    $conditions[] = $condition;
                }
            }
        }
        unset($item);

        $conditions = DB::save('item_condition', $conditions);

        $item_conditions = [];

        foreach ($conditions as $condition) {
            $item_conditions[$condition['itemid']][] = $condition;
        }

        $lld_macro_paths = [];

        foreach ($items as $item) {
            // update formulas
            if (isset($item['filter']) && $item['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
                $this->updateFormula($item['itemid'], $item['filter']['formula'], $item_conditions[$item['itemid']]);
            }

            // $item['lld_macro_paths'] expects to be filled with validated fields 'lld_macro' and 'path' and values.
            if (array_key_exists('lld_macro_paths', $item)) {
                foreach ($item['lld_macro_paths'] as $lld_macro_path) {
                    $lld_macro_paths[] = $lld_macro_path + ['itemid' => $item['itemid']];
                }
            }
        }

        DB::insertBatch('lld_macro_path', $lld_macro_paths);

        $this->createItemParameters($items, $itemids);
        $this->createItemPreprocessing($items);
        $this->createOverrides($items);
    }

    /**
     * Creates overrides for low-level discovery rules.
     *
     * @param array $items Low-level discovery rules.
     */
    protected function createOverrides(array $items)
    {
        $overrides = [];

        foreach ($items as $item) {
            if (array_key_exists('overrides', $item)) {
                foreach ($item['overrides'] as $override) {
                    // Formula will be added after conditions.
                    $new_override = [
                        'itemid' => $item['itemid'],
                        'name' => $override['name'],
                        'step' => $override['step'],
                        'stop' => array_key_exists('stop', $override) ? $override['stop'] : PRS_LLD_OVERRIDE_STOP_NO
                    ];

                    $new_override['evaltype'] = array_key_exists('filter', $override)
                        ? $override['filter']['evaltype']
                        : DB::getDefault('lld_override', 'evaltype');

                    $overrides[] = $new_override;
                }
            }
        }

        $overrideids = DB::insertBatch('lld_override', $overrides);

        if ($overrideids) {
            $ovrd_conditions = [];
            $ovrd_idx = 0;
            $cnd_idx = 0;

            foreach ($items as &$item) {
                if (array_key_exists('overrides', $item)) {
                    foreach ($item['overrides'] as &$override) {
                        $override['lld_overrideid'] = $overrideids[$ovrd_idx++];

                        if (array_key_exists('filter', $override)) {
                            foreach ($override['filter']['conditions'] as $condition) {
                                $ovrd_conditions[] = [
                                    'macro' => $condition['macro'],
                                    'value' => $condition['value'],
                                    'formulaid' => array_key_exists('formulaid', $condition)
                                        ? $condition['formulaid']
                                        : '',
                                    'operator' => array_key_exists('operator', $condition)
                                        ? $condition['operator']
                                        : DB::getDefault('lld_override_condition', 'operator'),
                                    'lld_overrideid' => $override['lld_overrideid']
                                ];
                            }
                        }
                    }
                    unset($override);
                }
            }
            unset($item);

            $conditionids = DB::insertBatch('lld_override_condition', $ovrd_conditions);

            $ids = [];

            if ($conditionids) {
                foreach ($items as &$item) {
                    if (array_key_exists('overrides', $item)) {
                        foreach ($item['overrides'] as &$override) {
                            if (array_key_exists('filter', $override)) {
                                foreach ($override['filter']['conditions'] as &$condition) {
                                    $condition['lld_override_conditionid'] = $conditionids[$cnd_idx++];
                                }
                                unset($condition);
                            }
                        }
                        unset($override);
                    }
                }
                unset($item);

                foreach ($items as $item) {
                    if (array_key_exists('overrides', $item)) {
                        foreach ($item['overrides'] as $override) {
                            if (array_key_exists('filter', $override)
                                && $override['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
                                $ids = [];
                                foreach ($override['filter']['conditions'] as $condition) {
                                    $ids[$condition['formulaid']] = $condition['lld_override_conditionid'];
                                }

                                $formula = CConditionHelper::replaceLetterIds($override['filter']['formula'], $ids);
                                DB::updateByPk('lld_override', $override['lld_overrideid'], ['formula' => $formula]);
                            }
                        }
                    }
                }
            }

            $operations = [];
            foreach ($items as $item) {
                if (array_key_exists('overrides', $item)) {
                    foreach ($item['overrides'] as $override) {
                        if (array_key_exists('operations', $override)) {
                            foreach ($override['operations'] as $operation) {
                                $operations[] = [
                                    'lld_overrideid' => $override['lld_overrideid'],
                                    'operationobject' => $operation['operationobject'],
                                    'operator' => array_key_exists('operator', $operation)
                                        ? $operation['operator']
                                        : DB::getDefault('lld_override_operation', 'operator'),
                                    'value' => array_key_exists('value', $operation) ? $operation['value'] : ''
                                ];
                            }
                        }
                    }
                }
            }

            $operationids = DB::insertBatch('lld_override_operation', $operations);

            $opr_idx = 0;
            $opstatus = [];
            $opdiscover = [];
            $opperiod = [];
            $ophistory = [];
            $optrends = [];
            $opseverity = [];
            $optag = [];
            $optemplate = [];
            $opinventory = [];

            foreach ($items as $item) {
                if (array_key_exists('overrides', $item)) {
                    foreach ($item['overrides'] as $override) {
                        if (array_key_exists('operations', $override)) {
                            foreach ($override['operations'] as $operation) {
                                $operation['lld_override_operationid'] = $operationids[$opr_idx++];

                                // Discover status applies to all operation object types.
                                if (array_key_exists('opdiscover', $operation)) {
                                    $opdiscover[] = [
                                        'lld_override_operationid' => $operation['lld_override_operationid'],
                                        'discover' => $operation['opdiscover']['discover']
                                    ];
                                }

                                switch ($operation['operationobject']) {
                                    case OPERATION_OBJECT_ITEM_PROTOTYPE:
                                        if (array_key_exists('opstatus', $operation)) {
                                            $opstatus[] = [
                                                'lld_override_operationid' => $operation['lld_override_operationid'],
                                                'status' => $operation['opstatus']['status']
                                            ];
                                        }

                                        if (array_key_exists('opperiod', $operation)) {
                                            $opperiod[] = [
                                                'lld_override_operationid' => $operation['lld_override_operationid'],
                                                'delay' => $operation['opperiod']['delay']
                                            ];
                                        }

                                        if (array_key_exists('ophistory', $operation)) {
                                            $ophistory[] = [
                                                'lld_override_operationid' => $operation['lld_override_operationid'],
                                                'history' => $operation['ophistory']['history']
                                            ];
                                        }

                                        if (array_key_exists('optrends', $operation)) {
                                            $optrends[] = [
                                                'lld_override_operationid' => $operation['lld_override_operationid'],
                                                'trends' => $operation['optrends']['trends']
                                            ];
                                        }

                                        if (array_key_exists('optag', $operation)) {
                                            foreach ($operation['optag'] as $tag) {
                                                $optag[] = [
                                                    'lld_override_operationid' =>
                                                        $operation['lld_override_operationid'],
                                                    'tag' => $tag['tag'],
                                                    'value' => array_key_exists('value', $tag) ? $tag['value'] : ''
                                                ];
                                            }
                                        }
                                        break;

                                    case OPERATION_OBJECT_TRIGGER_PROTOTYPE:
                                        if (array_key_exists('opstatus', $operation)) {
                                            $opstatus[] = [
                                                'lld_override_operationid' => $operation['lld_override_operationid'],
                                                'status' => $operation['opstatus']['status']
                                            ];
                                        }

                                        if (array_key_exists('opseverity', $operation)) {
                                            $opseverity[] = [
                                                'lld_override_operationid' => $operation['lld_override_operationid'],
                                                'severity' => $operation['opseverity']['severity']
                                            ];
                                        }

                                        if (array_key_exists('optag', $operation)) {
                                            foreach ($operation['optag'] as $tag) {
                                                $optag[] = [
                                                    'lld_override_operationid' =>
                                                        $operation['lld_override_operationid'],
                                                    'tag' => $tag['tag'],
                                                    'value' => array_key_exists('value', $tag) ? $tag['value'] : ''
                                                ];
                                            }
                                        }
                                        break;

                                    case OPERATION_OBJECT_HOST_PROTOTYPE:
                                        if (array_key_exists('opstatus', $operation)) {
                                            $opstatus[] = [
                                                'lld_override_operationid' => $operation['lld_override_operationid'],
                                                'status' => $operation['opstatus']['status']
                                            ];
                                        }

                                        if (array_key_exists('optemplate', $operation)) {
                                            foreach ($operation['optemplate'] as $template) {
                                                $optemplate[] = [
                                                    'lld_override_operationid' =>
                                                        $operation['lld_override_operationid'],
                                                    'templateid' => $template['templateid']
                                                ];
                                            }
                                        }

                                        if (array_key_exists('optag', $operation)) {
                                            foreach ($operation['optag'] as $tag) {
                                                $optag[] = [
                                                    'lld_override_operationid' =>
                                                        $operation['lld_override_operationid'],
                                                    'tag' => $tag['tag'],
                                                    'value' => array_key_exists('value', $tag) ? $tag['value'] : ''
                                                ];
                                            }
                                        }

                                        if (array_key_exists('opinventory', $operation)) {
                                            $opinventory[] = [
                                                'lld_override_operationid' => $operation['lld_override_operationid'],
                                                'inventory_mode' => $operation['opinventory']['inventory_mode']
                                            ];
                                        }
                                        break;
                                }
                            }
                        }
                    }
                }
            }

            DB::insertBatch('lld_override_opstatus', $opstatus, false);
            DB::insertBatch('lld_override_opdiscover', $opdiscover, false);
            DB::insertBatch('lld_override_opperiod', $opperiod, false);
            DB::insertBatch('lld_override_ophistory', $ophistory, false);
            DB::insertBatch('lld_override_optrends', $optrends, false);
            DB::insertBatch('lld_override_opseverity', $opseverity, false);
            DB::insertBatch('lld_override_optag', $optag);
            DB::insertBatch('lld_override_optemplate', $optemplate);
            DB::insertBatch('lld_override_opinventory', $opinventory, false);
        }
    }

    protected function updateReal(array $items)
    {
        CArrayHelper::sort($items, ['itemid']);

        $ruleIds = prs_objectValues($items, 'itemid');

        $data = [];
        foreach ($items as $item) {
            $values = $item;

            if (isset($item['filter'])) {
                // clear the formula for non-custom expression rules
                if ($item['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
                    $values['formula'] = '';
                }

                $values['evaltype'] = $item['filter']['evaltype'];
                unset($values['filter']);
            }

            $data[] = ['values' => $values, 'where' => ['itemid' => $item['itemid']]];
        }
        DB::update('items', $data);

        $newRuleConditions = null;
        foreach ($items as $item) {
            // conditions
            if (isset($item['filter'])) {
                if ($newRuleConditions === null) {
                    $newRuleConditions = [];
                }

                $newRuleConditions[$item['itemid']] = [];
                foreach ($item['filter']['conditions'] as $condition) {
                    $condition['itemid'] = $item['itemid'];

                    $newRuleConditions[$item['itemid']][] = $condition;
                }
            }
        }

        // replace conditions
        $ruleConditions = [];
        if ($newRuleConditions !== null) {
            // fetch existing conditions
            $exConditions = (new Query())->select('item_conditionid,itemid,macro,value,operator')
                ->from('item_condition')
                ->where(['itemid' => $ruleIds])
                ->orderBy(['item_conditionid' => SORT_ASC])
                ->all();
            $exRuleConditions = [];
            foreach ($exConditions as $condition) {
                $exRuleConditions[$condition['itemid']][] = $condition;
            }

            // replace and add the new IDs
            $conditions = DB::replaceByPosition('item_condition', $exRuleConditions, $newRuleConditions);
            foreach ($conditions as $condition) {
                $ruleConditions[$condition['itemid']][] = $condition;
            }
        }

        $itemids = [];
        $lld_macro_paths = [];
        $db_lld_macro_paths = [];

        foreach ($items as $item) {
            // update formulas
            if (isset($item['filter']) && $item['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
                $this->updateFormula($item['itemid'], $item['filter']['formula'], $ruleConditions[$item['itemid']]);
            }

            // "lld_macro_paths" could be empty or filled with fields "lld_macro", "path" or "lld_macro_pathid".
            if (array_key_exists('lld_macro_paths', $item)) {
                $itemids[$item['itemid']] = true;

                if ($item['lld_macro_paths']) {
                    foreach ($item['lld_macro_paths'] as $lld_macro_path) {
                        $lld_macro_paths[] = $lld_macro_path + ['itemid' => $item['itemid']];
                    }
                }
            }
        }

        // Gather all existing LLD macros from given discovery rules.
        if ($itemids) {
            $db_lld_macro_paths = (new Query())->select(['lld_macro_pathid', 'itemid', 'lld_macro', 'path'])
                ->from(['lld_macro_path'])
                ->where(['itemid' => array_keys($itemids)])
                ->all();
        }

        /*
         * DB::replaceByPosition() does not allow to change records one by one due to unique indexes on two table
         * columns. Problems arise when given records are the same as records in DB and they are sorted differently.
         * That's why checking differences between old and new records is done manually.
         */

        $lld_macro_paths_to_update = [];

        foreach ($lld_macro_paths as $idx1 => $lld_macro_path) {
            foreach ($db_lld_macro_paths as $idx2 => $db_lld_macro_path) {
                if (array_key_exists('lld_macro_pathid', $lld_macro_path)) {
                    // Update records by primary key.

                    // Find matching "lld_macro_pathid" and update fields accordingly.
                    if (bccomp($lld_macro_path['lld_macro_pathid'], $db_lld_macro_path['lld_macro_pathid']) == 0) {
                        $fields_to_update = [];

                        if (array_key_exists('lld_macro', $lld_macro_path)
                            && $lld_macro_path['lld_macro'] === $db_lld_macro_path['lld_macro']) {
                            // If same "lld_macro" is found in DB, update only "path" if necessary.

                            if (array_key_exists('path', $lld_macro_path)
                                && $lld_macro_path['path'] !== $db_lld_macro_path['path']) {
                                $fields_to_update['path'] = $lld_macro_path['path'];
                            }
                        } else {
                            /*
                             * Update all other fields that correspond to given "lld_macro_pathid". Except for primary
                             * key "lld_macro_pathid" and "itemid".
                             */

                            foreach ($lld_macro_path as $field => $value) {
                                if ($field !== 'itemid' && $field !== 'lld_macro_pathid') {
                                    $fields_to_update[$field] = $value;
                                }
                            }
                        }

                        /*
                         * If there are any changes made, update fields in DB. Otherwise skip updating and result in
                         * success anyway.
                         */
                        if ($fields_to_update) {
                            $lld_macro_paths_to_update[] = $fields_to_update
                                + ['lld_macro_pathid' => $lld_macro_path['lld_macro_pathid']];
                        }

                        /*
                         * Remove processed LLD macros from the list. Macros left in $db_lld_macro_paths will be removed
                         * afterwards.
                         */
                        unset($db_lld_macro_paths[$idx2]);
                        unset($lld_macro_paths[$idx1]);
                    }
                    // Incorrect "lld_macro_pathid" cannot be given due to validation done previously.
                } else {
                    // Add or update fields by given "lld_macro".

                    if (bccomp($lld_macro_path['itemid'], $db_lld_macro_path['itemid']) == 0) {
                        if ($lld_macro_path['lld_macro'] === $db_lld_macro_path['lld_macro']) {
                            // If same "lld_macro" is given, add primary key and update only "path", if necessary.

                            if ($lld_macro_path['path'] !== $db_lld_macro_path['path']) {
                                $lld_macro_paths_to_update[] = [
                                    'lld_macro_pathid' => $db_lld_macro_path['lld_macro_pathid'],
                                    'path' => $lld_macro_path['path']
                                ];
                            }

                            /*
                             * Remove processed LLD macros from the list. Macros left in $db_lld_macro_paths will
                             * be removed afterwards. And macros left in $lld_macro_paths will be created.
                             */
                            unset($db_lld_macro_paths[$idx2]);
                            unset($lld_macro_paths[$idx1]);
                        }
                    }
                }
            }
        }

        // After all data has been collected, proceed with record update in DB.
        $lld_macro_pathids_to_delete = prs_objectValues($db_lld_macro_paths, 'lld_macro_pathid');

        if ($lld_macro_pathids_to_delete) {
            DB::delete('lld_macro_path', ['lld_macro_pathid' => $lld_macro_pathids_to_delete]);
        }

        if ($lld_macro_paths_to_update) {
            $data = [];

            foreach ($lld_macro_paths_to_update as $lld_macro_path) {
                $data[] = [
                    'values' => $lld_macro_path,
                    'where' => [
                        'lld_macro_pathid' => $lld_macro_path['lld_macro_pathid']
                    ]
                ];
            }

            DB::update('lld_macro_path', $data);
        }

        DB::insertBatch('lld_macro_path', $lld_macro_paths);

        $this->updateItemParameters($items);
        $this->updateItemPreprocessing($items);

        // Delete old overrides and replace with new ones if any.
        $ovrd_itemids = [];
        foreach ($items as $item) {
            if (array_key_exists('overrides', $item)) {
                $ovrd_itemids[$item['itemid']] = true;
            }
        }

        if ($ovrd_itemids) {
           LldOverride::deleteAll(['itemid' => array_keys($ovrd_itemids)]);
        }

        $this->createOverrides($items);
    }

    /**
     * Converts a formula with letters to a formula with IDs and updates it.
     *
     * @param string $itemId
     * @param string $evalFormula formula with letters
     * @param array $conditions
     */
    protected function updateFormula($itemId, $evalFormula, array $conditions)
    {
        $ids = [];
        foreach ($conditions as $condition) {
            $ids[$condition['formulaid']] = $condition['item_conditionid'];
        }
        $formula = CConditionHelper::replaceLetterIds($evalFormula, $ids);

        DB::updateByPk('items', $itemId, [
            'formula' => $formula
        ]);
    }

    /**
     * Check item data and set missing default values.
     *
     * @param array $items passed by reference
     * @param bool $update
     * @param array $dbItems
     */
    protected function checkInput(array &$items, $update = false, array $dbItems = [])
    {
        // add the values that cannot be changed, but are required for further processing
        foreach ($items as &$item) {
            $item['value_type'] = ITEM_VALUE_TYPE_TEXT;

            // unset fields that are updated using the 'filter' parameter
            unset($item['evaltype']);
            unset($item['formula']);
        }
        unset($item);
        parent::checkInput($items, $update);

        $validateItems = $items;
        if ($update) {
            $validateItems = $this->extendFromObjects(prs_toHash($validateItems, 'itemid'), $dbItems, ['name']);
        }

        // filter validator
        $filterValidator = new CSchemaValidator($this->getFilterSchema());

        // condition validation
        $conditionValidator = new CSchemaValidator($this->getFilterConditionSchema());
        foreach ($validateItems as $item) {
            // validate custom formula and conditions
            if (isset($item['filter'])) {
                $filterValidator->setObjectName($item['name']);
                $this->checkValidator($item['filter'], $filterValidator);

                foreach ($item['filter']['conditions'] as $condition) {
                    $conditionValidator->setObjectName($item['name']);
                    $this->checkValidator($condition, $conditionValidator);
                }
            }
        }

        $this->validateOverrides($validateItems);
    }

    /**
     * @param array $items
     * @throws Exception
     * @throws ValidateException
     * @throws \yii\db\Exception
     */
    protected function validateOverrides(array $items): void
    {
        $fieldRules = [
            'step' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => '1:' . PRS_MAX_INT32],
            'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_override', 'name')],
            'stop' => [Int32Validator::class, 'in' => implode(',', [PRS_LLD_OVERRIDE_STOP_NO, PRS_LLD_OVERRIDE_STOP_YES]), 'default' => PRS_LLD_OVERRIDE_STOP_NO],
            'filter' => [ObjectValidator::class, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
                'evaltype' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION])],
                'formula' => [Utf8StringValidator::class],
                'conditions' => [ObjectsValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
                    'macro' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_override_condition', 'macro')],
                    'operator' => [Int32Validator::class, 'in' => implode(',', [CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS]), 'default' => DB::getDefault('lld_override_condition', 'operator')],
                    'value' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('lld_override_condition', 'value')],
                    'formulaid' => [Utf8StringValidator::class]
                ]]
            ]],
            'operations' => [ObjectsValidator::class, 'fields' => [
                'operationobject' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => implode(',', [OPERATION_OBJECT_ITEM_PROTOTYPE, OPERATION_OBJECT_TRIGGER_PROTOTYPE, OPERATION_OBJECT_GRAPH_PROTOTYPE, OPERATION_OBJECT_HOST_PROTOTYPE])],
                'operator' => [Int32Validator::class, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP]), 'default' => DB::getDefault('lld_override_operation', 'operator')],
                'value' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('lld_override_operation', 'value')],
                'opstatus' => [ObjectValidator::class, 'fields' => [
                    'status' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => implode(',', [PRS_PROTOTYPE_STATUS_ENABLED, PRS_PROTOTYPE_STATUS_DISABLED])]
                ]],
                'opdiscover' => [ObjectValidator::class, 'fields' => [
                    'discover' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => implode(',', [PRS_PROTOTYPE_DISCOVER, PRS_PROTOTYPE_NO_DISCOVER])]
                ]],
                'opperiod' => [ObjectValidator::class, 'fields' => [
                    'delay' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_override_opperiod', 'delay')]
                ]],
                'ophistory' => [ObjectValidator::class, 'fields' => [
                    'history' => [TimeUnitValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,' . implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('lld_override_ophistory', 'history')]
                ]],
                'optrends' => [ObjectValidator::class, 'fields' => [
                    'trends' => [TimeUnitValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,' . implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('lld_override_optrends', 'trends')]
                ]],
                'opseverity' => [ObjectValidator::class, 'fields' => [
                    'severity' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))]
                ]],
                'optag' => [ObjectsValidator::class, 'flags' => API_NOT_EMPTY, 'uniq' => [['tag', 'value']], 'fields' => [
                    'tag' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_override_optag', 'tag')],
                    'value' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('lld_override_optag', 'value'), 'default' => DB::getDefault('lld_override_optag', 'value')]
                ]],
                'optemplate' => [ObjectsValidator::class, 'flags' => API_NOT_EMPTY, 'fields' => [
                    'templateid' => [IdValidator::class, 'flags' => API_REQUIRED]
                ]],
                'opinventory' => [ObjectValidator::class, 'fields' => [
                    'inventory_mode' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
                ]]
            ]]
        ];

        // Schema for filter is already validated in API validator. Create the formula validator for filter.
        $condition_validator = new CConditionValidator([
            'messageMissingFormula' => t('zapi', 'Formula missing for override "%1$s".'),
            'messageInvalidFormula' => t('zapi', 'Incorrect custom expression "%2$s" for override "%1$s": %3$s.'),
            'messageMissingCondition' =>
                t('zapi', 'Condition "%2$s" used in formula "%3$s" for override "%1$s" is not defined.'),
            'messageUnusedCondition' => t('zapi', 'Condition "%2$s" is not used in formula "%3$s" for override "%1$s".')
        ]);

        $update_interval_parser = new CUpdateIntervalParser([
            'usermacros' => true,
            'lldmacros' => true
        ]);

        $lld_idx = 0;
        foreach ($items as $item) {
            if (array_key_exists('overrides', $item)) {
                $path = '/' . (++$lld_idx) . '/overrides';
                if (!ValidateHelper::validateObjects($item['overrides'], $fieldRules, ['uniq' => [['name'], ['step']]], $error)) {
                    self::exception(60750003, $error);
                }

                foreach ($item['overrides'] as $ovrd_idx => $override) {
                    if (array_key_exists('filter', $override)) {
                        $condition_validator->setObjectName($override['name']);

                        // Validate the formula and check if they are in the conditions.
                        if (!$condition_validator->validate($override['filter'])) {
                            self::exception(60750003, $condition_validator->getError());
                        }

                        // Validate that conditions have correct macros and 'formulaid' for custom expressions.
                        if (array_key_exists('conditions', $override['filter'])) {
                            foreach ($override['filter']['conditions'] as $cnd_idx => $condition) {
                                // API validator only checks if 'macro' field exists and is not empty. It must be macro.
                                if (!preg_match('/^' . PRS_PREG_EXPRESSION_LLD_MACROS . '$/', $condition['macro'])) {
                                    self::exception(60750003, t('zapi', 'Incorrect filter condition macro for override "{name}".', ['name' => $override['name']]));
                                }

                                if ($override['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
                                    /*
                                     * Check only if 'formulaid' exists. It cannot be empty or incorrect, but that is
                                     * already validated by previously set conditionValidator.
                                     */
                                    if (!array_key_exists('formulaid', $condition)) {
                                        $cond_path = $path . '/' . ($ovrd_idx + 1) . '/filter/conditions/' . ($cnd_idx + 1);
                                        self::invalidAttrException($path, t('zapi', 'the parameter "{param}" is missing', ['param' => 'formulaid']));
                                    }
                                }
                            }
                        }
                    }

                    // Check integrity of 'overrideobject' and its fields.
                    if (array_key_exists('operations', $override)) {
                        foreach ($override['operations'] as $opr_idx => $operation) {
                            $opr_path = $path . '/' . ($ovrd_idx + 1) . '/operations/' . ($opr_idx + 1);

                            switch ($operation['operationobject']) {
                                case OPERATION_OBJECT_ITEM_PROTOTYPE:
                                    foreach (['opseverity', 'optemplate', 'opinventory'] as $field) {
                                        if (array_key_exists($field, $operation)) {
                                            self::invalidAttrException($opr_path, t('zapi', 'unexpected parameter "{param}"', ['param' => $field]));
                                        }
                                    }

                                    if (!array_key_exists('opstatus', $operation)
                                        && !array_key_exists('opperiod', $operation)
                                        && !array_key_exists('ophistory', $operation)
                                        && !array_key_exists('optrends', $operation)
                                        && !array_key_exists('optag', $operation)
                                        && !array_key_exists('opdiscover', $operation)) {
                                        self::invalidAttrException($opr_path, t('zapi', 'value must be one of "{list}"', ['list' => 'opstatus, opdiscover, opperiod, ophistory, optrends, optag']));
                                    }

                                    if (array_key_exists('opperiod', $operation)
                                        && !ItemHelper::validateDelay($update_interval_parser, 'delay',
                                            $operation['opperiod']['delay'], $error)) {
                                        self::exception(60750003, $error);
                                    }
                                    break;

                                case OPERATION_OBJECT_TRIGGER_PROTOTYPE:
                                    foreach (['opperiod', 'ophistory', 'optrends', 'optemplate', 'opinventory'] as
                                             $field) {
                                        if (array_key_exists($field, $operation)) {
                                            self::invalidAttrException($opr_path, t('zapi', 'unexpected parameter "{param}"', ['param' => $field]));
                                        }
                                    }

                                    if (!array_key_exists('opstatus', $operation)
                                        && !array_key_exists('opseverity', $operation)
                                        && !array_key_exists('optag', $operation)
                                        && !array_key_exists('opdiscover', $operation)) {
                                        self::invalidAttrException($opr_path, t('zapi', 'value must be one of "{list}"', ['list' => 'opstatus, opdiscover, opseverity, optag']));
                                    }
                                    break;

                                case OPERATION_OBJECT_GRAPH_PROTOTYPE:
                                    foreach (['opstatus', 'opperiod', 'ophistory', 'optrends', 'opseverity', 'optag',
                                                 'optemplate', 'opinventory'] as $field) {
                                        if (array_key_exists($field, $operation)) {
                                            self::invalidAttrException($opr_path, t('zapi', 'unexpected parameter "{param}"', ['param' => $field]));
                                        }
                                    }

                                    if (!array_key_exists('opdiscover', $operation)) {
                                        self::invalidAttrException($opr_path, t('zapi', 'unexpected parameter "{param}"', ['param' => $field]));

                                    }
                                    break;

                                case OPERATION_OBJECT_HOST_PROTOTYPE:
                                    foreach (['opperiod', 'ophistory', 'optrends', 'opseverity'] as $field) {
                                        if (array_key_exists($field, $operation)) {
                                            self::invalidAttrException($opr_path, t('zapi', 'unexpected parameter "{param}"', ['param' => $field]));
                                        }
                                    }

                                    if (!array_key_exists('opstatus', $operation)
                                        && !array_key_exists('optemplate', $operation)
                                        && !array_key_exists('optag', $operation)
                                        && !array_key_exists('opinventory', $operation)
                                        && !array_key_exists('opdiscover', $operation)) {
                                        self::invalidAttrException($opr_path, t('zapi', 'value must be one of "{list}"', ['list' => 'opstatus, opdiscover, optemplate, optag, opinventory']));
                                    }

                                    if (array_key_exists('optemplate', $operation)) {
                                        $templates_cnt = Hosts::find()
                                            ->where(['hostid' => prs_objectValues($operation['optemplate'], 'templateid')])
                                            ->andWhere(['status' => 3])
                                            ->count();

                                        if (count($operation['optemplate']) != $templates_cnt) {
                                            throw new ValidateException(60750004);
                                        }
                                    }
                                    break;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns the parameters for creating a discovery rule filter validator.
     *
     * @return array
     */
    protected function getFilterSchema()
    {
        return [
            'validators' => [
                'evaltype' => new CLimitedSetValidator([
                    'values' => [
                        CONDITION_EVAL_TYPE_OR,
                        CONDITION_EVAL_TYPE_AND,
                        CONDITION_EVAL_TYPE_AND_OR,
                        CONDITION_EVAL_TYPE_EXPRESSION
                    ],
                    'messageInvalid' => t('zapi', 'Incorrect type of calculation for discovery rule "%1$s".')
                ]),
                'formula' => new CStringValidator([
                    'empty' => true
                ]),
                'conditions' => new CCollectionValidator([
                    'empty' => true,
                    'messageInvalid' => t('zapi', 'Incorrect conditions for discovery rule "%1$s".')
                ])
            ],
            'postValidators' => [
                new CConditionValidator([
                    'messageMissingFormula' => t('zapi', 'Formula missing for discovery rule "%1$s".'),
                    'messageInvalidFormula' => t('zapi', 'Incorrect custom expression "%2$s" for discovery rule "%1$s": %3$s.'),
                    'messageMissingCondition' => t('zapi', 'Condition "%2$s" used in formula "%3$s" for discovery rule "%1$s" is not defined.'),
                    'messageUnusedCondition' => t('zapi', 'Condition "%2$s" is not used in formula "%3$s" for discovery rule "%1$s".')
                ])
            ],
            'required' => ['evaltype', 'conditions'],
            'messageRequired' => t('zapi', 'No "%2$s" given for the filter of discovery rule "%1$s".'),
            'messageUnsupported' => t('zapi', 'Unsupported parameter "%2$s" for the filter of discovery rule "%1$s".')
        ];
    }

    /**
     * Returns the parameters for creating a discovery rule filter condition validator.
     *
     * @return array
     */
    protected function getFilterConditionSchema()
    {
        return [
            'validators' => [
                'macro' => new CStringValidator([
                    'regex' => '/^' . PRS_PREG_EXPRESSION_LLD_MACROS . '$/',
                    'messageEmpty' => t('zapi', 'Empty filter condition macro for discovery rule "%1$s".'),
                    'messageRegex' => t('zapi', 'Incorrect filter condition macro for discovery rule "%1$s".')
                ]),
                'value' => new CStringValidator([
                    'empty' => true
                ]),
                'formulaid' => new CStringValidator([
                    'regex' => '/[A-Z]+/',
                    'messageEmpty' => t('zapi', 'Empty filter condition formula ID for discovery rule "%1$s".'),
                    'messageRegex' => t('zapi', 'Incorrect filter condition formula ID for discovery rule "%1$s".')
                ]),
                'operator' => new CLimitedSetValidator([
                    'values' => [CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP, CONDITION_OPERATOR_EXISTS,
                        CONDITION_OPERATOR_NOT_EXISTS
                    ],
                    'messageInvalid' => t('zapi', 'Incorrect filter condition operator for discovery rule "%1$s".')
                ])
            ],
            'required' => ['macro', 'value'],
            'messageRequired' => t('zapi', 'No "%2$s" given for a filter condition of discovery rule "%1$s".'),
            'messageUnsupported' => t('zapi', 'Unsupported parameter "%2$s" for a filter condition of discovery rule "%1$s".')
        ];
    }

    /**
     * @param array $item
     * @param $method
     * @throws ValidateException
     */
    protected function checkSpecificFields(array $item, $method)
    {
        if (array_key_exists('lifetime', $item)
            && !ValidateHelper::validateTimeUnit($item['lifetime'], SEC_PER_HOUR, 25 * SEC_PER_YEAR, true, $error,
                ['usermacros' => true])) {
            self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'lifetime', 'error' => $error]));
        }
    }

    /**
     * @param array $lld_macro_paths
     * @param $macro_names
     * @param $path
     * @throws ValidateException
     */
    protected function checkDuplicateLLDMacros(array $lld_macro_paths, $macro_names, $path)
    {
        foreach ($lld_macro_paths as $num => $lld_macro_path) {
            if (array_key_exists('lld_macro', $lld_macro_path)) {
                if (array_key_exists($lld_macro_path['lld_macro'], $macro_names)) {
                    self::invalidAttrException($path . '/lld_macro_paths/' . ($num + 1) . '/lld_macro', t('zapi', 'value "{value}" already exists', ['value' => $lld_macro_path['lld_macro']]));
                }

                $macro_names[$lld_macro_path['lld_macro']] = true;
            }
        }
    }

    /**
     * @param array $items
     * @throws ValidateException
     * @throws Exception
     * @throws \yii\db\Exception
     */
    protected function validateCreateLLDMacroPaths(array $items)
    {
        $rules = [
            'lld_macro_paths' => [ObjectsValidator::class, 'flags' => API_NOT_EMPTY, 'fields' => [
                'lld_macro' => [LLDMacroValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_macro_path', 'lld_macro')],
                'path' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_macro_path', 'path')]
            ]]
        ];

        foreach ($items as $key => $item) {
            if (array_key_exists('lld_macro_paths', $item)) {
                $item = array_intersect_key($item, $rules);
                $path = '/' . ($key + 1);

                if (!ValidateHelper::validateObject($item, $rules, ['_path' => $path], $error)) {
                    self::exception(60750003, $error);
                }
                $this->checkDuplicateLLDMacros($item['lld_macro_paths'], [], $path);
            }
        }
    }

    /**
     * @param array $items
     * @throws ValidateException
     * @throws Exception
     * @throws \yii\db\Exception
     */
    protected function validateUpdateLLDMacroPaths(array $items)
    {
        $rules = [
            'lld_macro_paths' => [ObjectsValidator::class, 'fields' => [
                'lld_macro_pathid' => [IdValidator::class],
                'lld_macro' => [LLDMacroValidator::class, 'length' => DB::getFieldLength('lld_macro_path', 'lld_macro')],
                'path' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_macro_path', 'path')]
            ]]
        ];

        $items = $this->extendObjects('items', $items, ['templateid']);

        foreach ($items as $key => $item) {
            if (array_key_exists('lld_macro_paths', $item)) {
                $itemid = $item['itemid'];
                $templateid = $item['templateid'] ?: 0;

                $item = array_intersect_key($item, $rules);
                $path = '/' . ($key + 1);

                if (!ValidateHelper::validateObject($item, $rules, ['_path' => $path], $error)) {
                    self::exception(60750003, $error);
                }

                if (array_key_exists('lld_macro_paths', $item)) {
                    if ($templateid != 0) {
                        self::invalidAttrException('/lld_macro_paths', t('zapi', 'cannot update property for templated discovery rule'));
                    }

                    $lld_macro_pathids = [];

                    // Check that fields exists, are not empty, do not duplicate and collect IDs to compare with DB.
                    foreach ($item['lld_macro_paths'] as $num => $lld_macro_path) {
                        $subpath = $num + 1;

                        // API_NOT_EMPTY will not work, so we need at least one field to be present.
                        if (!array_key_exists('lld_macro', $lld_macro_path)
                            && !array_key_exists('path', $lld_macro_path)
                            && !array_key_exists('lld_macro_pathid', $lld_macro_path)) {
                            self::invalidAttrException($path . '/lld_macro_paths/' . $subpath, t('zapi', 'cannot be empty'));
                        }

                        // API 'uniq' => true will not work, because we validate API_ID not API_IDS. So make IDs unique.
                        if (array_key_exists('lld_macro_pathid', $lld_macro_path)) {
                            $lld_macro_pathids[$lld_macro_path['lld_macro_pathid']] = true;
                        } else {
                            /*
                             * In case "lld_macro_pathid" does not exist, we need to treat it as a new LLD macro with
                             * both fields present.
                             */
                            if (array_key_exists('lld_macro', $lld_macro_path)
                                && !array_key_exists('path', $lld_macro_path)) {
                                self::invalidAttrException($path . '/lld_macro_paths/' . $subpath, t('zapi', 'the parameter "{param}" is missing', ['param' => 'path']));

                            } elseif (array_key_exists('path', $lld_macro_path)
                                && !array_key_exists('lld_macro', $lld_macro_path)) {
                                self::invalidAttrException($path . '/lld_macro_paths/' . $subpath, t('zapi', 'the parameter "{param}" is missing', ['param' => 'lld_macro']));
                            }
                        }
                    }

                    $this->checkDuplicateLLDMacros($item['lld_macro_paths'], [], $path);

                    /*
                     * Validate "lld_macro_pathid" field. If "lld_macro_pathid" doesn't correspond to given "itemid"
                     * or does not exist, throw an exception.
                     */
                    if ($lld_macro_pathids) {
                        $lld_macro_pathids = array_keys($lld_macro_pathids);

                        $db_lld_macro_paths = (new Query())->select(['lmp.lld_macro_pathid', 'lmp.lld_macro'])
                            ->from(['lmp' => 'lld_macro_path'])
                            ->where(['lmp.itemid' => $itemid])
                            ->andWhere(['lmp.lld_macro_pathid' => $lld_macro_pathids])
                            ->indexBy('lld_macro_pathid')
                            ->all();

                        if (count($db_lld_macro_paths) != count($lld_macro_pathids)) {
                            throw new ValidateException(60750004);
                        }

                        $macro_names = [];

                        foreach ($item['lld_macro_paths'] as $num => $lld_macro_path) {
                            if (array_key_exists('lld_macro_pathid', $lld_macro_path)
                                && !array_key_exists('lld_macro', $lld_macro_path)) {
                                $db_lld_macro_path = $db_lld_macro_paths[$lld_macro_path['lld_macro_pathid']];
                                $macro_names[$db_lld_macro_path['lld_macro']] = true;
                            }
                        }

                        $this->checkDuplicateLLDMacros($item['lld_macro_paths'], $macro_names, $path);
                    }
                }
            }
        }
    }
	
	
    /**
     * @param array $templateIds
     * @param array $hostIds
     *
     * @return array Array of discovery rule IDs.
     */
    public function syncTemplates(array $templateIds, array $hostIds)
    {
        $output = [];
        foreach ($this->fieldRules as $field_name => $rules) {
            if (!array_key_exists('system', $rules) && !array_key_exists('host', $rules)) {
                $output[] = $field_name;
            }
        }

        $tplItems = DiscoverRuleHelper::getDiscoverRules([
            'output' => $output,
            'selectFilter' => ['formula', 'evaltype', 'conditions'],
            'selectLLDMacroPaths' => ['lld_macro', 'path'],
            'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
            'selectOverrides' => ['name', 'step', 'stop', 'filter', 'operations'],
            'hostids' => $templateIds,
            'preservekeys' => true,
            'nopermissions' => true
        ]);

        foreach ($tplItems as &$item) {
            if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
                if (array_key_exists('query_fields', $item) && is_array($item['query_fields'])) {
                    $item['query_fields'] = $item['query_fields'] ? json_encode($item['query_fields']) : '';
                }

                if (array_key_exists('headers', $item) && is_array($item['headers'])) {
                    $item['headers'] = $this->headersArrayToString($item['headers']);
                }
            } else {
                $item['query_fields'] = '';
                $item['headers'] = '';
            }

            // Option 'Convert to JSON' is not supported for discovery rule.
            unset($item['output_format']);
        }
        unset($item);

        $this->inherit($tplItems, $hostIds);

        return array_keys($tplItems);
    }
	
    /**
     * @param array      $templateIds
     * @param array|null $hostIds
     */
    public static function unlinkTemplateObjects(array $templateIds, array $hostIds = null): void
    {
        $query = new Query();
        $query->from([
            'i' => Items::tableName(),
            'ii' => Items::tableName(),
            'h' => Hosts::tableName(),
        ]);
        $query->where('i.itemid=ii.templateid')
            ->andWhere('ii.hostid=h.hostid')
            ->andWhere(SqlHelper::whereIn('i.hostid', $templateIds))
            ->andWhere(['i.flags' => PRS_FLAG_DISCOVERY_RULE]);

        if ($hostIds) {
            $query->andWhere(SqlHelper::whereIn('ii.hostid', $hostIds));
        }

        $query->select(['ii.itemid', 'host_status' => 'h.status']);
        $updItems = [];
        $ruleIds = [];
        foreach ($query->each() as $row) {
            $updItem = [
                'templateid' => 0,
                'valuemapid' => 0
            ];

            if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
                $updItem += ['uuid' => generateUuidV4()];
            }

            $updItems[] = [
                'values' => $updItem,
                'where' => ['itemid' => $row['itemid']]
            ];

            $ruleIds[] = $row['itemid'];
        }

        if ($updItems) {
            DB::update('items', $updItems);

            /*
			 * TODO: The trigger prototypes and graphs also should be updated here when new audit log will be added for
			 * them.
			 */
            ItemAssist::unlinkTemplateObjects($ruleIds);
            HostPrototypeService::instance()->unlinkTemplateObjects($ruleIds);
        }
    }

    /**
     * @param array      $templateids
     * @param array|null $hostids
     */
    public static function clearTemplateObjects(array $templateIds, array $hostIds = null): void
    {
        $query = new Query();
        $query->from([
            'i' => Items::tableName(),
            'ii' => Items::tableName(),
        ]);
        $query->where('i.itemid=ii.templateid')
            ->andWhere(SqlHelper::whereIn('i.hostid', $templateIds))
            ->andWhere(['i.flags' => PRS_FLAG_DISCOVERY_RULE]);

        if ($hostIds) {
            $query->andWhere(SqlHelper::whereIn('ii.hostid', $hostIds));
        }

        $ruleIds = $query->select(['ii.itemid'])
            ->column();
        if ($ruleIds) {
            DiscoveryRuleManager::delete($ruleIds);
        }
    }

    /**
	 * Copies the given discovery rules to the specified hosts.
	 *
	 * @throws ValidateException if no discovery rule IDs or host IDs are given or
	 * the user doesn't have the necessary permissions.
	 *
	 * @param array $data
	 * @param array $data['discoveryids']  An array of item ids to be cloned.
	 * @param array $data['hostids']       An array of host ids were the items should be cloned to.
	 *
	 * @return Result
	 */
	public function copy(array $data): Result
    {
		// validate data
		if (!isset($data['discoveryids']) || !$data['discoveryids']) {
            return Result::instance()->setErrcode(60750601)->setErrmsg(t('zapi', 'No discovery rule IDs given.'));
		}
		if (!isset($data['hostids']) || !$data['hostids']) {
            return Result::instance()->setErrcode(60750601)->setErrmsg(t('zapi', 'No host IDs given.'));
		}

		// check if the given discovery rules exist 
        $search = new \app\customs\zapi\models\search\DiscoverRuleSearch();
        $search->is_all = true;
        $provider = $search->search([
			'countOutput' => true,
			'itemids' => $data['discoveryids']
		]);
  

        $models = $provider->getModels();
        if (isset($models[0]['rowscount'])) {
            $total = $models[0]['rowscount'];
        } else {
            $total = $provider->getTotalCount();
        }
		if ($total != count($data['discoveryids'])) {
			$error = t('zapi', 'No permissions to referred object or it does not exist!');
            return Result::instance()->setErrcode(60750601)->setErrmsg($error);
		}

		// copy
        try {
            foreach ($data['discoveryids'] as $discoveryid) {
                foreach ($data['hostids'] as $hostid) {
                    $this->copyDiscoveryRule($discoveryid, $hostid);
                }
            }
        } catch(\Exception $e) {
            return $this->errorException($e);
        }

		return Result::instance()->setSuccess();
	}

    /**
	 * Copies the given discovery rule to the specified host.
	 *
	 * @throws ValidateException if the discovery rule interfaces could not be mapped
	 * to the new host interfaces.
	 *
	 * @param string $discoveryid  The ID of the discovery rule to be copied
	 * @param string $hostid       Destination host id
	 *
	 * @return Result
	 */
	protected function copyDiscoveryRule($discoveryid, $hostid): Result
    {
		// fetch discovery to clone
		$srcDiscovery = DiscoverRuleHelper::getDiscoverRules([
			'itemids' => $discoveryid,
			'output' => ['itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
				'value_type', 'trapper_hosts', 'units', 'lastlogsize', 'logtimefmt', 'valuemapid', 'params',
				'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'mtime', 'flags',
				'interfaceid', 'description', 'inventory_link', 'lifetime', 'jmx_endpoint', 'url', 'query_fields',
				'parameters', 'timeout', 'posts', 'status_codes', 'follow_redirects', 'post_type', 'http_proxy',
				'headers', 'retrieve_mode', 'request_method', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password',
				'verify_peer', 'verify_host', 'allow_traps', 'master_itemid'
			],
			'selectFilter' => ['evaltype', 'formula', 'conditions'],
			'selectLLDMacroPaths' => ['lld_macro', 'path'],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectOverrides' => ['name', 'step', 'stop', 'filter', 'operations'],
			'preservekeys' => true
		]);
		$srcDiscovery = reset($srcDiscovery);

		// fetch source and destination hosts
		$hosts = HostHelper::getHosts([
			'output' => ['hostid', 'host', 'name', 'status'],
			'selectInterfaces' => ['interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
			'hostids' => [$srcDiscovery['hostid'], $hostid],
			'templated_hosts' => true,
			'preservekeys' => true
		]);
		$src_host = $hosts[$srcDiscovery['hostid']];
		$dst_host = $hosts[$hostid];

		$dstDiscovery = $srcDiscovery;
		$dstDiscovery['hostid'] = $hostid;
		unset($dstDiscovery['itemid']);
		if ($dstDiscovery['filter']) {
			foreach ($dstDiscovery['filter']['conditions'] as &$condition) {
				unset($condition['itemid'], $condition['item_conditionid']);
			}
			unset($condition);
		}

		if (!$dstDiscovery['lld_macro_paths']) {
			unset($dstDiscovery['lld_macro_paths']);
		}

		if ($dstDiscovery['overrides']) {
			foreach ($dstDiscovery['overrides'] as &$override) {
				if (array_key_exists('filter', $override)) {
					if (!$override['filter']['conditions']) {
						unset($override['filter']);
					}
					unset($override['filter']['eval_formula']);
				}
			}
			unset($override);
		}
		else {
			unset($dstDiscovery['overrides']);
		}

		// if this is a plain host, map discovery interfaces
		if ($src_host['status'] != HOST_STATUS_TEMPLATE) {
			// find a matching interface
			$interface = self::findInterfaceForItem($dstDiscovery['type'], $dst_host['interfaces']);
			if ($interface) {
				$dstDiscovery['interfaceid'] = $interface['interfaceid'];
			}
			// no matching interface found, throw an error
			elseif ($interface !== false) {
				self::exception(60750601, t('zapi', 'Cannot find host interface on "{host}" for item key "{key}".',[
					'host' => $dst_host['name'],
					'key' => $dstDiscovery['key_']
				]));
			}
		}

		// Master item should exists for LLD rule with type dependent item.
		if ($srcDiscovery['type'] == ITEM_TYPE_DEPENDENT) {
			$master_items = DBfetchArray(DBselect(
				'SELECT i1.itemid'.
				' FROM items i1,items i2'.
				' WHERE i1.key_=i2.key_'.
					' AND i1.hostid='.prs_dbstr($dstDiscovery['hostid']).
					' AND i2.itemid='.prs_dbstr($srcDiscovery['master_itemid'])
			));
            $query = new Query();
            $query->from([
                'i1' => 'items',
                'i2' => 'items',
            ])
            ->select(['i1.itemid'])
            ->where('i1.key_=i2.key_')
            ->andWhere(['i1.hostid' => $dstDiscovery['hostid']])
            ->andWhere(['i2.itemid' => $srcDiscovery['master_itemid']]);

            $dstDiscovery['master_itemid'] = $query->scalar();

			if (!$dstDiscovery['master_itemid']) {
                self::exception(60750601, t('zapi', 'Discovery rule "{rule}" cannot be copied without its master item.',[
					'rule' => $srcDiscovery['name']
				]));
			}
		}

		// save new discovery
		$newDiscovery = $this->create([$dstDiscovery]);
        $data = $newDiscovery->getData();
		$dstDiscovery['itemid'] = $data['itemids'][0];

		// copy prototypes
		$this->copyItemPrototypes($srcDiscovery['itemid'], $src_host, $dstDiscovery['itemid'], $dst_host);

		// fetch new prototypes
		$dstDiscovery['items'] = ItemHelper::getItemPrototypes([
			'output' => ['itemid', 'key_'],
			'discoveryids' => $dstDiscovery['itemid'],
			'preservekeys' => true
		]);

		if ($dstDiscovery['items']) {
			// copy graphs
			$this->copyGraphPrototypes($srcDiscovery, $dstDiscovery);

			// copy triggers
			$this->copyTriggerPrototypes($srcDiscovery, $src_host, $dst_host);
		}

		// copy host prototypes
		$this->copyHostPrototypes($discoveryid, $dstDiscovery['itemid']);

		return Result::instance()->setSuccess();
	}

	/**
	 * Create copies of items prototypes from the given source LLD rule to the given destination host or template.
	 *
	 * @param string $src_ruleid
	 * @param array  $src_host
	 * @param array  $src_host['interfaces']
	 * @param string $dst_ruleid
	 * @param array  $dst_host
	 * @param string $dst_host['hostid']
	 * @param string $dst_host['host']
	 * @param array  $dst_host['interfaces']
	 * @param int    $dst_host['status']
	 *
	 * @throws ValidateException
	 */
	private static function copyItemPrototypes(string $src_ruleid, array $src_host, string $dst_ruleid,
			array $dst_host): void {
		$src_items = ItemHelper::getItemPrototypes([
			'output' => ['itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history', 'trends',
				'valuemapid', 'logtimefmt', 'description', 'status', 'discover',

				// Type fields.
				// The fields used for multiple item types.
				'interfaceid', 'authtype', 'username', 'password', 'params', 'timeout', 'delay', 'trapper_hosts',

				// Dependent item type specific fields.
				'master_itemid',

				// HTTP Agent item type specific fields.
				'url', 'query_fields', 'request_method', 'post_type', 'posts',
				'headers', 'status_codes', 'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy',
				'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'allow_traps',

				// IPMI item type specific fields.
				'ipmi_sensor',

				// JMX item type specific fields.
				'jmx_endpoint',

				// Script item type specific fields.
				'parameters',

				// SNMP item type specific fields.
				'snmp_oid',

				// SSH item type specific fields.
				'publickey', 'privatekey'
			],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectTags' => ['tag', 'value'],
			'discoveryids' => $src_ruleid,
			'preservekeys' => true
		]);

		if (!$src_items) {
			return;
		}

		$src_itemids = array_fill_keys(array_keys($src_items), true);
		$src_valuemapids = [];
		$src_interfaceids = [];
		$src_dep_items = [];
		$dep_itemids = [];

		foreach ($src_items as $itemid => $item) {
			if ($item['valuemapid'] != 0) {
				$src_valuemapids[$item['valuemapid']] = true;
			}

			if ($item['interfaceid'] != 0) {
				$src_interfaceids[$item['interfaceid']] = true;
			}

			if ($item['type'] == ITEM_TYPE_DEPENDENT) {
				if (array_key_exists($item['master_itemid'], $src_itemids)) {
					$src_dep_items[$item['master_itemid']][] = $item;

					unset($src_items[$itemid]);
				}
				else {
					$dep_itemids[$item['master_itemid']][] = $item['itemid'];
				}
			}
		}

		$valuemap_links = [];

		if ($src_valuemapids) {
			$src_valuemaps = ValueMapHelper::getValueMappings([
				'output' => ['valuemapid', 'name'],
				'valuemapids' => array_keys($src_valuemapids)
			]);

			$dst_valuemaps = ValueMapHelper::getValueMappings([
				'output' => ['valuemapid', 'hostid', 'name'],
				'hostids' => $dst_host['hostid'],
				'filter' => ['name' => array_unique(array_column($src_valuemaps, 'name'))]
			]);

			$dst_valuemapids = [];

			foreach ($dst_valuemaps as $dst_valuemap) {
				$dst_valuemapids[$dst_valuemap['name']][$dst_valuemap['hostid']] = $dst_valuemap['valuemapid'];
			}

			foreach ($src_valuemaps as $src_valuemap) {
				if (array_key_exists($src_valuemap['name'], $dst_valuemapids)) {
					foreach ($dst_valuemapids[$src_valuemap['name']] as $dst_hostid => $dst_valuemapid) {
						$valuemap_links[$src_valuemap['valuemapid']][$dst_hostid] = $dst_valuemapid;
					}
				}
			}
		}

		$interface_links = [];
		$dst_interfaceids = [];

		if ($src_interfaceids) {
			$src_interfaces = [];

			foreach ($src_host['interfaces'] as $src_interface) {
				if (array_key_exists($src_interface['interfaceid'], $src_interfaceids)) {
					$src_interfaces[$src_interface['interfaceid']] =
						array_diff_key($src_interface, array_flip(['interfaceid']));
				}
			}

			foreach ($dst_host['interfaces'] as $dst_interface) {
				$dst_interfaceid = $dst_interface['interfaceid'];
				unset($dst_interface['interfaceid']);

				foreach ($src_interfaces as $src_interfaceid => $src_interface) {
					if ($src_interface == $dst_interface) {
						$interface_links[$src_interfaceid][$dst_host['hostid']] = $dst_interfaceid;
					}
				}

				if ($dst_interface['main'] == INTERFACE_PRIMARY) {
					$dst_interfaceids[$dst_host['hostid']][$dst_interface['type']] = $dst_interfaceid;
				}
			}
		}

		$master_item_links = [];

		if ($dep_itemids) {
			$master_items = ItemHelper::getItems([
				'output' => ['itemid', 'key_'],
				'itemids' => array_keys($dep_itemids),
				'webitems' => true
			]);

			$options = $dst_host['status'] == HOST_STATUS_TEMPLATE
				? ['templateids' => $dst_host['hostid']]
				: ['hostids' => $dst_host['hostid']];

			$dst_master_items = ItemHelper::getItems([
				'output' => ['itemid', 'hostid', 'key_'],
				'filter' => ['key_' => array_unique(array_column($master_items, 'key_'))],
				'webitems' => true
			] + $options);

			$dst_master_itemids = [];

			foreach ($dst_master_items as $item) {
				$dst_master_itemids[$item['hostid']][$item['key_']] = $item['itemid'];
			}

			foreach ($master_items as $item) {
				if (array_key_exists($dst_host['hostid'], $dst_master_itemids)
						&& array_key_exists($item['key_'], $dst_master_itemids[$dst_host['hostid']])) {
					$master_item_links[$item['itemid']][$dst_host['hostid']] =
						$dst_master_itemids[$dst_host['hostid']][$item['key_']];
				}
				else {
					$src_itemid = reset($dep_itemids[$item['itemid']]);

					self::exception(60750601, t('zapi',
						'Cannot copy item prototype with key "{src_key}" without its master item with key "{dst_key}"',
                        [
						'src_key' => $src_items[$src_itemid]['key_'],
                        'dst_key' => $item['key_']
					]));
				}
			}
		}

		do {
			$dst_items = [];

			foreach ($src_items as $src_item) {
				$dst_item = array_diff_key($src_item, array_flip(['itemid']));

				if ($src_item['valuemapid'] != 0) {
					if (array_key_exists($src_item['valuemapid'], $valuemap_links)
							&& array_key_exists($dst_host['hostid'], $valuemap_links[$src_item['valuemapid']])) {
						$dst_item['valuemapid'] = $valuemap_links[$src_item['valuemapid']][$dst_host['hostid']];
					}
					else {
						$dst_item['valuemapid'] = 0;
					}
				}

				$dst_item['interfaceid'] = 0;

				if ($src_item['interfaceid'] != 0) {
					if (array_key_exists($src_item['interfaceid'], $interface_links)
							&& array_key_exists($dst_host['hostid'], $interface_links[$src_item['interfaceid']])) {
						$dst_item['interfaceid'] = $interface_links[$src_item['interfaceid']][$dst_host['hostid']];
					}
					else {
						$type = itemTypeInterface($src_item['type']);

						if (in_array($type,
							[INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI]
						)) {
							if (array_key_exists($dst_host['hostid'], $dst_interfaceids)
									&& array_key_exists($type, $dst_interfaceids[$dst_host['hostid']])) {
								$dst_item['interfaceid'] = $dst_interfaceids[$dst_host['hostid']][$type];
							}
							else {
                                self::exception(60750601, t('zapi', 'Cannot find host interface on "{host}" for item key "{key}".',[
                                    'host' => $dst_host['host'],
                                    'key' => $src_item['key_']
                                ]));
							}
						}
					}
				}

				if ($src_item['type'] == ITEM_TYPE_DEPENDENT) {
					$dst_item['master_itemid'] = $master_item_links[$src_item['master_itemid']][$dst_host['hostid']];
				}

				$dst_items[] = ['hostid' => $dst_host['hostid'], 'ruleid' => $dst_ruleid] + ItemHelper::getSanitizedItemFields([
					'templateid' => 0,
					'flags' => PRS_FLAG_DISCOVERY_PROTOTYPE,
					'hosts' => [$dst_host]
				] + $dst_item);
			}

			$response = ItemPrototypeAssist::instance()->create($dst_items);

			$_src_items = [];

			if ($src_dep_items) {
				foreach ($src_items as $src_item) {
					$dst_itemid = array_shift($response['itemids']);

					if (array_key_exists($src_item['itemid'], $src_dep_items)) {
						$master_item_links[$src_item['itemid']][$dst_host['hostid']] = $dst_itemid;

						$_src_items = array_merge($_src_items, $src_dep_items[$src_item['itemid']]);
						unset($src_dep_items[$src_item['itemid']]);
					}
				}
			}

			$src_items = $_src_items;
		} while ($src_items);
	}

	/**
	 * Copies all of the graphs from the source discovery to the target discovery rule.
	 *
	 * @throws ValidateException if graph saving fails
	 *
	 * @param array $srcDiscovery    The source discovery rule to copy from
	 * @param array $dstDiscovery    The target discovery rule to copy to
	 *
	 * @return Result
	 */
	protected function copyGraphPrototypes(array $srcDiscovery, array $dstDiscovery):Result
    {
		return Result::instance()->setSuccess();
	}

	/**
	 * Copy all of the host prototypes from the source discovery rule to the target discovery rule.
	 *
	 * @param string $src_discoveryid
	 * @param string $dst_discoveryid
	 *
	 * @throws ValidateException
	 */
	protected function copyHostPrototypes(string $src_discoveryid, string $dst_discoveryid): void {
		$src_host_prototypes = HostHelper::getHostPrototypes([
			'output' => ['host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode'],
			'selectInterfaces' => ['type', 'useip', 'ip', 'dns', 'port', 'main', 'details'],
			'selectGroupLinks' => ['groupid'],
			'selectGroupPrototypes' => ['name'],
			'selectTemplates' => ['templateid'],
			'selectTags' => ['tag', 'value'],
			'selectMacros' => ['macro', 'type', 'value', 'description'],
			'discoveryids' => $src_discoveryid
		]);

		if (!$src_host_prototypes) {
			return;
		}

		$dst_host_prototypes = [];

		foreach ($src_host_prototypes as $i => $src_host_prototype) {
			unset($src_host_prototypes[$i]);

			$dst_host_prototype = ['ruleid' => $dst_discoveryid] + array_intersect_key($src_host_prototype, array_flip([
				'host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode', 'groupLinks',
				'groupPrototypes', 'templates', 'tags'
			]));

			if ($src_host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
				foreach ($src_host_prototype['interfaces'] as $src_interface) {
					$dst_interface =
						array_intersect_key($src_interface, array_flip(['type', 'useip', 'ip', 'dns', 'port', 'main']));

					if ($src_interface['type'] == INTERFACE_TYPE_SNMP) {
						switch ($src_interface['details']['version']) {
							case SNMP_V1:
							case SNMP_V2C:
								$dst_interface['details'] = array_intersect_key($src_interface['details'],
									array_flip(['version', 'bulk', 'community'])
								);
								break;

							case SNMP_V3:
								$field_names = array_flip(['version', 'bulk', 'contextname', 'securityname',
									'securitylevel'
								]);

								if ($src_interface['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
									$field_names += array_flip(['authprotocol', 'authpassphrase']);
								}
								elseif ($src_interface['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
									$field_names +=
										array_flip(['authprotocol', 'authpassphrase', 'privprotocol', 'privpassphrase']);
								}

								$dst_interface['details'] = array_intersect_key($src_interface['details'], $field_names);
								break;
						}
					}

					$dst_host_prototype['interfaces'][] = $dst_interface;
				}
			}

			foreach ($src_host_prototype['macros'] as $src_macro) {
				if ($src_macro['type'] == PRS_MACRO_TYPE_SECRET) {
					$dst_host_prototype['macros'][] = ['type' => PRS_MACRO_TYPE_TEXT, 'value' => ''] + $src_macro;
				}
				else {
					$dst_host_prototype['macros'][] = $src_macro;
				}
			}

			$dst_host_prototypes[] = $dst_host_prototype;
		}

		HostPrototypeService::instance()->create($dst_host_prototypes);
	}
}
