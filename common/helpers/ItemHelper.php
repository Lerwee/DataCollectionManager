<?php

namespace app\customs\zapi\common\helpers;

use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CSchedulingIntervalParser;
use app\customs\zapi\common\parsers\CSimpleIntervalParser;
use app\customs\zapi\common\parsers\CTimePeriodParser;
use app\customs\zapi\common\parsers\CUpdateIntervalParser;
use app\customs\zapi\models\search\item\ItemPrototypeSearch;
use app\customs\zapi\models\search\item\ItemSearch;
use app\customs\zapi\services\assist\ItemTestAssist;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Interfaces;
use app\modules\libzbx\models\Items;
use app\modules\libzbx\models\zbx\Valuemap;
use yii\db\Exception;
use app\customs\zapi\services\assist\ItemAssist;

/**
 * Class ItemHelper
 * @package app\customs\zapi\common\helpers
 */
class ItemHelper
{
    /**
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function getItemFormData(array $data): array
    {
        $items = static::getItems([
            'output' => ['itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
                'value_type', 'trapper_hosts', 'units', 'logtimefmt', 'templateid', 'valuemapid', 'params',
                'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'flags', 'interfaceid',
                'description', 'inventory_link', 'lifetime', 'jmx_endpoint', 'master_itemid', 'url', 'query_fields',
                'parameters', 'timeout', 'posts', 'status_codes', 'follow_redirects', 'post_type', 'http_proxy',
                'headers', 'retrieve_mode', 'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file',
                'ssl_key_password', 'verify_peer', 'verify_host', 'allow_traps'
            ],
            'selectHosts' => ['status', 'name', 'flags'],
            'selectDiscoveryRule' => ['itemid', 'name', 'templateid'],
            'selectItemDiscovery' => ['parent_itemid'],
            'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
            'selectTags' => ['tag', 'value'],
            'itemids' => $data['itemid']
        ]);
        if (empty($items)) {
            return [];
        }
        $item = reset($items);

        $host = current($item['hosts'] ?? []);
        $item['host_status'] = $host ? $host['status'] : -1;
        $i = 0;
        foreach ($item['preprocessing'] as &$step) {
            if ($step['type'] == PRS_PREPROC_SCRIPT) {
                $step['params'] = [$step['params'], ''];
            } else {
                $step['params'] = explode("\n", $step['params']);
            }
            $step['sortorder'] = $i++;
        }
        unset($step);

        if ($item['type'] != ITEM_TYPE_JMX) {
            $item['jmx_endpoint'] = PRS_DEFAULT_JMX_ENDPOINT;
        }
        if ($item['type'] == ITEM_TYPE_DEPENDENT) {
            // Unset master item if submitted form has no master_itemid set.
            if ($item['master_itemid']) {
                $master_item_options = [
                    'output' => ['itemid', 'type', 'hostid', 'name', 'key_'],
                    'itemids' => $item['master_itemid'],
                    'webitems' => true
                ];
                $master_items = self::getItems($master_item_options);
                if ($master_items) {
                    $item['master_item'] = reset($master_items);
                }
            }
        }
        $item['parent_discoveryid'] = 0;
        return self::formatFormData($item, $data);
    }

    /**
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function getItemPrototypeFormData(array $data): array
    {
        $itemPrototype = static::getItemPrototypes([
            'itemids' => $data['itemid'],
            'output' => [
                'itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
                'value_type', 'trapper_hosts', 'units', 'logtimefmt', 'templateid', 'valuemapid', 'params',
                'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'interfaceid',
                'description', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'parameters', 'posts',
                'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode',
                'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer',
                'verify_host', 'allow_traps', 'discover'
            ],
            'selectDiscoveryRule' => ['itemid', 'templateid'],
            'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
            'selectTags' => ['tag', 'value']
        ]);
        if (empty($itemPrototype)) {
            return [];
        }
        $itemPrototype = reset($itemPrototype);
        $i = 0;
        foreach ($itemPrototype['preprocessing'] as &$step) {
            if ($step['type'] == PRS_PREPROC_SCRIPT) {
                $step['params'] = [$step['params'], ''];
            } else {
                $step['params'] = explode("\n", $step['params']);
            }
            $step['sortorder'] = $i++;
        }
        unset($step);

        if ($itemPrototype['type'] != ITEM_TYPE_JMX) {
            $itemPrototype['jmx_endpoint'] = PRS_DEFAULT_JMX_ENDPOINT;
        }

        if ($itemPrototype['type'] == ITEM_TYPE_DEPENDENT) {
            $master_prototypes = self::getItems([
                    'output' => ['itemid', 'hostid', 'name', 'key_'],
                    'itemids' => [$itemPrototype['master_itemid']],
                    'hostids' => [$itemPrototype['hostid']],
                    'webitems' => true
                ])
                + self::getItemPrototypes([
                    'output' => ['itemid', 'hostid', 'name', 'key_'],
                    'itemids' => [$itemPrototype['master_itemid']],
                ]);

            if ($master_prototypes) {
                $itemPrototype['master_item'] = reset($master_prototypes);
            }
        }
        return self::formatFormData($itemPrototype, $data);
    }

    public static function formatFormData(array $item, array $data): array
    {
        // Unset empty and inherited tags.
        foreach ($data['tags'] as $key => $tag) {
            if ($tag['tag'] === '' && $tag['value'] === '') {
                unset($data['tags'][$key]);
            } elseif (array_key_exists('type', $tag) && !($tag['type'] & PRS_PROPERTY_OWN)) {
                unset($data['tags'][$key]);
            } else {
                unset($data['tags'][$key]['type']);
            }
        }

        if (isset($item['discover'])) {
            $data['discover'] = $item['discover'];
        }

        if ($data['type'] == ITEM_TYPE_HTTPAGENT) {
            foreach (['query_fields', 'headers'] as $property) {
                $values = [];

                if (is_array($data[$property]) && array_key_exists('name', $data[$property])
                    && array_key_exists('value', $data[$property])) {
                    foreach ($data[$property]['name'] as $index => $key) {
                        if (array_key_exists($index, $data[$property]['value'])) {
                            $sortorder = $data[$property]['sortorder'][$index];
                            $values[$sortorder] = [$key => $data[$property]['value'][$index]];
                        }
                    }
                }
                ksort($values);
                $data[$property] = $values;
            }

            $data['parameters'] = [];
        } elseif ($data['type'] == ITEM_TYPE_SCRIPT) {
            $values = [];

            if (is_array($data['parameters']) && array_key_exists('name', $data['parameters'])
                && array_key_exists('value', $data['parameters'])) {
                foreach ($data['parameters']['name'] as $index => $key) {
                    if (array_key_exists($index, $data['parameters']['value'])) {
                        $values[] = [
                            'name' => $key,
                            'value' => $data['parameters']['value'][$index]
                        ];
                    }
                }
            }
            $data['parameters'] = $values;

            $data['headers'] = [];
            $data['query_fields'] = [];
        } else {
            $data['headers'] = [];
            $data['query_fields'] = [];
            $data['parameters'] = [];
        }

        // Dependent item initialization by master_itemid.
        $data['master_itemname'] = '';
        if (array_key_exists('master_item', $item)) {
            $data['master_itemid'] = $item['master_item']['itemid'];
            $data['master_itemname'] = $item['master_item']['name'];
            // Do not initialize item data if only master_item array was passed.
            unset($item['master_item']);
        }

        // hostid
        if ($data['parent_discoveryid'] != 0) {
            $discoveryRule = Items::find()->select(['hostid'])->where(['itemid' => $data['parent_discoveryid']])->limit(1)->asArray()->one();
            $data['hostid'] = $discoveryRule['hostid'];
            $data['host'] = Hosts::find()->select(['hostid', 'flags'])->where(['hostid' => $discoveryRule['hostid']])->asArray()->one();
        } else {
            $data['hostid'] = getRequest('hostid', 0);
        }

        foreach ($data['preprocessing'] as &$step) {
            $step += [
                'error_handler' => PRS_PREPROC_FAIL_DEFAULT,
                'error_handler_params' => ''
            ];
        }
        unset($step);

        // types, http items only for internal processes
        $data['types'] = static::itemType2str();
        unset($data['types'][ITEM_TYPE_HTTPTEST]);

        if ($data['is_discovery_rule']) {
            unset($data['types'][ITEM_TYPE_CALCULATED], $data['types'][ITEM_TYPE_SNMPTRAP]);
        }

        // item
        if (array_key_exists('itemid', $item)) {
            $data['item'] = $item;
            $data['hostid'] = !empty($data['hostid']) ? $data['hostid'] : $data['item']['hostid'];
            $data['limited'] = ($data['item']['templateid'] != 0);
            $data['interfaceid'] = $item['interfaceid'];

            // discovery rule
            if ($data['is_discovery_rule']) {
                $flag = PRS_FLAG_DISCOVERY_RULE;
            } // item prototype
            elseif ($data['parent_discoveryid'] != 0) {
                $flag = PRS_FLAG_DISCOVERY_PROTOTYPE;
            } // plain item
            else {
                $flag = PRS_FLAG_DISCOVERY_NORMAL;
            }

            $data['templates'] = static::getItemParentTemplates([$item], $flag);
        }

        // caption
        if ($data['is_discovery_rule']) {
            $data['caption'] = t('zapi', 'Discovery rule');
        } else {
            $data['caption'] = ($data['parent_discoveryid'] != 0) ? t('zapi', 'Item prototype') : t('zapi', 'Item');
        }

        // hostname
        if (empty($data['is_discovery_rule']) && empty($data['hostname'])) {
            if (!empty($data['hostid'])) {
                $hostInfo = Hosts::find()->select(['hostid', 'name'])
                    ->where(['hostid' => $data['hostid']])
                    ->andWhere(['status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE]])
                    ->limit(1)->asArray()->one();
                $data['hostname'] = $hostInfo['name'];
            } else {
                $data['hostname'] = t('zapi', 'not selected');
            }
        }

        // fill data from item
        if ($item) {
            $data['name'] = $data['item']['name'];
            $data['description'] = $data['item']['description'];
            $data['key'] = $data['item']['key_'];
            $data['interfaceid'] = $data['item']['interfaceid'];
            $data['type'] = $data['item']['type'];
            $data['snmp_oid'] = $data['item']['snmp_oid'];
            $data['value_type'] = $data['item']['value_type'];
            $data['trapper_hosts'] = $data['item']['trapper_hosts'];
            $data['units'] = $data['item']['units'];
            $data['valuemapid'] = $data['item']['valuemapid'];
            $data['hostid'] = $data['item']['hostid'];
            $data['params'] = $data['item']['params'];
            $data['ipmi_sensor'] = $data['item']['ipmi_sensor'];
            $data['authtype'] = $data['item']['authtype'];
            $data['username'] = $data['item']['username'];
            $data['password'] = $data['item']['password'];
            $data['publickey'] = $data['item']['publickey'];
            $data['privatekey'] = $data['item']['privatekey'];
            $data['logtimefmt'] = $data['item']['logtimefmt'];
            $data['jmx_endpoint'] = $data['item']['jmx_endpoint'];
            // ITEM_TYPE_HTTPAGENT
            $data['timeout'] = $data['item']['timeout'];
            $data['url'] = $data['item']['url'];
            $data['query_fields'] = $data['item']['query_fields'];
            $data['parameters'] = $data['item']['parameters'];
            $data['posts'] = $data['item']['posts'];
            $data['status_codes'] = $data['item']['status_codes'];
            $data['follow_redirects'] = $data['item']['follow_redirects'];
            $data['post_type'] = $data['item']['post_type'];
            $data['http_proxy'] = $data['item']['http_proxy'];
            $data['headers'] = $data['item']['headers'];
            $data['retrieve_mode'] = $data['item']['retrieve_mode'];
            $data['request_method'] = $data['item']['request_method'];
            $data['allow_traps'] = $data['item']['allow_traps'];
            $data['ssl_cert_file'] = $data['item']['ssl_cert_file'];
            $data['ssl_key_file'] = $data['item']['ssl_key_file'];
            $data['ssl_key_password'] = $data['item']['ssl_key_password'];
            $data['verify_peer'] = $data['item']['verify_peer'];
            $data['verify_host'] = $data['item']['verify_host'];
            $data['http_authtype'] = $data['item']['authtype'];
            $data['http_username'] = $data['item']['username'];
            $data['http_password'] = $data['item']['password'];

            $paramsFieldName = ItemHelper::getParamFieldNameByType($item['type']);
            $data[$paramsFieldName] = $item['params'];

            if (!$data['is_discovery_rule']) {
                $data['tags'] = $data['item']['tags'];
            }

            if ($data['type'] == ITEM_TYPE_HTTPAGENT) {
                // Convert hash to array where every item is hash for single key value pair as it is used by view.
                $headers = [];

                foreach ($data['headers'] as $key => $value) {
                    $headers[] = [$key => $value];
                }

                $data['headers'] = $headers;
            } elseif ($data['type'] == ITEM_TYPE_SCRIPT && $data['parameters']) {
                ArrayHelper::multisort($data['parameters'], 'name');
            }

            $data['preprocessing'] = $data['item']['preprocessing'];

            if (!$data['is_discovery_rule']) {
                $data['output_format'] = $data['item']['output_format'];
            }

            if ($item) {
                $data['delay'] = $data['item']['delay'];

                $update_interval_parser = new CUpdateIntervalParser([
                    'usermacros' => true,
                    'lldmacros' => ($data['parent_discoveryid'] != 0)
                ]);

                if ($update_interval_parser->parse($data['delay']) == CParser::PARSE_SUCCESS) {
                    $data['delay'] = $update_interval_parser->getDelay();

                    if ($data['delay'][0] !== '{') {
                        $delay = timeUnitToSeconds($data['delay']);

                        if ($delay == 0 && ($data['type'] == ITEM_TYPE_TRAPPER || $data['type'] == ITEM_TYPE_SNMPTRAP
                                || $data['type'] == ITEM_TYPE_DEPENDENT || ($data['type'] == ITEM_TYPE_PERSEUS_ACTIVE
                                    && strncmp($data['key'], 'mqtt.get', 8) === 0))) {
                            $data['delay'] = PRS_ITEM_DELAY_DEFAULT;
                        }
                    }

                    foreach ($update_interval_parser->getIntervals() as $interval) {
                        if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
                            $data['delay_flex'][] = [
                                'delay' => $interval['update_interval'],
                                'period' => $interval['time_period'],
                                'schedule' => '',
                                'type' => ITEM_DELAY_FLEXIBLE
                            ];
                        } else {
                            $data['delay_flex'][] = [
                                'delay' => '',
                                'period' => '',
                                'schedule' => $interval['interval'],
                                'type' => ITEM_DELAY_SCHEDULING
                            ];
                        }
                    }
                } else {
                    $data['delay'] = PRS_ITEM_DELAY_DEFAULT;
                }

                $data['history'] = $data['item']['history'];
                $data['status'] = $data['item']['status'];
                $data['trends'] = $data['item']['trends'];
            }
        }

        if (!$data['delay_flex']) {
            $data['delay_flex'][] = ['delay' => '', 'period' => '', 'schedule' => '', 'type' => ITEM_DELAY_FLEXIBLE];
        }

        // interfaces
        $interfaces = Interfaces::find()->where(['hostid' => $data['hostid']])->asArray()->all();
        foreach ($interfaces as &$interface) {
            $interface['type_label'] = ItemHelper::interfaceType2str($interface['type']);
        }
        unset($interface);
        $data['interfaces'] = $interfaces;
        // Sort interfaces to be listed starting with one selected as 'main'.
//        CArrayHelper::sort($data['interfaces'], [
//            ['field' => 'main', 'order' => PRS_SORT_DOWN],
//            ['field' => 'interfaceid', 'order' => PRS_SORT_UP]
//        ]);
        ArrayHelper::multisort($data['interfaces'], ['main', 'interfaceid'], [SORT_DESC, SORT_ASC]);
//        $data['interfaces_group'] = ArrayHelper::index($data['interfaces'], null, 'type');

        if ($data['is_discovery_rule']) {
            unset($data['valuemapid']);
        } else if ($data['valuemapid'] != 0) {
            $valueMaps = Valuemap::find()->select(['valuemapid', 'name'])
                ->where(['valuemapid' => $data['valuemapid']])
                ->asArray()->all();
            $data['valuemap'] = CArrayHelper::renameObjectsKeys($valueMaps, ['valuemapid' => 'id']);
        } else {
            $data['valuemap'] = [];
        }

        // possible host inventories
        if ($data['parent_discoveryid'] == 0) {
            $data['possibleHostInventories'] = getHostInventories();

            // get already populated fields by other items
            $data['alreadyPopulated'] = static::getItems([
                'output' => ['inventory_link'],
                'filter' => ['hostid' => $data['hostid']],
                'nopermissions' => true
            ]);
            $data['alreadyPopulated'] = prs_toHash($data['alreadyPopulated'], 'inventory_link');
        }

        // unset ssh auth fields
        if ($data['type'] != ITEM_TYPE_SSH) {
            $data['authtype'] = ITEM_AUTHTYPE_PASSWORD;
            $data['publickey'] = '';
            $data['privatekey'] = '';
        }

        if ($data['type'] != ITEM_TYPE_DEPENDENT) {
            $data['master_itemid'] = 0;
        }


        if (!$data['is_discovery_rule']) {
            // Select inherited tags.
            if ($data['show_inherited_tags'] && array_key_exists('item', $data)) {
                if ($data['item']['discoveryRule']) {
                    $items = [$data['item']['discoveryRule']];
                    $parent_templates = static::getItemParentTemplates($items, PRS_FLAG_DISCOVERY_RULE)['templates'];
                } else {
                    $items = [[
                        'templateid' => $data['item']['templateid'],
                        'itemid' => $data['itemid']
                    ]];
                    $parent_templates = static::getItemParentTemplates($items, PRS_FLAG_DISCOVERY_NORMAL)['templates'];
                }
                unset($parent_templates[0]);

                $templateTags = HostHelper::getTags(array_keys($parent_templates));

                $inherited_tags = [];

                // Make list of template tags.
                foreach ($parent_templates as $templateid => $template) {
                    foreach ($templateTags[$templateid] as $tag) {
                        if (array_key_exists($tag['tag'], $inherited_tags)
                            && array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
                            $inherited_tags[$tag['tag']][$tag['value']]['parent_templates'] += [
                                $templateid => $template
                            ];
                        } else {
                            $inherited_tags[$tag['tag']][$tag['value']] = $tag + [
                                    'parent_templates' => [$templateid => $template],
                                    'type' => PRS_PROPERTY_INHERITED
                                ];
                        }
                    }
                }

                $hostTags = HostHelper::getTags($data['hostid']);

                // Overwrite and attach host level tags.
                if ($hostTags) {
                    $hostTag = current($hostTags);
                    foreach ($hostTag as $tag) {
                        $inherited_tags[$tag['tag']][$tag['value']] = $tag;
                        $inherited_tags[$tag['tag']][$tag['value']]['type'] = PRS_PROPERTY_INHERITED;
                    }
                }

                // Overwrite and attach item's own tags.
                foreach ($data['tags'] as $tag) {
                    if (array_key_exists($tag['tag'], $inherited_tags)
                        && array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
                        $inherited_tags[$tag['tag']][$tag['value']]['type'] = PRS_PROPERTY_BOTH;
                    } else {
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

            if (!$data['tags']) {
                $data['tags'] = [['tag' => '', 'value' => '']];
            } else {
                ArrayHelper::multisort($data['tags'], ['tag', 'value']);
            }
        }
        $data['display_interfaces'] = false;
        if (!empty($data['hostid'])) {
            $host = Hosts::find()->select(['hostid', 'name', 'host', 'status'])->where(['hostid' => $data['hostid']])->one();
            if ($host) {
                $data['display_interfaces'] = ($host['status'] == HOST_STATUS_MONITORED || $host['status'] == HOST_STATUS_NOT_MONITORED);
            }
        }

        $testAbleTypes = ItemTestAssist::getTestableItemTypes($data['hostid']);
        if ($data['type'] == ITEM_TYPE_SIMPLE && (substr($data['key'], 0, 7) == 'vmware.' || substr($data['key'], 0, 8) == 'icmpping')) {
            $data['disable_test'] = 1;
        } else {
            $data['disable_test'] = (int)!in_array($data['type'], $testAbleTypes);
        }
        $data['test_able_types'] = $testAbleTypes;

        return $data;
    }

    /**
     * @param array $options
     * @return array
     * @throws Exception
     */
    public static function getItems(array $options): array
    {
        $search = new ItemSearch();
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
    public static function getItemPrototypes(array $options): array
    {
        $search = new ItemPrototypeSearch();
        $search->is_all = true;
        $provider = $search->search($options);
        return $provider->getModels();
    }


    /**
     * @param $hostIds
     * @return array[]
     */
    public static function getItemHostsAndTemplates($hostIds): array
    {
        $models = Hosts::find()->select(['hostid', 'flags', 'status'])->where(['hostid' => $hostIds])->asArray()->all();
        $hosts = $templates = [];
        foreach ($models as $model) {
            if ($model['status'] == 3) {
                $templates[$model['hostid']] = $model;
            } else {
                $hosts[$model['hostid']] = $model;
            }
        }
        return [$templates, $hosts];
    }

    /**
     * Get parent templates for each given item.
     *
     * @param array $items An array of items.
     * @param string $items []['itemid']      ID of an item.
     * @param string $items []['templateid']  ID of parent template item.
     * @param int $flag Origin of the item (PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_RULE,
     *                                       PRS_FLAG_DISCOVERY_PROTOTYPE).
     *
     * @return array
     */
    public static function getItemParentTemplates(array $items, $flag)
    {
        $parent_itemids = [];
        $data = [
            'links' => [],
            'templates' => []
        ];

        foreach ($items as $item) {
            if ($item['templateid'] != 0) {
                $parent_itemids[$item['templateid']] = true;
                $data['links'][$item['itemid']] = ['itemid' => $item['templateid']];
            }
        }

        if (!$parent_itemids) {
            return $data;
        }

        $all_parent_itemids = [];
        $hostids = [];
        if ($flag == PRS_FLAG_DISCOVERY_PROTOTYPE) {
            $lld_ruleids = [];
        }

        do {
            if ($flag == PRS_FLAG_DISCOVERY_RULE) {
                $db_items = Items::find()->select(['itemid', 'hostid', 'templateid'])
                    ->where(['itemid' => array_keys($parent_itemids)])
                    ->asArray()->all();
            } elseif ($flag == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                $db_items = static::getItemPrototypes([
                    'output' => ['itemid', 'hostid', 'templateid'],
                    'itemids' => array_keys($parent_itemids),
                    'selectDiscoveryRule' => ['itemid']
                ]);
            } // PRS_FLAG_DISCOVERY_NORMAL
            else {
                $db_items = static::getItems([
                    'output' => ['itemid', 'hostid', 'templateid'],
                    'itemids' => array_keys($parent_itemids),
                    'webitems' => true
                ]);
            }

            $all_parent_itemids += $parent_itemids;
            $parent_itemids = [];

            foreach ($db_items as $db_item) {
                $data['templates'][$db_item['hostid']] = [];
                $hostids[$db_item['itemid']] = $db_item['hostid'];

                if ($flag == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                    $lld_ruleids[$db_item['itemid']] = $db_item['discoveryRule']['itemid'];
                }

                if ($db_item['templateid'] != 0) {
                    if (!array_key_exists($db_item['templateid'], $all_parent_itemids)) {
                        $parent_itemids[$db_item['templateid']] = true;
                    }

                    $data['links'][$db_item['itemid']] = ['itemid' => $db_item['templateid']];
                }
            }
        } while ($parent_itemids);

        foreach ($data['links'] as &$parent_item) {
            $parent_item['hostid'] = array_key_exists($parent_item['itemid'], $hostids)
                ? $hostids[$parent_item['itemid']]
                : 0;

            if ($flag == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                $parent_item['lld_ruleid'] = array_key_exists($parent_item['itemid'], $lld_ruleids)
                    ? $lld_ruleids[$parent_item['itemid']]
                    : 0;
            }
        }
        unset($parent_item);

        $db_templates = $rw_templates = [];
        if ($data['templates']) {
            $db_templates = Hosts::find()->select(['templateid' => 'hostid', 'name'])
                ->where(['hostid' => array_keys($data['templates'])])
                ->indexBy('templateid')
                ->asArray()->all();
        }

        if ($db_templates) {
            $rw_templates = Hosts::find()->select(['templateid' => 'hostid'])
                ->where(['hostid' => array_keys($db_templates)])
                ->indexBy('templateid')
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

    public static function isItemExampleKey(int $type, string $key): bool
    {
        if (($type == ITEM_TYPE_DB_MONITOR && $key === PRS_DEFAULT_KEY_DB_MONITOR)
            || ($type == ITEM_TYPE_SSH && $key === PRS_DEFAULT_KEY_SSH)
            || ($type == ITEM_TYPE_TELNET && $key === PRS_DEFAULT_KEY_TELNET)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check the format of the given custom intervals. Unset the custom intervals with empty values.
     * @param array $delay_flex
     * @param bool $lldMacros
     * @param string|null $error
     * @return bool
     */
    public static function isValidCustomIntervals(array &$delay_flex, bool $lldMacros = false, ?string &$error = ''): bool
    {
        if (!$delay_flex) {
            return true;
        }

        $simple_interval_parser = new CSimpleIntervalParser([
            'usermacros' => true,
            'lldmacros' => $lldMacros
        ]);

        $time_period_parser = new CTimePeriodParser([
            'usermacros' => true,
            'lldmacros' => $lldMacros
        ]);

        $scheduling_interval_parser = new CSchedulingIntervalParser([
            'usermacros' => true,
            'lldmacros' => $lldMacros
        ]);

        foreach ($delay_flex as $i => $interval) {
            if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
                if ($interval['delay'] === '' && $interval['period'] === '') {
                    unset($delay_flex[$i]);
                    continue;
                }

                if ($simple_interval_parser->parse($interval['delay']) != CParser::PARSE_SUCCESS) {
                    $error = t('zapi', 'Invalid interval "{value}".', ['value' => $interval['delay']]);
                    return false;
                } elseif ($time_period_parser->parse($interval['period']) != CParser::PARSE_SUCCESS) {
                    $error = t('zapi', 'Invalid interval "{value}".', ['value' => $interval['period']]);
                    return false;
                }
            } else {
                if ($interval['schedule'] === '') {
                    unset($delay_flex[$i]);
                    continue;
                }

                if ($scheduling_interval_parser->parse($interval['schedule']) != CParser::PARSE_SUCCESS) {
                    $error = t('zapi', 'Invalid interval "{value}".', ['value' => $interval['schedule']]);
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Format tags received via form for API input.
     *
     * @param array $tags Array of item tags, as received from form submit.
     *
     * @return array
     */
    public static function prepareItemTags(array $tags): array
    {
        foreach ($tags as $key => $tag) {
            if ($tag['tag'] === '' && $tag['value'] === '') {
                unset($tags[$key]);
            } elseif (array_key_exists('type', $tag) && !($tag['type'] & PRS_PROPERTY_OWN)) {
                unset($tags[$key]);
            } else {
                unset($tags[$key]['type']);
            }
        }

        return $tags;
    }

    /**
     * Normalizes item preprocessing step parameters after item preprocessing form submit.
     *
     * @param array $preprocessing Array of item preprocessing steps, as received from form submit.
     *
     * @return array
     */
    public static function normalizeItemPreprocessingSteps(array $preprocessing): array
    {
        foreach ($preprocessing as &$step) {
            switch ($step['type']) {
                case PRS_PREPROC_MULTIPLIER:
                case PRS_PREPROC_PROMETHEUS_TO_JSON:
                    $step['params'] = trim($step['params'][0]);
                    break;

                case PRS_PREPROC_RTRIM:
                case PRS_PREPROC_LTRIM:
                case PRS_PREPROC_TRIM:
                case PRS_PREPROC_XPATH:
                case PRS_PREPROC_JSONPATH:
                case PRS_PREPROC_VALIDATE_REGEX:
                case PRS_PREPROC_VALIDATE_NOT_REGEX:
                case PRS_PREPROC_ERROR_FIELD_JSON:
                case PRS_PREPROC_ERROR_FIELD_XML:
                case PRS_PREPROC_THROTTLE_TIMED_VALUE:
                case PRS_PREPROC_SCRIPT:
                    $step['params'] = $step['params'][0];
                    break;

                case PRS_PREPROC_SNMP_WALK_VALUE:
                case PRS_PREPROC_VALIDATE_RANGE:
                    foreach ($step['params'] as &$param) {
                        $param = trim($param);
                    }
                    unset($param);

                    $step['params'] = implode("\n", $step['params']);
                    break;

                case PRS_PREPROC_PROMETHEUS_PATTERN:
                    foreach ($step['params'] as &$param) {
                        $param = trim($param);
                    }
                    unset($param);

                    if (in_array($step['params'][1], [
                        PRS_PREPROC_PROMETHEUS_SUM, PRS_PREPROC_PROMETHEUS_MIN,
                        PRS_PREPROC_PROMETHEUS_MAX, PRS_PREPROC_PROMETHEUS_AVG, PRS_PREPROC_PROMETHEUS_COUNT
                    ])) {
                        $step['params'][2] = $step['params'][1];
                        $step['params'][1] = PRS_PREPROC_PROMETHEUS_FUNCTION;
                    }

                    if (!array_key_exists(2, $step['params'])) {
                        $step['params'][2] = '';
                    }

                    $step['params'] = implode("\n", $step['params']);
                    break;

                case PRS_PREPROC_REGSUB:
                case PRS_PREPROC_ERROR_FIELD_REGEX:
                case PRS_PREPROC_STR_REPLACE:
                    $step['params'] = implode("\n", $step['params']);
                    break;

                case PRS_PREPROC_CSV_TO_JSON:
                    if (!array_key_exists(2, $step['params'])) {
                        $step['params'][2] = PRS_PREPROC_CSV_NO_HEADER;
                    }
                    $step['params'] = implode("\n", $step['params']);
                    break;

                case PRS_PREPROC_SNMP_WALK_TO_JSON:
                    $step['params'] = array_values($step['params']);

                    $step['params'] = implode("\n", array_map(function (string $value): string {
                        return trim($value);
                    }, $step['params']));
                    break;

                default:
                    $step['params'] = '';
            }

            $step += [
                'error_handler' => PRS_PREPROC_FAIL_DEFAULT,
                'error_handler_params' => ''
            ];

            // Remove fictional fields that don't belong to DB and API.
            unset($step['sortorder'], $step['on_fail']);
        }
        unset($step);

        return $preprocessing;
    }

    /**
     * Prepare ITEM_TYPE_SCRIPT type item data for create or update API calls.
     * - Converts 'parameters' from array of keys and array of values to arrays of names and values.
     *   IN:
     *   Array (
     *       [name] => Array (
     *           [0] => a
     *           [1] => c
     *       )
     *       [value] => Array (
     *           [0] => b
     *           [1] => d
     *       )
     *   )
     *
     *   OUT:
     *   Array (
     *       [0] => Array (
     *           [name] => a
     *           [value] => b
     *       )
     *       [1] => Array (
     *           [name] => c
     *           [value] => d
     *       )
     *   )
     *
     * @param array $item Array of form fields data for ITEM_TYPE_SCRIPT item.
     * @param array $item ['parameters']            Item parameters array.
     * @param array $item ['parameters']['name']    Item parameter names array.
     * @param array $item ['parameters']['values']  Item parameter values array.
     *
     * @return array
     */
    public static function prepareScriptItemFormData(array $item): array
    {
        $values = [];

        if (is_array($item['parameters']) && array_key_exists('name', $item['parameters'])
            && array_key_exists('value', $item['parameters'])) {
            foreach ($item['parameters']['name'] as $index => $key) {
                if (array_key_exists($index, $item['parameters']['value'])
                    && ($key !== '' || $item['parameters']['value'][$index] !== '')) {
                    $values[] = [
                        'name' => $key,
                        'value' => $item['parameters']['value'][$index]
                    ];
                }
            }
        }

        $item['parameters'] = $values;

        return $item;
    }

    /**
     * Format query fields received via form for API input.
     *
     * @param array $query_fields
     *
     * @return array
     */
    public static function prepareItemQueryFields(array $query_fields): array
    {
        if ($query_fields) {
            $_query_fields = [];

            foreach ($query_fields['name'] as $index => $key) {
                $value = $query_fields['value'][$index];
                $sortorder = $query_fields['sortorder'][$index];

                if ($key !== '' || $value !== '') {
                    $_query_fields[$sortorder] = [$key => $value];
                }
            }

            ksort($_query_fields);
            $query_fields = array_values($_query_fields);
        }

        return $query_fields;
    }

    /**
     * Format headers field received via form for API input.
     *
     * @param array $headers
     *
     * @return array
     */
    public static function prepareItemHeaders(array $headers): array
    {
        if ($headers) {
            $_headers = [];

            foreach ($headers['name'] as $i => $name) {
                $value = $headers['value'][$i];

                if ($name === '' && $value === '') {
                    continue;
                }

                $_headers[$name] = $value;
            }

            $headers = $_headers;
        }

        return $headers;
    }

    /**
     * Format parameters field received via form for API input.
     *
     * @param array $parameters
     *
     * @return array
     */
    public static function prepareItemParameters(array $parameters): array
    {
        $_parameters = [];

        if (
            array_key_exists('name', $parameters)
            && array_key_exists('value', $parameters)
        ) {
            foreach ($parameters['name'] as $index => $name) {
                if (
                    array_key_exists($index, $parameters['value'])
                    && ($name !== '' || $parameters['value'][$index] !== '')
                ) {
                    $_parameters[] = [
                        'name' => $name,
                        'value' => $parameters['value'][$index]
                    ];
                }
            }
        }

        return $_parameters;
    }

    /**
     * Validates update interval for items, item prototypes and low-level discovery rules and their overrides.
     *
     * @param CUpdateIntervalParser $parser [IN]      Parser used for delay validation.
     * @param string $value [IN]      Update interval to parse and validate.
     * @param string $field_name [IN]  Frontend or API field name in the error
     * @param string $error [OUT]     Returned error string if delay validation fails.
     *
     * @return bool
     */
    public static function validateDelay(CUpdateIntervalParser $parser, $field_name, $value, &$error)
    {
        if ($parser->parse($value) != CParser::PARSE_SUCCESS) {
            $error = t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => $field_name, 'error' => t('zapi', 'invalid delay')]);

            return false;
        }

        $delay = $parser->getDelay();

        if ($delay[0] !== '{') {
            $delay_sec = timeUnitToSeconds($delay);
            $intervals = $parser->getIntervals();
            $flexible_intervals = $parser->getIntervals(ITEM_DELAY_FLEXIBLE);
            $has_scheduling_intervals = (bool)$parser->getIntervals(ITEM_DELAY_SCHEDULING);
            $has_macros = false;

            foreach ($intervals as $interval) {
                if (strpos($interval['interval'], '{') !== false) {
                    $has_macros = true;
                    break;
                }
            }

            // If delay is 0, there must be at least one either flexible or scheduling interval.
            if ($delay_sec == 0 && !$intervals) {
                $error = t('zapi', 'Item will not be refreshed. Specified update interval requires having at least one either flexible or scheduling interval.');

                return false;
            } elseif ($delay_sec < 0 || $delay_sec > SEC_PER_DAY) {
                $error = t('zapi', 'Item will not be refreshed. Update interval should be between 1s and 1d. Also Scheduled/Flexible intervals can be used.');

                return false;
            }

            // If there are scheduling intervals or intervals with macros, skip the next check calculation.
            if (!$has_macros && !$has_scheduling_intervals && $flexible_intervals
                && calculateItemNextCheck(0, $delay_sec, $flexible_intervals, time()) == PRS_JAN_2038) {
                $error = t('zapi', 'Item will not be refreshed. Please enter a correct update interval.');

                return false;
            }
        }

        return true;
    }

    /**
     * Get all given delay intervals as string in API format.
     *
     * @param string $delay
     * @param array $delay_flex
     *
     * @return string
     */
    public static function getDelayWithCustomIntervals(string $delay, array $delay_flex): string
    {
        foreach ($delay_flex as $interval) {
            if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
                $delay .= ';' . $interval['delay'] . '/' . $interval['period'];
            } else {
                $delay .= ';' . $interval['schedule'];
            }
        }

        return $delay;
    }

    /**
     * Apply sorting for discovery rule filter or override filter conditions, if appropriate.
     * Prioritization by non/exist operator applied between matching macros.
     *
     * @param array $conditions
     * @param int $evaltype
     *
     * @return array
     */
    public static function sortLldRuleFilterConditions(array $conditions, int $evaltype): array
    {
        switch ($evaltype) {
            case CONDITION_EVAL_TYPE_AND_OR:
            case CONDITION_EVAL_TYPE_AND:
            case CONDITION_EVAL_TYPE_OR:
                usort($conditions, static function (array $condition_a, array $condition_b): int {
                    $comparison = strnatcasecmp($condition_a['macro'], $condition_b['macro']);

                    if ($comparison != 0) {
                        return $comparison;
                    }

                    $exist_operators = [CONDITION_OPERATOR_NOT_EXISTS, CONDITION_OPERATOR_EXISTS];

                    $comparison = (int)in_array($condition_b['operator'], $exist_operators)
                        - (int)in_array($condition_a['operator'], $exist_operators);

                    if ($comparison != 0) {
                        return $comparison;
                    }

                    return strnatcasecmp($condition_a['value'], $condition_b['value']);
                });

                foreach ($conditions as $i => &$condition) {
                    $condition['formulaid'] = num2letter($i);
                }
                unset($condition);
                break;

            case CONDITION_EVAL_TYPE_EXPRESSION:
                ArrayHelper::multisort($conditions, ['formulaid']);
                break;
        }

        return array_values($conditions);
    }

    /**
     * Expands item name and for dependent item master item name.
     *
     * @param array $items Array of items.
     * @param string $data_source 'items' or 'itemprototypes'.
     *
     * @return array
     */
    public static function expandItemNamesWithMasterItems($items, $data_source): array
    {
        $itemids = [];
        $master_itemids = [];

        foreach ($items as $item_index => &$item) {
            if ($item['type'] == ITEM_TYPE_DEPENDENT) {
                $master_itemids[$item['master_itemid']] = true;
            }

            // The "source" is required to tell the frontend where the link should point at - item or item prototype.
            $item['source'] = $data_source;
            $itemids[$item_index] = $item['itemid'];
        }
        unset($item);

        $master_itemids = array_diff(array_keys($master_itemids), $itemids);

        if ($master_itemids) {
            $options = [
                'output' => ['itemid', 'type', 'name'],
                'itemids' => $master_itemids,
                'editable' => true,
                'preservekeys' => true
            ];
            $master_items = self::getItems($options + ['webitems' => true]);

            foreach ($master_items as &$master_item) {
                $master_item['source'] = 'items';
            }
            unset($master_item);

            $master_item_prototypes = self::getItemPrototypes($options);

            foreach ($master_item_prototypes as &$master_item_prototype) {
                $master_item_prototype['source'] = 'itemprototypes';
            }
            unset($master_item_prototype);

            $master_items += $master_item_prototypes;
        }

        foreach ($items as &$item) {
            if ($item['type'] == ITEM_TYPE_DEPENDENT) {
                $master_itemid = $item['master_itemid'];
                $items_index = array_search($master_itemid, $itemids);
                $item['master_item'] = array_fill_keys(['name', 'type', 'source'], '');
                $item['master_item'] = ($items_index === false)
                    ? array_intersect_key($master_items[$master_itemid], $item['master_item'])
                    : array_intersect_key($items[$items_index], $item['master_item']);
                $item['master_item']['itemid'] = $master_itemid;
            }
        }
        unset($item);

        return $items;
    }

    /**
     * Prepare item value for displaying, apply value map and/or convert units.
     *
     * @param int|float|string $value
     * @param array $item
     * @param bool $trim Whether to trim non-numeric value to a length of 20 characters.
     * @param array $convert_options Options for unit conversion. See @convertUnitsRaw.
     *
     * @return string
     * @see formatHistoryValueRaw
     *
     */
    public static function formatHistoryValue($value, array $item, bool $trim = true, array $convert_options = []): string
    {
        $formatted_value = self::formatHistoryValueRaw($value, $item, $trim, $convert_options);

        return $formatted_value['value'] . ($formatted_value['units'] !== '' ? ' ' . $formatted_value['units'] : '');
    }

    /**
     * Prepare item value for displaying, apply value map and/or convert units.
     *
     * @param int|float|string $value
     * @param array $item
     * @param bool $trim Whether to trim non-numeric value to a length of 20 characters.
     * @param array $convert_options Options for unit conversion. See @convertUnitsRaw.
     *
     * $item = [
     *     'value_type' => (int)     ITEM_VALUE_TYPE_FLOAT | ITEM_VALUE_TYPE_UINT64, ...
     *     'units' =>      (string)  Item units.
     *     'valuemap' =>   (array)   Item value map.
     * ]
     *
     * @return array
     */
    public static function formatHistoryValueRaw($value, array $item, bool $trim = true, array $convert_options = []): array
    {
        $mapped_value = in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_STR])
            ? ValueMapHelper::getMappedValue($item['value_type'], $value, $item['valuemap'])
            : false;

        switch ($item['value_type']) {
            case ITEM_VALUE_TYPE_FLOAT:
            case ITEM_VALUE_TYPE_UINT64:
                if ($mapped_value !== false) {
                    return [
                        'value' => $mapped_value . ' (' . $value . ')',
                        'units' => '',
                        'is_mapped' => true
                    ];
                }

                if ($item['units'] === 's' && array_key_exists('decimals', $convert_options)
                    && $convert_options['decimals'] != 0) {
                    return [
                        'value' => self::convertUnitSWithDecimals($value, false, $convert_options['decimals'], true),
                        'units' => '',
                        'is_mapped' => false
                    ];
                }

                $converted_value = convertUnitsRaw([
                        'value' => $value,
                        'units' => $item['units']
                    ] + $convert_options);

                return [
                    'value' => $converted_value['value'],
                    'units' => $converted_value['units'],
                    'is_mapped' => false
                ];

            case ITEM_VALUE_TYPE_STR:
            case ITEM_VALUE_TYPE_TEXT:
            case ITEM_VALUE_TYPE_LOG:
                if ($trim && mb_strlen($value) > 20) {
                    $value = mb_substr($value, 0, 20) . '...';
                }

                if ($mapped_value !== false) {
                    $value = $mapped_value . ' (' . $value . ')';
                }

                return [
                    'value' => $value,
                    'units' => '',
                    'is_mapped' => $mapped_value !== false
                ];

            default:
                return [
                    'value' => t('zapi', 'Unknown value type'),
                    'units' => '',
                    'is_mapped' => false
                ];
        }
    }

    /**
     * Converts seconds to the biggest unit of measure with decimals.
     *
     * @param int|float|string $value Time period in seconds
     * @param bool $ignore_millisec Ignores milliseconds
     * @param int $decimals Max number of first non-zero decimals to display
     * @param bool $decimals_exact Display exactly this number of decimals instead of first non-zeros
     *
     * @return string
     */
    public static function convertUnitSWithDecimals($value, bool $ignore_millisec = false, int $decimals = PRS_UNITS_ROUNDOFF_SUFFIXED,
                                                    bool $decimals_exact = false): string
    {
        $value = (float)$value;
        $part = '';
        $result = 0;

        foreach ([
                     'y' => SEC_PER_YEAR,
                     'M' => SEC_PER_MONTH,
                     'd' => SEC_PER_DAY,
                     'h' => SEC_PER_HOUR,
                     'm' => SEC_PER_MIN,
                     's' => 1
                 ] as $key => $sec_per_part) {
            if (floor($value / $sec_per_part) > 0) {
                $part = $key;
                $result = $value / $sec_per_part;
                break;
            }
        }

        if ($part === '' && $ignore_millisec) {
            $part = 's';
            $result = $value;
        } elseif ($part === '') {
            $part = 'ms';
            $result = $value * 1000;
        }

        return formatFloat($result, ['decimals' => $decimals, 'decimals_exact' => $decimals_exact]) . $part;
    }

    /**
     * Prepare ITEM_TYPE_HTTPAGENT type item data for create or update API calls.
     * - Converts 'query_fields' from array of keys and array of values to array of hash maps for every field.
     * - Converts 'headers' from array of keys and array of values to hash map.
     * - For request method HEAD set retrieve mode to retrieve only headers.
     *
     * @param array $item Array of form fields data for ITEM_TYPE_HTTPAGENT item.
     * @param int $item ['request_method']     Request method type.
     * @param array $item ['query_fields']       Array of 'name' and 'value' arrays for URL query fields.
     * @param array $item ['headers']            Array of 'name' and 'value' arrays for headers.
     *
     * @return array
     */
    public static function prepareItemHttpAgentFormData(array $item): array
    {
        if ($item['request_method'] == HTTPCHECK_REQUEST_HEAD) {
            $item['retrieve_mode'] = HTTPTEST_STEP_RETRIEVE_MODE_HEADERS;
        }

        if ($item['query_fields']) {
            $query_fields = [];
            foreach ($item['query_fields']['name'] as $index => $key) {
                $value = $item['query_fields']['value'][$index];
                $sortorder = $item['query_fields']['sortorder'][$index];

                if ($key !== '' || $value !== '') {
                    $query_fields[$sortorder] = [$key => $value];
                }
            }

            ksort($query_fields);
            $item['query_fields'] = $query_fields;
        }

        if ($item['headers']) {
            $tmp_headers = [];
                foreach ($item['headers']['name'] as $index => $key) {
                $value = $item['headers']['value'][$index];
                $sortorder = $item['headers']['sortorder'][$index];

                if ($key !== '' || $value !== '') {
                    $tmp_headers[$sortorder] = [$key => $value];
                }
            }

            ksort($tmp_headers);
            $headers = [];

            foreach ($tmp_headers as $key_value_pair) {
                $headers[key($key_value_pair)] = reset($key_value_pair);
            }

            $item['headers'] = $headers;
        }

        return $item;
    }

    /**
     * Returns the name of the given interface type. Items "status" and "state" properties must be defined.
     *
     * @param int $type
     *
     * @return null
     */
    public static function interfaceType2str(int $type)
    {
        $interfaceGroupLabels = [
            INTERFACE_TYPE_AGENT => t('zapi', 'Agent'),
            INTERFACE_TYPE_SNMP => t('zapi', 'SNMP'),
            INTERFACE_TYPE_JMX => t('zapi', 'JMX'),
            INTERFACE_TYPE_IPMI => t('zapi', 'IPMI')
        ];

        return $interfaceGroupLabels[$type] ?? null;
    }

    public static function itemTypeInterface($type = null)
    {
        $types = [
            ITEM_TYPE_SNMP => INTERFACE_TYPE_SNMP,
            ITEM_TYPE_SNMPTRAP => INTERFACE_TYPE_SNMP,
            ITEM_TYPE_IPMI => INTERFACE_TYPE_IPMI,
            ITEM_TYPE_PERSEUS => INTERFACE_TYPE_AGENT,
            ITEM_TYPE_SIMPLE => INTERFACE_TYPE_OPT,
            ITEM_TYPE_EXTERNAL => INTERFACE_TYPE_OPT,
            ITEM_TYPE_SSH => INTERFACE_TYPE_OPT,
            ITEM_TYPE_TELNET => INTERFACE_TYPE_OPT,
            ITEM_TYPE_JMX => INTERFACE_TYPE_JMX,
            ITEM_TYPE_HTTPAGENT => INTERFACE_TYPE_OPT
        ];
        if (is_null($type)) {
            return $types;
        } elseif (isset($types[$type])) {
            return $types[$type];
        } else {
            return false;
        }
    }

    /*
    * Quoting $param if it contain special characters.
    *
    * @param string $param
    * @param bool   $forced
    *
    * @return string
    */
    public static function quoteItemKeyParam($param, $forced = false)
    {
        if (!$forced) {
            if (!isset($param[0]) || ($param[0] != '"' && false === strpbrk($param, ',]'))) {
                return $param;
            }
        }

        return '"' . str_replace('"', '\\"', $param) . '"';
    }

    /**
     * Converts headers field text to hash with header name as key.
     *
     * @param string $headers Headers string, one header per line, line delimiter "\r\n".
     *
     * @return array
     */
    public static function headersStringToArray(string $headers): array
    {
        $result = [];

        foreach (explode("\r\n", $headers) as $header) {
            $header = explode(': ', $header, 2);

            if (count($header) == 2) {
                $result[$header[0]] = $header[1];
            }
        }

        return $result;
    }

    /**
     * Get item type string name by item type number, or array of all item types if null passed.
     *
     * @param int|null $type
     *
     * @return array|string
     */
    public static function itemType2str($type = null)
    {
        $types = [
            ITEM_TYPE_PERSEUS => t('zapi', 'Perseus agent'),
            ITEM_TYPE_PERSEUS_ACTIVE => t('zapi', 'Perseus agent (active)'),
            ITEM_TYPE_SIMPLE => t('zapi', 'Simple check'),
            ITEM_TYPE_SNMP => t('zapi', 'SNMP agent'),
            ITEM_TYPE_SNMPTRAP => t('zapi', 'SNMP trap'),
            ITEM_TYPE_INTERNAL => t('zapi', 'Perseus internal'),
            ITEM_TYPE_TRAPPER => t('zapi', 'Perseus trapper'),
            ITEM_TYPE_EXTERNAL => t('zapi', 'External check'),
            ITEM_TYPE_DB_MONITOR => t('zapi', 'Database monitor'),
            ITEM_TYPE_HTTPAGENT => t('zapi', 'HTTP agent'),
            ITEM_TYPE_IPMI => t('zapi', 'IPMI agent'),
            ITEM_TYPE_SSH => t('zapi', 'SSH agent'),
            ITEM_TYPE_TELNET => t('zapi', 'TELNET agent'),
            ITEM_TYPE_JMX => t('zapi', 'JMX agent'),
            ITEM_TYPE_CALCULATED => t('zapi', 'Calculated'),
            ITEM_TYPE_HTTPTEST => t('zapi', 'Web monitoring'),
            ITEM_TYPE_DEPENDENT => t('zapi', 'Dependent item'),
            ITEM_TYPE_SCRIPT => t('zapi', 'Script')
        ];

        if ($type === null) {
            return $types;
        }

        return array_key_exists($type, $types) ? $types[$type] : t('zapi', 'Unknown');
    }

    /**
     * @param $itemType
     * @return string
     */
    public static function getParamFieldNameByType($itemType): string
    {
        switch ($itemType) {
            case ITEM_TYPE_SCRIPT:
                return 'script';
            case ITEM_TYPE_SSH:
            case ITEM_TYPE_TELNET:
            case ITEM_TYPE_JMX:
                return 'params_es';
            case ITEM_TYPE_DB_MONITOR:
                return 'params_ap';
            case ITEM_TYPE_CALCULATED:
                return 'params_f';
            default:
                return 'params';
        }
    }

    /**
     * @param int   $item_type
     * @param array $hostids
     *
     * @return array
     */
    public static function getItemTypeCountByHostId(int $item_type, array $hostids): array 
    {
        $items_count = static::getItems([
            'countOutput' => true,
            'groupCount' => true,
            'hostids' => $hostids,
            'filter' => ['type' => $item_type]
        ]);

        return array_column($items_count, 'rowscount', 'hostid');
    }

    /**
     * Named SNMPv3 authentication protocols.
     *
     * @return array
     */
    public static function getSnmpV3AuthProtocols(): array {
        return [
            ITEM_SNMPV3_AUTHPROTOCOL_MD5 => 'MD5',
            ITEM_SNMPV3_AUTHPROTOCOL_SHA1 => 'SHA1',
            ITEM_SNMPV3_AUTHPROTOCOL_SHA224 => 'SHA224',
            ITEM_SNMPV3_AUTHPROTOCOL_SHA256 => 'SHA256',
            ITEM_SNMPV3_AUTHPROTOCOL_SHA384 => 'SHA384',
            ITEM_SNMPV3_AUTHPROTOCOL_SHA512 => 'SHA512'
        ];
    }

    /**
     * Named SNMPv3 privacy protocols.
     *
     * @return array
     */
    public static function getSnmpV3PrivProtocols(): array {
        return [
            ITEM_SNMPV3_PRIVPROTOCOL_DES => 'DES',
            ITEM_SNMPV3_PRIVPROTOCOL_AES128 => 'AES128',
            ITEM_SNMPV3_PRIVPROTOCOL_AES192 => 'AES192',
            ITEM_SNMPV3_PRIVPROTOCOL_AES256 => 'AES256',
            ITEM_SNMPV3_PRIVPROTOCOL_AES192C => 'AES192C',
            ITEM_SNMPV3_PRIVPROTOCOL_AES256C => 'AES256C'
        ];
    }

    /**
     * Returns human readable an item value type
     *
     * @param int $valueType
     *
     * @return string
     */
    public static function itemValueTypeString($valueType) {
        switch ($valueType) {
            case ITEM_VALUE_TYPE_UINT64:
                return t('zapi', 'Numeric (unsigned)');
            case ITEM_VALUE_TYPE_FLOAT:
                return t('zapi', 'Numeric (float)');
            case ITEM_VALUE_TYPE_STR:
                return t('zapi', 'Character');
            case ITEM_VALUE_TYPE_LOG:
                return t('zapi', 'Log');
            case ITEM_VALUE_TYPE_TEXT:
                return t('zapi', 'Text');
        }
        return t('zapi', 'Unknown');
    }

    public static function preprocessingTypes(): array
    {
        return [
            PRS_PREPROC_REGSUB => [
                'group' => t('zapi', 'Text'),
                'name' => t('zapi', 'Regular expression')
            ],
            PRS_PREPROC_STR_REPLACE => [
                'group' => t('zapi', 'Text'),
                'name' => t('zapi', 'Replace')
            ],
            PRS_PREPROC_TRIM => [
                'group' => t('zapi', 'Text'),
                'name' => t('zapi', 'Trim')
            ],
            PRS_PREPROC_RTRIM => [
                'group' => t('zapi', 'Text'),
                'name' => t('zapi', 'Right trim')
            ],
            PRS_PREPROC_LTRIM => [
                'group' => t('zapi', 'Text'),
                'name' => t('zapi', 'Left trim')
            ],
            PRS_PREPROC_XPATH => [
                'group' => t('zapi', 'Structured data'),
                'name' => t('zapi', 'XML XPath')
            ],
            PRS_PREPROC_JSONPATH => [
                'group' => t('zapi', 'Structured data'),
                'name' => t('zapi', 'JSONPath')
            ],
            PRS_PREPROC_CSV_TO_JSON => [
                'group' => t('zapi', 'Structured data'),
                'name' => t('zapi', 'CSV to JSON')
            ],
            PRS_PREPROC_XML_TO_JSON => [
                'group' => t('zapi', 'Structured data'),
                'name' => t('zapi', 'XML to JSON')
            ],
            PRS_PREPROC_SNMP_WALK_VALUE => [
                'group' => t('zapi', 'SNMP'),
                'name' => t('zapi', 'SNMP walk value')
            ],
            PRS_PREPROC_SNMP_WALK_TO_JSON => [
                'group' => t('zapi', 'SNMP'),
                'name' => t('zapi', 'SNMP walk to JSON')
            ],
            PRS_PREPROC_MULTIPLIER => [
                'group' => t('zapi', 'Arithmetic'),
                'name' => t('zapi', 'Custom multiplier')
            ],
            PRS_PREPROC_DELTA_VALUE => [
                'group' => _x('Change', 'noun'),
                'name' => t('zapi', 'Simple change')
            ],
            PRS_PREPROC_DELTA_SPEED => [
                'group' => _x('Change', 'noun'),
                'name' => t('zapi', 'Change per second')
            ],
            PRS_PREPROC_BOOL2DEC => [
                'group' => t('zapi', 'Numeral systems'),
                'name' => t('zapi', 'Boolean to decimal')
            ],
            PRS_PREPROC_OCT2DEC => [
                'group' => t('zapi', 'Numeral systems'),
                'name' => t('zapi', 'Octal to decimal')
            ],
            PRS_PREPROC_HEX2DEC => [
                'group' => t('zapi', 'Numeral systems'),
                'name' => t('zapi', 'Hexadecimal to decimal')
            ],
            PRS_PREPROC_SCRIPT => [
                'group' => t('zapi', 'Custom scripts'),
                'name' => t('zapi', 'JavaScript')
            ],
            PRS_PREPROC_VALIDATE_RANGE => [
                'group' => t('zapi', 'Validation'),
                'name' => t('zapi', 'In range')
            ],
            PRS_PREPROC_VALIDATE_REGEX => [
                'group' => t('zapi', 'Validation'),
                'name' => t('zapi', 'Matches regular expression')
            ],
            PRS_PREPROC_VALIDATE_NOT_REGEX => [
                'group' => t('zapi', 'Validation'),
                'name' => t('zapi', 'Does not match regular expression')
            ],
            PRS_PREPROC_ERROR_FIELD_JSON => [
                'group' => t('zapi', 'Validation'),
                'name' => t('zapi', 'Check for error in JSON')
            ],
            PRS_PREPROC_ERROR_FIELD_XML => [
                'group' => t('zapi', 'Validation'),
                'name' => t('zapi', 'Check for error in XML')
            ],
            PRS_PREPROC_ERROR_FIELD_REGEX => [
                'group' => t('zapi', 'Validation'),
                'name' => t('zapi', 'Check for error using regular expression')
            ],
            PRS_PREPROC_VALIDATE_NOT_SUPPORTED => [
                'group' => t('zapi', 'Validation'),
                'name' => t('zapi', 'Check for not supported value')
            ],
            PRS_PREPROC_THROTTLE_VALUE => [
                'group' => t('zapi', 'Throttling'),
                'name' => t('zapi', 'Discard unchanged')
            ],
            PRS_PREPROC_THROTTLE_TIMED_VALUE => [
                'group' => t('zapi', 'Throttling'),
                'name' => t('zapi', 'Discard unchanged with heartbeat')
            ],
            PRS_PREPROC_PROMETHEUS_PATTERN => [
                'group' => t('zapi', 'Prometheus'),
                'name' => t('zapi', 'Prometheus pattern')
            ],
            PRS_PREPROC_PROMETHEUS_TO_JSON => [
                'group' => t('zapi', 'Prometheus'),
                'name' => t('zapi', 'Prometheus to JSON')
            ]
        ];
    }

    /**
     * Get either one or all item preprocessing types.
     * If $grouped set to true, returns group labels. Returns empty string if no specific type is found.
     *
     * Usage examples:
     *    - get_preprocessing_types(null, true, [5, 4, 2])             Returns array as defined.
     *    - get_preprocessing_types(4, true, [5, 4, 2])                Returns string: 'Trim'.
     *    - get_preprocessing_types(<wrong type>, true, [5, 4, 2])     Returns an empty string: ''.
     *    - get_preprocessing_types(null, false, [5, 12, 15, 16, 20])  Returns subarrays in one array maintaining index:
     *                                                                     [5] => Regular expression
     *                                                                     [12] => JSONPath
     *                                                                     [15] => Does not match regular expression
     *                                                                     [16] => Check for error in JSON
     *                                                                     [20] => Discard unchanged with heartbeat
     *
     * @param int   $type             Item preprocessing type.
     * @param bool  $grouped          Group label flag. If specific type is given, this parameter does not matter.
     * @param array $supported_types  Array of supported pre-processing types. If none are given, empty array is returned.
     *
     * @return array|string
     */
    public static function getPreprocessingTypes($type = null, $grouped = true, array $supported_types = [])
    {
        $types = static::preprocessingTypes();

        $filtered_types = [];

        foreach ($types as $_type => $data) {
            if (in_array($_type, $supported_types)) {
                $filtered_types[$data['group']][$_type] = $data['name'];
            }
        }

        $groups = [];

        foreach ($filtered_types as $label => $types) {
            $groups[] = [
                'label' => $label,
                'types' => $types
            ];
        }

        if ($type !== null) {
            foreach ($groups as $group) {
                if (array_key_exists($type, $group['types'])) {
                    return $group['types'][$type];
                }
            }

            return '';
        }
        elseif ($grouped) {
            return $groups;
        }
        else {
            $types = [];

            foreach ($groups as $group) {
                $types += $group['types'];
            }

            return $types;
        }
    }


    /**
     * Returns an array of allowed item types for "Check now" functionality.
     *
     * @return array
     */
    public static function checkNowAllowedTypes(): array
    {
        return [
            ITEM_TYPE_PERSEUS,
            ITEM_TYPE_SIMPLE,
            ITEM_TYPE_INTERNAL,
            ITEM_TYPE_EXTERNAL,
            ITEM_TYPE_DB_MONITOR,
            ITEM_TYPE_IPMI,
            ITEM_TYPE_SSH,
            ITEM_TYPE_TELNET,
            ITEM_TYPE_CALCULATED,
            ITEM_TYPE_JMX,
            ITEM_TYPE_DEPENDENT,
            ITEM_TYPE_HTTPAGENT,
            ITEM_TYPE_SNMP,
            ITEM_TYPE_SCRIPT
        ];
    }

    /**
     * Get sanitized item fields of given input.
     *
     * @param array  $input
     * @param string $input['templateid']
     * @param int    $input['flags']
     * @param int    $input['type']
     * @param string $input['key_']
     * @param int    $input['value_type']
     * @param int    $input['authtype']
     * @param int    $input['allow_traps']
     * @param int    $input['hosts'][0]['status']
     *
     * @return array
     */
    public static function getSanitizedItemFields(array $input): array
    {
        $field_names = self::getMainItemFieldNames($input);

        if ($input['flags'] != PRS_FLAG_DISCOVERY_CREATED) {
            $field_names = array_merge($field_names, self::getTypeItemFieldNames($input));
            $field_names = self::getConditionalItemFieldNames($field_names, $input);
        }

        return array_intersect_key($input, array_flip($field_names));
    }

    /**
     * Get main item fields of given input.
     *
     * @param array  $input
     * @param string $input['templateid']
     * @param int    $input['flags']
     *
     * @return array
     */
    public static function getMainItemFieldNames(array $input): array
    {
        switch ($input['flags']) {
            case PRS_FLAG_DISCOVERY_NORMAL:
                if ($input['templateid'] == 0) {
                    return ['name', 'type', 'key_', 'value_type', 'units', 'history', 'trends', 'valuemapid',
                        'inventory_link', 'logtimefmt', 'description', 'status', 'tags', 'preprocessing'
                    ];
                }
                else {
                    return ['history', 'trends', 'inventory_link', 'description', 'status', 'tags'];
                }

            case PRS_FLAG_DISCOVERY_PROTOTYPE:
                if ($input['templateid'] == 0) {
                    return ['name', 'type', 'key_', 'value_type', 'units', 'history', 'trends', 'valuemapid', 'logtimefmt',
                        'description', 'status', 'discover', 'tags', 'preprocessing'
                    ];
                }
                else {
                    return ['history', 'trends', 'description', 'status', 'discover', 'tags'];
                }

            case PRS_FLAG_DISCOVERY_CREATED:
                return ['status'];
        }
        return [];
    }

    /**
     * Get item field names of the given type and template ID.
     *
     * @param array  $input
     * @param string $input['templateid']
     * @param int    $input['type']
     */
    public static function getTypeItemFieldNames(array $input): array {
        switch ($input['type']) {
            case ITEM_TYPE_PERSEUS:
                return ['interfaceid', 'delay'];

            case ITEM_TYPE_TRAPPER:
                return ['trapper_hosts'];

            case ITEM_TYPE_SIMPLE:
                return ['interfaceid', 'username', 'password', 'delay'];

            case ITEM_TYPE_INTERNAL:
                return ['delay'];

            case ITEM_TYPE_PERSEUS_ACTIVE:
                return ['delay'];

            case ITEM_TYPE_EXTERNAL:
                return ['interfaceid', 'delay'];

            case ITEM_TYPE_DB_MONITOR:
                return ['username', 'password', 'params', 'delay'];

            case ITEM_TYPE_IPMI:
                if ($input['templateid'] == 0) {
                    return ['interfaceid', 'ipmi_sensor', 'delay'];
                }
                else {
                    return ['interfaceid', 'delay'];
                }

            case ITEM_TYPE_SSH:
                return ['interfaceid', 'authtype', 'username', 'publickey', 'privatekey', 'password', 'params', 'delay'];

            case ITEM_TYPE_TELNET:
                return ['interfaceid', 'username', 'password', 'params', 'delay'];

            case ITEM_TYPE_CALCULATED:
                return ['params', 'delay'];

            case ITEM_TYPE_JMX:
                if ($input['templateid'] == 0) {
                    return ['interfaceid', 'jmx_endpoint', 'username', 'password', 'delay'];
                }
                else {
                    return ['interfaceid', 'username', 'password', 'delay'];
                }

            case ITEM_TYPE_SNMPTRAP:
                return ['interfaceid'];

            case ITEM_TYPE_DEPENDENT:
                if ($input['templateid'] == 0) {
                    return ['master_itemid'];
                }

                return [];

            case ITEM_TYPE_HTTPAGENT:
                if ($input['templateid'] == 0) {
                    return ['url', 'query_fields', 'request_method', 'post_type', 'posts', 'headers', 'status_codes',
                        'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy', 'interfaceid', 'authtype',
                        'username', 'password', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file',
                        'ssl_key_password', 'timeout', 'delay', 'allow_traps', 'trapper_hosts'
                    ];
                }
                else {
                    return ['interfaceid', 'delay', 'allow_traps', 'trapper_hosts'];
                }

            case ITEM_TYPE_SNMP:
                if ($input['templateid'] == 0) {
                    return ['interfaceid', 'snmp_oid', 'delay'];
                }
                else {
                    return ['interfaceid', 'delay'];
                }

            case ITEM_TYPE_SCRIPT:
                if ($input['templateid'] == 0) {
                    return ['parameters', 'params', 'timeout', 'delay'];
                }
                else {
                    return ['delay'];
                }
        }
        return [];
    }

    /**
     * Get item field names excluding those that don't match a specific conditions.
     *
     * @param array  $field_names
     * @param array  $input
     * @param int    $input['type']
     * @param string $input['key_']
     * @param int    $input['value_type']
     * @param int    $input['authtype']
     * @param int    $input['allow_traps']
     * @param int    $input['hosts'][0]['status']
     *
     * @return array
     */
    public static function getConditionalItemFieldNames(array $field_names, array $input): array
    {
        return array_filter($field_names, static function ($field_name) use ($input): bool {
            switch ($field_name) {
                case 'units':
                case 'trends':
                    return in_array($input['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);

                case 'valuemapid':
                    return in_array($input['value_type'],
                        [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64]
                    );

                case 'inventory_link':
                    return in_array($input['value_type'],
                        [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT]
                    );

                case 'logtimefmt':
                    return $input['value_type'] == ITEM_VALUE_TYPE_LOG;

                case 'interfaceid':
                    return in_array($input['hosts'][0]['status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);

                case 'username':
                case 'password':
                    return $input['type'] != ITEM_TYPE_HTTPAGENT || in_array($input['authtype'],
                        [PRS_HTTP_AUTH_BASIC, PRS_HTTP_AUTH_NTLM, PRS_HTTP_AUTH_KERBEROS, PRS_HTTP_AUTH_DIGEST]
                    );

                case 'delay':
                    return $input['type'] != ITEM_TYPE_PERSEUS_ACTIVE || strncmp($input['key_'], 'mqtt.get', 8) != 0;

                case 'trapper_hosts':
                    return $input['type'] != ITEM_TYPE_HTTPAGENT || $input['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_ON;

                case 'publickey':
                case 'privatekey':
                    return $input['authtype'] == ITEM_AUTHTYPE_PUBLICKEY;
            }

            return true;
        });
    }

    /**
     * Create copies of items from the given sources to the given destination hosts or templates.
     *
     * If source type is 'templateids' or 'hostids', only non-inherited items are copied.
     *
     * If source type is 'itemids', all the given items are copied.
     *
     * @param string $src_type
     * @param array  $src_ids
     * @param array  $dst_hosts
     *
     * @return Result
     */
    public static function copyItemsToHosts(string $src_type, array $src_ids, array $dst_hosts): Result
    {
        $options = in_array($src_type, ['templateids', 'hostids']) ? ['inherited' => false] : [];

        if ($src_type === 'hostids') {
            $options['filter'] = ['flags' => PRS_FLAG_DISCOVERY_NORMAL];
        }

        $src_items = self::getItems([
            'output' => ['itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history', 'trends',
                'valuemapid', 'inventory_link', 'logtimefmt', 'description', 'status',

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
            $src_type => $src_ids,
            'preservekeys' => true
        ] + $options);

        if (!$src_items) {
            return Result::instance()->setSuccess();
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

        $dst_hostids = array_keys($dst_hosts);
        $valuemap_links = [];

        if ($src_valuemapids) {
            $src_valuemaps = ValueMapHelper::getValueMappings([
                'output' => ['valuemapid', 'name'],
                'valuemapids' => array_keys($src_valuemapids)
            ]);

            $dst_valuemaps = ValueMapHelper::getValueMappings([
                'output' => ['valuemapid', 'hostid', 'name'],
                'hostids' => $dst_hostids,
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
        $dst_is_template = reset($dst_hosts)['status'] == HOST_STATUS_TEMPLATE;

        if (!$dst_is_template) {
            $src_interfaces = [];

            if ($src_interfaceids) {
                $src_hosts = HostHelper::getHosts([
                    'output' => [],
                    'selectInterfaces' => ['interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
                    $src_type => $src_ids
                ]);

                foreach ($src_hosts as $src_host) {
                    foreach ($src_host['interfaces'] as $src_interface) {
                        if (array_key_exists($src_interface['interfaceid'], $src_interfaceids)) {
                            $src_interfaces[$src_interface['interfaceid']] =
                                array_diff_key($src_interface, array_flip(['interfaceid']));
                        }
                    }
                }
            }

            foreach ($dst_hosts as $dst_hostid => $dst_host) {
                foreach ($dst_host['interfaces'] as $dst_interface) {
                    $dst_interfaceid = $dst_interface['interfaceid'];
                    unset($dst_interface['interfaceid']);

                    foreach ($src_interfaces as $src_interfaceid => $src_interface) {
                        if ($src_interface == $dst_interface) {
                            $interface_links[$src_interfaceid][$dst_hostid] = $dst_interfaceid;
                        }
                    }

                    if ($dst_interface['main'] == INTERFACE_PRIMARY) {
                        $dst_interfaceids[$dst_hostid][$dst_interface['type']] = $dst_interfaceid;
                    }
                }
            }
        }

        $master_item_links = [];

        if ($dep_itemids) {
            $master_items = self::getItems([
                'output' => ['itemid', 'key_'],
                'itemids' => array_keys($dep_itemids),
                'webitems' => true
            ]);

            $options = $dst_is_template ? ['templateids' => $dst_hostids] : ['hostids' => $dst_hostids];

            $dst_master_items = self::getItems([
                'output' => ['itemid', 'hostid', 'key_'],
                'filter' => ['key_' => array_unique(array_column($master_items, 'key_'))],
                'webitems' => true
            ] + $options);

            $dst_master_itemids = [];

            foreach ($dst_master_items as $item) {
                $dst_master_itemids[$item['hostid']][$item['key_']] = $item['itemid'];
            }

            foreach ($master_items as $item) {
                foreach ($dst_hostids as $dst_hostid) {
                    if (array_key_exists($dst_hostid, $dst_master_itemids)
                            && array_key_exists($item['key_'], $dst_master_itemids[$dst_hostid])) {
                        $master_item_links[$item['itemid']][$dst_hostid] = $dst_master_itemids[$dst_hostid][$item['key_']];
                    }
                    else {
                        $src_itemid = reset($dep_itemids[$item['itemid']]);

                        $error = t('zapi', 'Cannot copy item with key "{src_key}" without its master item with key "{dst_key}".', [
                            'dst_key' => $src_items[$src_itemid]['key_'],
                            'src_key' => $item['key_']
                        ]);

                        return Result::instance()->setErrcode(60750204)->setErrmsg($error);
                    }
                }
            }
        }

        do {
            $dst_items = [];

            foreach ($dst_hosts as $dst_hostid => $dst_host) {
                foreach ($src_items as $src_item) {
                    $dst_item = array_diff_key($src_item, array_flip(['itemid']));

                    if ($src_item['valuemapid'] != 0) {
                        if (array_key_exists($src_item['valuemapid'], $valuemap_links)
                                && array_key_exists($dst_hostid, $valuemap_links[$src_item['valuemapid']])) {
                            $dst_item['valuemapid'] = $valuemap_links[$src_item['valuemapid']][$dst_hostid];
                        }
                        else {
                            $dst_item['valuemapid'] = 0;
                        }
                    }

                    $dst_item['interfaceid'] = 0;

                    if (!$dst_is_template) {
                        if (array_key_exists($src_item['interfaceid'], $interface_links)
                                && array_key_exists($dst_hostid, $interface_links[$src_item['interfaceid']])) {
                            $dst_item['interfaceid'] = $interface_links[$src_item['interfaceid']][$dst_hostid];
                        }
                        else {
                            $type = self::itemTypeInterface($src_item['type']);

                            if (in_array($type,
                                [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI]
                            )) {
                                if (array_key_exists($dst_hostid, $dst_interfaceids)
                                        && array_key_exists($type, $dst_interfaceids[$dst_hostid])) {
                                    $dst_item['interfaceid'] = $dst_interfaceids[$dst_hostid][$type];
                                }
                                else {
                                    $error = t('zapi', 'Cannot find host interface on "{host}" for item with key "{key}".',[
                                        'host' => $dst_host['host'],
                                        'key' => $src_item['key_']
                                    ]);
                                    
                                    return Result::instance()->setErrcode(60750204)->setErrmsg($error);
                                }
                            }
                        }
                    }

                    if ($src_item['type'] == ITEM_TYPE_DEPENDENT) {
                        $dst_item['master_itemid'] = $master_item_links[$src_item['master_itemid']][$dst_hostid];
                    }

                    $dst_items[] = ['hostid' => $dst_hostid] + self::getSanitizedItemFields([
                        'templateid' => 0,
                        'flags' => PRS_FLAG_DISCOVERY_NORMAL,
                        'hosts' => [$dst_host]
                    ] + $dst_item);
                }
            }

            $result = ItemAssist::instance()->create($dst_items);

            if ($result->isSuccess()) {
                return $result;
            }

            $response = $result->getData();

            $_src_items = [];

            if ($src_dep_items) {
                foreach ($dst_hostids as $dst_hostid) {
                    foreach ($src_items as $src_item) {
                        $dst_itemid = array_shift($response['itemids']);

                        if (array_key_exists($src_item['itemid'], $src_dep_items)) {
                            $master_item_links[$src_item['itemid']][$dst_hostid] = $dst_itemid;

                            $_src_items = array_merge($_src_items, $src_dep_items[$src_item['itemid']]);
                            unset($src_dep_items[$src_item['itemid']]);
                        }
                    }
                }
            }

            $src_items = $_src_items;
        } while ($src_items);

        return $result;
    }
}
