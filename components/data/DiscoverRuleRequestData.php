<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CSchedulingIntervalParser;
use app\customs\zapi\common\parsers\CSimpleIntervalParser;
use app\customs\zapi\common\parsers\CTimePeriodParser;

/**
 * Class DiscoverRuleFormRequestData
 * @package app\customs\zapi\components\data
 */
class DiscoverRuleRequestData extends RequestData
{
    public $action = 'create';

    /**
     * @return Result
     */
    public function validate(): Result
    {
        $paramsFieldName = ItemHelper::getParamFieldNameByType($this->getRequest('type', 0));
        $this->data['params'] = $this->getRequest($paramsFieldName, '');
        $delay = $this->getRequest('delay', DB::getDefault('items', 'delay'));
        $type = $this->getRequest('type', ITEM_TYPE_PERSEUS);
        $item_key = $this->getRequest('key', '');

        if (($type == ITEM_TYPE_DB_MONITOR && $item_key === PRS_DEFAULT_KEY_DB_MONITOR)
            || ($type == ITEM_TYPE_SSH && $item_key === PRS_DEFAULT_KEY_SSH)
            || ($type == ITEM_TYPE_TELNET && $item_key === PRS_DEFAULT_KEY_TELNET)) {
            return $this->error(60750003, t('zapi', 'Check the key, please. Default example was passed.'));
        }

        /*
         * "delay_flex" is a temporary field that collects flexible and scheduling intervals separated by a semicolon.
         * In the end, custom intervals together with "delay" are stored in the "delay" variable.
         */
        if ($type != ITEM_TYPE_TRAPPER && $type != ITEM_TYPE_SNMPTRAP
            && ($type != ITEM_TYPE_PERSEUS_ACTIVE || strncmp($item_key, 'mqtt.get', 8) !== 0)
            && hasRequest('delay_flex')) {
            $intervals = [];
            $simple_interval_parser = new CSimpleIntervalParser(['usermacros' => true]);
            $time_period_parser = new CTimePeriodParser(['usermacros' => true]);
            $scheduling_interval_parser = new CSchedulingIntervalParser(['usermacros' => true]);

            foreach ($this->getRequest('delay_flex') as $interval) {
                if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
                    if ($interval['delay'] === '' && $interval['period'] === '') {
                        continue;
                    }

                    if ($simple_interval_parser->parse($interval['delay']) != CParser::PARSE_SUCCESS) {
                        return $this->error(60750003, t('zapi', 'Invalid interval "{value}".', ['value' => $interval['delay']]));
                    }

                    if ($time_period_parser->parse($interval['period']) != CParser::PARSE_SUCCESS) {
                        return $this->error(60750003, t('zapi', 'Invalid interval "{value}".', ['value' => $interval['period']]));
                    }

                    $intervals[] = $interval['delay'] . '/' . $interval['period'];
                } else {
                    if ($interval['schedule'] === '') {
                        continue;
                    }

                    if ($scheduling_interval_parser->parse($interval['schedule']) != CParser::PARSE_SUCCESS) {
                        return $this->error(60750003, t('zapi', 'Invalid interval "{value}".', ['value' => $interval['schedule']]));
                    }

                    $intervals[] = $interval['schedule'];
                }
            }

            if ($intervals) {
                $delay .= ';' . implode(';', $intervals);
            }
        }

        $preprocessing = $this->getRequest('preprocessing', []);
        $preprocessing = ItemHelper::normalizeItemPreprocessingSteps($preprocessing);

        $newItem = [
            'itemid' => $this->getRequest('itemid'),
            'interfaceid' => $this->getRequest('interfaceid', 0),
            'name' => $this->getRequest('name'),
            'description' => $this->getRequest('description'),
            'key_' => $item_key,
            'hostid' => $this->getRequest('hostid'),
            'delay' => $delay,
            'status' => $this->getRequest('status', ITEM_STATUS_DISABLED),
            'type' => $this->getRequest('type'),
            'snmp_oid' => $this->getRequest('snmp_oid'),
            'trapper_hosts' => $this->getRequest('trapper_hosts', ""),
            'authtype' => $this->getRequest('authtype'),
            'username' => $this->getRequest('username'),
            'password' => $this->getRequest('password'),
            'publickey' => $this->getRequest('publickey'),
            'privatekey' => $this->getRequest('privatekey'),
            'params' => $this->getRequest('params'),
            'ipmi_sensor' => $this->getRequest('ipmi_sensor'),
            'lifetime' => $this->getRequest('lifetime')
        ];

        if ($newItem['type'] == ITEM_TYPE_HTTPAGENT) {
            $http_item = [
                'timeout' => $this->getRequest('timeout', DB::getDefault('items', 'timeout')),
                'url' => $this->getRequest('url'),
                'query_fields' => $this->getRequest('query_fields', []),
                'posts' => $this->getRequest('posts'),
                'status_codes' => $this->getRequest('status_codes', DB::getDefault('items', 'status_codes')),
                'follow_redirects' => $this->getRequest('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF),
                'post_type' => (int)$this->getRequest('post_type'),
                'http_proxy' => $this->getRequest('http_proxy'),
                'headers' => $this->getRequest('headers', []),
                'retrieve_mode' => (int)$this->getRequest('retrieve_mode'),
                'request_method' => (int)$this->getRequest('request_method'),
                'output_format' => (int)$this->getRequest('output_format'),
                'allow_traps' => (int)$this->getRequest('allow_traps', HTTPCHECK_ALLOW_TRAPS_OFF),
                'ssl_cert_file' => $this->getRequest('ssl_cert_file'),
                'ssl_key_file' => $this->getRequest('ssl_key_file'),
                'ssl_key_password' => $this->getRequest('ssl_key_password'),
                'verify_peer' => (int)$this->getRequest('verify_peer'),
                'verify_host' => (int)$this->getRequest('verify_host'),
                'authtype' => $this->getRequest('http_authtype', PRS_HTTP_AUTH_NONE),
                'username' => $this->getRequest('http_username', ''),
                'password' => $this->getRequest('http_password', '')
            ];
            $newItem = ItemHelper::prepareItemHttpAgentFormData($http_item) + $newItem;
        }

        if ($newItem['type'] == ITEM_TYPE_SCRIPT) {
            $script_item = [
                'parameters' => $this->getRequest('parameters', []),
                'timeout' => $this->getRequest('timeout', DB::getDefault('items', 'timeout'))
            ];

            $newItem = ItemHelper::prepareScriptItemFormData($script_item) + $newItem;
        }

        if ($newItem['type'] == ITEM_TYPE_JMX) {
            $newItem['jmx_endpoint'] = $this->getRequest('jmx_endpoint', '');
        }

        if ($this->getRequest('type') == ITEM_TYPE_DEPENDENT) {
            $newItem['master_itemid'] = $this->getRequest('master_itemid');
        }

        // add macros; ignore empty new macros
        $lld_rule_filter = [
            'evaltype' => $this->getRequest('evaltype'),
            'conditions' => []
        ];
        $conditions = $this->getRequest('conditions', []);
        ksort($conditions);
        $conditions = array_values($conditions);

        foreach ($conditions as $condition) {
            if ($condition['macro'] === '' && $condition['value'] === '') {
                continue;
            }

            $condition['macro'] = mb_strtoupper($condition['macro']);

            $lld_rule_filter['conditions'][] = $condition;
        }

        if ($lld_rule_filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
            // if only one or no conditions are left, reset the evaltype to and/or and clear the formula
            if (count($lld_rule_filter['conditions']) <= 1) {
                $lld_rule_filter['formula'] = '';
                $lld_rule_filter['evaltype'] = CONDITION_EVAL_TYPE_AND_OR;
            } else {
                $lld_rule_filter['formula'] = $this->getRequest('formula');
            }
        }
        $newItem['filter'] = $lld_rule_filter;

        $lld_macro_paths = $this->getRequest('lld_macro_paths', []);

        foreach ($lld_macro_paths as &$lld_macro_path) {
            $lld_macro_path['lld_macro'] = mb_strtoupper($lld_macro_path['lld_macro']);
        }
        unset($lld_macro_path);

        $newItem['lld_macro_paths'] = $lld_macro_paths;

        foreach ($newItem['lld_macro_paths'] as $i => $lld_macro_path) {
            if ($lld_macro_path['lld_macro'] === '' && $lld_macro_path['path'] === '') {
                unset($newItem['lld_macro_paths'][$i]);
            }
        }

        $overrides = $this->getRequest('overrides', []);
        $newItem['overrides'] = $overrides;

        if ($this->action == 'update') {
            $item = DiscoverRuleHelper::getDiscoverRules([
                'itemids' => $this->getRequest('itemid'),
                'output' => API_OUTPUT_EXTEND,
                'selectHosts' => ['hostid', 'name', 'status', 'flags'],
                'selectFilter' => ['formula', 'evaltype', 'conditions'],
                'selectLLDMacroPaths' => ['lld_macro', 'path'],
                'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
                'selectOverrides' => ['name', 'step', 'stop', 'filter', 'operations'],
                'editable' => true
            ]);
            $item = reset($item);
            if (!$item) {
                return $this->error(60750004);
            }

            // Unset equal values if item script type and parameters have not changed.
            $compare = function ($arr, $arr2) {
                return (array_combine(array_column($arr, 'name'), array_column($arr, 'value')) ==
                    array_combine(array_column($arr2, 'name'), array_column($arr2, 'value'))
                );
            };
            if ($newItem['type'] == ITEM_TYPE_SCRIPT && $newItem['type'] == $item['type']
                && $compare($item['parameters'], $newItem['parameters'])) {
                unset($newItem['parameters']);
            }

            if ($newItem['type'] == $item['type']) {
                $newItem = CArrayHelper::unsetEqualValues($newItem, $item, ['itemid']);
            }

            // don't update the filter if it hasn't changed
            $conditionsChanged = false;
            if (count($newItem['filter']['conditions']) != count($item['filter']['conditions'])) {
                $conditionsChanged = true;
            } else {
                $conditions = $item['filter']['conditions'];
                foreach ($newItem['filter']['conditions'] as $i => $condition) {
                    if (CArrayHelper::unsetEqualValues($condition, $conditions[$i])) {
                        $conditionsChanged = true;
                        break;
                    }
                }
            }
            $lld_rule_filter = CArrayHelper::unsetEqualValues($newItem['filter'], $item['filter']);
            if (!isset($lld_rule_filter['evaltype']) && !isset($lld_rule_filter['formula']) && !$conditionsChanged) {
                unset($newItem['filter']);
            }

            $lld_macro_paths_changed = false;

            if (count($newItem['lld_macro_paths']) != count($item['lld_macro_paths'])) {
                $lld_macro_paths_changed = true;
            } else {
                $lld_macro_paths = array_values($item['lld_macro_paths']);
                $newItem['lld_macro_paths'] = array_values($newItem['lld_macro_paths']);

                foreach ($newItem['lld_macro_paths'] as $i => $lld_macro_path) {
                    if (CArrayHelper::unsetEqualValues($lld_macro_path, $lld_macro_paths[$i])) {
                        $lld_macro_paths_changed = true;
                        break;
                    }
                }
            }

            if (!$lld_macro_paths_changed) {
                unset($newItem['lld_macro_paths']);
            }

            if ($item['preprocessing'] !== $preprocessing) {
                $newItem['preprocessing'] = $preprocessing;
            }
            return $this->success($newItem);
        } else {
            if (!$newItem['lld_macro_paths']) {
                unset($newItem['lld_macro_paths']);
            }

            if ($preprocessing) {
                $newItem['preprocessing'] = $preprocessing;
            }
            return $this->success($newItem);
        }
    }
}
