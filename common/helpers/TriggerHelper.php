<?php

namespace app\customs\zapi\common\helpers;

use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\customs\zapi\common\macros\CMacrosResolverHelper;
use app\customs\zapi\common\parsers\CExpressionParser;
use app\customs\zapi\common\parsers\results\CExpressionParserResult;
use app\customs\zapi\models\search\trigger\TriggerPrototypeSearch;
use app\customs\zapi\models\search\trigger\TriggerSearch;
use app\customs\zapi\services\assist\TriggerAssist;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Triggers;
use yii\db\Exception;
use yii\db\Query;

class TriggerHelper
{
    /**
     * @param array $data
     * @param bool $formRefresh
     * @return array
     * @throws Exception
     */
    public static function getTriggerFormData(array $data, bool $formRefresh = false): array
    {
        if ($data['triggerid'] !== null) {
            // Get trigger.
            $options = [
                'output' => API_OUTPUT_EXTEND,
                'selectHosts' => ['hostid'],
                'triggerids' => $data['triggerid']
            ];

            if (!$formRefresh) {
                $options['selectTags'] = ['tag', 'value'];
            }

            if ($data['show_inherited_tags']) {
                $options['selectItems'] = ['itemid', 'templateid', 'flags'];
            }
            if ($data['parent_discoveryid'] === null) {
                $options['selectDiscoveryRule'] = ['itemid', 'name', 'templateid'];
                $options['selectTriggerDiscovery'] = ['parent_triggerid'];
                $triggers = static::getTriggers($options);
                $flag = PRS_FLAG_DISCOVERY_NORMAL;
            } else {
                $triggers = static::getTriggerPrototypes($options);
                $flag = PRS_FLAG_DISCOVERY_PROTOTYPE;
            }
            if (empty($triggers)) {
                return [];
            }
            $triggers = CMacrosResolverHelper::resolveTriggerExpressions($triggers,
                ['sources' => ['expression', 'recovery_expression']]
            );

            $trigger = reset($triggers);

            if (!$formRefresh) {
                $data['tags'] = $trigger['tags'];
            }

            // Get templates.
            $data['templates'] = self::getTriggerParentTemplates([$trigger], $flag);

            if ($data['show_inherited_tags']) {
                if ($data['parent_discoveryid'] === null) {
                    if ($trigger['discoveryRule']) {
                        $item_parent_templates = ItemHelper::getItemParentTemplates([$trigger['discoveryRule']],
                            PRS_FLAG_DISCOVERY_RULE
                        )['templates'];
                    }
                    else {
                        $item_parent_templates = ItemHelper::getItemParentTemplates($trigger['items'],
                            PRS_FLAG_DISCOVERY_NORMAL
                        )['templates'];
                    }
                }
                else {
                    $items = [];
                    $item_prototypes = [];

                    foreach ($trigger['items'] as $item) {
                        if ($item['flags'] == PRS_FLAG_DISCOVERY_NORMAL) {
                            $items[] = $item;
                        }
                        else {
                            $item_prototypes[] = $item;
                        }
                    }

                    $item_parent_templates = ItemHelper::getItemParentTemplates($items, PRS_FLAG_DISCOVERY_NORMAL)['templates']
                     + ItemHelper::getItemParentTemplates($item_prototypes, PRS_FLAG_DISCOVERY_PROTOTYPE)['templates'];
                }
                unset($item_parent_templates[0]);

                $templateTags = HostHelper::getTags(array_keys($item_parent_templates));

                $inherited_tags = [];

                foreach ($item_parent_templates as $templateid => $template) {
                    foreach ($templateTags[$templateid] as $tag) {
                        if (array_key_exists($tag['tag'], $inherited_tags)
                            && array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
                            $inherited_tags[$tag['tag']][$tag['value']]['parent_templates'] += [
                                $templateid => $template
                            ];
                        }
                        else {
                            $inherited_tags[$tag['tag']][$tag['value']] = $tag + [
                                'parent_templates' => [$templateid => $template],
                                'type' => PRS_PROPERTY_INHERITED
                            ];
                        }
                    }
                }

                $hostTags = HostHelper::getTags($data['hostid']);

                if ($hostTags) {
                    $hostTag = current($hostTags);
                    foreach ($hostTag as $tag) {
                        $inherited_tags[$tag['tag']][$tag['value']] = $tag;
                        $inherited_tags[$tag['tag']][$tag['value']]['type'] = PRS_PROPERTY_INHERITED;
                    }
                }

                foreach ($data['tags'] as $tag) {
                    if (array_key_exists($tag['tag'], $inherited_tags)
                        && array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
                        $inherited_tags[$tag['tag']][$tag['value']]['type'] = PRS_PROPERTY_BOTH;
                    }
                    else {
                        $inherited_tags[$tag['tag']][$tag['value']] = $tag + ['type' => PRS_PROPERTY_OWN];
                    }
                }
                $data['tags'] = [];

                foreach ($inherited_tags as $tag) {
                    foreach ($tag as $value) {
                        $data['tags'][] = $value;
                    }
                }
            }

            $data['limited'] = ($trigger['templateid'] != 0);

            // Select first host from triggers if no matching value is given.
            $hosts = $trigger['hosts'];
            if (count($hosts) > 0 && !in_array(['hostid' => $data['hostid']], $hosts)) {
                $host = reset($hosts);
                $data['hostid'] = $host['hostid'];
            }
        }

        // tags
        if (!$data['tags']) {
            $data['tags'][] = ['tag' => '', 'value' => ''];
        }
        else {
            ArrayHelper::multisort($data['tags'], ['tag', 'value']);
        }

        if ((!empty($data['triggerid']) && !isset($_REQUEST['form_refresh'])) || $data['limited']) {
            $data['expression'] = $trigger['expression'];
            $data['recovery_expression'] = $trigger['recovery_expression'];

            if (!$data['limited'] || !isset($_REQUEST['form_refresh'])) {
                $data['description'] = $trigger['description'];
                $data['event_name'] = $trigger['event_name'];
                $data['opdata'] = $trigger['opdata'];
                $data['type'] = $trigger['type'];
                $data['recovery_mode'] = $trigger['recovery_mode'];
                $data['correlation_mode'] = $trigger['correlation_mode'];
                $data['correlation_tag'] = $trigger['correlation_tag'];
                $data['manual_close'] = $trigger['manual_close'];
                $data['priority'] = $trigger['priority'];
                $data['status'] = $trigger['status'];
                $data['comments'] = $trigger['comments'];
                $data['url_name'] = $trigger['url_name'];
                $data['url'] = $trigger['url'];

                if ($data['parent_discoveryid'] !== null) {
                    $data['discover'] = $trigger['discover'];
                }

                $db_triggers = (new Query())->select(['t.triggerid', 't.description'])
                    ->from(['t' => 'triggers', 'd' => 'trigger_depends'])
                    ->where('t.triggerid=d.triggerid_up')
                    ->andWhere(['d.triggerid_down' => $data['triggerid']])
                    ->all();
                foreach ($db_triggers as $db_trigger) {
                    if (uint_in_array($db_trigger['triggerid'], $data['dependencies'])) {
                        continue;
                    }
                    array_push($data['dependencies'], $db_trigger['triggerid']);
                }
            }
        }

        $readonly = false;
        if ($data['triggerid'] !== null) {
            $data['flags'] = $trigger['flags'];

            if ($data['parent_discoveryid'] === null) {
                $data['discoveryRule'] = $trigger['discoveryRule'];
                $data['triggerDiscovery'] = $trigger['triggerDiscovery'];
            }

            if ($trigger['flags'] == PRS_FLAG_DISCOVERY_CREATED || $data['limited']) {
                $readonly = true;
            }
        }
        $data['readonly'] = (int)$readonly;

        if ($data['dependencies']) {
            $dependencyTriggers = static::getTriggers([
                'output' => ['triggerid', 'description', 'flags'],
                'selectHosts' => ['hostid', 'name'],
                'triggerids' => $data['dependencies'],
                'preservekeys' => true
            ]);

            if ($data['parent_discoveryid']) {
                $dependencyTriggerPrototypes = self::getTriggerPrototypes([
                    'output' => ['triggerid', 'description', 'flags'],
                    'selectHosts' => ['hostid', 'name'],
                    'triggerids' => $data['dependencies'],
                    'preservekeys' => true
                ]);
                $data['db_dependencies'] = $dependencyTriggers + $dependencyTriggerPrototypes;
            }
            else {
                $data['db_dependencies'] = $dependencyTriggers;
            }
        }

        foreach ($data['db_dependencies'] as &$dependency) {
            order_result($dependency['hosts'], 'name', PRS_SORT_UP);
        }
        unset($dependency);

        order_result($data['db_dependencies'], 'description');

        return $data;
    }

    /**
     * 获取触发器
     * @param array $options
     * @return array
     * @throws Exception
     */
    public static function getTriggers(array $options): array
    {
        $search = new TriggerSearch();
        $search->is_all = true;
        $provider = $search->search($options);
        return $provider->getModels();
    }

    /**
     * 获取触发器类型
     * @param array $options
     * @return array
     * @throws Exception
     */
    public static function getTriggerPrototypes(array $options): array
    {
        $search = new TriggerPrototypeSearch();
        $search->is_all = true;
        $provider = $search->search($options);
        return $provider->getModels();
    }

    /**
     * Get parent templates for each given trigger.
     *
     * @param $array $triggers                  An array of triggers.
     * @param string $triggers []['triggerid']   ID of a trigger.
     * @param string $triggers []['templateid']  ID of parent template trigger.
     * @param int $flag Origin of the trigger (PRS_FLAG_DISCOVERY_NORMAL or
     *                                          PRS_FLAG_DISCOVERY_PROTOTYPE).
     *
     * @return array
     * @throws Exception
     */
    public static function getTriggerParentTemplates(array $triggers, $flag)
    {
        $parent_triggerids = [];
        $data = [
            'links' => [],
            'templates' => []
        ];

        foreach ($triggers as $trigger) {
            if ($trigger['templateid'] != 0) {
                $parent_triggerids[$trigger['templateid']] = true;
                $data['links'][$trigger['triggerid']] = ['triggerid' => $trigger['templateid']];
            }
        }

        if (!$parent_triggerids) {
            return $data;
        }

        $all_parent_triggerids = [];
        $hostids = [];
        if ($flag == PRS_FLAG_DISCOVERY_PROTOTYPE) {
            $lld_ruleids = [];
        }

        do {
            if ($flag == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                $db_triggers = TriggerHelper::getTriggerPrototypes([
                    'output' => ['triggerid', 'templateid'],
                    'selectHosts' => ['hostid'],
                    'selectDiscoveryRule' => ['itemid'],
                    'triggerids' => array_keys($parent_triggerids)
                ]);
            } // PRS_FLAG_DISCOVERY_NORMAL
            else {
                $db_triggers = TriggerHelper::getTriggers([
                    'output' => ['triggerid', 'templateid'],
                    'selectHosts' => ['hostid'],
                    'triggerids' => array_keys($parent_triggerids)
                ]);
            }
            $all_parent_triggerids += $parent_triggerids;
            $parent_triggerids = [];

            foreach ($db_triggers as $db_trigger) {
                foreach ($db_trigger['hosts'] as $host) {
                    $data['templates'][$host['hostid']] = [];
                    $hostids[$db_trigger['triggerid']][] = $host['hostid'];
                }

                if ($flag == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                    $lld_ruleids[$db_trigger['triggerid']] = $db_trigger['discoveryRule']['itemid'];
                }

                if ($db_trigger['templateid'] != 0) {
                    if (!array_key_exists($db_trigger['templateid'], $all_parent_triggerids)) {
                        $parent_triggerids[$db_trigger['templateid']] = true;
                    }

                    $data['links'][$db_trigger['triggerid']] = ['triggerid' => $db_trigger['templateid']];
                }
            }
        } while ($parent_triggerids);

        foreach ($data['links'] as &$parent_trigger) {
            $parent_trigger['hostids'] = array_key_exists($parent_trigger['triggerid'], $hostids)
            ? $hostids[$parent_trigger['triggerid']]
            : [0];

            if ($flag == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                $parent_trigger['lld_ruleid'] = array_key_exists($parent_trigger['triggerid'], $lld_ruleids)
                ? $lld_ruleids[$parent_trigger['triggerid']]
                : 0;
            }
        }
        unset($parent_trigger);

        $db_templates = [];
        if ($data['templates']) {
            $db_templates = Hosts::find()->select(['hostid', 'name'])
                ->where(['hostid' => array_keys($data['templates'])])
                ->indexBy('hostid')
                ->asArray()->all();
        }
        $rw_templates = [];
        if ($db_templates) {
            $rw_templates = Hosts::find()->select(['hostid'])
                ->where(['hostid' => array_keys($db_templates)])
                ->indexBy('hostid')
                ->asArray()->all();
        }

        $data['templates'][0] = [];

        foreach ($data['templates'] as $hostid => &$template) {
            $template = array_key_exists($hostid, $db_templates)
            ? [
                'hostid' => $hostid,
                'name' => $db_templates[$hostid]['name'],
                'permission' => array_key_exists($hostid, $rw_templates) ? PERM_READ_WRITE : PERM_READ
            ]
            : [
                'hostid' => $hostid,
                'name' => t('zapi', 'Inaccessible template'),
                'permission' => PERM_DENY
            ];
        }
        unset($template);

        return $data;
    }

    /**
     * Quoting $param if it contains special characters.
     *
     * @param string $param
     * @param bool $forced
     *
     * @return string
     */
    public static function quoteFunctionParam($param, $forced = false)
    {
        if (!$forced) {
            if (!isset($param[0]) || ($param[0] != '"' && false === strpbrk($param, ',)'))) {
                return $param;
            }
        }

        return '"' . str_replace('"', '\\"', $param) . '"';
    }

    /**
     * 触发器表达式函数
     * @return array
     */
    public static function functionTypes(): array
    {
        return [
            PRS_FUNCTION_TYPE_AGGREGATE => t('zapi', 'Aggregate functions'),
            PRS_FUNCTION_TYPE_BITWISE => t('zapi', 'Bitwise functions'),
            PRS_FUNCTION_TYPE_DATE_TIME => t('zapi', 'Date and time functions'),
            PRS_FUNCTION_TYPE_HISTORY => t('zapi', 'History functions'),
            PRS_FUNCTION_TYPE_MATH => t('zapi', 'Mathematical functions'),
            PRS_FUNCTION_TYPE_OPERATOR => t('zapi', 'Operator functions'),
            PRS_FUNCTION_TYPE_PREDICTION => t('zapi', 'Prediction functions'),
            PRS_FUNCTION_TYPE_STRING => t('zapi', 'String functions')
        ];
    }

    /**
     * Copy the given triggers to the target hosts or templates, taking care of copied trigger dependencies.
     *
     * If the $src_hostid parameter is passed, the given host will be replaced with the destination host.
     * Without $src_hostid, only triggers that belong to a single host or template can be copied.
     *
     * If a trigger is copied alongside with the trigger which it depends on, then dependencies are replaced directly,
     * using new IDs.
     * If the source trigger depends on the trigger from the same host or template, the same trigger-up should exist on the
     * target host or template.
     *
     * @param array       $dst_hostids     Hosts and templates to copy triggers to.
     *                                     IDs not present in the database will be ignored.
     * @param string|null $src_hostid      ID of host to use as context for trigger when multiple hosts are involved.
     * @param array|null  $src_triggerids  Triggers which will be copied to destination host(s).
     *
     * @return Result
     */
    public static function copyTriggersToHosts(array $dst_hostids, ?string $src_hostid, array $src_triggerids = null): Result
    {
        $dst_templates = TemplateHelper::getTemplates([
            'output' => ['host'],
            'templateids' => $dst_hostids,
            'editable' => true,
            'preservekeys' => true
        ]);

        $_dst_hostids = array_diff($dst_hostids, array_keys($dst_templates));

        $dst_hosts = $_dst_hostids
        ? HostHelper::getHosts([
            'output' => ['host', 'status'],
            'hostids' => $_dst_hostids,
            'editable' => true,
            'preservekeys' => true
        ])
        : [];

        $dst_hosts = $dst_templates + $dst_hosts;

        if (!$dst_hosts || count($dst_hosts) != count($dst_hostids)) {
            return Result::instance()->setErrcode(10000404)->setData(debug_backtrace());
        }

        if ($src_hostid) {
            $src_hosts = TemplateHelper::getTemplates([
                'output' => ['host'],
                'templateids' => $src_hostid
            ]);

            $src_hosts = $src_hosts
            ? $src_hosts
            : HostHelper::getHosts([
                'output' => ['host'],
                'hostids' => $src_hostid
            ]);

            if (!$src_hosts) {
                return Result::instance()
                    ->setErrcode(10000404)
                    ->setData(debug_backtrace());
            }

            $src_host = $src_hosts[0]['host'];
        }

        $options = [
            'output' => ['triggerid', 'expression', 'description', 'url_name', 'url', 'status', 'priority', 'comments', 'type',
                'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag', 'manual_close', 'opdata',
                'event_name'
            ],
            'selectDependencies' => ['triggerid'],
            'selectTags' => ['tag', 'value'],
            'preservekeys' => true
        ];

        if (!$src_hostid) {
            $options += ['selectHosts' => ['hostid', 'host']];
        }

        if ($src_triggerids) {
            $options += ['triggerids' => $src_triggerids];
        } else {
            $options += [
                'hostids' => $src_hostid,
                'inherited' => false,
                'filter' => ['flags' => PRS_FLAG_DISCOVERY_NORMAL]
            ];
        }

        $src_triggers = self::getTriggers($options);

        if ($src_triggerids) {
            if (count($src_triggers) != count($src_triggerids)) {
                return Result::instance()->setErrcode(10000404)->setData(debug_backtrace());
            }
        } else {
            if (!$src_triggers) {
                return Result::instance()->setSuccess();
            }
        }

        $src_triggers = CMacrosResolverHelper::resolveTriggerExpressions($src_triggers,
            ['sources' => ['expression', 'recovery_expression']]
        );

        if (!$src_hostid) {
            foreach ($src_triggers as $src_trigger) {
                if (count($src_trigger['hosts']) > 1) {
                    $error = (t('zapi', 'Cannot copy trigger "{trigger}", because it has multiple hosts in the expression.', [
                        'trigger' => $src_trigger['description']
                    ]));
                    return Result::instance()->setErrcode(60750301)->setErrmsg($error);
                }
            }
        }

        $dst_triggers = [];
        $trigger_links = [];
        $i = 0;

        foreach ($dst_hosts as $dst_hostid => $dst_host) {
            foreach ($src_triggers as $src_triggerid => $src_trigger) {
                $dst_trigger = array_intersect_key($src_trigger, array_flip(['expression', 'description', 'url_name', 'url',
                    'status', 'priority', 'comments', 'type', 'recovery_mode', 'recovery_expression', 'correlation_mode',
                    'correlation_tag', 'manual_close', 'opdata', 'event_name', 'tags'
                ]));

                $_src_host = $src_hostid ? $src_host : $src_trigger['hosts'][0]['host'];

                $dst_trigger['expression'] =
                self::triggerExpressionReplaceHost($src_trigger['expression'], $_src_host, $dst_host['host']);

                if ($src_trigger['recovery_mode'] == PRS_RECOVERY_MODE_RECOVERY_EXPRESSION) {
                    $dst_trigger['recovery_expression'] =
                    self::triggerExpressionReplaceHost($src_trigger['recovery_expression'], $_src_host, $dst_host['host']);
                }

                $dst_triggers[] = $dst_trigger;
                $trigger_links[$src_triggerid][$dst_hostid] = $i;

                $i++;
            }
        }

        $result = TriggerAssist::instance()->create($dst_triggers);

        if (!$result->isSuccess()) {
            return $result;
        }
        $data = $result->getData();

        $dst_triggerids = $data['triggerids'];

        $dst_triggers = [];
        $src_triggerids_up = [];

        foreach ($trigger_links as $src_triggerid => $links) {
            foreach ($links as $dst_hostid => $i) {
                if (!$src_triggers[$src_triggerid]['dependencies']) {
                    continue;
                }

                $dst_triggers[$i] = ['triggerid' => $dst_triggerids[$i]];

                foreach ($src_triggers[$src_triggerid]['dependencies'] as $src_trigger_up) {
                    if (array_key_exists($src_trigger_up['triggerid'], $trigger_links)) {
                        $dst_triggers[$i]['dependencies'][] = [
                            'triggerid' => $dst_triggerids[$trigger_links[$src_trigger_up['triggerid']][$dst_hostid]]
                        ];
                    } elseif ($src_triggerids) {
                        $src_triggerids_up[$src_trigger_up['triggerid']] = true;
                    } else {
                        $dst_triggers[$i]['dependencies'][] = ['triggerid' => $src_trigger_up['triggerid']];
                    }
                }
            }
        }

        if ($src_triggerids_up) {
            $src_triggers_up = self::getTriggers([
                'output' => ['description', 'expression', 'recovery_mode', 'recovery_expression'],
                'selectHosts' => ['hostid'],
                'triggerids' => array_keys($src_triggerids_up),
                'preservekeys' => true
            ]);

            $src_triggers_up = CMacrosResolverHelper::resolveTriggerExpressions($src_triggers_up,
                ['sources' => ['expression', 'recovery_expression']]
            );

            $src_host_dependencies = [];

            foreach ($trigger_links as $src_triggerid => $links) {
                $_src_hostid = $src_hostid ? $src_hostid : $src_triggers[$src_triggerid]['hosts'][0]['hostid'];

                foreach ($links as $dst_hostid => $i) {
                    foreach ($src_triggers[$src_triggerid]['dependencies'] as $src_trigger_up) {
                        if (!array_key_exists($src_trigger_up['triggerid'], $src_triggers_up)) {
                            continue;
                        }

                        $src_hostids_up = array_column($src_triggers_up[$src_trigger_up['triggerid']]['hosts'], 'hostid');

                        if (in_array($_src_hostid, $src_hostids_up)) {
                            $src_host_dependencies[$src_trigger_up['triggerid']][$src_triggerid] = true;
                        } else {
                            $dst_triggers[$i]['dependencies'][] = ['triggerid' => $src_trigger_up['triggerid']];
                        }
                    }
                }
            }

            if ($src_host_dependencies) {
                $descriptions = array_unique(array_column(array_intersect_key($src_triggers_up, $src_host_dependencies),
                    'description'
                ));

                $dst_host_triggers = self::getTriggers([
                    'output' => ['triggerid', 'description', 'expression', 'recovery_expression'],
                    'selectHosts' => ['hostid'],
                    'hostids' => array_keys($dst_hosts),
                    'filter' => ['description' => $descriptions],
                    'preservekeys' => true
                ]);

                if (!$dst_host_triggers) {
                    $src_triggerid_up = key($src_host_dependencies);
                    $src_triggerid = key($src_host_dependencies[$src_triggerid_up]);
                    $dst_hostid = key($trigger_links[$src_triggerid]);

                    $error = array_key_exists('status', $dst_hosts[$dst_hostid])
                    ? ('Trigger "{dst_trigger}" cannot depend on the non-existent trigger "{src_trigger}" on the host "{host}".')
                    : ('Trigger "{dst_trigger}" cannot depend on the non-existent trigger "{src_trigger}" on the template "{host}".');

                    $error = t('zapi', $error, [
                        'dst_trigger' => $src_triggers[$src_triggerid]['description'],
                        'src_trigger' => $src_triggers_up[$src_triggerid_up]['description'],
                        'host' => $dst_hosts[$dst_hostid]['host']
                    ]);

                    return Result::instance()->setErrcode(60750301)->setErrmsg($error);
                }

                $dst_host_triggers = CMacrosResolverHelper::resolveTriggerExpressions($dst_host_triggers,
                    ['sources' => ['expression', 'recovery_expression']]
                );

                $dst_host_triggerids = [];

                foreach ($dst_host_triggers as $i => $trigger) {
                    $description = $trigger['description'];
                    $expression = $trigger['expression'];
                    $recovery_expression = $trigger['recovery_expression'];

                    if ($src_hostid) {
                        foreach ($trigger['hosts'] as $host) {
                            if (array_key_exists($host['hostid'], $dst_hosts)) {
                                $dst_host_triggerids[$host['hostid']][$description][$expression][$recovery_expression] =
                                    $trigger['triggerid'];
                            }
                        }
                    } else {
                        $dst_hostid = $trigger['hosts'][0]['hostid'];

                        $dst_host_triggerids[$dst_hostid][$description][$expression][$recovery_expression] =
                            $trigger['triggerid'];
                    }
                }

                foreach ($src_host_dependencies as $src_triggerid_up => $src_triggerids) {
                    foreach ($src_triggerids as $src_triggerid => $foo) {
                        foreach ($trigger_links[$src_triggerid] as $dst_hostid => $i) {
                            $src_trigger_up = $src_triggers_up[$src_triggerid_up];
                            $_src_host = $src_hostid ? $src_host : $src_trigger['hosts'][0]['host'];
                            $dst_host = $dst_hosts[$dst_hostid]['host'];

                            $description = $src_trigger_up['description'];
                            $expression =
                                triggerExpressionReplaceHost($src_trigger_up['expression'], $_src_host, $dst_host);
                            $recovery_expression =
                                triggerExpressionReplaceHost($src_trigger_up['recovery_expression'], $_src_host, $dst_host);

                            if (array_key_exists($dst_hostid, $dst_host_triggerids)
                                && array_key_exists($description, $dst_host_triggerids[$dst_hostid])
                                && array_key_exists($expression, $dst_host_triggerids[$dst_hostid][$description])
                                && array_key_exists($recovery_expression, $dst_host_triggerids[$dst_hostid][$description][$expression])) {
                                $dst_triggerid_up =
                                    $dst_host_triggerids[$dst_hostid][$description][$expression][$recovery_expression];

                                $dst_triggers[$i]['dependencies'][] = ['triggerid' => $dst_triggerid_up];
                            } else {
                                $error = array_key_exists('status', $dst_hosts[$dst_hostid])
                                ? ('Trigger "{dst_trigger}" cannot depend on the non-existent trigger "{src_trigger}" on the host "{host}".')
                                : ('Trigger "{dst_trigger}" cannot depend on the non-existent trigger "{src_trigger}" on the template "{host}".');

                                $error = t('zapi', $error, [
                                    'dst_trigger' => $description,
                                    'src_trigger' => $src_trigger_up['description'],
                                    'host' => $dst_host
                                ]);
                                return Result::instance()->setErrcode(60750301)->setErrmsg($error);
                            }
                        }
                    }
                }
            }
        }

        if ($dst_triggers) {
            $result = TriggerAssist::instance()->update(array_values($dst_triggers));
            return $result;
        }

        return Result::instance()->setSuccess();
    }

    /**
     * Purpose: Replaces host in trigger expression.
     * nodata(/localhost/agent.ping, 5m)  =>  nodata(/localhost6/agent.ping, 5m)
     *
     * @param string $expression    full expression with host names and item keys
     * @param string $src_host
     * @param string $dst_host
     *
     * @return string
     */
    public static function triggerExpressionReplaceHost(string $expression, string $src_host, string $dst_host): string
    {
        $expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);

        if ($expression_parser->parse($expression) == CExpressionParser::PARSE_SUCCESS) {
            $hist_functions = $expression_parser->getResult()->getTokensOfTypes(
                [CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
            );
            $hist_function = end($hist_functions);
            do {
                $query_parameter = $hist_function['data']['parameters'][0];
                if ($query_parameter['data']['host'] === $src_host) {
                    $expression = substr_replace($expression, '/'.$dst_host.'/'.$query_parameter['data']['item'],
                        $query_parameter['pos'], $query_parameter['length']
                    );
                }
            } while ($hist_function = prev($hist_functions));
        }

        return $expression;
    }
}
