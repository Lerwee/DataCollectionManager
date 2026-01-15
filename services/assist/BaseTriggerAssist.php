<?php

namespace app\customs\zapi\services\assist;

use app\common\helpers\ArrayHelper;
use app\customs\zapi\common\data\CHistFunctionData;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\TriggerHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\macros\CMacrosResolverHelper;
use app\customs\zapi\common\parsers\CExpressionParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\results\CExpressionParserResult;
use app\customs\zapi\common\validators\EventNameValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\TriggerExpressionValidator;
use app\customs\zapi\common\validators\UrlValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\UuidValidator;
use app\customs\zapi\common\validators\z\object\CUpdateDiscoveredValidator;
use app\customs\zapi\components\RelationMap;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Items;
use app\modules\libzbx\models\Triggers;
use app\modules\libzbx\models\zbx\Hstgrp;
use yii\base\Exception;
use yii\db\Expression;
use yii\db\Query;

/**
 * Class BaseTriggerAssist
 * @package app\customs\zapi\services\assist
 */
abstract class BaseTriggerAssist extends BaseAssist
{
    protected const FLAGS = null;

    /**
     * Prepares and returns an array of child triggers, inherited from triggers $tpl_triggers on the given hosts.
     *
     * @param array $tpl_triggers
     * @param string $tpl_triggers [<tnum>]['triggerid']
     */
    private function prepareInheritedTriggers(array $tpl_triggers, array $hostids = null, array &$ins_triggers = null, array &$upd_triggers = null, array &$db_triggers = null)
    {
        $ins_triggers = [];
        $upd_triggers = [];
        $db_triggers = [];

        $rows = (new Query())->select(['t.triggerid', 'h.hostid'])
            ->from(['t' => 'triggers', 'f' => 'functions', 'i' => 'items', 'h' => 'hosts'])
            ->where('t.triggerid=f.triggerid')
            ->andWhere('f.itemid=i.itemid')
            ->andWhere('i.hostid=h.hostid')
            ->andWhere(['t.triggerid' => ArrayHelper::getColumn($tpl_triggers, 'triggerid')])
            ->andWhere(['h.status' => HOST_STATUS_TEMPLATE])
            ->distinct()
            ->all();

        $tpl_hostids_by_triggerid = [];
        $tpl_hostids = [];

        foreach ($rows as $row) {
            $tpl_hostids_by_triggerid[$row['triggerid']][] = $row['hostid'];
            $tpl_hostids[$row['hostid']] = true;
        }

        // Unset host-level triggers.
        foreach ($tpl_triggers as $tnum => $tpl_trigger) {
            if (!array_key_exists($tpl_trigger['triggerid'], $tpl_hostids_by_triggerid)) {
                unset($tpl_triggers[$tnum]);
            }
        }

        if (!$tpl_triggers) {
            // Nothing to inherit, just exit.
            return;
        }

        $hosts_by_tpl_hostid = self::getLinkedHosts(array_keys($tpl_hostids), $hostids);
        $chd_triggers_tpl = $this->getHostTriggersByTemplateId(array_keys($tpl_hostids_by_triggerid), $hostids);
        $tpl_triggers_by_description = [];

        // Preparing list of missing triggers on linked hosts.
        foreach ($tpl_triggers as $tpl_trigger) {
            $hostids = [];

            foreach ($tpl_hostids_by_triggerid[$tpl_trigger['triggerid']] as $tpl_hostid) {
                if (array_key_exists($tpl_hostid, $hosts_by_tpl_hostid)) {
                    foreach ($hosts_by_tpl_hostid[$tpl_hostid] as $host) {
                        if (array_key_exists($host['hostid'], $chd_triggers_tpl)
                            && array_key_exists($tpl_trigger['triggerid'], $chd_triggers_tpl[$host['hostid']])) {
                            continue;
                        }

                        $hostids[$host['hostid']] = true;
                    }
                }
            }

            if ($hostids) {
                $tpl_triggers_by_description[$tpl_trigger['description']][] = [
                    'triggerid' => $tpl_trigger['triggerid'],
                    'expression' => $tpl_trigger['expression'],
                    'recovery_mode' => $tpl_trigger['recovery_mode'],
                    'recovery_expression' => $tpl_trigger['recovery_expression'],
                    'hostids' => $hostids
                ];
            }
        }

        $chd_triggers_all = array_replace_recursive($chd_triggers_tpl,
            $this->getHostTriggersByDescription($tpl_triggers_by_description)
        );

        $expression_parser = new CExpressionParser([
            'usermacros' => true,
            'lldmacros' => $this instanceof TriggerPrototypeAssist
        ]);

        $recovery_expression_parser = new CExpressionParser([
            'usermacros' => true,
            'lldmacros' => $this instanceof TriggerPrototypeAssist
        ]);

        // List of triggers to check for duplicates. Grouped by description.
        $descriptions = [];
        $triggerids = [];

        $output = ['triggerid', 'url_name', 'url', 'status', 'priority', 'comments', 'type', 'correlation_mode', 'correlation_tag',
            'manual_close', 'opdata', 'event_name'
        ];
        if ($this instanceof TriggerPrototypeAssist) {
            $output[] = 'discover';
        }

        $db_tpl_triggers = Triggers::find()->select($output)
            ->where(['triggerid' => array_keys($tpl_hostids_by_triggerid)])
            ->indexBy('triggerid')
            ->asArray()->all();

        foreach ($tpl_triggers as $tpl_trigger) {
            $db_tpl_trigger = $db_tpl_triggers[$tpl_trigger['triggerid']];

            $tpl_hostid = $tpl_hostids_by_triggerid[$tpl_trigger['triggerid']][0];

            // expression: func(/template/item) => func(/host/item)
            if ($expression_parser->parse($tpl_trigger['expression']) != CParser::PARSE_SUCCESS) {
                self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Incorrect value for field "{field}": {error}.',
                    ['field' => 'expression', 'error' => $expression_parser->getError()]
                ));
            }

            // recovery_expression: func(/template/item) => func(/host/item)
            if ($tpl_trigger['recovery_mode'] == PRS_RECOVERY_MODE_RECOVERY_EXPRESSION) {
                if ($recovery_expression_parser->parse($tpl_trigger['recovery_expression']) != CParser::PARSE_SUCCESS) {
                    self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Incorrect value for field "{field}": {error}.',
                        ['field' => 'recovery_expression', 'error' => $recovery_expression_parser->getError()]
                    ));
                }
            }

            $new_trigger = $tpl_trigger;
            $new_trigger['uuid'] = '';
            unset($new_trigger['triggerid'], $new_trigger['templateid']);

            if (array_key_exists($tpl_hostid, $hosts_by_tpl_hostid)) {
                foreach ($hosts_by_tpl_hostid[$tpl_hostid] as $host) {
                    $new_trigger['expression'] = $tpl_trigger['expression'];
                    $hist_functions = $expression_parser->getResult()->getTokensOfTypes(
                        [CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
                    );
                    $hist_function = end($hist_functions);
                    do {
                        $query_parameter = $hist_function['data']['parameters'][0];
                        $new_trigger['expression'] = substr_replace($new_trigger['expression'],
                            '/' . $host['host'] . '/' . $query_parameter['data']['item'], $query_parameter['pos'],
                            $query_parameter['length']
                        );
                    } while ($hist_function = prev($hist_functions));

                    if ($tpl_trigger['recovery_mode'] == PRS_RECOVERY_MODE_RECOVERY_EXPRESSION) {
                        $new_trigger['recovery_expression'] = $tpl_trigger['recovery_expression'];
                        $hist_functions = $recovery_expression_parser->getResult()->getTokensOfTypes(
                            [CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
                        );
                        $hist_function = end($hist_functions);
                        do {
                            $query_parameter = $hist_function['data']['parameters'][0];
                            $new_trigger['recovery_expression'] = substr_replace($new_trigger['recovery_expression'],
                                '/' . $host['host'] . '/' . $query_parameter['data']['item'], $query_parameter['pos'],
                                $query_parameter['length']
                            );
                        } while ($hist_function = prev($hist_functions));
                    }

                    if (array_key_exists($host['hostid'], $chd_triggers_all)
                        && array_key_exists($tpl_trigger['triggerid'], $chd_triggers_all[$host['hostid']])) {
                        $chd_trigger = $chd_triggers_all[$host['hostid']][$tpl_trigger['triggerid']];

                        $upd_triggers[] = $new_trigger + [
                                'triggerid' => $chd_trigger['triggerid'],
                                'templateid' => $tpl_trigger['triggerid']
                            ];
                        $db_triggers[$chd_trigger['triggerid']] = $chd_trigger;
                        $triggerids[] = $chd_trigger['triggerid'];

                        $check_duplicates = ($chd_trigger['description'] !== $new_trigger['description']
                            || $chd_trigger['expression'] !== $new_trigger['expression']
                            || $chd_trigger['recovery_expression'] !== $new_trigger['recovery_expression']);

                        if ($check_duplicates) {
                            $descriptions[$new_trigger['description']][] = [
                                'expression' => $new_trigger['expression'],
                                'recovery_expression' => $new_trigger['recovery_expression'],
                                'host' => [
                                    'hostid' => $host['hostid'],
                                    'status' => $host['status']
                                ]
                            ];
                        }
                    } else {
                        $ins_triggers[] = $new_trigger + $db_tpl_trigger + ['templateid' => $tpl_trigger['triggerid']];
                    }

                }
            }
        }

        if ($triggerids) {
            // Add trigger tags.
            $rows = (new Query())->select(['tt.triggertagid', 'tt.triggerid', 'tt.tag', 'tt.value'])
                ->from(['tt' => 'trigger_tag'])
                ->where(['tt.triggerid' => $triggerids])
                ->all();

            $trigger_tags = [];

            foreach ($rows as $row) {
                $trigger_tags[$row['triggerid']][] = [
                    'triggertagid' => $row['triggertagid'],
                    'tag' => $row['tag'],
                    'value' => $row['value']
                ];
            }

            foreach ($db_triggers as &$db_trigger) {
                $db_trigger['tags'] = array_key_exists($db_trigger['triggerid'], $trigger_tags)
                    ? $trigger_tags[$db_trigger['triggerid']]
                    : [];
            }
            unset($db_trigger);

            // Add discovery rule IDs.
            if ($this instanceof TriggerPrototypeAssist) {
                $rows = (new Query())->select(['id.parent_itemid', 'f.triggerid'])
                    ->from(['id' => 'item_discovery', 'f' => 'functions'])
                    ->where(['f.triggerid' => $triggerids])
                    ->andWhere('f.itemid=id.itemid')
                    ->all();

                $drule_by_triggerid = [];

                foreach ($rows as $row) {
                    $drule_by_triggerid[$row['triggerid']] = $row['parent_itemid'];
                }

                foreach ($db_triggers as &$db_trigger) {
                    $db_trigger['discoveryRule']['itemid'] = $drule_by_triggerid[$db_trigger['triggerid']];
                }
                unset($db_trigger);
            }
        }

        $this->checkDuplicates($descriptions);
    }


    /**
     * Returns list of linked hosts.
     *
     * Output format:
     *   [
     *     <tpl_hostid> => [
     *       [
     *         'hostid' => <hostid>,
     *         'host' => <host>
     *       ],
     *       ...
     *     ],
     *     ...
     *   ]
     *
     * @param array $tpl_hostids
     * @param array $hostids The function will return a list of all linked hosts if no hostids are specified.
     *
     * @return array
     */
    private static function getLinkedHosts(array $tpl_hostids, array $hostids = null)
    {
        // Fetch all child hosts and templates
        $rows = (new Query())->select(['ht.hostid', 'ht.templateid', 'h.host', 'h.status'])
            ->from(['ht' => 'hosts_templates', 'h' => 'hosts'])
            ->where('ht.hostid=h.hostid')
            ->andWhere(['ht.templateid' => $tpl_hostids])
            ->andWhere(['h.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]])
            ->andFilterWhere(['ht.hostid' => $hostids])
            ->all();

        $hosts_by_tpl_hostid = [];

        foreach ($rows as $row) {
            $hosts_by_tpl_hostid[$row['templateid']][] = [
                'hostid' => $row['hostid'],
                'host' => $row['host'],
                'status' => $row['status']
            ];
        }

        return $hosts_by_tpl_hostid;
    }

    /**
     * Returns list of already linked triggers.
     *
     * Output format:
     *   [
     *     <hostid> => [
     *       <tpl_triggerid> => ['triggerid' => <triggerid>],
     *       ...
     *     ],
     *     ...
     *   ]
     *
     * @param array $tpl_triggerids
     * @param array $hostids The function will return a list of all linked triggers if no hosts are specified.
     *
     * @return array
     */
    private function getHostTriggersByTemplateId(array $tpl_triggerids, array $hostids = null)
    {
        $output = 't.triggerid,t.expression,t.description,t.url_name,t.url,t.status,t.priority,t.comments,t.type,' .
            't.recovery_mode,t.recovery_expression,t.correlation_mode,t.correlation_tag,t.manual_close,t.opdata,' .
            't.templateid,t.event_name,i.hostid';
        if ($this instanceof TriggerPrototypeAssist) {
            $output .= ',t.discover';
        }

        $chd_triggers = (new Query())->select($output)
            ->from(['t' => 'triggers', 'f' => 'functions', 'i' => 'items'])
            ->where('t.triggerid=f.triggerid')
            ->andWhere('f.itemid=i.itemid')
            ->andWhere(['t.templateid' => $tpl_triggerids])
            ->andFilterWhere(['i.hostid' => $hostids])
            ->distinct()
            ->all();

        $chd_triggers = CMacrosResolverHelper::resolveTriggerExpressions($chd_triggers,
            ['sources' => ['expression', 'recovery_expression']]
        );

        $chd_triggers_tpl = [];

        foreach ($chd_triggers as $chd_trigger) {
            $hostid = $chd_trigger['hostid'];
            unset($chd_trigger['hostid']);

            $chd_triggers_tpl[$hostid][$chd_trigger['templateid']] = $chd_trigger;
        }

        return $chd_triggers_tpl;
    }

    /**
     * Returns list of not inherited triggers with same name and expression.
     *
     * Output format:
     *   [
     *     <hostid> => [
     *       <tpl_triggerid> => ['triggerid' => <triggerid>],
     *       ...
     *     ],
     *     ...
     *   ]
     *
     * @param array $tpl_triggers_by_description The list of hostids, grouped by trigger description and expression.
     *
     * @return array
     */
    private function getHostTriggersByDescription(array $tpl_triggers_by_description)
    {
        $chd_triggers_description = [];

        $expression_parser = new CExpressionParser([
            'usermacros' => true,
            'lldmacros' => $this instanceof TriggerPrototypeAssist
        ]);

        $recovery_expression_parser = new CExpressionParser([
            'usermacros' => true,
            'lldmacros' => $this instanceof TriggerPrototypeAssist
        ]);

        $output = 't.triggerid,t.expression,t.description,t.url_name,t.url,t.status,t.priority,t.comments,t.type,' .
            't.recovery_mode,t.recovery_expression,t.correlation_mode,t.correlation_tag,t.manual_close,t.opdata,' .
            't.event_name,i.hostid,h.host';
        if ($this instanceof TriggerPrototypeAssist) {
            $output .= ',t.discover';
        }

        foreach ($tpl_triggers_by_description as $description => $tpl_triggers) {
            $hostids = [];

            foreach ($tpl_triggers as $tpl_trigger) {
                $hostids += $tpl_trigger['hostids'];
            }

            $chd_triggers = (new Query())->select($output)
                ->from(['t' => 'triggers', 'f' => 'functions', 'i' => 'items', 'h' => 'hosts'])
                ->where('t.triggerid=f.triggerid')
                ->andWhere('f.itemid=i.itemid')
                ->andWhere('i.hostid=h.hostid')
                ->andWhere(['t.description' => [$description]])
                ->andFilterWhere(['i.hostid' => array_keys($hostids)])
                ->distinct()
                ->all();

            $chd_triggers = CMacrosResolverHelper::resolveTriggerExpressions($chd_triggers,
                ['sources' => ['expression', 'recovery_expression']]
            );

            foreach ($tpl_triggers as $tpl_trigger) {
                // expression: func(/template/item) => func(/host/item)
                if ($expression_parser->parse($tpl_trigger['expression']) != CParser::PARSE_SUCCESS) {
                    self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Incorrect value for field "{field}": {error}.',
                        ['field' => 'expression', 'error' => $expression_parser->getError()]
                    ));
                }

                // recovery_expression: func(/template/item) => func(/host/item)
                if ($tpl_trigger['recovery_mode'] == PRS_RECOVERY_MODE_RECOVERY_EXPRESSION) {
                    if ($recovery_expression_parser->parse($tpl_trigger['recovery_expression']) !=
                        CParser::PARSE_SUCCESS) {
                        self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Incorrect value for field "{field}": {error}.',
                            ['field' => 'recovery_expression', 'error' => $recovery_expression_parser->getError()]
                        ));
                    }
                }

                foreach ($chd_triggers as $chd_trigger) {
                    if (!array_key_exists($chd_trigger['hostid'], $tpl_trigger['hostids'])) {
                        continue;
                    }

                    if ($chd_trigger['recovery_mode'] != $tpl_trigger['recovery_mode']) {
                        continue;
                    }

                    // Replace template name in /host/key reference to target host name.
                    $expression = $tpl_trigger['expression'];
                    $hist_functions = $expression_parser->getResult()->getTokensOfTypes(
                        [CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
                    );
                    $hist_function = end($hist_functions);
                    do {
                        $query_parameter = $hist_function['data']['parameters'][0];
                        $expression = substr_replace($expression,
                            '/' . $chd_trigger['host'] . '/' . $query_parameter['data']['item'], $query_parameter['pos'],
                            $query_parameter['length']
                        );
                    } while ($hist_function = prev($hist_functions));

                    if ($chd_trigger['expression'] !== $expression) {
                        continue;
                    }

                    if ($tpl_trigger['recovery_mode'] == PRS_RECOVERY_MODE_RECOVERY_EXPRESSION) {
                        $recovery_expression = $tpl_trigger['recovery_expression'];
                        $hist_functions = $recovery_expression_parser->getResult()->getTokensOfTypes(
                            [CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
                        );
                        $hist_function = end($hist_functions);
                        do {
                            $query_parameter = $hist_function['data']['parameters'][0];
                            $recovery_expression = substr_replace($recovery_expression,
                                '/' . $chd_trigger['host'] . '/' . $query_parameter['data']['item'], $query_parameter['pos'],
                                $query_parameter['length']
                            );
                        } while ($hist_function = prev($hist_functions));

                        if ($chd_trigger['recovery_expression'] !== $recovery_expression) {
                            continue;
                        }
                    }

                    $hostid = $chd_trigger['hostid'];
                    unset($chd_trigger['hostid'], $chd_trigger['host']);
                    $chd_triggers_description[$hostid][$tpl_trigger['triggerid']] = $chd_trigger + ['templateid' => 0];
                }
            }
        }

        return $chd_triggers_description;
    }

    /**
     * Updates children of triggers on the given hosts and propagates the inheritance to all child hosts.
     * All of the child triggers that became obsolete will be deleted if the given triggers were assigned to a different
     * template or host.
     *
     * @param array $triggers
     * @param string $triggers []['triggerid']
     * @param string $triggers []['description']
     * @param string $triggers []['expression']
     * @param int $triggers []['recovery mode']
     * @param string $triggers []['recovery_expression']
     * @param array $hostids
     */
    protected function inherit(array $triggers, array $hostids = null)
    {
        $this->prepareInheritedTriggers($triggers, $hostids, $ins_triggers, $upd_triggers, $db_triggers);

        if ($ins_triggers) {
            $this->createReal($ins_triggers, true);
        }

        if ($upd_triggers) {
            $this->updateReal($upd_triggers, $db_triggers, true);
        }

        if ($ins_triggers || $upd_triggers) {
            $this->inherit(array_merge($ins_triggers + $upd_triggers));
        }
    }

    /**
     * Populate an array by "hostid" keys.
     *
     * @param array $descriptions
     * @param string $descriptions [<description>][]['expression']
     *
     * @return array
     * @throws ValidateException
     *
     */
    protected function populateHostIds($descriptions)
    {
        $expression_parser = new CExpressionParser([
            'usermacros' => true,
            'lldmacros' => $this instanceof TriggerPrototypeAssist
        ]);

        $hosts = [];

        foreach ($descriptions as $description => $triggers) {
            foreach ($triggers as $index => $trigger) {
                $expression_parser->parse($trigger['expression']);
                $hosts[$expression_parser->getResult()->getHosts()[0]][$description][] = $index;
            }
        }

        $db_hosts = (new Query())->select(['h.hostid', 'h.host', 'h.status'])
            ->from(['h' => 'hosts'])
            ->where(['h.host' => array_keys($hosts)])
            ->andWhere(['h.status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE]])
            ->all();

        foreach ($db_hosts as $db_host) {
            foreach ($hosts[$db_host['host']] as $description => $indexes) {
                foreach ($indexes as $index) {
                    $descriptions[$description][$index]['host'] = [
                        'hostid' => $db_host['hostid'],
                        'status' => $db_host['status']
                    ];
                }
            }
            unset($hosts[$db_host['host']]);
        }

        if ($hosts) {
            $error_wrong_host = ($this instanceof TriggerAssist)
                ? t('zapi', 'Incorrect trigger expression. Host "{name}" does not exist or you have no access to this host.', ['name' => key($hosts)])
                : t('zapi', 'Incorrect trigger prototype expression. Host "{name}" does not exist or you have no access to this host.', ['name' => key($hosts)]);
            self::exception(60750003, $error_wrong_host);
        }

        return $descriptions;
    }

    /**
     * @param array $triggers
     * @param array $descriptions
     * @throws Exception
     * @throws ValidateException
     * @throws \yii\db\Exception
     */
    private static function validateUuid(array $triggers, array $descriptions): void
    {
        foreach ($descriptions as $_triggers) {
            foreach ($_triggers as $_trigger) {
                $triggers[$_trigger['index']]['host_status'] = $_trigger['host']['status'];
            }
        }

        $fieldRules = [
            'host_status' => ['safe'],
            'uuid' => [
                MultipleValidator::class,
                'rules' => [UuidValidator::class, 'when' => function ($model) {
                    return $model->host_status == HOST_STATUS_TEMPLATE;
                }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('triggers', 'uuid'), 'unset' => true]
            ]
        ];

        if (!ValidateHelper::validateObjects($triggers, $fieldRules, ['flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid']]], $error)) {
            self::exception(60750003, $error);
        }
    }

    /**
     * Add the UUID to those of the given triggers that belong to a template and don't have the 'uuid' parameter set.
     *
     * @param array $triggers
     * @param array $descriptions
     */
    private static function addUuid(array &$triggers, array $descriptions): void
    {
        foreach ($descriptions as $_triggers) {
            foreach ($_triggers as $_trigger) {
                if ($_trigger['host']['status'] == HOST_STATUS_TEMPLATE
                    && !array_key_exists('uuid', $triggers[$_trigger['index']])) {
                    $triggers[$_trigger['index']]['uuid'] = generateUuidV4();
                }
            }
        }
    }

    /**
     * Verify trigger UUIDs are not repeated.
     *
     * @param array $triggers
     * @param array|null $db_triggers
     *
     * @throws ValidateException
     */
    private static function checkUuidDuplicates(array $triggers, array $db_triggers = null): void
    {
        $trigger_indexes = [];

        foreach ($triggers as $i => $trigger) {
            if (!array_key_exists('uuid', $trigger) || $trigger['uuid'] === '') {
                continue;
            }

            if ($db_triggers === null || $trigger['uuid'] !== $db_triggers[$trigger['triggerid']]['uuid']) {
                $trigger_indexes[$trigger['uuid']] = $i;
            }
        }

        if (!$trigger_indexes) {
            return;
        }

        $duplicates = Triggers::find()->select(['uuid'])
            ->where([
                'flags' => static::FLAGS,
                'uuid' => array_keys($trigger_indexes)
            ])
            ->limit(1)
            ->asArray()->one();

        $error = '';
        if ($duplicates) {
            switch (static::FLAGS) {
                case PRS_FLAG_DISCOVERY_NORMAL:
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($trigger_indexes[$duplicates[0]['uuid']] + 1), 'error' => t('zapi', 'trigger with the same UUID already exists')]);
                    break;

                case PRS_FLAG_DISCOVERY_PROTOTYPE:
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($trigger_indexes[$duplicates[0]['uuid']] + 1), 'error' => t('zapi', 'trigger prototype with the same UUID already exists')]);
                    break;
            }

            self::exception(60750003, $error);
        }
    }

    /**
     * Checks triggers for duplicates.
     *
     * @param array $descriptions
     * @param string $descriptions [<description>][]['expression']
     * @param string $descriptions [<description>][]['recovery_expression']
     * @param string $descriptions [<description>][]['hostid']
     *
     * @throws ValidateException
     */
    protected function checkDuplicates(array $descriptions)
    {
        foreach ($descriptions as $description => $triggers) {
            $hostids = [];
            $expressions = [];

            foreach ($triggers as $trigger) {
                if (array_key_exists('name_updated', $trigger) && !$trigger['name_updated']) {
                    continue;
                }

                $hostids[$trigger['host']['hostid']] = true;
                $expressions[$trigger['expression']][$trigger['recovery_expression']] = $trigger['host']['hostid'];
            }

            if (!$hostids) {
                continue;
            }

            $db_triggers = (new Query())->select(['t.expression', 't.recovery_expression'])
                ->from(['t' => 'triggers', 'f' => 'functions', 'i' => 'items', 'h' => 'hosts'])
                ->where('t.triggerid=f.triggerid')
                ->andWhere('f.itemid=i.itemid')
                ->andWhere('i.hostid=h.hostid')
                ->andWhere(['t.description' => $description])
                ->andFilterWhere(['i.hostid' => array_keys($hostids)])
                ->distinct()
                ->all();

            $db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($db_triggers,
                ['sources' => ['expression', 'recovery_expression']]
            );

            foreach ($db_triggers as $db_trigger) {
                $expression = $db_trigger['expression'];
                $recovery_expression = $db_trigger['recovery_expression'];

                if (array_key_exists($expression, $expressions)
                    && array_key_exists($recovery_expression, $expressions[$expression])) {

                    $db_hosts = Hosts::find()->select(['name'])
                        ->where(['hostid' => $expressions[$expression][$recovery_expression]])
                        ->asArray()
                        ->all();

                    $error_already_exists = ($this instanceof TriggerAssist)
                        ? t('zapi', 'Trigger "{name}" already exists on {host}".', ['name' => $description, 'host' => $db_hosts[0]['name']])
                        : t('zapi', 'Trigger prototype "{name}" already exists on {host}".', ['name' => $description, 'host' => $db_hosts[0]['name']]);
                    self::exception(60750003, $error_already_exists);
                }
            }
        }
    }

    /**
     * Validate integrity of trigger recovery properties.
     *
     * @static
     *
     * @param array $trigger
     * @param int $trigger ['recovery_mode']
     * @param string $trigger ['recovery_expression']
     *
     * @throws ValidateException
     */
    private static function checkTriggerRecoveryMode(array $trigger)
    {
        if ($trigger['recovery_mode'] == PRS_RECOVERY_MODE_RECOVERY_EXPRESSION) {
            if ($trigger['recovery_expression'] === '') {
                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'recovery_expression', 'error' => t('zapi', 'cannot be empty')]));
            }
        } elseif ($trigger['recovery_expression'] !== '') {
            self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'recovery_expression', 'error' => t('zapi', 'should be empty')]));
        }
    }

    /**
     * Validate trigger correlation mode and related properties.
     *
     * @static
     *
     * @param array $trigger
     * @param int $trigger ['correlation_mode']
     * @param string $trigger ['correlation_tag']
     * @param int $trigger ['recovery_mode']
     *
     * @throws ValidateException
     */
    private static function checkTriggerCorrelationMode(array $trigger)
    {
        if ($trigger['correlation_mode'] == PRS_TRIGGER_CORRELATION_TAG) {
            if ($trigger['recovery_mode'] == PRS_RECOVERY_MODE_NONE) {
                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'correlation_mode', 'error' => t('zapi', 'unexpected value "{value}"'), ['value' => $trigger['correlation_mode']]]));
            }

            if ($trigger['correlation_tag'] === '') {
                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'correlation_tag', 'error' => t('zapi', 'cannot be empty')]));
            }
        } elseif ($trigger['correlation_tag'] !== '') {
            self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'correlation_tag', 'error' => t('zapi', 'should be empty')]));

        }
    }

    /**
     * Validate trigger to be created.
     *
     * @param array $triggers [IN/OUT]
     * @param array $triggers []['description']                  [IN]
     * @param string $triggers []['expression']                   [IN]
     * @param string $triggers []['opdata']                       [IN]
     * @param string $triggers []['event_name']                   [IN]
     * @param string $triggers []['comments']                     [IN] (optional)
     * @param int $triggers []['priority']                     [IN] (optional)
     * @param int $triggers []['status']                       [IN] (optional)
     * @param int $triggers []['type']                         [IN] (optional)
     * @param string $triggers []['url_name']                     [IN] (optional)
     * @param string $triggers []['url']                          [IN] (optional)
     * @param int $triggers []['recovery_mode']                [IN/OUT] (optional)
     * @param string $triggers []['recovery_expression']          [IN/OUT] (optional)
     * @param int $triggers []['correlation_mode']             [IN/OUT] (optional)
     * @param string $triggers []['correlation_tag']              [IN/OUT] (optional)
     * @param int $triggers []['manual_close']                 [IN] (optional)
     * @param int $triggers []['discover']                     [IN] (optional) for trigger prototypes only
     * @param array $triggers []['tags']                         [IN] (optional)
     * @param string $triggers []['tags'][]['tag']                [IN]
     * @param string $triggers []['tags'][]['value']              [IN/OUT] (optional)
     * @param array $triggers []['dependencies']                 [IN] (optional)
     * @param string $triggers []['dependencies'][]['triggerid']  [IN]
     *
     * @throws ValidateException
     * @throws Exception
     */
    protected function validateCreate(array &$triggers)
    {
        $fieldRules = [
            'uuid' => ['safe'],
            'description' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('triggers', 'description')],
            'expression' => [TriggerExpressionValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_LLD_MACRO],
            'event_name' => [EventNameValidator::class, 'length' => DB::getFieldLength('triggers', 'event_name')],
            'opdata' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('triggers', 'opdata')],
            'comments' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('triggers', 'comments')],
            'priority' => [Int32Validator::class, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))],
            'status' => [Int32Validator::class, 'in' => implode(',', [TRIGGER_STATUS_ENABLED, TRIGGER_STATUS_DISABLED])],
            'type' => [Int32Validator::class, 'in' => implode(',', [TRIGGER_MULT_EVENT_DISABLED, TRIGGER_MULT_EVENT_ENABLED])],
            'url_name' => [Utf8StringValidator::class, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('triggers', 'url_name')],
            'url' => [UrlValidator::class, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('triggers', 'url')],
            'recovery_mode' => [Int32Validator::class, 'in' => implode(',', [PRS_RECOVERY_MODE_EXPRESSION, PRS_RECOVERY_MODE_RECOVERY_EXPRESSION, PRS_RECOVERY_MODE_NONE]), 'default' => DB::getDefault('triggers', 'recovery_mode')],
            'recovery_expression' => [TriggerExpressionValidator::class, 'flags' => API_ALLOW_LLD_MACRO, 'default' => DB::getDefault('triggers', 'recovery_expression')],
            'correlation_mode' => [Int32Validator::class, 'in' => implode(',', [PRS_TRIGGER_CORRELATION_NONE, PRS_TRIGGER_CORRELATION_TAG]), 'default' => DB::getDefault('triggers', 'correlation_mode')],
            'correlation_tag' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('triggers', 'correlation_tag'), 'default' => DB::getDefault('triggers', 'correlation_tag')],
            'manual_close' => [Int32Validator::class, 'in' => implode(',', [PRS_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED, PRS_TRIGGER_MANUAL_CLOSE_ALLOWED])],
            'tags' => [ObjectsValidator::class, 'uniq' => [['tag', 'value']], 'fields' => [
                'tag' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('trigger_tag', 'tag')],
                'value' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('trigger_tag', 'value'), 'default' => DB::getDefault('trigger_tag', 'value')]
            ]],
            'dependencies' => [ObjectsValidator::class, 'uniq' => [['triggerid']], 'fields' => [
                'triggerid' => [IdValidator::class, 'flags' => API_REQUIRED]
            ]]
        ];
        if ($this instanceof TriggerPrototypeAssist) {
            $fieldRules['discover'] = [Int32Validator::class, 'in' => implode(',', [TRIGGER_DISCOVER, TRIGGER_NO_DISCOVER])];
        } else {
            $fieldRules['expression']['flags'] &= ~API_ALLOW_LLD_MACRO;
            $fieldRules['recovery_expression']['flags'] &= ~API_ALLOW_LLD_MACRO;
        }
        if (!ValidateHelper::validateObjects($triggers, $fieldRules, ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['description', 'expression']]], $error)) {
            self::exception(60750003, $error);
        }

        $descriptions = [];
        foreach ($triggers as $index => $trigger) {
            self::checkTriggerRecoveryMode($trigger);
            self::checkTriggerCorrelationMode($trigger);

            $descriptions[$trigger['description']][] = [
                'index' => $index,
                'expression' => $trigger['expression'],
                'recovery_expression' => $trigger['recovery_expression']
            ];
        }

        $descriptions = $this->populateHostIds($descriptions);

        self::validateUuid($triggers, $descriptions);

        self::addUuid($triggers, $descriptions);

        self::checkUuidDuplicates($triggers);
        $this->checkDuplicates($descriptions);
        $this->checkDependencies($triggers);
    }

    /**
     * Validate trigger to be updated.
     *
     * @param array $triggers [IN/OUT]
     * @param array $triggers []['triggerid']                    [IN]
     * @param array $triggers []['description']                  [IN/OUT] (optional)
     * @param string $triggers []['expression']                   [IN/OUT] (optional)
     * @param string $triggers []['event_name']                   [IN] (optional)
     * @param string $triggers []['opdata']                       [IN] (optional)
     * @param string $triggers []['comments']                     [IN] (optional)
     * @param int $triggers []['priority']                     [IN] (optional)
     * @param int $triggers []['status']                       [IN] (optional)
     * @param int $triggers []['type']                         [IN] (optional)
     * @param string $triggers []['url_name']                     [IN] (optional)
     * @param string $triggers []['url']                          [IN] (optional)
     * @param int $triggers []['recovery_mode']                [IN/OUT] (optional)
     * @param string $triggers []['recovery_expression']          [IN/OUT] (optional)
     * @param int $triggers []['correlation_mode']             [IN/OUT] (optional)
     * @param string $triggers []['correlation_tag']              [IN/OUT] (optional)
     * @param int $triggers []['manual_close']                 [IN] (optional)
     * @param int $triggers []['discover']                     [IN] (optional) for trigger prototypes only
     * @param array $triggers []['tags']                         [IN] (optional)
     * @param string $triggers []['tags'][]['tag']                [IN]
     * @param string $triggers []['tags'][]['value']              [IN/OUT] (optional)
     * @param array $triggers []['dependencies']                 [IN] (optional)
     * @param string $triggers []['dependencies'][]['triggerid']  [IN]
     * @param array $db_triggers [OUT]
     * @param array $db_triggers [<tnum>]['triggerid']           [OUT]
     * @param array $db_triggers [<tnum>]['description']         [OUT]
     * @param string $db_triggers [<tnum>]['expression']          [OUT]
     * @param string $db_triggers [<tnum>]['event_name']          [OUT]
     * @param string $db_triggers [<tnum>]['opdata']              [OUT]
     * @param int $db_triggers [<tnum>]['recovery_mode']       [OUT]
     * @param string $db_triggers [<tnum>]['recovery_expression'] [OUT]
     * @param string $db_triggers [<tnum>]['url_name']            [OUT]
     * @param string $db_triggers [<tnum>]['url']                 [OUT]
     * @param int $db_triggers [<tnum>]['status']              [OUT]
     * @param int $db_triggers [<tnum>]['discover']            [OUT]
     * @param int $db_triggers [<tnum>]['priority']            [OUT]
     * @param string $db_triggers [<tnum>]['comments']            [OUT]
     * @param int $db_triggers [<tnum>]['type']                [OUT]
     * @param string $db_triggers [<tnum>]['templateid']          [OUT]
     * @param int $db_triggers [<tnum>]['correlation_mode']    [OUT]
     * @param string $db_triggers [<tnum>]['correlation_tag']     [OUT]
     * @param int $db_triggers [<tnum>]['discover']            [OUT] for trigger prototypes only
     *
     * @throws Exception
     * @throws ValidateException
     */
    protected function validateUpdate(array &$triggers, array &$db_triggers = null)
    {
        $fieldRules = [
            'uuid' => ['safe'],
            'triggerid' => [IdValidator::class, 'flags' => API_REQUIRED],
            'description' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('triggers', 'description')],
            'expression' => [TriggerExpressionValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_LLD_MACRO],
            'event_name' => [EventNameValidator::class, 'length' => DB::getFieldLength('triggers', 'event_name')],
            'opdata' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('triggers', 'opdata')],
            'comments' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('triggers', 'comments')],
            'priority' => [Int32Validator::class, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))],
            'status' => [Int32Validator::class, 'in' => implode(',', [TRIGGER_STATUS_ENABLED, TRIGGER_STATUS_DISABLED])],
            'type' => [Int32Validator::class, 'in' => implode(',', [TRIGGER_MULT_EVENT_DISABLED, TRIGGER_MULT_EVENT_ENABLED])],
            'url_name' => [Utf8StringValidator::class, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('triggers', 'url_name')],
            'url' => [UrlValidator::class, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('triggers', 'url')],
            'recovery_mode' => [Int32Validator::class, 'in' => implode(',', [PRS_RECOVERY_MODE_EXPRESSION, PRS_RECOVERY_MODE_RECOVERY_EXPRESSION, PRS_RECOVERY_MODE_NONE])],
            'recovery_expression' => [TriggerExpressionValidator::class, 'flags' => API_ALLOW_LLD_MACRO],
            'correlation_mode' => [Int32Validator::class, 'in' => implode(',', [PRS_TRIGGER_CORRELATION_NONE, PRS_TRIGGER_CORRELATION_TAG])],
            'correlation_tag' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('triggers', 'correlation_tag')],
            'manual_close' => [Int32Validator::class, 'in' => implode(',', [PRS_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED, PRS_TRIGGER_MANUAL_CLOSE_ALLOWED])],
            'tags' => [ObjectsValidator::class, 'uniq' => [['tag', 'value']], 'fields' => [
                'tag' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('trigger_tag', 'tag')],
                'value' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('trigger_tag', 'value'), 'default' => DB::getDefault('trigger_tag', 'value')]
            ]],
            'dependencies' => [ObjectsValidator::class, 'uniq' => [['triggerid']], 'fields' => [
                'triggerid' => [IdValidator::class, 'flags' => API_REQUIRED]
            ]]
        ];

        if ($this instanceof TriggerPrototypeAssist) {
            $fieldRules['discover'] = [Int32Validator::class, 'in' => implode(',', [TRIGGER_DISCOVER, TRIGGER_NO_DISCOVER])];
        } else {
            $fieldRules['expression']['flags'] &= ~API_ALLOW_LLD_MACRO;
            $fieldRules['recovery_expression']['flags'] &= ~API_ALLOW_LLD_MACRO;
        }
        if (!ValidateHelper::validateObjects($triggers, $fieldRules, ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['description', 'expression']]], $error)) {
            self::exception(60750003, $error);
        }

        $options = [
            'output' => ['uuid', 'triggerid', 'description', 'expression', 'url_name', 'url', 'status', 'priority',
                'comments', 'type', 'templateid', 'recovery_mode', 'recovery_expression', 'correlation_mode',
                'correlation_tag', 'manual_close', 'opdata', 'event_name'
            ],
            'triggerids' => prs_objectValues($triggers, 'triggerid'),
            'editable' => true,
            'preservekeys' => true
        ];

        $class = get_class($this);

        switch ($class) {
            case TriggerAssist::class:
                $error_cannot_update = t('zapi', 'Cannot update "%1$s" for templated trigger "%2$s".');
                $options['output'][] = 'flags';

                // Discovered fields, except status, cannot be updated.
                $update_discovered_validator = new CUpdateDiscoveredValidator([
                    'allowed' => ['triggerid', 'status'],
                    'messageAllowedField' => t('zapi', 'Cannot update "%2$s" for a discovered trigger "%1$s".')
                ]);
                break;

            case TriggerPrototypeAssist::class:
                $error_cannot_update = t('zapi', 'Cannot update "%1$s" for templated trigger prototype "%2$s".');
                $options['output'][] = 'discover';
                $options['selectDiscoveryRule'] = ['itemid'];
                break;

            default:
                self::exception(PRS_API_ERROR_INTERNAL,t('zapi', 'Internal error.'));
        }

        $db_triggers = Triggers::find()->select($options['output'])
            ->where(['triggerid' => prs_objectValues($triggers, 'triggerid')])
            ->indexBy('triggerid')
            ->asArray()->all();
        if ($class == TriggerPrototypeAssist::class) {
            $db_triggers = $this->setTriggerDiscoverRule($db_triggers);
        }

        if (count($db_triggers) != count($triggers)) {
            self::exception(PRS_API_ERROR_PERMISSIONS, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        $db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($db_triggers, [
            'sources' => ['expression', 'recovery_expression']
        ]);

        $this->addAffectedObjects($triggers, $db_triggers);

        $read_only_fields = ['uuid', 'description', 'expression', 'recovery_mode', 'recovery_expression',
            'correlation_mode', 'correlation_tag', 'manual_close'
        ];

        $descriptions = [];

        foreach ($triggers as $index => &$trigger) {
            $db_trigger = $db_triggers[$trigger['triggerid']];
            $description = array_key_exists('description', $trigger)
                ? $trigger['description']
                : $db_trigger['description'];

            if ($class === TriggerAssist::class) {
                $update_discovered_validator->setObjectName($description);
                $this->checkPartialValidator($trigger, $update_discovered_validator, $db_trigger);
            }

            if ($db_trigger['templateid'] != 0) {
                $this->checkNoParameters($trigger, $read_only_fields, $error_cannot_update, $description);
            }

            $field_names = ['description', 'expression', 'recovery_mode', 'manual_close'];
            foreach ($field_names as $field_name) {
                if (!array_key_exists($field_name, $trigger)) {
                    $trigger[$field_name] = $db_trigger[$field_name];
                }
            }

            if (!array_key_exists('recovery_expression', $trigger)) {
                $trigger['recovery_expression'] = ($trigger['recovery_mode'] == PRS_RECOVERY_MODE_RECOVERY_EXPRESSION)
                    ? $db_trigger['recovery_expression']
                    : '';
            }
            if (!array_key_exists('correlation_mode', $trigger)) {
                $trigger['correlation_mode'] = ($trigger['recovery_mode'] != PRS_RECOVERY_MODE_NONE)
                    ? $db_trigger['correlation_mode']
                    : PRS_TRIGGER_CORRELATION_NONE;
            }
            if (!array_key_exists('correlation_tag', $trigger)) {
                $trigger['correlation_tag'] = ($trigger['correlation_mode'] == PRS_TRIGGER_CORRELATION_TAG)
                    ? $db_trigger['correlation_tag']
                    : '';
            }

            self::checkTriggerRecoveryMode($trigger);
            self::checkTriggerCorrelationMode($trigger);

            $name_updated = $trigger['expression'] !== $db_trigger['expression']
                || $trigger['recovery_expression'] !== $db_trigger['recovery_expression']
                || $trigger['description'] !== $db_trigger['description'];

            if ($name_updated || array_key_exists('uuid', $trigger)) {
                $descriptions[$trigger['description']][] = [
                    'index' => $index,
                    'expression' => $trigger['expression'],
                    'recovery_expression' => $trigger['recovery_expression'],
                    'name_updated' => $name_updated
                ];
            }
        }
        unset($trigger);

        if ($descriptions) {
            $descriptions = $this->populateHostIds($descriptions);

            self::validateUuid($triggers, $descriptions);

            self::checkUuidDuplicates($triggers, $db_triggers);
            $this->checkDuplicates($descriptions);
        }

        $this->checkDependencies($triggers, $db_triggers);
        $this->checkDependenciesLinks($triggers, $db_triggers);
    }

    /**
     * @param array $triggers
     * @param array $db_triggers
     */
    private function addAffectedObjects(array $triggers, array &$db_triggers): void
    {
        if ($this instanceof TriggerAssist) {
            self::addAffectedHosts($triggers, $db_triggers);
        }

        self::addAffectedTags($triggers, $db_triggers);
        self::addAffectedDependencies($triggers, $db_triggers);
    }


    /**
     * @param array $triggers
     * @param array $db_triggers
     */
    protected static function addAffectedHosts(array $triggers, array &$db_triggers): void
    {
        $triggerids = [];

        foreach ($triggers as $trigger) {
            $db_trigger = $db_triggers[$trigger['triggerid']];

            if ((array_key_exists('expression', $trigger) && $trigger['expression'] !== $db_trigger['expression'])
                || (array_key_exists('recovery_expression', $trigger)
                    && $trigger['recovery_expression'] !== $db_trigger['recovery_expression'])) {
                $triggerids[] = $trigger['triggerid'];
                $db_triggers[$trigger['triggerid']]['hosts'] = [];
            }
        }

        if (!$triggerids) {
            return;
        }

        $rows = (new Query())->select(['f.triggerid', 'i.hostid'])
            ->from(['f' => 'functions', 'i' => 'items'])
            ->where('f.itemid=i.itemid')
            ->andWhere(['f.triggerid' => $triggerids])
            ->distinct()
            ->all();

        foreach ($rows as $row) {
            $db_triggers[$row['triggerid']]['hosts'][$row['hostid']] = true;
        }
    }

    /**
     * @param array $triggers
     * @param array $db_triggers
     */
    private static function addAffectedTags(array $triggers, array &$db_triggers): void
    {
        $triggerids = [];

        foreach ($triggers as $trigger) {
            if (array_key_exists('tags', $trigger)) {
                $triggerids[] = $trigger['triggerid'];
                $db_triggers[$trigger['triggerid']]['tags'] = [];
            }
        }

        if (!$triggerids) {
            return;
        }

        $db_tags = (new Query())->select(['triggertagid', 'triggerid', 'tag', 'value'])
            ->from(['trigger_tag'])
            ->andWhere(['triggerid' => $triggerids])
            ->all();

        foreach ($db_tags as $db_tag) {
            $db_triggers[$db_tag['triggerid']]['tags'][$db_tag['triggertagid']] =
                array_diff_key($db_tag, array_flip(['triggerid']));
        }
    }

    /**
     * @param array $triggers
     * @param array $db_triggers
     */
    protected static function addAffectedDependencies(array $triggers, array &$db_triggers): void
    {
        $triggerids = [];

        foreach ($triggers as $trigger) {
            $db_trigger = $db_triggers[$trigger['triggerid']];

            if (array_key_exists('dependencies', $trigger) || array_key_exists('hosts', $db_trigger)) {
                $triggerids[] = $trigger['triggerid'];
                $db_triggers[$trigger['triggerid']]['dependencies'] = [];
            }
        }

        if (!$triggerids) {
            return;
        }

        $db_deps = (new Query())->select(['triggerdepid', 'triggerid_down', 'triggerid_up'])
            ->from(['trigger_depends'])
            ->andWhere(['triggerid_down' => $triggerids])
            ->all();

        foreach ($db_deps as $db_dep) {
            $db_triggers[$db_dep['triggerid_down']]['dependencies'][$db_dep['triggerdepid']] = [
                'triggerdepid' => $db_dep['triggerdepid'],
                'triggerid' => $db_dep['triggerid_up']
            ];
        }
    }

    /**
     * Check trigger dependencies of the given triggers.
     *
     * @param array $triggers
     * @param array|null $db_triggers
     *
     * @throws ValidateException
     */
    private function checkDependencies(array $triggers, array $db_triggers = null): void
    {
        $edit_triggerids_up = [];

        foreach ($triggers as $trigger) {
            if (!array_key_exists('dependencies', $trigger)) {
                continue;
            }

            $triggerids_up = array_column($trigger['dependencies'], 'triggerid');

            if ($db_triggers === null) {
                $edit_triggerids_up += array_flip($triggerids_up);
            } else {
                $db_triggerids_up = array_column($db_triggers[$trigger['triggerid']]['dependencies'], 'triggerid');

                $ins_triggerids_up = array_flip(array_diff($triggerids_up, $db_triggerids_up));
                $del_triggerids_up = array_flip(array_diff($db_triggerids_up, $triggerids_up));

                $edit_triggerids_up += $ins_triggerids_up + $del_triggerids_up;
            }
        }

        if (!$edit_triggerids_up) {
            return;
        }

        $count = Triggers::find()->where(['triggerid' => array_keys($edit_triggerids_up)])->count();

        if ($this instanceof TriggerPrototypeAssist) {
            $trigger_prototypes_up = Triggers::find()->select(['triggerid'])
                ->where(['triggerid' => array_keys($edit_triggerids_up)])
                ->andWhere(['flags' => PRS_FLAG_DISCOVERY_PROTOTYPE])
                ->indexBy('triggerid')->asArray()->all();

            $count += count($trigger_prototypes_up);
        }

        if ($count != count($edit_triggerids_up)) {
            self::exception(60750003, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        if ($this instanceof TriggerPrototypeAssist) {
            $ins_dependencies = [];
            $triggerids = [];

            foreach ($triggers as $trigger) {
                if (!array_key_exists('dependencies', $triggers)) {
                    continue;
                }

                $db_triggers_up = ($db_triggers !== null)
                    ? array_column($db_triggers[$trigger['triggerid']]['dependencies'], null, 'triggerid')
                    : [];

                foreach ($trigger['dependencies'] as $trigger_up) {
                    if (!array_key_exists($trigger_up['triggerid'], $db_triggers_up)
                        && array_key_exists($trigger_up['triggerid'], $trigger_prototypes_up)) {
                        $ins_dependencies[$trigger_up['triggerid']][$trigger['triggerid']] = true;
                        $triggerids[$trigger['triggerid']] = true;
                    }
                }
            }

            if (!$ins_dependencies) {
                return;
            }

            $rows = (new Query())->select(['f.triggerid', 'id.parent_itemid'])
                ->from(['f' => 'functions', 'id' => 'item_discovery'])
                ->where('f.itemid=id.itemid')
                ->andWhere(['f.triggerid' => array_keys($ins_dependencies + $triggerids)])
                ->distinct()
                ->all();

            $lld_rules = [];

            foreach ($rows as $row) {
                $lld_rules[$row['triggerid']] = $row['parent_itemid'];
            }

            foreach ($ins_dependencies as $triggerid_up => $triggerids) {
                foreach ($triggerids as $triggerid) {
                    if (bccomp($lld_rules[$triggerid_up], $lld_rules[$triggerid]) != 0) {
                        self::exception(PRS_API_ERROR_PARAMETERS,
                            t('zapi', 'Trigger prototype "%1$s" cannot depend on the trigger prototype "%2$s", because dependencies on trigger prototypes from another LLD rule are not allowed.')
                        );
                    }
                }
            }
        }
    }

    /**
     * Check linkage of dependencies.
     *
     * @param array $triggers
     * @param array|null $db_triggers
     * @throws ValidateException
     */
    protected function checkDependenciesLinks(array $triggers, array $db_triggers = null): void
    {
        $ins_dependencies = [];
        $del_dependencies = [];

        foreach ($triggers as $trigger) {
            if (!array_key_exists('dependencies', $trigger)) {
                continue;
            }

            $db_triggers_up = ($db_triggers !== null)
                ? array_column($db_triggers[$trigger['triggerid']]['dependencies'], null, 'triggerid')
                : [];

            foreach ($trigger['dependencies'] as $trigger_up) {
                if (array_key_exists($trigger_up['triggerid'], $db_triggers_up)) {
                    unset($db_triggers_up[$trigger_up['triggerid']]);
                } else {
                    $ins_dependencies[$trigger_up['triggerid']][$trigger['triggerid']] = true;
                }
            }

            foreach ($db_triggers_up as $db_trigger_up) {
                $del_dependencies[$db_trigger_up['triggerid']][$trigger['triggerid']] = true;
            }
        }

        if ($ins_dependencies) {
            if ($this instanceof TriggerPrototypeAssist) {
                $rows = (new Query())->select(['triggerid', 'flags'])
                    ->from(['triggers'])
                    ->andWhere(['triggerid' => array_keys($ins_dependencies)])
                    ->all();

                $ins_dependencies_prototypes = [];

                foreach ($rows as $row) {
                    if ($row['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE) {

                        /*
                         * Trigger cannot depend on trigger prototypes, so it's enough to check circular dependencies
                         * only among the trigger prototypes.
                         */
                        $ins_dependencies_prototypes[$row['triggerid']] = $ins_dependencies[$row['triggerid']];

                        /*
                         * Since the trigger prototypes can depend only on trigger prototypes from the same LLD rule,
                         * for such dependencies it is not necessary to check the dependencies of host and template
                         * triggers. Only dependencies on triggers are required to be checked.
                         */
                        unset($ins_dependencies[$row['triggerid']]);
                    }
                }
            }

            if ($db_triggers !== null) {
                if ($this instanceof TriggerPrototypeAssist && $ins_dependencies_prototypes) {
                    self::checkCircularDependencies($ins_dependencies_prototypes, $del_dependencies);
                } else {
                    self::checkCircularDependencies($ins_dependencies, $del_dependencies);
                }
            }

            $trigger_hosts = self::getTriggerHosts($ins_dependencies);

            self::checkDependenciesOfHostTriggers($ins_dependencies, $trigger_hosts);
            self::checkDependenciesOfTemplateTriggers($ins_dependencies, $trigger_hosts);
        }
    }

    /**
     * Inserts trigger or trigger prototypes records into the database.
     *
     * @param array $triggers [IN/OUT]
     * @param array $triggers []['triggerid']           [OUT]
     * @param array $triggers []['description']         [IN]
     * @param string $triggers []['expression']          [IN]
     * @param int $triggers []['recovery_mode']       [IN]
     * @param string $triggers []['recovery_expression'] [IN]
     * @param string $triggers []['url_name']            [IN] (optional)
     * @param string $triggers []['url']                 [IN] (optional)
     * @param int $triggers []['status']              [IN] (optional)
     * @param int $triggers []['priority']            [IN] (optional)
     * @param string $triggers []['comments']            [IN] (optional)
     * @param int $triggers []['type']                [IN] (optional)
     * @param string $triggers []['templateid']          [IN] (optional)
     * @param array $triggers []['tags']                [IN] (optional)
     * @param string $triggers []['tags'][]['tag']       [IN]
     * @param string $triggers []['tags'][]['value']     [IN]
     * @param int $triggers []['correlation_mode']    [IN] (optional)
     * @param string $triggers []['correlation_tag']     [IN] (optional)
     * @param bool $inherited [IN] (optional)  If set to true, trigger will be created for
     *                                                                   non-editable host/template.
     */
    protected function createReal(array &$triggers, $inherited = false)
    {
        $new_triggers = $triggers;
        $new_functions = [];
        $triggers_functions = [];
        $new_tags = [];
        $this->implode_expressions($new_triggers, null, $triggers_functions, $inherited);

        $triggerid = DB::reserveIds('triggers', count($new_triggers));

        foreach ($new_triggers as $tnum => &$new_trigger) {
            $new_trigger['triggerid'] = $triggerid;
            $triggers[$tnum]['triggerid'] = $triggerid;

            foreach ($triggers_functions[$tnum] as $trigger_function) {
                $trigger_function['triggerid'] = $triggerid;
                $new_functions[] = $trigger_function;
            }

            if ($this instanceof TriggerPrototypeAssist) {
                $new_trigger['flags'] = PRS_FLAG_DISCOVERY_PROTOTYPE;
            }

            if (array_key_exists('tags', $new_trigger)) {
                foreach ($new_trigger['tags'] as $tag) {
                    $tag['triggerid'] = $triggerid;
                    $new_tags[] = $tag;
                }
            }

            $triggerid = bcadd($triggerid, 1, 0);
        }
        unset($new_trigger);

        DB::insert('triggers', $new_triggers, false);
        DB::insertBatch('functions', $new_functions, false);

        if ($new_tags) {
            DB::insert('trigger_tag', $new_tags);
        }

//        if (!$inherited) {
//            $resource = ($this instanceof TriggerAssist) ? CAudit::RESOURCE_TRIGGER : CAudit::RESOURCE_TRIGGER_PROTOTYPE;
//            $this->addAuditBulk(CAudit::ACTION_ADD, $resource, $triggers);
//        }
    }


    /**
     * Update trigger or trigger prototypes records in the database.
     *
     * @param array $triggers [IN] list of triggers to be updated
     * @param array $triggers [<tnum>]['triggerid']                  [IN]
     * @param array $triggers [<tnum>]['description']                [IN]
     * @param string $triggers [<tnum>]['expression']                 [IN]
     * @param int $triggers [<tnum>]['recovery_mode']              [IN]
     * @param string $triggers [<tnum>]['recovery_expression']        [IN]
     * @param string $triggers [<tnum>]['url_name']                   [IN] (optional)
     * @param string $triggers [<tnum>]['url']                        [IN] (optional)
     * @param int $triggers [<tnum>]['status']                     [IN] (optional)
     * @param int $triggers [<tnum>]['priority']                   [IN] (optional)
     * @param string $triggers [<tnum>]['comments']                   [IN] (optional)
     * @param int $triggers [<tnum>]['type']                       [IN] (optional)
     * @param string $triggers [<tnum>]['templateid']                 [IN] (optional)
     * @param array $triggers [<tnum>]['tags']                       [IN]
     * @param string $triggers [<tnum>]['tags'][]['tag']              [IN]
     * @param string $triggers [<tnum>]['tags'][]['value']            [IN]
     * @param int $triggers [<tnum>]['correlation_mode']           [IN]
     * @param string $triggers [<tnum>]['correlation_tag']            [IN]
     * @param array $db_triggers [IN]
     * @param array $db_triggers [<tnum>]['triggerid']               [IN]
     * @param array $db_triggers [<tnum>]['description']             [IN]
     * @param string $db_triggers [<tnum>]['expression']              [IN]
     * @param int $db_triggers [<tnum>]['recovery_mode']           [IN]
     * @param string $db_triggers [<tnum>]['recovery_expression']     [IN]
     * @param string $db_triggers [<tnum>]['url_name']                [IN]
     * @param string $db_triggers [<tnum>]['url']                     [IN]
     * @param int $db_triggers [<tnum>]['status']                  [IN]
     * @param int $db_triggers [<tnum>]['priority']                [IN]
     * @param string $db_triggers [<tnum>]['comments']                [IN]
     * @param int $db_triggers [<tnum>]['type']                    [IN]
     * @param string $db_triggers [<tnum>]['templateid']              [IN]
     * @param array $db_triggers [<tnum>]['discoveryRule']           [IN] For trigger prototypes only.
     * @param string $db_triggers [<tnum>]['discoveryRule']['itemid'] [IN]
     * @param array $db_triggers [<tnum>]['tags']                    [IN]
     * @param string $db_triggers [<tnum>]['tags'][]['tag']           [IN]
     * @param string $db_triggers [<tnum>]['tags'][]['value']         [IN]
     * @param int $db_triggers [<tnum>]['correlation_mode']        [IN]
     * @param string $db_triggers [<tnum>]['correlation_tag']         [IN]
     * @param bool $inherited [IN] (optional)  If set to true, trigger will be
     *                                                                                created for non-editable
     *                                                                                host/template.
     *
     */
    protected function updateReal(array $triggers, array $db_triggers, $inherited = false)
    {
        $upd_triggers = [];
        $new_functions = [];
        $del_functions_triggerids = [];
        $triggers_functions = [];
        $new_tags = [];
        $del_triggertagids = [];
        $save_triggers = $triggers;
        $this->implode_expressions($triggers, $db_triggers, $triggers_functions, $inherited);

        foreach ($triggers as $tnum => $trigger) {
            $db_trigger = $db_triggers[$trigger['triggerid']];
            $upd_trigger = ['values' => [], 'where' => ['triggerid' => $trigger['triggerid']]];

            if (array_key_exists($tnum, $triggers_functions)) {
                $del_functions_triggerids[] = $trigger['triggerid'];

                foreach ($triggers_functions[$tnum] as $trigger_function) {
                    $trigger_function['triggerid'] = $trigger['triggerid'];
                    $new_functions[] = $trigger_function;
                }

                $upd_trigger['values']['expression'] = $trigger['expression'];
                $upd_trigger['values']['recovery_expression'] = $trigger['recovery_expression'];
            }

            if (array_key_exists('uuid', $trigger)) {
                $upd_trigger['values']['uuid'] = $trigger['uuid'];
            }
            if ($trigger['description'] !== $db_trigger['description']) {
                $upd_trigger['values']['description'] = $trigger['description'];
            }
            if (array_key_exists('event_name', $trigger) && $trigger['event_name'] !== $db_trigger['event_name']) {
                $upd_trigger['values']['event_name'] = $trigger['event_name'];
            }
            if (array_key_exists('opdata', $trigger) && $trigger['opdata'] !== $db_trigger['opdata']) {
                $upd_trigger['values']['opdata'] = $trigger['opdata'];
            }
            if ($trigger['recovery_mode'] != $db_trigger['recovery_mode']) {
                $upd_trigger['values']['recovery_mode'] = $trigger['recovery_mode'];
            }
            if (array_key_exists('url_name', $trigger) && $trigger['url_name'] !== $db_trigger['url_name']) {
                $upd_trigger['values']['url_name'] = $trigger['url_name'];
            }
            if (array_key_exists('url', $trigger) && $trigger['url'] !== $db_trigger['url']) {
                $upd_trigger['values']['url'] = $trigger['url'];
            }
            if (array_key_exists('status', $trigger) && $trigger['status'] != $db_trigger['status']) {
                $upd_trigger['values']['status'] = $trigger['status'];
            }
            if ($this instanceof TriggerPrototypeAssist
                && array_key_exists('discover', $trigger) && $trigger['discover'] != $db_trigger['discover']) {
                $upd_trigger['values']['discover'] = $trigger['discover'];
            }
            if (array_key_exists('priority', $trigger) && $trigger['priority'] != $db_trigger['priority']) {
                $upd_trigger['values']['priority'] = $trigger['priority'];
            }
            if (array_key_exists('comments', $trigger) && $trigger['comments'] !== $db_trigger['comments']) {
                $upd_trigger['values']['comments'] = $trigger['comments'];
            }
            if (array_key_exists('type', $trigger) && $trigger['type'] != $db_trigger['type']) {
                $upd_trigger['values']['type'] = $trigger['type'];
            }
            if (array_key_exists('templateid', $trigger) && $trigger['templateid'] != $db_trigger['templateid']) {
                $upd_trigger['values']['templateid'] = $trigger['templateid'];
            }
            if ($trigger['correlation_mode'] != $db_trigger['correlation_mode']) {
                $upd_trigger['values']['correlation_mode'] = $trigger['correlation_mode'];
            }
            if ($trigger['correlation_tag'] !== $db_trigger['correlation_tag']) {
                $upd_trigger['values']['correlation_tag'] = $trigger['correlation_tag'];
            }
            if ($trigger['manual_close'] != $db_trigger['manual_close']) {
                $upd_trigger['values']['manual_close'] = $trigger['manual_close'];
            }

            if ($upd_trigger['values']) {
                $upd_triggers[] = $upd_trigger;
            }

            if (array_key_exists('tags', $trigger)) {
                // Add new trigger tags and replace changed ones.

                CArrayHelper::sort($db_trigger['tags'], ['tag', 'value']);
                CArrayHelper::sort($trigger['tags'], ['tag', 'value']);

                $tags_delete = $db_trigger['tags'];
                $tags_add = $trigger['tags'];

                foreach ($tags_delete as $dt_key => $tag_delete) {
                    foreach ($tags_add as $nt_key => $tag_add) {
                        if ($tag_delete['tag'] === $tag_add['tag'] && $tag_delete['value'] === $tag_add['value']) {
                            unset($tags_delete[$dt_key], $tags_add[$nt_key]);
                            continue 2;
                        }
                    }
                }

                foreach ($tags_delete as $tag_delete) {
                    $del_triggertagids[] = $tag_delete['triggertagid'];
                }

                foreach ($tags_add as $tag_add) {
                    $tag_add['triggerid'] = $trigger['triggerid'];
                    $new_tags[] = $tag_add;
                }
            }
        }

        if ($upd_triggers) {
            DB::update('triggers', $upd_triggers);
        }
        if ($del_functions_triggerids) {
            DB::delete('functions', ['triggerid' => $del_functions_triggerids]);
        }
        if ($new_functions) {
            DB::insertBatch('functions', $new_functions, false);
        }
        if ($del_triggertagids) {
            DB::delete('trigger_tag', ['triggertagid' => $del_triggertagids]);
        }
        if ($new_tags) {
            DB::insert('trigger_tag', $new_tags);
        }

//        if (!$inherited) {
//            $resource = ($this instanceof TriggerAssist) ? CAudit::RESOURCE_TRIGGER : CAudit::RESOURCE_TRIGGER_PROTOTYPE;
//            $this->addAuditBulk(CAudit::ACTION_UPDATE, $resource, $save_triggers, $db_triggers);
//        }
    }

    /**
     * Implodes expression and recovery_expression for each trigger. Also returns array of functions and
     * array of hostnames for each trigger.
     *
     * For example: last(/host/system.cpu.load)>10 will be translated to {12}>10 and created database representation.
     *
     * Note: All expressions must be already validated and exploded.
     *
     * @param array $triggers [IN]
     * @param string $triggers [<tnum>]['description']            [IN]
     * @param string $triggers [<tnum>]['expression']             [IN/OUT]
     * @param int $triggers [<tnum>]['recovery_mode']          [IN]
     * @param string $triggers [<tnum>]['recovery_expression']    [IN/OUT]
     * @param array|null $db_triggers [IN]
     * @param string $db_triggers [<triggerid>]['triggerid']           [IN]
     * @param string $db_triggers [<triggerid>]['expression']          [IN]
     * @param string $db_triggers [<triggerid>]['recovery_expression'] [IN]
     * @param array $triggers_functions [OUT] array of the new functions which must be
     *                                                                     inserted into DB
     * @param string $triggers_functions [<tnum>][]['functionid'] [OUT]
     * @param null $triggers_functions [<tnum>][]['triggerid']  [OUT] must be initialized before insertion into DB
     * @param string $triggers_functions [<tnum>][]['itemid']     [OUT]
     * @param string $triggers_functions [<tnum>][]['name']       [OUT]
     * @param string $triggers_functions [<tnum>][]['parameter']  [OUT]
     * @param bool $inherited [IN] (optional)  If set to true, triggers will be
     *                                                                                created for non-editable
     *                                                                                hosts/templates.
     *
     * @throws ValidateException|\yii\db\Exception
     */
    private function implode_expressions(array &$triggers, ?array $db_triggers, array &$triggers_functions, $inherited)
    {
        $class = get_class($this);

        switch ($class) {
            case TriggerAssist::class:
                $expression_parser = new CExpressionParser(['usermacros' => true]);
                $error_wrong_host = t('zapi', 'Incorrect trigger expression. Host "{host}" does not exist or you have no access to this host.');
                $error_host_and_template = t('zapi', 'Incorrect trigger expression. Trigger expression elements should not belong to a template and a host simultaneously.');
                break;

            case TriggerPrototypeAssist::class:
                $expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);
                $error_wrong_host = t('zapi', 'Incorrect trigger prototype expression. Host "{host}" does not exist or you have no access to this host.');
                $error_host_and_template = t('zapi', 'Incorrect trigger prototype expression. Trigger prototype expression elements should not belong to a template and a host simultaneously.');
                break;

            default:
                self::exception(60750003, t('zapi', 'Internal error.'));
        }

        $hist_function_value_types = (new CHistFunctionData())->getValueTypes();

        /*
         * [
         *     <host> => [
         *         'hostid' => <hostid>,
         *         'host' => <host>,
         *         'status' => <status>,
         *         'keys' => [
         *             <key> => [
         *                 'itemid' => <itemid>,
         *                 'key' => <key>,
         *                 'value_type' => <value_type>,
         *                 'flags' => <flags>,
         *                 'lld_ruleid' => <itemid> (TriggerAssistProrotype only)
         *             ]
         *         ]
         *     ]
         * ]
         */
        $hosts_keys = [];
        $functions_num = 0;

        foreach ($triggers as $trigger) {
            $expressions_changed = ($db_triggers === null
                || ($trigger['expression'] !== $db_triggers[$trigger['triggerid']]['expression']
                    || $trigger['recovery_expression'] !== $db_triggers[$trigger['triggerid']]['recovery_expression']));

            if (!$expressions_changed) {
                continue;
            }

            $expression_parser->parse($trigger['expression']);
            $hist_functions = $expression_parser->getResult()->getTokensOfTypes(
                [CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
            );

            if ($trigger['recovery_mode'] == PRS_RECOVERY_MODE_RECOVERY_EXPRESSION) {
                $expression_parser->parse($trigger['recovery_expression']);
                $hist_functions = array_merge($hist_functions, $expression_parser->getResult()->getTokensOfTypes(
                    [CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
                ));
            }

            foreach ($hist_functions as $hist_function) {
                $host = $hist_function['data']['parameters'][0]['data']['host'];
                $item = $hist_function['data']['parameters'][0]['data']['item'];

                if (!array_key_exists($host, $hosts_keys)) {
                    $hosts_keys[$host] = [
                        'hostid' => null,
                        'host' => $host,
                        'status' => null,
                        'keys' => []
                    ];
                }

                $hosts_keys[$host]['keys'][$item] = [
                    'itemid' => null,
                    'key' => $item,
                    'value_type' => null,
                    'flags' => null
                ];
            }
        }

        if (!$hosts_keys) {
            return;
        }

        $_db_hosts = Hosts::find()->select(['hostid', 'host', 'status'])
            ->where(['host' => array_keys($hosts_keys)])
            ->andWhere(['flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]])
            ->asArray()->all();

        if (count($hosts_keys) != count($_db_hosts)) {
            $_db_templates = Hosts::find()->select(['templateid' => 'hostid', 'host', 'status'])
                ->where(['host' => array_keys($hosts_keys)])
                ->andWhere(['status' => HOST_STATUS_TEMPLATE])
                ->asArray()->all();

            foreach ($_db_templates as &$_db_template) {
                $_db_template['hostid'] = $_db_template['templateid'];
                unset($_db_template['templateid']);
            }
            unset($_db_template);

            $_db_hosts = array_merge($_db_hosts, $_db_templates);
        }

        foreach ($_db_hosts as $_db_host) {
            $host_keys = &$hosts_keys[$_db_host['host']];

            $host_keys['hostid'] = $_db_host['hostid'];
            $host_keys['status'] = $_db_host['status'];

            if ($class === TriggerPrototypeAssist::class) {
                $_db_items = (new Query())->select(['i.itemid', 'i.key_', 'i.value_type', 'i.flags', 'id.parent_itemid'])
                    ->from(['i' => 'items'])
                    ->leftJoin(['id' => 'item_discovery'], 'i.itemid=id.itemid')
                    ->where(['i.hostid' => $host_keys['hostid']])
                    ->andWhere(['i.key_' => array_keys($host_keys['keys'])])
                    ->andWhere(['i.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_PROTOTYPE, PRS_FLAG_DISCOVERY_CREATED]])
                    ->all();
            } else {
                $_db_items = (new Query())->select(['i.itemid', 'i.key_', 'i.value_type', 'i.flags'])
                    ->from(['i' => 'items'])
                    ->where(['i.hostid' => $host_keys['hostid']])
                    ->andWhere(['i.key_' => array_keys($host_keys['keys'])])
                    ->andWhere(['i.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]])
                    ->all();
            }

            foreach ($_db_items as $_db_item) {
                $host_keys['keys'][$_db_item['key_']]['itemid'] = $_db_item['itemid'];
                $host_keys['keys'][$_db_item['key_']]['value_type'] = $_db_item['value_type'];
                $host_keys['keys'][$_db_item['key_']]['flags'] = $_db_item['flags'];

                if ($class === TriggerPrototypeAssist::class && $_db_item['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                    $host_keys['keys'][$_db_item['key_']]['lld_ruleid'] = $_db_item['parent_itemid'];
                }
            }

            unset($host_keys);
        }

        /*
         * The list of triggers with multiple templates.
         *
         * [
         *     [
         *         'description' => <description>,
         *         'templateids' => [<templateid>, ...]
         *     ],
         *     ...
         * ]
         */
        $mt_triggers = [];

        if ($class === TriggerAssist::class) {
            /*
             * The list of triggers which are moved from one host or template to another.
             *
             * [
             *     <triggerid> => [
             *         'description' => <description>
             *     ],
             *     ...
             * ]
             */
            $moved_triggers = [];
        }

        foreach ($triggers as $tnum => &$trigger) {
            $expressions_changed = ($db_triggers === null
                || ($trigger['expression'] !== $db_triggers[$trigger['triggerid']]['expression']
                    || $trigger['recovery_expression'] !== $db_triggers[$trigger['triggerid']]['recovery_expression']));

            if (!$expressions_changed) {
                continue;
            }

            $expression_parser->parse($trigger['expression']);

            $hist_functions1 = $expression_parser->getResult()->getTokensOfTypes(
                [CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
            );

            $hist_functions2 = [];

            if ($trigger['recovery_mode'] == PRS_RECOVERY_MODE_RECOVERY_EXPRESSION) {
                $expression_parser->parse($trigger['recovery_expression']);

                $hist_functions2 = $expression_parser->getResult()->getTokensOfTypes(
                    [CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
                );
            }

            $triggers_functions[$tnum] = [];
            if ($class === TriggerPrototypeAssist::class) {
                $lld_ruleids = [];
            }

            /*
             * 0x01 - with templates
             * 0x02 - with hosts
             */
            $status_mask = 0x00;
            // The lists of hostids and hosts which are used in the current trigger.
            $hostids = [];
            $hosts = [];

            // Common checks.
            foreach (array_merge($hist_functions1, $hist_functions2) as $hist_function) {
                $host = $hist_function['data']['parameters'][0]['data']['host'];
                $item = $hist_function['data']['parameters'][0]['data']['item'];

                $host_keys = $hosts_keys[$host];
                $key = $host_keys['keys'][$item];

                if ($host_keys['hostid'] === null) {
                    self::exception(60750003, t('zapi', $error_wrong_host, ['host' => $host_keys['host']]));
                }

                if ($key['itemid'] === null) {
                    self::exception(60750003, t('zapi', 'Incorrect item key "{key}" provided for trigger expression on "{host}".', ['key' => $key['key'], 'host' => $host_keys['host']]));
                }

                if (!in_array($key['value_type'], $hist_function_value_types[$hist_function['data']['function']])) {
                    self::exception(60750003, t('zapi', 'Incorrect item key "{key}" provided for trigger function "{function}".', ['key' => ItemHelper::itemValueTypeString($key['value_type']), 'function' => $hist_function['data']['function']]));
                }

                if (!array_key_exists($hist_function['match'], $triggers_functions[$tnum])) {
                    $query_parameter = $hist_function['data']['parameters'][0];
                    $parameter = substr_replace($hist_function['match'], TRIGGER_QUERY_PLACEHOLDER,
                        $query_parameter['pos'] - $hist_function['pos'], $query_parameter['length']
                    );
                    $triggers_functions[$tnum][$hist_function['match']] = [
                        'functionid' => null,
                        'triggerid' => null,
                        'itemid' => $key['itemid'],
                        'name' => $hist_function['data']['function'],
                        'parameter' => substr($parameter, strlen($hist_function['data']['function']) + 1, -1)
                    ];
                    $functions_num++;
                }

                if ($class === TriggerPrototypeAssist::class && $key['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                    $lld_ruleids[$key['lld_ruleid']] = true;
                }

                $status_mask |= ($host_keys['status'] == HOST_STATUS_TEMPLATE ? 0x01 : 0x02);

                $hostids[$host_keys['hostid']] = true;
                $hosts[$host] = true;
            }

            // When both templates and hosts are referenced in expressions.
            if ($status_mask == 0x03) {
                self::exception(60750003, $error_host_and_template);
            }

            // Triggers with children cannot be moved from one template to another host or template.
            if ($class === TriggerAssist::class && $db_triggers !== null && $expressions_changed) {
                $expression_parser->parse($db_triggers[$trigger['triggerid']]['expression']);
                $old_hosts1 = $expression_parser->getResult()->getHosts();
                $old_hosts2 = [];

                if ($trigger['recovery_mode'] == PRS_RECOVERY_MODE_RECOVERY_EXPRESSION) {
                    $expression_parser->parse($db_triggers[$trigger['triggerid']]['recovery_expression']);
                    $old_hosts2 = $expression_parser->getResult()->getHosts();
                }

                $is_moved = true;
                foreach (array_merge($old_hosts1, $old_hosts2) as $old_host) {
                    if (array_key_exists($old_host, $hosts)) {
                        $is_moved = false;
                        break;
                    }
                }

                if ($is_moved) {
                    $moved_triggers[$trigger['triggerid']] = ['description' => $trigger['description']];
                }
            }

            // The trigger with multiple templates.
            if ($status_mask == 0x01 && count($hostids) > 1) {
                $mt_triggers[] = [
                    'description' => $trigger['description'],
                    'templateids' => array_keys($hostids)
                ];
            }

            if ($class === TriggerPrototypeAssist::class) {
                $lld_ruleids = array_keys($lld_ruleids);

                if (!$lld_ruleids) {
                    self::exception(60750003, t('zapi', 'Trigger prototype "{name}" must contain at least one item prototype.', ['name' => $trigger['description']]));
                } elseif (count($lld_ruleids) > 1) {
                    self::exception(60750003, t('zapi', 'Trigger prototype "{name}" contains item prototypes from multiple discovery rules.', ['name' => $trigger['description']]));
                } elseif ($db_triggers !== null
                    && !idcmp($lld_ruleids[0], $db_triggers[$trigger['triggerid']]['discoveryRule']['itemid'])) {
                    self::exception(60750003, t('zapi', 'Cannot update trigger prototype "{name}": {error}.', ['name' => $trigger['description'], 'error' => t('zapi', 'trigger prototype cannot be moved to another template or host')]));
                }
            }
        }
        unset($trigger);

        if ($mt_triggers) {
            $this->validateTriggersWithMultipleTemplates($mt_triggers);
        }

        if ($class === TriggerAssist::class && $moved_triggers) {
            $this->validateMovedTriggers($moved_triggers);
        }

        $functionid = DB::reserveIds('functions', $functions_num);

        $expression_max_length = DB::getFieldLength('triggers', 'expression');
        $recovery_expression_max_length = DB::getFieldLength('triggers', 'recovery_expression');

        // Replace func(/host/item) macros with {<functionid>}.
        foreach ($triggers as $tnum => &$trigger) {
            $expressions_changed = ($db_triggers === null
                || ($trigger['expression'] !== $db_triggers[$trigger['triggerid']]['expression']
                    || $trigger['recovery_expression'] !== $db_triggers[$trigger['triggerid']]['recovery_expression']));

            if (!$expressions_changed) {
                continue;
            }

            foreach ($triggers_functions[$tnum] as &$trigger_function) {
                $trigger_function['functionid'] = $functionid;
                $functionid = bcadd($functionid, 1, 0);
            }
            unset($trigger_function);

            $expression_parser->parse($trigger['expression']);
            $hist_functions = $expression_parser->getResult()->getTokensOfTypes(
                [CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
            );
            $hist_function = end($hist_functions);
            do {
                $trigger['expression'] = substr_replace($trigger['expression'],
                    '{' . $triggers_functions[$tnum][$hist_function['match']]['functionid'] . '}',
                    $hist_function['pos'], $hist_function['length']
                );
            } while ($hist_function = prev($hist_functions));

            if (mb_strlen($trigger['expression']) > $expression_max_length) {
                $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($tnum + 1) . '/expression', 'error' => t('zapi', 'value is too long')]);
                self::exception(60750003, $error);
            }

            if ($trigger['recovery_mode'] == PRS_RECOVERY_MODE_RECOVERY_EXPRESSION) {
                $expression_parser->parse($trigger['recovery_expression']);
                $hist_functions = $expression_parser->getResult()->getTokensOfTypes(
                    [CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
                );
                $hist_function = end($hist_functions);
                do {
                    $trigger['recovery_expression'] = substr_replace($trigger['recovery_expression'],
                        '{' . $triggers_functions[$tnum][$hist_function['match']]['functionid'] . '}',
                        $hist_function['pos'], $hist_function['length']
                    );
                } while ($hist_function = prev($hist_functions));

                if (mb_strlen($trigger['recovery_expression']) > $recovery_expression_max_length) {
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($tnum + 1) . '/recovery_expression', 'error' => t('zapi', 'value is too long')]);
                    self::exception(60750003, $error);
                }
            }
        }
        unset($trigger);
    }


    /**
     * Check if all templates trigger belongs to are linked to same hosts.
     *
     * @param array $mt_triggers
     * @param string $mt_triggers []['description']
     * @param array $mt_triggers []['templateids']
     *
     * @throws ValidateException
     */
    protected function validateTriggersWithMultipleTemplates(array $mt_triggers)
    {
        switch (get_class($this)) {
            case 'CTrigger':
                $error_different_linkages = t('zapi', 'Trigger "{name}" belongs to templates with different linkages.');
                break;

            case TriggerPrototypeAssist::class:
                $error_different_linkages = t('zapi', 'Trigger prototype "{name}" belongs to templates with different linkages.');
                break;

            default:
                self::exception(60750003, t('zapi', 'Internal error.'));

        }

        $templateids = [];

        foreach ($mt_triggers as $mt_trigger) {
            foreach ($mt_trigger['templateids'] as $templateid) {
                $templateids[$templateid] = true;
            }
        }

        $templates = Hosts::find()->select(['templateid' => 'hostid'])
            ->where(['hostid' => array_keys($templateids)])
            ->andWhere(['status' => HOST_STATUS_TEMPLATE])
            ->indexBy('templateid')
            ->asArray()->all();

        $templates = $this->setTemplateParents($templates);
        $templates = $this->setTemplateHosts($templates);

        foreach ($templates as &$template) {
            $template = array_merge(
                prs_objectValues($template['hosts'], 'hostid'),
                prs_objectValues($template['templates'], 'templateid')
            );
        }
        unset($template);

        foreach ($mt_triggers as $mt_trigger) {
            $compare_links = null;

            foreach ($mt_trigger['templateids'] as $templateid) {
                if ($compare_links === null) {
                    $compare_links = $templates[$templateid];
                    continue;
                }

                $linked_to = $templates[$templateid];

                if (array_diff($compare_links, $linked_to) || array_diff($linked_to, $compare_links)) {
                    self::exception(60750003, t('zapi', $error_different_linkages, ['name' => $mt_trigger['description']]));
                }
            }
        }
    }

    protected function setTemplateParents($templates): array
    {
        $templateList = [];
        $relationMap = $this->createRelationMap($templates, 'templateid', 'hostid', 'hosts_templates');
        $related_ids = $relationMap->getRelatedIds();

        if ($related_ids) {
            $templateList = Hosts::find()->select(['templateid' => 'hostid'])
                ->where(['hostid' => array_keys($templates)])
                ->andWhere(['status' => HOST_STATUS_TEMPLATE])
                ->indexBy('templateid')
                ->asArray()->all();
        }
        return $relationMap->mapMany($templates, $templateList, 'templates');
    }

    protected function setTemplateHosts($templates): array
    {
        $hostList = [];
        $relationMap = $this->createRelationMap($templates, 'templateid', 'hostid', 'hosts_templates');
        $related_ids = $relationMap->getRelatedIds();

        if ($related_ids) {
            $hostList = Hosts::find()->select(['hostid'])
                ->where(['hostid' => array_keys($templates)])
                ->andWhere(['flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]])
                ->indexBy('hostid')
                ->asArray()->all();
        }
        return $relationMap->mapMany($templates, $hostList, 'hosts');
    }

    /**
     * Check if moved triggers does not have children.
     *
     * @param array $moved_triggers
     * @param string $moved_triggers [<triggerid>]['description']
     *
     * @throws ValidateException
     */
    protected function validateMovedTriggers(array $moved_triggers)
    {
        $_db_trigger = Triggers::find()->select(['templateid'])
            ->where(['templateid' => array_keys($moved_triggers)])
            ->asArray()->limit(1)->one();

        if ($_db_trigger) {
            self::exception(60750003, t('zapi', 'Cannot update trigger "{name}": {error}.', ['name' => $moved_triggers[$_db_trigger['templateid']]['description'], 'error' => t('zapi', 'trigger with linkages cannot be moved to another template or host')]));
        }
    }

    /**
     * Adds triggers and trigger prototypes from template to hosts.
     *
     * @param array $data
     */
    public function syncTemplates(array $data)
    {
        $data['templateids'] = prs_toArray($data['templateids']);
        $data['hostids'] = prs_toArray($data['hostids']);

        $output = ['triggerid', 'description', 'expression', 'recovery_mode', 'recovery_expression', 'url_name', 'url',
            'status', 'priority', 'comments', 'type', 'correlation_mode', 'correlation_tag', 'manual_close', 'opdata',
            'event_name'
        ];
        if ($this instanceof TriggerPrototypeAssist) {
            $output[] = 'discover';
            $triggers = TriggerHelper::getTriggerPrototypes([
                'output' => $output,
                'selectTags' => ['tag', 'value'],
                'hostids' => $data['templateids'],
                'preservekeys' => true,
                'nopermissions' => true
            ]);
        } else {
            $triggers = TriggerHelper::getTriggers([
                'output' => $output,
                'selectTags' => ['tag', 'value'],
                'hostids' => $data['templateids'],
                'preservekeys' => true,
                'nopermissions' => true
            ]);
        }

        $triggers = CMacrosResolverHelper::resolveTriggerExpressions($triggers,
            ['sources' => ['expression', 'recovery_expression']]
        );

        $this->inherit($triggers, $data['hostids']);
    }

    /**
     * @param $triggers
     * @return array
     */
    protected function setTriggerTags($triggers): array
    {
        $tags = (new Query())->select(['triggertagid'])
            ->from(['trigger_tag'])
            ->where(['triggerid' => array_keys($triggers)])
            ->indexBy('triggertagid')
            ->all();
        $relationMap = $this->createRelationMap($tags, 'triggerid', 'triggertagid');
        $tags = $this->unsetExtraFields($tags, ['triggertagid', 'triggerid'], []);
        return $relationMap->mapMany($triggers, $tags, 'tags');
    }


    protected function setTriggerDiscoverRule(array $triggers): array
    {
        $triggerPrototypeIds = ArrayHelper::getColumn($triggers, 'triggerid');
        $dbRules = (new Query())->select(['id.parent_itemid','f.triggerid'])
            ->from(['id' => 'item_discovery', 'f' => 'functions'])
            ->where(['f.triggerid' => $triggerPrototypeIds])
            ->andWhere('f.itemid=id.itemid')
            ->all();
        $relationMap = new RelationMap();
        foreach ($dbRules as $rule) {
            $relationMap->addRelation($rule['triggerid'], $rule['parent_itemid']);
        }

        $discoveryRules = Items::find()->select(['itemid'])
            ->where(['itemid' => $relationMap->getRelatedIds()])
            ->andWhere(['flags' => PRS_FLAG_DISCOVERY_RULE])
            ->indexBy('itemid')->asArray()->all();
        return $relationMap->mapOne($triggers, $discoveryRules, 'discoveryRule');
    }

    /**
     * Check whether circular linkage occurs as a result of the given changes in trigger dependencies.
     *
     * @param array $ins_dependencies [<triggerid_up>][<triggerid>]
     * @param array $del_dependencies [<triggerid_up>][<triggerid>]
     * @param bool $inherited Whether the check gets performed during inherit.
     *
     * @throws ValidateException
     */
    protected static function checkCircularDependencies(array $ins_dependencies, array $del_dependencies = [], bool $inherited = false): void
    {
        $links = [];
        $_triggerids_down = $ins_dependencies;

        do {
            $rows = (new Query())->select(['triggerid_up', 'triggerid_down'])
                ->from('trigger_depends')
                ->where(['triggerid_down' => array_keys($_triggerids_down)])
                ->all();

            $_triggerids_down = [];

            foreach ($rows as $row) {
                if (array_key_exists($row['triggerid_up'], $del_dependencies)
                    && array_key_exists($row['triggerid_down'], $del_dependencies[$row['triggerid_up']])) {
                    continue;
                }

                if (!array_key_exists($row['triggerid_up'], $links)) {
                    $_triggerids_down[$row['triggerid_up']] = true;
                }

                $links[$row['triggerid_up']][$row['triggerid_down']] = true;
            }
        } while ($_triggerids_down);

        foreach ($ins_dependencies as $triggerid_up => $triggerids) {
            if (array_key_exists($triggerid_up, $links)) {
                $links[$triggerid_up] += $triggerids;
            } else {
                $links[$triggerid_up] = $triggerids;
            }
        }

        foreach ($ins_dependencies as $triggerid_up => $triggerids) {
            foreach ($triggerids as $triggerid => $foo) {
                if (array_key_exists($triggerid, $links)) {
                    $links_path = [$triggerid => true];

                    if (self::circularLinkageExists($links, $triggerid_up, $links[$triggerid], $links_path)) {
                        $trigger_up_name = '';

                        $triggers = Triggers::find()->select(['triggerid', 'description', 'flags'])
                            ->where(['triggerid' => array_keys($links_path + [$triggerid_up => true])])
                            ->indexBy('triggerid')
                            ->asArray()
                            ->all();

                        foreach ($triggers as $_triggerid => $trigger) {
                            $description = '"' . $trigger['description'] . '"';

                            if (bccomp($_triggerid, $triggerid_up) == 0) {
                                $trigger_up_name = $description;
                            } else {
                                $links_path[$_triggerid] = $description;
                            }
                        }

                        $circular_linkage = (bccomp($triggerid_up, $triggerid) == 0)
                            ? $trigger_up_name . ' -> ' . $trigger_up_name
                            : $trigger_up_name . ' -> ' . implode(' -> ', $links_path) . ' -> ' . $trigger_up_name;

                        if ($inherited) {
                            $host = (new Query())->select(['h.host', 'h.status'])
                                ->from(['f' => 'functions', 'i' => 'items', 'h' => 'hosts'])
                                ->where('f.itemid=i.itemid')
                                ->andWhere('i.hostid=h.hostid')
                                ->andWhere(['f.triggerid' => [$triggerid_up]])
                                ->limit(1)->one();

                            if ($host['status'] == HOST_STATUS_TEMPLATE) {
                                $error = ($triggers[$triggerid]['flags'] == PRS_FLAG_DISCOVERY_NORMAL)
                                    ? t('zapi', 'Trigger "{name1}" cannot depend on the trigger "{name2}", because a circular linkage ({linkage}) would occur for template "{host}".')
                                    : t('zapi', 'Trigger prototype "{name1}" cannot depend on the trigger prototype "{name2}", because a circular linkage ({linkage}) would occur for template "{host}".');
                            } else {
                                $error = ($triggers[$triggerid]['flags'] == PRS_FLAG_DISCOVERY_NORMAL)
                                    ? t('zapi', 'Trigger "{name1}" cannot depend on the trigger "{name2}", because a circular linkage ({linkage}) would occur for host "{host}".')
                                    : t('zapi', 'Trigger prototype "{name1}" cannot depend on the trigger prototype "{name2}", because a circular linkage ({linkage}) would occur for host "{host}".');
                            }

                            self::exception(60750003, t('zapi', $error, [
                                    'name1' => $triggers[$triggerid]['description'], 'name2' => $triggers[$triggerid_up]['description'],
                                    'linkage' => $circular_linkage, 'host' => $host['host']
                                ]
                            ));
                        } else {
                            $error = ($triggers[$triggerid]['flags'] == PRS_FLAG_DISCOVERY_NORMAL)
                                ? t('zapi', 'Trigger "{name1}" cannot depend on the trigger "{name2}", because a circular linkage ({linkage}) would occur.')
                                : t('zapi', 'Trigger prototype "{name1}" cannot depend on the trigger prototype "{name2}", because a circular linkage ({linkage}) would occur.');
                            self::exception(60750003, t('zapi', $error, [
                                    'name1' => $triggers[$triggerid]['description'], 'name2' => $triggers[$triggerid_up]['description'],
                                    'linkage' => $circular_linkage
                                ]
                            ));
                        }
                    }
                }
            }
        }
    }

    /**
     * Recursively check whether the trigger, which a dependency is being set on, produces a circular linkage.
     *
     * @param array $links [<triggerid_up>][<triggerid>]
     * @param string $triggerid_up
     * @param array $triggerids [<triggerid>]
     * @param array $links_path Circular linkage path, collected performing the check.
     *
     * @return bool
     */
    private static function circularLinkageExists(array $links, string $triggerid_up, array $triggerids, array &$links_path): bool
    {
        if (array_key_exists($triggerid_up, $triggerids)) {
            return true;
        }

        $_links_path = $links_path;

        foreach ($triggerids as $triggerid => $foo) {
            if (array_key_exists($triggerid, $links)) {
                $links_path = $_links_path;
                $triggerid_links = array_diff_key($links[$triggerid], $links_path);

                if ($triggerid_links) {
                    $links_path[$triggerid] = true;

                    if (self::circularLinkageExists($links, $triggerid_up, $triggerid_links, $links_path)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get hosts data of all triggers given in trigger dependencies array.
     *
     * @param array $trigger_dependencies [<triggerid_up>][<triggerid>]
     *
     * @return array
     */
    public static function getTriggerHosts(array $trigger_dependencies): array
    {
        $all_triggerids = $trigger_dependencies;

        foreach ($trigger_dependencies as $triggerids) {
            $all_triggerids += $triggerids;
        }

        $rows = (new Query())->select(['f.triggerid', 'h.hostid', 'h.status'])
            ->from(['f' => 'functions', 'i' => 'items', 'h' => 'hosts'])
            ->where('f.itemid=i.itemid')
            ->andWhere('i.hostid=h.hostid')
            ->andWhere(['f.triggerid' => array_keys($all_triggerids)])
            ->distinct()
            ->all();


        $trigger_hosts = [];

        foreach ($rows as $row) {
            // Each trigger can have either only templateids or only hostids.
            if ($row['status'] == HOST_STATUS_TEMPLATE) {
                $trigger_hosts[$row['triggerid']]['templateids'][$row['hostid']] = true;
            } else {
                $trigger_hosts[$row['triggerid']]['hostids'][$row['hostid']] = true;
            }
        }

        return $trigger_hosts;
    }

    /**
     * Check whether the trigger dependencies are correctly set for host triggers.
     *
     * @param array $trigger_dependencies [<triggerid_up>][<triggerid>]
     * @param array $trigger_hosts
     *
     * @throws ValidateException
     */
    public static function checkDependenciesOfHostTriggers(array $trigger_dependencies, array $trigger_hosts): void
    {
        foreach ($trigger_dependencies as $triggerid_up => $triggerids) {
            if (array_key_exists('templateids', $trigger_hosts[$triggerid_up])) {
                foreach ($triggerids as $triggerid => $foo) {
                    if (array_key_exists('hostids', $trigger_hosts[$triggerid])) {
                        $triggers = Triggers::find()->select(['triggerid', 'description', 'flags'])
                            ->where(['triggerid' => [$triggerid, $triggerid_up]])
                            ->indexBy('triggerid')
                            ->asArray()->all();

                        $error = ($triggers[$triggerid]['flags'] == PRS_FLAG_DISCOVERY_NORMAL)
                            ? t('zapi', 'Trigger "{name1}" cannot depend on the trigger "{name2}", because dependencies of host triggers on template triggers are not allowed.')
                            : t('zapi', 'Trigger prototype "{name1}" cannot depend on the trigger "{name2}", because dependencies of host triggers on template triggers are not allowed.');

                        self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', $error, [
                                'name1' => $triggers[$triggerid]['description'],
                                'name2' => $triggers[$triggerid_up]['description']
                            ]
                        ));
                    }
                }
            }
        }
    }


    /**
     * Check whether the trigger dependencies are correctly set for template triggers.
     *
     * @param array $trigger_dependencies [<triggerid_up>][<triggerid>]
     * @param array $trigger_hosts
     *
     */
    public static function checkDependenciesOfTemplateTriggers(array $trigger_dependencies, array $trigger_hosts): void
    {
        /*
         * From the given trigger dependencies we should keep only the dependencies of the template triggers. There is
         * also no need to check the dependencies on triggers from the same template, because that is considered a
         * valid case. Thus, among the triggers that the template triggers depends on, we should only keep triggers from
         * other templates and hosts.
         */
        foreach ($trigger_dependencies as $triggerid_up => $triggerids) {
            $templateids_up = array_key_exists('templateids', $trigger_hosts[$triggerid_up])
                ? $trigger_hosts[$triggerid_up]['templateids']
                : [];

            foreach ($triggerids as $triggerid => $foo) {
                /*
                 * If trigger-up and dependent trigger have at least one common template, even if they also have
                 * other templates, it is important to understand that at the moment of the trigger dependency
                 * validation all of those different templates are already linked to all child templates,
                 * so there is no need to check them again.
                 */
                if (!array_key_exists('templateids', $trigger_hosts[$triggerid])
                    || array_intersect_key($templateids_up, $trigger_hosts[$triggerid]['templateids'])) {
                    unset($trigger_dependencies[$triggerid_up][$triggerid]);
                }
            }

            if (!$trigger_dependencies[$triggerid_up]) {
                unset($trigger_dependencies[$triggerid_up]);
            }
        }

        if (!$trigger_dependencies) {
            return;
        }

        self::checkTriggersUpNotFromParentTemplates($trigger_dependencies, $trigger_hosts);
        self::checkTriggersUpNotFromChildTemplatesOrHosts($trigger_dependencies, $trigger_hosts);
        self::checkTriggersUpTemplatesAreLinkedToChildTemplates($trigger_dependencies, $trigger_hosts);
    }


    /**
     * Check that the triggers-up of the given trigger dependencies do not come from parent templates of dependent
     * triggers.
     *
     * @param array $trigger_dependencies [<triggerid_up>][<triggerid>]
     * @param array $trigger_hosts
     *
     * @throws ValidateException
     */
    private static function checkTriggersUpNotFromParentTemplates(array $trigger_dependencies, array $trigger_hosts): void
    {
        $templateids = [];
        $template_triggers_up = [];
        $dependency_templates = [];

        foreach ($trigger_dependencies as $triggerid_up => $triggerids) {
            if (!array_key_exists('templateids', $trigger_hosts[$triggerid_up])) {
                continue;
            }

            $templateids_up = [];

            foreach ($trigger_hosts[$triggerid_up]['templateids'] as $templateid => $foo) {
                $template_triggers_up[$templateid][] = $triggerid_up;
                $templateids_up[$templateid] = true;
            }

            foreach ($triggerids as $triggerid => $foo) {
                foreach ($trigger_hosts[$triggerid]['templateids'] as $templateid => $foo) {
                    if (!array_key_exists($templateid, $dependency_templates)) {
                        $dependency_templates[$templateid] = [];
                    }

                    $dependency_templates[$templateid] += $templateids_up;
                }

                $templateids += $trigger_hosts[$triggerid]['templateids'];
            }
        }

        $_templateids = $templateids;
        $template_links = [];

        do {
            $rows = (new Query())->select(['hostid', 'templateid'])
                ->from('hosts_templates')
                ->where(['hostid' => array_keys($_templateids)])
                ->all();

            $_templateids = [];

            foreach ($rows as $row) {
                $template_links[$row['hostid']][$row['templateid']] = true;

                if (!array_key_exists($row['templateid'], $template_links)) {
                    $_templateids[$row['templateid']] = true;
                }
            }
        } while ($_templateids);

        if (!$template_links) {
            return;
        }

        // Check if the trigger-up is a trigger from the parent template of the dependent trigger.
        foreach ($dependency_templates as $templateid => $templateids_up) {
            if (array_key_exists($templateid, $template_links)) {
                foreach ($templateids_up as $templateid_up => $foo) {
                    if (self::checkTemplateUpExistsInTemplateLinks($template_links, $templateid, $templateid_up)) {
                        foreach ($template_triggers_up[$templateid_up] as $triggerid_up) {
                            foreach ($trigger_dependencies[$triggerid_up] as $triggerid => $foo) {
                                if (array_key_exists($templateid, $trigger_hosts[$triggerid]['templateids'])) {
                                    break 2;
                                }
                            }
                        }

                        $triggers = Triggers::find()->select(['triggerid', 'description', 'flags'])
                            ->where(['triggerid' => [$triggerid, $triggerid_up]])
                            ->indexBy('triggerid')
                            ->asArray()->all();

                        $templates = Hosts::find()->select(['hostid', 'host'])
                            ->where(['hostid' => $templateid_up])
                            ->asArray()->all();

                        $error = ($triggers[$triggerid]['flags'] == PRS_FLAG_DISCOVERY_NORMAL)
                            ? t('zapi', 'Trigger "{name1}" cannot depend on the trigger "{name2}" from the template "{template}", because dependencies on triggers from the parent template are not allowed.')
                            : t('zapi', 'Trigger prototype "{name1}" cannot depend on the trigger "{name2}" from the template "{template}", because dependencies on triggers from the parent template are not allowed.');

                        self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', $error, [
                                'name1' => $triggers[$triggerid]['description'],
                                'name2' => $triggers[$triggerid_up]['description'],
                                'template' => $templates[0]['host']
                            ]
                        ));
                    }
                }
            }
        }
    }


    /**
     * Check that the triggers-up of the given trigger dependencies are not from the child templates or hosts of the
     * dependent triggers.
     *
     * @param array $trigger_dependencies [<triggerid_up>][<triggerid>]
     * @param array $trigger_hosts
     *
     * @throws ValidateException
     */
    private static function checkTriggersUpNotFromChildTemplatesOrHosts(array $trigger_dependencies, array $trigger_hosts): void
    {
        $templateids = [];
        $template_triggers_up = [];
        $dependency_templates = [];

        foreach ($trigger_dependencies as $triggerid_up => $triggerids) {
            $templateids_up = [];

            if (array_key_exists('templateids', $trigger_hosts[$triggerid_up])) {
                foreach ($trigger_hosts[$triggerid_up]['templateids'] as $templateid => $foo) {
                    $template_triggers_up[$templateid][] = $triggerid_up;
                    $templateids_up[$templateid] = true;
                }
            } else {
                foreach ($trigger_hosts[$triggerid_up]['hostids'] as $hostid => $foo) {
                    $template_triggers_up[$hostid][] = $triggerid_up;
                    $templateids_up[$hostid] = true;
                }
            }

            foreach ($triggerids as $triggerid => $foo) {
                foreach ($trigger_hosts[$triggerid]['templateids'] as $templateid => $foo) {
                    if (!array_key_exists($templateid, $dependency_templates)) {
                        $dependency_templates[$templateid] = [];
                    }

                    $dependency_templates[$templateid] += $templateids_up;
                }

                $templateids += $trigger_hosts[$triggerid]['templateids'];
            }
        }

        $template_links = [];

        do {
            $rows = (new Query())->select(['hostid', 'templateid'])
                ->from('hosts_templates')
                ->where(['templateid' => array_keys($templateids)])
                ->all();

            $templateids = [];

            foreach ($rows as $row) {
                $template_links[$row['templateid']][$row['hostid']] = true;

                if (!array_key_exists($row['hostid'], $template_links)) {
                    $templateids[$row['hostid']] = true;
                }
            }
        } while ($templateids);

        if (!$template_links) {
            return;
        }

        // Check if each trigger-up is a trigger from the child template or host of the dependent trigger.
        foreach ($dependency_templates as $templateid => $templateids_up) {
            if (array_key_exists($templateid, $template_links)) {
                foreach ($templateids_up as $templateid_up => $foo) {
                    if (self::checkTemplateUpExistsInTemplateLinks($template_links, $templateid, $templateid_up)) {
                        foreach ($template_triggers_up[$templateid_up] as $triggerid_up) {
                            foreach ($trigger_dependencies[$triggerid_up] as $triggerid => $foo) {
                                if (array_key_exists($templateid, $trigger_hosts[$triggerid]['templateids'])) {
                                    break 2;
                                }
                            }
                        }

                        $triggers = Triggers::find()->select(['triggerid', 'description', 'flags'])
                            ->where(['triggerid' => [$triggerid, $triggerid_up]])
                            ->indexBy('triggerid')
                            ->asArray()->all();

                        $templates = Hosts::find()->select(['hostid', 'host', 'status'])
                            ->where(['hostid' => $templateid_up])
                            ->asArray()->all();

                        if ($triggers[$triggerid]['flags'] == PRS_FLAG_DISCOVERY_NORMAL) {
                            $error = ($templates[0]['status'] == HOST_STATUS_TEMPLATE)
                                ? t('zapi', 'Trigger "{name1}" cannot depend on the trigger "{name2}" from the template "{host}", because dependencies on triggers from a child template or host are not allowed.')
                                : t('zapi', 'Trigger "{name1}" cannot depend on the trigger "{name2}" from the host "{host}", because dependencies on triggers from a child template or host are not allowed.');
                        } else {
                            $error = ($templates[0]['status'] == HOST_STATUS_TEMPLATE)
                                ? t('zapi', 'Trigger prototype "{name1}" cannot depend on the trigger "{name2}" from the template "{host}", because dependencies on triggers from a child template or host are not allowed.')
                                : t('zapi', 'Trigger prototype "{name1}" cannot depend on the trigger "{name2}" from the host "{host}", because dependencies on triggers from a child template or host are not allowed.');
                        }

                        self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', $error, [
                                'name1' => $triggers[$triggerid]['description'],
                                'name2' => $triggers[$triggerid_up]['description'],
                                'host' => $templates[0]['host']
                            ]
                        ));
                    }
                }
            }
        }
    }


    /**
     * Recursively check if the given template-up exists in the chain of the given template links.
     *
     * @param array $template_links [<templateid>][<templateid_up>]
     * @param string $templateid
     * @param string $templateid_up
     *
     * @return bool
     */
    private static function checkTemplateUpExistsInTemplateLinks(array $template_links, string $templateid, string $templateid_up): bool
    {
        if (array_key_exists($templateid_up, $template_links[$templateid])) {
            return true;
        }

        foreach ($template_links[$templateid] as $_templateid => $foo) {
            if (array_key_exists($_templateid, $template_links)
                && self::checkTemplateUpExistsInTemplateLinks($template_links, $_templateid, $templateid_up)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the triggers-up templates of the given trigger dependencies are linked to child templates of the
     * dependent triggers.
     *
     * @param array $trigger_dependencies [<triggerid_up>][<triggerid>]
     * @param array $trigger_hosts
     *
     * @throws ValidateException
     */
    private static function checkTriggersUpTemplatesAreLinkedToChildTemplates(array $trigger_dependencies, array $trigger_hosts): void
    {
        $templateids_up = [];
        $templateids = [];

        foreach ($trigger_dependencies as $triggerid_up => $triggerids) {
            if (!array_key_exists('templateids', $trigger_hosts[$triggerid_up])) {
                unset($trigger_dependencies[$triggerid_up]);
                continue;
            }

            $templateids_up += $trigger_hosts[$triggerid_up]['templateids'];

            foreach ($triggerids as $triggerid => $foo) {
                $templateids += $trigger_hosts[$triggerid]['templateids'];
            }
        }

        $rows = (new Query())->select(['hostid', 'templateid'])
            ->from('hosts_templates')
            ->where(['templateid' => array_keys($templateids)])
            ->all();

        $template_links = [];

        foreach ($rows as $row) {
            $template_links[$row['templateid']][$row['hostid']] = [];
        }

        $rows = (new Query())->select(['ht.templateid', 'ht.hostid', 'host_templateid' => 'htt.templateid'])
            ->from(['ht' => 'hosts_templates', 'htt' => 'hosts_templates'])
            ->where('ht.hostid=htt.hostid')
            ->andWhere('ht.templateid!=htt.templateid')
            ->andWhere(['ht.templateid' => array_keys($templateids)])
            ->andWhere(['htt.templateid' => array_keys($templateids_up)])
            ->all();

        foreach ($rows as $row) {
            $template_links[$row['templateid']][$row['hostid']][$row['host_templateid']] = true;
        }

        foreach ($trigger_dependencies as $triggerid_up => $triggerids) {
            /*
             * If trigger belongs to more than one template, then it is not possible to link only part of them to
             * another host or template. That means each template ID of that trigger would have the same hosts
             * in template links. And vice versa, if at least one of the trigger templates was not found in template
             * links, then the trigger is not inherited further.
             */
            $templateid_up = key($trigger_hosts[$triggerid_up]['templateids']);

            foreach ($triggerids as $triggerid => $foo) {
                $templateid = key($trigger_hosts[$triggerid]['templateids']);

                if (!array_key_exists($templateid, $template_links)) {
                    continue;
                }

                foreach ($template_links[$templateid] as $hostid => $host_templateids) {
                    if (!array_key_exists($templateid_up, $host_templateids)) {
                        $triggers = Triggers::find()->select(['triggerid', 'description', 'flags'])
                            ->where(['triggerid' => [$triggerid, $triggerid_up]])
                            ->indexBy('triggerid')
                            ->asArray()->all();

                        $hosts = Hosts::find()->select(['hostid', 'host', 'status'])
                            ->where(['hostid' => [$templateid_up, $hostid]])
                            ->indexBy('hostid')
                            ->asArray()->all();

                        if ($triggers[$triggerid]['flags'] == PRS_FLAG_DISCOVERY_NORMAL) {
                            $error = ($hosts[$hostid]['status'] == HOST_STATUS_TEMPLATE)
                                ? t('zapi', 'Trigger "{name1}" cannot depend on the trigger "{name2}", because the template "{host1}" is not linked to the template "{host2}".')
                                : t('zapi', 'Trigger "{name1}" cannot depend on the trigger "{name2}", because the template "{{host1}}" is not linked to the host "{host2}".');
                        } else {
                            $error = ($hosts[$hostid]['status'] == HOST_STATUS_TEMPLATE)
                                ? t('zapi', 'Trigger prototype "{name1}" cannot depend on the trigger "{name2}", because the template "{host1}" is not linked to the template "{host2}".')
                                : t('zapi', 'Trigger prototype "{name1}" cannot depend on the trigger "{name2}", because the template "{host1}" is not linked to the host "{host2}".');
                        }

                        self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', $error, [
                                'name1' => $triggers[$triggerid]['description'],
                                'name2' => $triggers[$triggerid_up]['description'],
                                'host1' => $hosts[$templateid_up]['host'],
                                'host2' => $hosts[$hostid]['host']
                            ]
                        ));
                    }
                }
            }
        }
    }

    /**
     * Update the trigger dependencies.
     *
     * @param array $triggers
     * @param array|null $db_triggers
     * @throws \yii\db\Exception
     */
    public static function updateDependencies(array &$triggers, array $db_triggers = null): void
    {
        $ins_trigger_deps = [];
        $del_triggerdepids = [];
        $edit_dependencies = [];

        foreach ($triggers as &$trigger) {
            if (!array_key_exists('dependencies', $trigger)) {
                continue;
            }

            $db_triggers_up = ($db_triggers !== null)
                ? array_column($db_triggers[$trigger['triggerid']]['dependencies'], null, 'triggerid')
                : [];

            foreach ($trigger['dependencies'] as &$trigger_up) {
                if (array_key_exists($trigger_up['triggerid'], $db_triggers_up)) {
                    $trigger_up['triggerdepid'] = $db_triggers_up[$trigger_up['triggerid']]['triggerdepid'];
                    unset($db_triggers_up[$trigger_up['triggerid']]);
                } else {
                    $ins_trigger_deps[] = [
                        'triggerid_down' => $trigger['triggerid'],
                        'triggerid_up' => $trigger_up['triggerid']
                    ];

                    $edit_dependencies[$trigger['triggerid']][$trigger_up['triggerid']] = true;
                }
            }
            unset($trigger_up);

            foreach ($db_triggers_up as $db_trigger_up) {
                $del_triggerdepids[] = $db_trigger_up['triggerdepid'];

                $edit_dependencies[$trigger['triggerid']][$db_trigger_up['triggerid']] = false;
            }
        }
        unset($trigger);

        if ($del_triggerdepids) {
            DB::delete('trigger_depends', ['triggerdepid' => $del_triggerdepids]);
        }

        if ($ins_trigger_deps) {
            $triggerdepids = DB::insertBatch('trigger_depends', $ins_trigger_deps);
        }

        foreach ($triggers as &$trigger) {
            if (!array_key_exists('dependencies', $trigger)) {
                continue;
            }

            foreach ($trigger['dependencies'] as &$trigger_up) {
                if (!array_key_exists('triggerdepid', $trigger_up)) {
                    $trigger_up['triggerdepid'] = array_shift($triggerdepids);
                }
            }
            unset($trigger_up);
        }
        unset($trigger);

        if ($edit_dependencies) {
            $edit_dependencies = self::getTemplatedDependencies($edit_dependencies);

            if ($edit_dependencies) {
                self::inheritDependencies($edit_dependencies);
            }
        }
    }

    /**
     * Filter for dependencies whose dependent triggers belong to templates that are further linked
     * to some templates or hosts.
     *
     * @param array $edit_dependencies [<triggerid>][<triggerid_up>]
     *
     * @return array
     */
    protected static function getTemplatedDependencies(array $edit_dependencies): array
    {
        $subQuery = (new Query())->select(null)
            ->from(['ht' => 'hosts_templates', 'h2' => 'hosts'])
            ->where('i.hostid=ht.templateid')
            ->andWhere('ht.hostid=h2.hostid')
            ->andWhere(['h2.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]]);
        $triggerids = (new Query())->select(['f.triggerid'])
            ->from(['f' => 'functions', 'i' => 'items', 'h' => 'hosts'])
            ->where('f.itemid=i.itemid')
            ->andWhere('i.hostid=h.hostid')
            ->andWhere(['f.triggerid' => array_keys($edit_dependencies)])
            ->andWhere(['h.status' => [HOST_STATUS_TEMPLATE]])
            ->andWhere(['exists', $subQuery])
            ->distinct()
            ->column();

        return array_intersect_key($edit_dependencies, array_flip($triggerids));
    }


    /**
     * Inherit the given trigger dependencies.
     *
     * @param array $edit_dependencies [<triggerid>][<triggerid_up>]
     * @param array $hostids
     */
    protected static function inheritDependencies(array $edit_dependencies, array $hostids = null): void
    {
        $all_triggerids = $edit_dependencies;
        $all_triggerids_up = [];

        foreach ($edit_dependencies as $triggerids_up) {
            $all_triggerids += $triggerids_up;
            $all_triggerids_up += $triggerids_up;
        }

        $rows = (new Query())->select(['t.templateid', 't.triggerid', 't.flags', 'i.hostid'])
            ->from(['t' => 'triggers', 'f' => 'functions', 'i' => 'items'])
            ->where('t.triggerid=f.triggerid')
            ->andWhere('f.itemid=i.itemid')
            ->andWhere(['t.templateid' => array_keys($all_triggerids)])
            ->andFilterWhere(($hostids !== null) ? ['i.hostid' => $hostids] : [])
            ->distinct()
            ->all();

        $trigger_flags = [];
        $trigger_links = [];
        $tpl_triggerids_up = [];

        foreach ($rows as $row) {
            $trigger_links[$row['templateid']][$row['hostid']] = $row['triggerid'];
            $trigger_flags[$row['triggerid']] = $row['flags'];

            if (array_key_exists($row['templateid'], $all_triggerids_up)) {
                $tpl_triggerids_up[$row['templateid']] = true;
            }
        }

        $del_triggerdepids = [];
        $_edit_dependencies = [];

        if ($tpl_triggerids_up) {
            if ($hostids === null) {
                $rows = (new Query())->select(['t.triggerid', 'td.triggerid_up', 'td.triggerdepid', 'i.hostid'])
                    ->from(['t' => 'triggers', 'td' => 'trigger_depends', 'tt' => 'triggers', 'f' => 'functions', 'i' => 'items'])
                    ->where('t.triggerid=td.triggerid_down')
                    ->andWhere('td.triggerid_up=tt.triggerid')
                    ->andWhere('tt.triggerid=f.triggerid')
                    ->andWhere('f.itemid=i.itemid')
                    ->andWhere(['t.templateid' => array_keys($edit_dependencies)])
                    ->andWhere(['tt.templateid' => array_keys($tpl_triggerids_up)])
                    ->distinct()
                    ->all();
            } else {
                $rows = (new Query())->select(['t.triggerid', 'td.triggerid_up', 'td.triggerdepid', 'ii.hostid'])
                    ->from(['t' => 'triggers', 'f' => 'functions', 'i' => 'items', 'td' => 'trigger_depends', 'tt' => 'triggers', 'ff' => 'functions', 'ii' => 'items'])
                    ->where('t.triggerid=f.triggerid')
                    ->andWhere('f.itemid=i.itemid')
                    ->andWhere('td.triggerid_up=tt.triggerid')
                    ->andWhere('tt.triggerid=ff.triggerid')
                    ->andWhere('ff.itemid=ii.itemid')
                    ->andWhere(['t.templateid' => array_keys($edit_dependencies)])
                    ->andWhere(['i.hostid' => $hostids])
                    ->andWhere(['tt.templateid' => array_keys($tpl_triggerids_up)])
                    ->distinct()
                    ->all();
            }

            $tpl_child_dependencies = [];

            foreach ($rows as $row) {
                $tpl_child_dependencies[$row['triggerid']][$row['triggerid_up']][$row['hostid']] = $row['triggerdepid'];
            }

            foreach ($edit_dependencies as $triggerid => $triggerids_up) {
                $triggerids_up = array_intersect_key($triggerids_up, $tpl_triggerids_up);

                if (!$triggerids_up) {
                    continue;
                }

                foreach ($trigger_links[$triggerid] as $hostid => $child_triggerid) {
                    $upd_child_triggerids_up = [];

                    if (array_key_exists($child_triggerid, $tpl_child_dependencies)) {
                        foreach ($tpl_child_dependencies[$child_triggerid] as $child_triggerid_up => $hostids_up) {
                            $hostid_up = key($hostids_up);
                            $triggerdepid = reset($hostids_up);

                            if (in_array($child_triggerid_up, $upd_child_triggerids_up)
                                || in_array($triggerdepid, $del_triggerdepids)) {
                                continue;
                            }

                            foreach ($triggerids_up as $triggerid_up => $add) {
                                if (array_key_exists($hostid_up, $trigger_links[$triggerid_up])
                                    && bccomp($child_triggerid_up, $trigger_links[$triggerid_up][$hostid_up]) == 0) {
                                    if ($add) {
                                        $upd_child_triggerids_up[] = $child_triggerid_up;
                                    } else {
                                        $del_triggerdepids[] = $hostids_up[$hostid_up];

                                        $_edit_dependencies[$child_triggerid][$child_triggerid_up] = false;
                                    }
                                }
                            }
                        }
                    }

                    foreach ($triggerids_up as $triggerid_up => $add) {
                        if ($add) {
                            $child_triggerid_up = $trigger_links[$triggerid_up][$hostid];

                            if (!in_array($child_triggerid_up, $upd_child_triggerids_up)) {
                                $_edit_dependencies[$child_triggerid][$child_triggerid_up] = true;
                            }
                        }
                    }
                }
            }
        }

        $host_triggerids_up = array_diff_key($all_triggerids_up, $tpl_triggerids_up);

        if ($host_triggerids_up) {
            if ($hostids === null) {
                $rows = (new Query())->select(['t.triggerid', 'td.triggerid_up', 'td.triggerdepid'])
                    ->from(['t' => 'triggers', 'td' => 'trigger_depends'])
                    ->where('t.triggerid=td.triggerid_down')
                    ->andWhere(['t.templateid' => array_keys($edit_dependencies)])
                    ->andWhere(['td.triggerid_up' => array_keys($host_triggerids_up)])
                    ->distinct()
                    ->all();
            } else {
                $rows = (new Query())->select(['t.triggerid', 'td.triggerid_up', 'td.triggerdepid'])
                    ->from(['t' => 'triggers', 'f' => 'functions', 'i' => 'items', 'td' => 'trigger_depends'])
                    ->where('t.triggerid=f.triggerid')
                    ->andWhere('f.itemid=i.itemid')
                    ->andWhere('t.triggerid=td.triggerid_down')
                    ->andWhere(['t.templateid' => array_keys($edit_dependencies)])
                    ->andWhere(['i.hostid' => $hostids])
                    ->andWhere(['td.triggerid_up' => array_keys($host_triggerids_up)])
                    ->distinct()
                    ->all();
            }

            $host_child_dependencies = [];

            foreach ($rows as $row) {
                $host_child_dependencies[$row['triggerid']][$row['triggerid_up']] = $row['triggerdepid'];
            }

            foreach ($edit_dependencies as $triggerid => $triggerids_up) {
                $triggerids_up = array_intersect_key($triggerids_up, $host_triggerids_up);

                if (!$triggerids_up) {
                    continue;
                }

                foreach ($trigger_links[$triggerid] as $hostid => $child_triggerid) {
                    $upd_child_triggerids_up = [];

                    if (array_key_exists($child_triggerid, $host_child_dependencies)) {
                        foreach ($host_child_dependencies[$child_triggerid] as $child_triggerid_up => $triggerdepid) {
                            if (array_key_exists($child_triggerid_up, $triggerids_up)) {
                                $add = $triggerids_up[$child_triggerid_up];

                                if ($add) {
                                    $upd_child_triggerids_up[] = $child_triggerid_up;
                                } else {
                                    $del_triggerdepids[] = $triggerdepid;

                                    $_edit_dependencies[$child_triggerid][$child_triggerid_up] = false;
                                }
                            }
                        }
                    }

                    foreach ($triggerids_up as $triggerid_up => $add) {
                        if ($add && !in_array($triggerid_up, $upd_child_triggerids_up)) {
                            $_edit_dependencies[$child_triggerid][$triggerid_up] = true;
                        }
                    }
                }
            }
        }

        $ins_dependencies = [];
        $del_dependencies = [];
        $ins_trigger_deps = [];

        foreach ($_edit_dependencies as $triggerid => $triggerids_up) {
            foreach ($triggerids_up as $triggerid_up => $add) {
                if ($add) {
                    if ($trigger_flags[$triggerid] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                        if (array_key_exists($triggerid_up, $trigger_flags)
                            && $trigger_flags[$triggerid_up] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                            $ins_dependencies[$triggerid_up][$triggerid] = true;
                        }
                    } else {
                        $ins_dependencies[$triggerid_up][$triggerid] = true;
                    }

                    $ins_trigger_deps[] = [
                        'triggerid_down' => $triggerid,
                        'triggerid_up' => $triggerid_up
                    ];
                } else {
                    $del_dependencies[$triggerid_up][$triggerid] = true;
                }
            }
        }

        if ($ins_dependencies) {
            self::checkCircularDependencies($ins_dependencies, $del_dependencies, true);
        }

        if ($del_triggerdepids) {
            DB::delete('trigger_depends', ['triggerdepid' => $del_triggerdepids]);
        }

        if ($ins_trigger_deps) {
            DB::insertBatch('trigger_depends', $ins_trigger_deps);
        }

        if ($_edit_dependencies) {
            $_edit_dependencies = self::getTemplatedDependencies($_edit_dependencies);

            if ($_edit_dependencies) {
                self::inheritDependencies($_edit_dependencies);
            }
        }
    }

    /**
     * Inherit the trigger dependencies of the given templates to the given hosts.
     *
     * @param array $templateids
     * @param array $hostids
     */
    public static function syncTemplateDependencies(array $templateids, array $hostids): void
    {
        $rows = (new Query())->select(['triggerid' => new Expression('DISTINCT f.triggerid'), 'td.triggerid_up'])
            ->from(['i' => 'items', 'f' => 'functions', 'td' => 'trigger_depends'])
            ->where('i.itemid=f.itemid')
            ->andWhere('f.triggerid=td.triggerid_down')
            ->andWhere(['i.hostid' => $templateids])
            ->all();

        $edit_dependencies = [];

        foreach ($rows as $row) {
            $edit_dependencies[$row['triggerid']][$row['triggerid_up']] = true;
        }

        if ($edit_dependencies) {
            $edit_dependencies = self::getTemplatedDependencies($edit_dependencies);

            if ($edit_dependencies) {
                self::inheritDependencies($edit_dependencies, $hostids);
            }
        }
    }
}