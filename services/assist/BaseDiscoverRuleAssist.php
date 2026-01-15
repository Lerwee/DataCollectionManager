<?php

namespace app\customs\zapi\services\assist;

use app\common\helpers\ArrayHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\common\macros\CMacrosResolverGeneral;
use app\customs\zapi\common\parsers\CIPRangeParser;
use app\customs\zapi\common\parsers\CItemKey;
use app\customs\zapi\common\parsers\CLLDMacroFunctionParser;
use app\customs\zapi\common\parsers\CLLDMacroParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CPrometheusOutputParser;
use app\customs\zapi\common\parsers\CPrometheusPatternParser;
use app\customs\zapi\common\parsers\CRangesParser;
use app\customs\zapi\common\parsers\CUpdateIntervalParser;
use app\customs\zapi\common\parsers\CUserMacroParser;
use app\customs\zapi\common\validators\CalcFormulaValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\TimeUnitValidator;
use app\customs\zapi\common\validators\Utf8StringsValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\UuidValidator;
use app\customs\zapi\common\validators\z\CLimitedSetValidator;
use app\customs\zapi\common\validators\z\object\CUpdateDiscoveredValidator;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Interfaces;
use app\modules\libzbx\models\Items;
use yii\base\Exception;
use yii\db\Query;

/**
 * Class BaseItemForm
 * @package app\customs\zapi\forms\item
 */
class BaseDiscoverRuleAssist extends BaseItemAssist
{
    const ERROR_INVALID_KEY = 'invalidKey';
    protected $fieldRules;

    public function __construct()
    {
        parent::__construct();

        // template - if templated item, value is taken from template item, cannot be changed on host
        // system - values should not be updated
        // host - value should be null for template items
        $this->fieldRules = [
            'uuid' => ['template' => 1],
            'type' => ['template' => 1],
            'snmp_oid' => ['template' => 1],
            'hostid' => [],
            'name' => ['template' => 1],
            'description' => [],
            'key_' => ['template' => 1],
            'master_itemid' => ['template' => 1],
            'delay' => [],
            'history' => [],
            'trends' => [],
            'status' => [],
            'discover' => [],
            'value_type' => ['template' => 1],
            'trapper_hosts' => [],
            'units' => ['template' => 1],
            'formula' => ['template' => 1],
            'error' => ['system' => 1],
            'lastlogsize' => ['system' => 1],
            'logtimefmt' => [],
            'templateid' => ['system' => 1],
            'valuemapid' => ['template' => 1],
            'params' => [],
            'ipmi_sensor' => ['template' => 1],
            'authtype' => [],
            'username' => [],
            'password' => [],
            'publickey' => [],
            'privatekey' => [],
            'mtime' => ['system' => 1],
            'flags' => [],
            'filter' => [],
            'interfaceid' => ['host' => 1],
            'inventory_link' => [],
            'lifetime' => [],
            'preprocessing' => ['template' => 1],
            'overrides' => ['template' => 1],
            'jmx_endpoint' => [],
            'url' => ['template' => 1],
            'timeout' => ['template' => 1],
            'query_fields' => ['template' => 1],
            'parameters' => ['template' => 1],
            'posts' => ['template' => 1],
            'status_codes' => ['template' => 1],
            'follow_redirects' => ['template' => 1],
            'post_type' => ['template' => 1],
            'http_proxy' => ['template' => 1],
            'headers' => ['template' => 1],
            'retrieve_mode' => ['template' => 1],
            'request_method' => ['template' => 1],
            'output_format' => ['template' => 1],
            'allow_traps' => [],
            'ssl_cert_file' => ['template' => 1],
            'ssl_key_file' => ['template' => 1],
            'ssl_key_password' => ['template' => 1],
            'verify_peer' => ['template' => 1],
            'verify_host' => ['template' => 1]
        ];
    }

    /**
     * Return first main interface matched from list of preferred types, or NULL.
     *
     * @param array $interfaces An array of interfaces to choose from.
     *
     * @return ?array
     */
    public static function findInterfaceByPriority(array $interfaces): ?array
    {
        $interface_by_type = [];

        foreach ($interfaces as $interface) {
            if ($interface['main'] == INTERFACE_PRIMARY) {
                $interface_by_type[$interface['type']] = $interface;
            }
        }

        foreach (self::INTERFACE_TYPES_BY_PRIORITY as $interface_type) {
            if (array_key_exists($interface_type, $interface_by_type)) {
                return $interface_by_type[$interface_type];
            }
        }

        return null;
    }

    /**
     * Returns the interface that best matches the given item.
     *
     * @param array $item_type An item type
     * @param array $interfaces An array of interfaces to choose from
     *
     * @return array|boolean    The best matching interface;
     *                            an empty array of no matching interface was found;
     *                            false, if the item does not need an interface
     */
    public static function findInterfaceForItem($item_type, array $interfaces)
    {
        $type = ItemHelper::itemTypeInterface($item_type);

        if ($type == INTERFACE_TYPE_OPT) {
            return false;
        } elseif ($type == INTERFACE_TYPE_ANY) {
            return self::findInterfaceByPriority($interfaces);
        } // the item uses a specific type of interface
        elseif ($type !== false) {
            $interface_by_type = [];

            foreach ($interfaces as $interface) {
                if ($interface['main'] == INTERFACE_PRIMARY) {
                    $interface_by_type[$interface['type']] = $interface;
                }
            }

            return array_key_exists($type, $interface_by_type) ? $interface_by_type[$type] : [];
        } // the item does not need an interface
        else {
            return false;
        }
    }

    /**
     * Updates the children of the item on the given hosts and propagates the inheritance to the child hosts.
     *
     * @param array $tpl_items An array of items to inherit.
     * @param array|null $hostids An array of hosts to inherit to; if set to null, the items will be inherited to all
     *                               linked hosts or templates.
     */
    protected function inherit(array $tpl_items, array $hostids = null)
    {
        $tpl_items = prs_toHash($tpl_items, 'itemid');

        // Inherit starting from common items and finishing up dependent.
        while ($tpl_items) {
            $_tpl_items = [];

            foreach ($tpl_items as $tpl_item) {
                if ($tpl_item['type'] != ITEM_TYPE_DEPENDENT
                    || !array_key_exists($tpl_item['master_itemid'], $tpl_items)) {
                    $_tpl_items[$tpl_item['itemid']] = $tpl_item;
                }
            }

            foreach ($_tpl_items as $itemid => $_tpl_item) {
                unset($tpl_items[$itemid]);
            }

            $this->_inherit($_tpl_items, $hostids);
        }
    }

    /**
     * @param array $tpl_items
     * @param array|null $hostids
     * @throws Exception
     * @throws ValidateException
     * @throws \yii\base\NotSupportedException
     * @throws \yii\db\Exception
     */
    private function _inherit(array $tpl_items, array $hostids = null)
    {
        // Prepare the child items.
        $new_items = $this->prepareInheritedItems($tpl_items, $hostids);
        if (!$new_items) {
            return;
        }

        $ins_items = [];
        $upd_items = [];

        foreach ($new_items as $new_item) {
            if (array_key_exists('itemid', $new_item)) {
                if ($this instanceof ItemPrototypeAssist) {
                    unset($new_item['ruleid']);
                }
                $upd_items[$new_item['itemid']] = $new_item;
            } else {
                $ins_items[] = $new_item;
            }
        }

        $this->validateDependentItems($new_items);

        // Save the new items.
        if ($ins_items) {
            $this->createReal($ins_items);
        }

        if ($upd_items) {
            $this->updateReal($upd_items);
        }

        $new_items = array_merge($upd_items, $ins_items);

        // Inheriting items from the templates.
        $db_items = (new Query())->select(['i.itemid'])
            ->from(['i' => 'items', 'h' => 'hosts'])
            ->where('i.hostid=h.hostid')
            ->andWhere(['i.itemid' => array_column($new_items, 'itemid')])
            ->andWhere(['h.status' => [HOST_STATUS_TEMPLATE]])
            ->all();

        $tpl_itemids = [];
        foreach ($db_items as $db_item) {
            $tpl_itemids[$db_item['itemid']] = true;
        }

        foreach ($new_items as $index => $new_item) {
            if (!array_key_exists($new_item['itemid'], $tpl_itemids)) {
                unset($new_items[$index]);
            }
        }

        $this->inherit($new_items);
    }


    /**
     * @param array $tpl_items
     * @param array $chd_hosts
     * @throws ValidateException
     */
    private function checkDoubleInheritedNames1(array $tpl_items, array $chd_hosts): void
    {
        $templateids = array_unique(array_column($tpl_items, 'hostid'));

        $tpl_links = [];

        foreach ($chd_hosts as $chd_host) {
            foreach ($chd_host['parentTemplates'] as $template) {
                if (!in_array($template['templateid'], $templateids)) {
                    continue;
                }

                $tpl_links[$template['templateid']][] = $chd_host['hostid'];
            }
        }

        $item_indexes = [];

        foreach ($tpl_items as $i => $tpl_item) {
            if (!array_key_exists($tpl_item['hostid'], $tpl_links)) {
                continue;
            }

            $item_indexes[$tpl_item['key_']][] = $i;
        }

        foreach ($item_indexes as $key => $indexes) {
            if (count($indexes) == 1) {
                continue;
            }

            $hostids = [];

            foreach ($indexes as $i) {
                $templateid = $tpl_items[$i]['hostid'];
                $same_hosts = array_intersect($tpl_links[$templateid], $hostids);

                if ($same_hosts) {
                    $hostid = reset($same_hosts);
                    self::exception(60750003, t('zai', 'Discovery rule "{key}" already exists on "{host}", inherited from another template.', ['key' => $key, 'host' => $chd_hosts[$hostid]['host']]));
                }

                $hostids = array_merge($hostids, $tpl_links[$templateid]);
            }
        }
    }


    /**
     * @param array $items
     * @param false $update
     * @throws Exception
     * @throws ValidateException
     * @throws \yii\db\Exception
     */
    protected function checkInput(array &$items, bool $update = false)
    {
        $fieldRules = [
            'type' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => implode(',', static::SUPPORTED_ITEM_TYPES)]
        ];
        if ($update) {
            unset($fieldRules['type']['flags']);
        }

        foreach ($items as $num => $item) {
            $data = array_intersect_key($item, $fieldRules);
            if (!ValidateHelper::validateObject($data, $fieldRules, ['_path' => '/' . ($num + 1)], $error)) {
                self::exception(60750003, $error);
            }
        }

        if ($update) {
            $itemDbFields = ['itemid' => null];

            $dbItemsFields = ['itemid', 'templateid'];
            foreach ($this->fieldRules as $field => $rule) {
                if (!isset($rule['system'])) {
                    $dbItemsFields[] = $field;
                }
            }

            $dbItems = DiscoverRuleHelper::getDiscoverRules([
                'output' => $dbItemsFields,
                'itemids' => prs_objectValues($items, 'itemid'),
                'editable' => true,
                'preservekeys' => true
            ]);

            $dbHosts = Hosts::find()->select(['hostid', 'status', 'name'])
                ->where(['hostid' => prs_objectValues($dbItems, 'hostid')])
                ->andWhere(['status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE]])
                ->indexBy('hostid')
                ->asArray()->all();
        } else {
            $itemDbFields = [
                'name' => null,
                'key_' => null,
                'hostid' => null,
                'type' => null,
                'value_type' => null,
                'delay' => null
            ];

            $dbItems = null;

            $dbHosts = Hosts::find()->select(['hostid', 'status', 'name'])
                ->where(['hostid' => prs_objectValues($items, 'hostid')])
                ->andWhere(['status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE]])
                ->indexBy('hostid')
                ->asArray()->all();

            $discovery_rules = [];

            if ($this instanceof ItemPrototypeAssist) {
                $itemDbFields['ruleid'] = null;
                $druleids = prs_objectValues($items, 'ruleid');

                if ($druleids) {
                    $discovery_rules = DiscoverRuleHelper::getDiscoverRules([
                        'output' => ['hostid'],
                        'itemids' => $druleids,
                        'preservekeys' => true
                    ]);
                }
            }
        }

        // interfaces
        $interfaces = Interfaces::find()->select(['interfaceid', 'hostid', 'type'])
            ->where(['hostid' => prs_objectValues($dbHosts, 'hostid')])
            ->indexBy('interfaceid')
            ->asArray()->all();

        if ($update) {
            $updateDiscoveredValidator = new CUpdateDiscoveredValidator([
                'allowed' => ['itemid', 'status'],
                'messageAllowedField' => t('zapi', 'Cannot update "%2$s" for a discovered item "%1$s".')
            ]);
            foreach ($items as &$item) {
                // check permissions
                if (!array_key_exists($item['itemid'], $dbItems)) {
                    self::exception(60750003, t('zapi', 'No permissions to referred object or it does not exist!'));
                }

                $dbItem = $dbItems[$item['itemid']];

                if (array_key_exists('hostid', $item) && bccomp($dbItem['hostid'], $item['hostid']) != 0) {
                    self::exception(60750003,
                        t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'hostid', 'error' => t('zapi', 'cannot be changed')])
                    );
                }

                $itemName = array_key_exists('name', $item) ? $item['name'] : $dbItem['name'];

                // discovered fields, except status, cannot be updated
                $updateDiscoveredValidator->setObjectName($itemName);
                $this->checkPartialValidator($item, $updateDiscoveredValidator, $dbItem);

                $item += [
                    'hostid' => $dbItem['hostid'],
                    'type' => $dbItem['type'],
                    'name' => $dbItem['name'],
                    'key_' => $dbItem['key_'],
                    'flags' => $dbItem['flags']
                ];
            }
            unset($item);
        } else {
            foreach ($items as &$item) {
                $item['flags'] = PRS_FLAG_DISCOVERY_RULE;
                unset($item['itemid']);
            }
            unset($item);
        }

        $item_key_parser = new CItemKey();
        $ip_range_parser = new CIPRangeParser([
            'v6' => PRS_HAVE_IPV6,
            'ranges' => false,
            'usermacros' => true,
            'macros' => [
                '{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'
            ]
        ]);
        $update_interval_parser = new CUpdateIntervalParser([
            'usermacros' => true,
            'lldmacros' => (get_class($this) === 'ItemPrototypeAssist')
        ]);

        $index = 0;
        foreach ($items as $inum => &$item) {
            $item = $this->clearValues($item);
            $index++;

            $fullItem = $items[$inum];

            if (!check_db_fields($itemDbFields, $item, true)) {
                self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
            }

            if ($update) {
                $type = array_key_exists('type', $item) ? $item['type'] : $dbItems[$item['itemid']]['type'];

                if ($type == ITEM_TYPE_HTTPAGENT) {
                    $this->validateHTTPCheck($fullItem, $dbItems[$item['itemid']]);
                }

                check_db_fields($dbItems[$item['itemid']], $fullItem, true);

                $this->checkNoParameters(
                    $item,
                    ['templateid', 'state', 'lastlogsize', 'mtime', 'error'],
                    t('zapi', 'Cannot update "%1$s" for item "%2$s".'),
                    $item['name']
                );

                $field_rules = $this->fieldRules;

                if ($fullItem['type'] == ITEM_TYPE_SCRIPT) {
                    $field_rules = [
                            'params' => ['template' => 1]
                        ] + $this->fieldRules;
                }

                // apply rules
                foreach ($field_rules as $field => $rules) {
                    if ((0 != $fullItem['templateid'] && isset($rules['template'])) || isset($rules['system'])) {
                        unset($item[$field]);

                        // For templated item and fields that should not be modified, use the value from DB.
                        if (array_key_exists($field, $dbItems[$item['itemid']])
                            && array_key_exists($field, $fullItem)) {
                            $fullItem[$field] = $dbItems[$item['itemid']][$field];
                        }
                    }
                }

                if (!isset($item['key_'])) {
                    $item['key_'] = $fullItem['key_'];
                }
                if (!isset($item['hostid'])) {
                    $item['hostid'] = $fullItem['hostid'];
                }

                // If a templated item is being assigned to an interface with a different type, ignore it.
                $itemInterfaceType = ItemHelper::itemTypeInterface($dbItems[$item['itemid']]['type']);

                if ($itemInterfaceType !== INTERFACE_TYPE_ANY && $itemInterfaceType !== INTERFACE_TYPE_OPT
                    && $fullItem['templateid']
                    && array_key_exists('interfaceid', $item) && array_key_exists($item['interfaceid'], $interfaces)
                    && $interfaces[$item['interfaceid']]['type'] != $itemInterfaceType) {

                    unset($item['interfaceid']);
                }
            } else {
                if ($fullItem['type'] == ITEM_TYPE_HTTPAGENT) {
                    $this->validateHTTPCheck($fullItem, []);
                }

                if (!isset($dbHosts[$item['hostid']])) {
                    self::exception(60750003, t('zapi', 'No permissions to referred object or it does not exist!'));
                }

                check_db_fields($itemDbFields, $fullItem, true);

                $this->checkNoParameters(
                    $item,
                    ['templateid', 'state'],
                    t('zapi', 'Cannot set "%1$s" for item "%2$s".'),
                    $item['name']
                );

                if ($this instanceof ItemPrototypeAssist && (!array_key_exists($fullItem['ruleid'], $discovery_rules)
                        || $discovery_rules[$fullItem['ruleid']]['hostid'] != $fullItem['hostid'])) {
                    self::exception(60750003, t('zapi', 'No permissions to referred object or it does not exist!'));
                }
            }

            if ($fullItem['type'] == ITEM_TYPE_CALCULATED) {
                $fieldRules = [
                    'params' => [CalcFormulaValidator::class, 'flags' => $this instanceof ItemPrototypeAssist ? API_ALLOW_LLD_MACRO : 0, 'length' => DB::getFieldLength('items', 'params')],
                    'value_type' => [Int32Validator::class, 'in' => implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT])]
                ];

                $data = array_intersect_key($item, $fieldRules);

                if (!ValidateHelper::validateObject($data, $fieldRules, ['_path' => '/' . ($inum + 1)], $error)) {
                    self::exception(60750003, $error);
                }
            }

            if ($fullItem['type'] == ITEM_TYPE_SCRIPT) {
                if ($update) {
                    if ($dbItems[$item['itemid']]['type'] == $fullItem['type']) {
                        $flags = API_NOT_EMPTY;
                    } else {
                        $flags = API_REQUIRED | API_NOT_EMPTY;
                    }
                } else {
                    $flags = API_REQUIRED | API_NOT_EMPTY;
                }

                $fieldRules = [
                    'params' => [Utf8StringValidator::class, 'flags' => $flags, 'length' => DB::getFieldLength('items', 'params')],
                    'timeout' => [
                        TimeUnitValidator::class, 'flags' => ($this instanceof ItemPrototypeAssist)
                            ? $flags | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO
                            : $flags | API_ALLOW_USER_MACRO,
                        'in' => '1:' . SEC_PER_MIN
                    ],
                    'parameters' => [ObjectsValidator::class, 'flags' => API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
                        'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_parameter', 'name')],
                        'value' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('item_parameter', 'value')]
                    ]]
                ];

                $data = array_intersect_key($item, $fieldRules);

                if (!ValidateHelper::validateObject($data, $fieldRules, ['_path' => '/' . ($inum + 1)], $error)) {
                    self::exception(60750003, $error);
                }
            }

            $host = $dbHosts[$fullItem['hostid']];

            // Validate update interval.
            if (!in_array($fullItem['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT])
                && ($fullItem['type'] != ITEM_TYPE_PERSEUS_ACTIVE || strncmp($fullItem['key_'], 'mqtt.get', 8) !== 0)
                && !ItemHelper::validateDelay($update_interval_parser, 'delay', $fullItem['delay'], $error)) {
                self::exception(60750003, $error);
            }

            // For non-numeric types, whichever value was entered in trends field, is overwritten to zero.
            if ($fullItem['value_type'] == ITEM_VALUE_TYPE_STR || $fullItem['value_type'] == ITEM_VALUE_TYPE_LOG
                || $fullItem['value_type'] == ITEM_VALUE_TYPE_TEXT) {
                $item['trends'] = '0';
            }

            // Check if the item requires an interface.
            if ($host['status'] == HOST_STATUS_TEMPLATE) {
                unset($item['interfaceid']);
            } else {
                $item_interface_type = ItemHelper::itemTypeInterface($fullItem['type']);

                if ($item_interface_type !== false) {
                    if (!array_key_exists('interfaceid', $fullItem) || !$fullItem['interfaceid']) {
                        if ($item_interface_type != INTERFACE_TYPE_OPT) {
                            self::exception(60750003, t('zapi', 'No interface found.'));
                        }
                    } elseif (!array_key_exists($fullItem['interfaceid'], $interfaces)
                        || bccomp($interfaces[$fullItem['interfaceid']]['hostid'], $fullItem['hostid']) != 0) {
                        self::exception(60750003, t('zapi', 'Item uses host interface from non-parent host.'));
                    } elseif ($item_interface_type !== INTERFACE_TYPE_ANY && $item_interface_type !== INTERFACE_TYPE_OPT
                        && $interfaces[$fullItem['interfaceid']]['type'] != $item_interface_type) {
                        self::exception(60750003, t('zapi', 'Item uses incorrect interface type.'));
                    }
                } // No interface required, just set it to zero.
                else {
                    $item['interfaceid'] = 0;
                }
            }

            // item key
            if ($fullItem['type'] == ITEM_TYPE_DB_MONITOR) {
                if ($fullItem['flags'] != PRS_FLAG_DISCOVERY_RULE) {
                    if (strcmp($fullItem['key_'], PRS_DEFAULT_KEY_DB_MONITOR) == 0) {
                        self::exception(60750003, t('zapi', 'Check the key, please. Default example was passed.'));
                    }
                } else {
                    if (strcmp($fullItem['key_'], PRS_DEFAULT_KEY_DB_MONITOR_DISCOVERY) == 0) {
                        self::exception(60750003, t('zapi', 'Check the key, please. Default example was passed.'));
                    }
                }
            } elseif (($fullItem['type'] == ITEM_TYPE_SSH && strcmp($fullItem['key_'], PRS_DEFAULT_KEY_SSH) == 0)
                || ($fullItem['type'] == ITEM_TYPE_TELNET && strcmp($fullItem['key_'], PRS_DEFAULT_KEY_TELNET) == 0)) {
                self::exception(60750003, t('zapi', 'Check the key, please. Default example was passed.'));
            }

            // key
            if ($item_key_parser->parse($fullItem['key_']) != CParser::PARSE_SUCCESS) {
                self::exception(60750003,
                    t('zapi', 'Invalid key "{key}" for discovery rule "{name}" on "{host}": {error}.', [
                        'key' => $fullItem['key_'], 'name' => $fullItem['name'], 'host' => $host['name'], 'error' => $item_key_parser->getError()
                    ])
                );
            }

            if (($fullItem['type'] == ITEM_TYPE_TRAPPER || $fullItem['type'] == ITEM_TYPE_HTTPAGENT)
                && array_key_exists('trapper_hosts', $fullItem) && $fullItem['trapper_hosts'] !== ''
                && !$ip_range_parser->parse($fullItem['trapper_hosts'])) {
                self::exception(60750003,
                    t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'trapper_hosts', 'error' => $ip_range_parser->getError()])
                );
            }

            // jmx
            if ($fullItem['type'] == ITEM_TYPE_JMX) {
                if (!array_key_exists('jmx_endpoint', $fullItem) && !$update) {
                    $item['jmx_endpoint'] = PRS_DEFAULT_JMX_ENDPOINT;
                }
                if (array_key_exists('jmx_endpoint', $fullItem) && $fullItem['jmx_endpoint'] === '') {
                    self::exception(60750003,
                        t('zapi', 'Incorrect value for field "{field}": {error}.', ['jmx_endpoint', t('zapi', 'cannot be empty')])
                    );
                }

                if (($fullItem['username'] === '') !== ($fullItem['password'] === '')) {
                    self::exception(60750003,
                        t('zapi', 'Incorrect value for field "{field}": {error}.', [
                            'field' => 'username',
                            'error' => t('zapi', 'both username and password should be either present or empty')
                        ])
                    );
                }
            } else {
                if (array_key_exists('jmx_endpoint', $item) && $item['jmx_endpoint'] !== '') {
                    self::exception(60750003,
                        t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'jmx_endpoint', 'error' => t('zapi', 'should be empty')])
                    );
                } elseif (array_key_exists('jmx_endpoint', $fullItem) && $fullItem['jmx_endpoint'] !== '') {
                    $item['jmx_endpoint'] = '';
                }
            }

            // Dependent item.
            if ($fullItem['type'] == ITEM_TYPE_DEPENDENT) {
                if ($update) {
                    if (array_key_exists('master_itemid', $item) && !$item['master_itemid']) {
                        self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                            ['field' => 'master_itemid', 'error' => t('zapi', 'cannot be empty')]
                        ));
                    }
                    if ($dbItems[$fullItem['itemid']]['type'] != ITEM_TYPE_DEPENDENT
                        && !array_key_exists('master_itemid', $item)) {
                        self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                            ['field' => 'master_itemid', 'error' => t('zapi', 'cannot be empty')]
                        ));
                    }
                } elseif (!array_key_exists('master_itemid', $item) || !$item['master_itemid']) {
                    self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                        ['field' => 'master_itemid', 'error' => t('zapi', 'cannot be empty')]
                    ));
                }
                if (array_key_exists('master_itemid', $item) && !is_int($item['master_itemid'])
                    && !(is_string($item['master_itemid']) && ctype_digit($item['master_itemid']))) {
                    self::exception(60750003, t('zapi', 'Incorrect value "{value}" for "{field}" field.',
                        ['value' => $item['master_itemid'], 'field' => 'master_itemid']
                    ));
                }
            } else {
                if (array_key_exists('master_itemid', $item) && $item['master_itemid']) {
                    self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                        ['field' => 'master_itemid', 'error' => t('zapi', 'should be empty')]
                    ));
                }
                $item['master_itemid'] = 0;
            }

            // ssh, telnet
            if ($fullItem['type'] == ITEM_TYPE_SSH || $fullItem['type'] == ITEM_TYPE_TELNET) {
                if ($fullItem['username'] === '') {
                    self::exception(60750003, t('zapi', 'No authentication user name specified.'));
                }

                if ($fullItem['type'] == ITEM_TYPE_SSH && $fullItem['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
                    if ($fullItem['publickey'] === '') {
                        self::exception(60750003, t('zapi', 'No public key file specified.'));
                    }
                    if ($fullItem['privatekey'] === '') {
                        self::exception(60750003, t('zapi', 'No private key file specified.'));
                    }
                }
            }

            // Prevent IPMI sensor field being empty if item key is not "ipmi.get".
            if ($fullItem['type'] == ITEM_TYPE_IPMI && $fullItem['key_'] !== 'ipmi.get'
                && (!array_key_exists('ipmi_sensor', $fullItem) || $fullItem['ipmi_sensor'] === '')) {
                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                    ['field' => 'ipmi_sensor', 'error' => t('zapi', 'cannot be empty')]
                ));
            }

            // snmp trap
            if ($fullItem['type'] == ITEM_TYPE_SNMPTRAP
                && $fullItem['key_'] !== 'snmptrap.fallback' && $item_key_parser->getKey() !== 'snmptrap') {
                self::exception(60750003, t('zapi', 'SNMP trap key is invalid.'));
            }

            // snmp oid
            if ($fullItem['type'] == ITEM_TYPE_SNMP
                && (!array_key_exists('snmp_oid', $fullItem) || $fullItem['snmp_oid'] === '')) {
                self::exception(60750003, t('zapi', 'No SNMP OID specified.'));
            }

            $this->checkSpecificFields($fullItem, $update ? 'update' : 'create');

            $this->validateItemPreprocessing($fullItem);
            $this->validateTags($item, '/' . $index);
        }
        unset($item);

        $this->validateValueMaps($items);

        self::validateUuid($items, $dbHosts);

        if (!$update) {
            self::addUuid1($items, $dbHosts);
        }

        self::checkUuidDuplicates($items, $dbItems);
        $this->checkExistingItems($items);
    }

    /**
     * @param array $items
     * @param array|null $db_items
     * @throws ValidateException
     */
    protected static function checkUuidDuplicates(array $items, ?array $db_items = null): void
    {
        $item_indexes = [];

        foreach ($items as $i => $item) {
            if (!array_key_exists('uuid', $item) || $item['uuid'] === '') {
                continue;
            }

            if ($db_items === null || $item['uuid'] !== $db_items[$item['itemid']]['uuid']) {
                $item_indexes[$item['uuid']] = $i;
            }
        }

        if (!$item_indexes) {
            return;
        }

        $duplicates = Items::find()->select(['uuid'])
            ->where(['flags' => PRS_FLAG_DISCOVERY_RULE, 'uuid' => array_keys($item_indexes)])
            ->limit(1)->one();

        if ($duplicates) {
            self::invalidAttrException('/' . ($item_indexes[$duplicates[0]['uuid']] + 1), t('zapi', 'LLD rule with the same UUID already exists'));
        }
    }

    /**
     * @param array $item
     * @param string $path
     * @throws ValidateException
     * @throws \yii\db\Exception
     */
    protected function validateTags(array $item, string $path = '/')
    {
        if (!array_key_exists('tags', $item)) {
            return;
        }

        $fieldRules = [
            'tags' => [ObjectsValidator::class, 'uniq' => [['tag', 'value']], 'fields' => [
                'tag' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_tag', 'tag')],
                'value' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('item_tag', 'value')]
            ]]
        ];

        $item_tags = ['tags' => $item['tags']];
        if (!ValidateHelper::validate($item_tags, $fieldRules, ['_path' => $path], $error)) {
            self::exception(60750003, $error);
        }
    }

    /**
     * @param array $items
     * @param array $db_hosts
     * @throws ValidateException
     * @throws Exception
     * @throws \yii\db\Exception
     */
    private static function validateUuid(array $items, array $db_hosts): void
    {
        foreach ($items as &$item) {
            $item['host_status'] = $db_hosts[$item['hostid']]['status'];
        }
        unset($item);

        $fieldRules = [
            'host_status' => ['safe'],
            'uuid' => [MultipleValidator::class, 'rules' => [
                UuidValidator::class, 'when' => function ($model) {
                    return $model->host_status == HOST_STATUS_TEMPLATE;
                }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'uuid'), 'unset' => true]
            ]
        ];

        if (!ValidateHelper::validateObjects($items, $fieldRules, ['_path' => '/', 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid']]], $error)) {
            self::exception(60750003, $error);
        }
    }

    /**
     * Add the UUID to those of the given items that belong to a template and don't have the 'uuid' parameter set.
     *
     * @param array $items
     * @param array $db_hosts
     */
    protected static function addUuid1(array &$items, array $db_hosts): void
    {
        foreach ($items as &$item) {
            if ($db_hosts[$item['hostid']]['status'] == HOST_STATUS_TEMPLATE && !array_key_exists('uuid', $item)) {
                $item['uuid'] = generateUuidV4();
            }
        }
        unset($item);
    }

    protected function clearValues(array $item)
    {
        if (isset($item['port']) && $item['port'] != '') {
            $item['port'] = ltrim($item['port'], '0');
            if ($item['port'] == '') {
                $item['port'] = 0;
            }
        }

        if (array_key_exists('type', $item) &&
            ($item['type'] == ITEM_TYPE_DEPENDENT || $item['type'] == ITEM_TYPE_TRAPPER
                || ($item['type'] == ITEM_TYPE_PERSEUS_ACTIVE && array_key_exists('key_', $item)
                    && strncmp($item['key_'], 'mqtt.get', 8) === 0))) {
            $item['delay'] = 0;
        }

        return $item;
    }

    /**
     * Prepares and returns an array of child items, inherited from items $tpl_items on the given hosts.
     *
     * @param array $tpl_items
     * @param string $tpl_items [<itemid>]['itemid']
     * @param string $tpl_items [<itemid>]['hostid']
     * @param string $tpl_items [<itemid>]['key_']
     * @param int $tpl_items [<itemid>]['type']
     * @param array $tpl_items [<itemid>]['preprocessing']                    (optional)
     * @param int $tpl_items [<itemid>]['preprocessing'][]['type']
     * @param string $tpl_items [<itemid>]['preprocessing'][]['params']
     * @param int $tpl_items [<itemid>]['flags']
     * @param string $tpl_items [<itemid>]['master_itemid']                    (optional)
     * @param mixed $tpl_items [<itemid>][<field_name>]                       (optional)
     * @param array|null $hostids
     *
     * @return array an array of unsaved child items
     * @throws ValidateException
     */
    private function prepareInheritedItems(array $tpl_items, array $hostids = null): array
    {
        $itemids_by_templateid = [];
        foreach ($tpl_items as $tpl_item) {
            $itemids_by_templateid[$tpl_item['hostid']][] = $tpl_item['itemid'];
        }

        // Fetch all child hosts.
        $query = Hosts::find()->alias('h')->select(['h.hostid', 'h.host', 'h.status']);
        if ($hostids) {
            $query->andWhere(['h.hostid' => (array)$hostids]);
        }
        if ($itemids_by_templateid) {
            $query->andWhere(['ht.templateid' => array_keys($itemids_by_templateid)])
                ->leftJoin(['ht' => 'hosts_templates'], 'h.hostid=ht.hostid');
        }
        $chd_hosts = $query
            ->andWhere(['status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE]])
            ->indexBy('hostid')
            ->asArray()->all();
        $interfaces = Interfaces::find()->select(['hostid', 'interfaceid', 'main', 'type'])
            ->where(['hostid' => array_keys($chd_hosts)])
            ->asArray()->all();
        $interfaces = ArrayHelper::index($interfaces, null, 'hostid');
        $hostTemplates = HostHelper::getHostParentTemplates(array_keys($chd_hosts));
        foreach ($chd_hosts as $hostId => &$host) {
            $host['parentTemplates'] = $hostTemplates[$hostId] ?? [];
            $host['interfaces'] = $interfaces[$hostId] ?? [];
        }
        unset($host);
        if (!$chd_hosts) {
            return [];
        }

        $this->checkDoubleInheritedNames1($tpl_items, $chd_hosts);

        $chd_items_tpl = [];
        $chd_items_key = [];

        // Preparing list of items by item templateid.
        $db_items = (new Query())->select(['i.itemid', 'i.hostid', 'i.type', 'i.key_', 'i.flags', 'i.templateid'])
            ->from(['i' => 'items'])
            ->where(['i.templateid' => prs_objectValues($tpl_items, 'itemid')])
            ->andFilterWhere($hostids !== null ? ['i.hostid' => $hostids] : [])
            ->all();

        foreach ($db_items as $db_item) {
            $hostid = $db_item['hostid'];
            unset($db_item['hostid']);

            $chd_items_tpl[$hostid][$db_item['templateid']] = $db_item;
        }

        $hostids_by_key = [];

        // Preparing list of items by item key.
        foreach ($chd_hosts as $chd_host) {
            $tpl_itemids = [];

            foreach ($chd_host['parentTemplates'] as $parent_template) {
                if (array_key_exists($parent_template['templateid'], $itemids_by_templateid)) {
                    $tpl_itemids = array_merge($tpl_itemids, $itemids_by_templateid[$parent_template['templateid']]);
                }
            }

            foreach ($tpl_itemids as $tpl_itemid) {
                if (!array_key_exists($chd_host['hostid'], $chd_items_tpl)
                    || !array_key_exists($tpl_itemid, $chd_items_tpl[$chd_host['hostid']])) {
                    $hostids_by_key[$tpl_items[$tpl_itemid]['key_']][] = $chd_host['hostid'];
                }
            }
        }

        foreach ($hostids_by_key as $key_ => $key_hostids) {
            $query = (new Query())
                ->select(['i.itemid', 'i.hostid', 'i.type', 'i.key_', 'i.flags', 'i.templateid'])
                ->from(['i' => 'items'])
                ->where(['i.hostid' => $key_hostids])
                ->where(['i.key_' => [$key_]]);
            if ($this instanceof ItemPrototypeAssist) {
                $query->addSelect(['ruleid' => 'id.parent_itemid'])
                    ->leftJoin(['id' => 'item_discovery'], 'i.itemid=id.itemid');
            }
            $db_items = $query->all();

            foreach ($db_items as $db_item) {
                $hostid = $db_item['hostid'];
                unset($db_item['hostid']);

                $chd_items_key[$hostid][$db_item['key_']] = $db_item;
            }
        }

        // List of the discovery rules.
        if ($this instanceof ItemPrototypeAssist) {
            // List of itemids without 'ruleid' property.
            $tpl_itemids = [];
            $tpl_ruleids = [];
            foreach ($tpl_items as $tpl_item) {
                if (!array_key_exists('ruleid', $tpl_item)) {
                    $tpl_itemids[] = $tpl_item['itemid'];
                } else {
                    $tpl_ruleids[$tpl_item['ruleid']] = true;
                }
            }

            if ($tpl_itemids) {
                $db_rules = (new Query())->select(['id.parent_itemid', 'id.itemid'])
                    ->from(['id' => 'item_discovery'])
                    ->where(['id.itemid' => $tpl_itemids])
                    ->all();

                foreach ($db_rules as $db_rule) {
                    $tpl_items[$db_rule['itemid']]['ruleid'] = $db_rule['parent_itemid'];
                    $tpl_ruleids[$db_rule['parent_itemid']] = true;
                }
            }

            $db_rules = (new Query())->select(['i.hostid', 'i.templateid', 'i.itemid'])
                ->from(['i' => 'items'])
                ->where(['i.templateid' => array_keys($tpl_ruleids)])
                ->andFilterWhere($hostids !== null ? ['i.hostid' => $hostids] : [])
                ->all();

            // List of child lld ruleids by child hostid and parent lld ruleid.
            $chd_ruleids = [];
            foreach ($db_rules as $db_rule) {
                $chd_ruleids[$db_rule['hostid']][$db_rule['templateid']] = $db_rule['itemid'];
            }
        }

        $new_items = [];
        // List of the updated item keys by hostid.
        $upd_hostids_by_key = [];

        foreach ($chd_hosts as $chd_host) {
            $tpl_itemids = [];

            foreach ($chd_host['parentTemplates'] as $parent_template) {
                if (array_key_exists($parent_template['templateid'], $itemids_by_templateid)) {
                    $tpl_itemids = array_merge($tpl_itemids, $itemids_by_templateid[$parent_template['templateid']]);
                }
            }

            foreach ($tpl_itemids as $tpl_itemid) {
                $tpl_item = $tpl_items[$tpl_itemid];

                $chd_item = null;

                // Update by templateid.
                if (array_key_exists($chd_host['hostid'], $chd_items_tpl)
                    && array_key_exists($tpl_item['itemid'], $chd_items_tpl[$chd_host['hostid']])) {
                    $chd_item = $chd_items_tpl[$chd_host['hostid']][$tpl_item['itemid']];

                    if ($tpl_item['key_'] !== $chd_item['key_']) {
                        $upd_hostids_by_key[$tpl_item['key_']][] = $chd_host['hostid'];
                    }
                } // Update by key.
                elseif (array_key_exists($chd_host['hostid'], $chd_items_key)
                    && array_key_exists($tpl_item['key_'], $chd_items_key[$chd_host['hostid']])) {
                    $chd_item = $chd_items_key[$chd_host['hostid']][$tpl_item['key_']];

                    // Check if an item of a different type with the same key exists.
                    if ($tpl_item['flags'] != $chd_item['flags']) {
                        $this->errorInheritFlags($chd_item['flags'], $chd_item['key_'], $chd_host['host']);
                    }

                    // Check if item already linked to another template.
                    if ($chd_item['templateid'] != 0 && bccomp($chd_item['templateid'], $tpl_item['itemid']) != 0) {
                        self::exception(60750003, t('zai', 'Discovery rule "{key}" already exists on "{host}", inherited from another template.', ['key' => $tpl_item['key_'], 'host' => $chd_host['host']]));
                    }

                    if ($this instanceof ItemPrototypeAssist) {
                        $chd_ruleid = $chd_ruleids[$chd_host['hostid']][$tpl_item['ruleid']];
                        if (bccomp($chd_item['ruleid'], $chd_ruleid) != 0) {
                            self::exception(60750003,
                                t('zapi', 'Item prototype "{key}" already exists on "{host}", linked to another rule.',
                                    ['key' => $chd_item['key_'], 'host' => $chd_host['host']]
                                )
                            );
                        }
                    }
                }

                // copying item
                $new_item = $tpl_item;
                $new_item['uuid'] = '';

                if ($chd_item !== null) {
                    $new_item['itemid'] = $chd_item['itemid'];

                    if ($new_item['type'] == ITEM_TYPE_HTTPAGENT) {
                        $new_item['interfaceid'] = null;
                    }
                } else {
                    unset($new_item['itemid']);
                    if ($this instanceof ItemPrototypeAssist) {
                        $new_item['ruleid'] = $chd_ruleids[$chd_host['hostid']][$tpl_item['ruleid']];
                    }
                }
                $new_item['hostid'] = $chd_host['hostid'];
                $new_item['templateid'] = $tpl_item['itemid'];

                if ($chd_host['status'] != HOST_STATUS_TEMPLATE) {
                    if ($chd_item === null || $new_item['type'] != $chd_item['type']) {
                        $interface = self::findInterfaceForItem($new_item['type'], $chd_host['interfaces']);

                        if ($interface) {
                            $new_item['interfaceid'] = $interface['interfaceid'];
                        } elseif ($interface !== false) {
                            self::exception(60750003, t('zapi', 'Cannot find host interface on "{host}" for item key "{key}".', ['key' => $new_item['key_'], 'host' => $chd_host['host']]));
                        }
                    }

                    if ($this instanceof ItemAssist || $this instanceof DiscoverRuleAssist) {
                        if (!array_key_exists('itemid', $new_item)) {
                            $new_item['rtdata'] = true;
                        }
                    }
                }

                if (array_key_exists('preprocessing', $new_item)) {
                    foreach ($new_item['preprocessing'] as $preprocessing) {
                        if ($chd_item) {
                            $preprocessing['itemid'] = $chd_item['itemid'];
                        } else {
                            unset($preprocessing['itemid']);
                        }
                    }
                }

                $new_items[] = $new_item;
            }
        }

        // Check if item with a new key already exists on the child host.
        if ($upd_hostids_by_key) {
            $sql_where = ['or'];
            foreach ($upd_hostids_by_key as $key => $hostids) {
                $sql_where[] = ['i.hostid' => $hostids, 'i.key_' => ZSqlHelper::zbxDbstr($key)];
            }

            $db_item = (new Query())->select(['i.hostid', 'i.key_'])
                ->from(['i' => 'items'])
                ->where($sql_where)
                ->one();

            if ($db_item) {
                self::exception(60750003, t('zapi', 'Discovery rule "{key}" already exists on "{host}".', ['key' => $db_item['key_'], 'host' => $chd_hosts[$db_item['hostid']]['host']]));
            }
        }

        return $this->prepareDependentItems($tpl_items, $new_items, $hostids);
    }

    /**
     * @param array $tpl_items
     * @param array $new_items
     * @param array|null $hostids
     * @return array
     */
    private function prepareDependentItems(array $tpl_items, array $new_items, array $hostids = null): array
    {
        $tpl_master_itemids = [];

        foreach ($tpl_items as $tpl_item) {
            if ($tpl_item['type'] == ITEM_TYPE_DEPENDENT) {
                $tpl_master_itemids[$tpl_item['master_itemid']] = true;
            }
        }

        if ($tpl_master_itemids) {
            $db_items = (new Query())->select(['i.itemid', 'i.hostid', 'i.templateid'])
                ->from(['i' => 'items'])
                ->where(['i.templateid' => array_keys($tpl_master_itemids)])
                ->andFilterWhere($hostids !== null ? ['i.hostid' => $hostids] : [])
                ->all();

            $master_links = [];

            foreach ($db_items as $db_item) {
                $master_links[$db_item['templateid']][$db_item['hostid']] = $db_item['itemid'];
            }

            foreach ($new_items as &$new_item) {
                if ($new_item['type'] == ITEM_TYPE_DEPENDENT) {
                    $tpl_item = $tpl_items[$new_item['templateid']];

                    if (array_key_exists('master_itemid', $tpl_item)) {
                        $new_item['master_itemid'] = $master_links[$tpl_item['master_itemid']][$new_item['hostid']];
                    }
                }
            }
            unset($new_item);
        }

        return $new_items;
    }

    /**
     * Validate item pre-processing.
     *
     * @param array $item An array of single item data.
     * @param array $item ['preprocessing']                            An array of item pre-processing data.
     * @param string $item ['preprocessing'][]['type']                  The preprocessing option type. Possible values:
     *                                                                  1 - PRS_PREPROC_MULTIPLIER;
     *                                                                  2 - PRS_PREPROC_RTRIM;
     *                                                                  3 - PRS_PREPROC_LTRIM;
     *                                                                  4 - PRS_PREPROC_TRIM;
     *                                                                  5 - PRS_PREPROC_REGSUB;
     *                                                                  6 - PRS_PREPROC_BOOL2DEC;
     *                                                                  7 - PRS_PREPROC_OCT2DEC;
     *                                                                  8 - PRS_PREPROC_HEX2DEC;
     *                                                                  9 - PRS_PREPROC_DELTA_VALUE;
     *                                                                  10 - PRS_PREPROC_DELTA_SPEED;
     *                                                                  11 - PRS_PREPROC_XPATH;
     *                                                                  12 - PRS_PREPROC_JSONPATH;
     *                                                                  13 - PRS_PREPROC_VALIDATE_RANGE;
     *                                                                  14 - PRS_PREPROC_VALIDATE_REGEX;
     *                                                                  15 - PRS_PREPROC_VALIDATE_NOT_REGEX;
     *                                                                  16 - PRS_PREPROC_ERROR_FIELD_JSON;
     *                                                                  17 - PRS_PREPROC_ERROR_FIELD_XML;
     *                                                                  18 - PRS_PREPROC_ERROR_FIELD_REGEX;
     *                                                                  19 - PRS_PREPROC_THROTTLE_VALUE;
     *                                                                  20 - PRS_PREPROC_THROTTLE_TIMED_VALUE;
     *                                                                  21 - PRS_PREPROC_SCRIPT;
     *                                                                  22 - PRS_PREPROC_PROMETHEUS_PATTERN;
     *                                                                  23 - PRS_PREPROC_PROMETHEUS_TO_JSON;
     *                                                                  24 - PRS_PREPROC_CSV_TO_JSON;
     *                                                                  25 - PRS_PREPROC_STR_REPLACE;
     *                                                                  26 - PRS_PREPROC_VALIDATE_NOT_SUPPORTED;
     * @param string $item ['preprocessing'][]['params']                Additional parameters used by preprocessing
     *                                                                 option. Multiple parameters are separated by LF
     *                                                                 (\n) character.
     * @param string $item ['preprocessing'][]['error_handler']         Action type used in case of preprocessing step
     *                                                                 failure. Possible values:
     *                                                                  0 - PRS_PREPROC_FAIL_DEFAULT;
     *                                                                  1 - PRS_PREPROC_FAIL_DISCARD_VALUE;
     *                                                                  2 - PRS_PREPROC_FAIL_SET_VALUE;
     *                                                                  3 - PRS_PREPROC_FAIL_SET_ERROR.
     * @param string $item ['preprocessing'][]['error_handler_params']  Error handler parameters.
     * @throws ValidateException|Exception
     */
    protected function validateItemPreprocessing(array $item)
    {
        if (array_key_exists('preprocessing', $item)) {
            if (!is_array($item['preprocessing'])) {
                self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
            }

            $type_validator = new CLimitedSetValidator(['values' => static::SUPPORTED_PREPROCESSING_TYPES]);

            $error_handler_validator = new CLimitedSetValidator([
                'values' => [PRS_PREPROC_FAIL_DEFAULT, PRS_PREPROC_FAIL_DISCARD_VALUE, PRS_PREPROC_FAIL_SET_VALUE,
                    PRS_PREPROC_FAIL_SET_ERROR
                ]
            ]);

            $unsupported_error_handler_validator = new CLimitedSetValidator([
                'values' => [PRS_PREPROC_FAIL_DISCARD_VALUE, PRS_PREPROC_FAIL_SET_VALUE, PRS_PREPROC_FAIL_SET_ERROR]
            ]);

            $prometheus_pattern_parser = new CPrometheusPatternParser(['usermacros' => true,
                'lldmacros' => ($this instanceof ItemPrototypeAssist)
            ]);
            $prometheus_output_parser = new CPrometheusOutputParser(['usermacros' => true,
                'lldmacros' => ($this instanceof ItemPrototypeAssist)
            ]);

            $required_fields = ['type', 'params', 'error_handler', 'error_handler_params'];
            $delta = false;
            $throttling = false;
            $prometheus = false;

            foreach ($item['preprocessing'] as $preprocessing) {
                $missing_keys = array_diff($required_fields, array_keys($preprocessing));

                if ($missing_keys) {
                    self::exception(60750003,
                        t('zapi', 'Item pre-processing is missing parameters: {param}', ['param' => implode(', ', $missing_keys)])
                    );
                }

                if (is_array($preprocessing['type'])) {
                    self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                } elseif ($preprocessing['type'] === '' || $preprocessing['type'] === null
                    || $preprocessing['type'] === false) {
                    self::exception(60750003,
                        t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'type', 'error' => t('zapi', 'cannot be empty')])
                    );
                }

                if (!$type_validator->validate($preprocessing['type'])) {
                    self::exception(60750003,
                        t('zapi', 'Incorrect value for field "{field}": {error}.', [
                                'field' => 'type',
                                'error' => t('zapi', 'unexpected value "{value}"', ['value' => $preprocessing['type']])
                            ]
                        )
                    );
                }

                if (array_key_exists('params', $preprocessing) && $preprocessing['params'] !== null) {
                    $preprocessing['params'] = str_replace("\r\n", "\n", $preprocessing['params']);
                }

                switch ($preprocessing['type']) {
                    case PRS_PREPROC_MULTIPLIER:
                        // Check if custom multiplier is a valid number.
                        $params = $preprocessing['params'];

                        if (is_array($params)) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif ($params === '' || $params === null || $params === false) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'cannot be empty')])
                            );
                        }

                        if (is_numeric($params)) {
                            break;
                        }

                        $types = ['usermacros' => true];

                        if ($this instanceof ItemPrototypeAssist) {
                            $types['lldmacros'] = true;
                        }

                        if (!CMacrosResolverGeneral::getMacroPositions($params, $types)) {
                            self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                ['field' => 'params', 'error' => t('zapi', 'a numeric value is expected')]
                            ));
                        }
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
                    case PRS_PREPROC_SCRIPT:
                        // Check 'params' if not empty.
                        if (is_array($preprocessing['params'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif ($preprocessing['params'] === '' || $preprocessing['params'] === null
                            || $preprocessing['params'] === false) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'cannot be empty')])
                            );
                        }
                        break;

                    case PRS_PREPROC_REGSUB:
                    case PRS_PREPROC_ERROR_FIELD_REGEX:
                    case PRS_PREPROC_STR_REPLACE:
                        // Check if 'params' are not empty and if second parameter contains (after \n) is not empty.
                        if (is_array($preprocessing['params'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif ($preprocessing['params'] === '' || $preprocessing['params'] === null
                            || $preprocessing['params'] === false) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'cannot be empty')])
                            );
                        }

                        $params = explode("\n", $preprocessing['params']);

                        if ($params[0] === '') {
                            self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                ['field' => 'params', 'error' => t('zapi', 'first parameter is expected')]
                            ));
                        }

                        if (($preprocessing['type'] == PRS_PREPROC_REGSUB
                                || $preprocessing['type'] == PRS_PREPROC_ERROR_FIELD_REGEX)
                            && (!array_key_exists(1, $params) || $params[1] === '')) {
                            self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                ['field' => 'params', 'error' => t('zapi', 'second parameter is expected')]
                            ));
                        }
                        break;

                    case PRS_PREPROC_VALIDATE_RANGE:
                        if (is_array($preprocessing['params'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif (trim($preprocessing['params']) === '' || $preprocessing['params'] === null
                            || $preprocessing['params'] === false) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'cannot be empty')])
                            );
                        }

                        $params = explode("\n", $preprocessing['params']);

                        if ($params[0] !== '' && !is_numeric($params[0])
                            && (new CUserMacroParser())->parse($params[0]) != CParser::PARSE_SUCCESS
                            && (!($this instanceof ItemPrototypeAssist)
                                || ((new CLLDMacroFunctionParser())->parse($params[0]) != CParser::PARSE_SUCCESS
                                    && (new CLLDMacroParser())->parse($params[0]) != CParser::PARSE_SUCCESS))) {
                            self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                ['field' => 'params', 'error' => t('zapi', 'a numeric value is expected')]
                            ));
                        }

                        if ($params[1] !== '' && !is_numeric($params[1])
                            && (new CUserMacroParser())->parse($params[1]) != CParser::PARSE_SUCCESS
                            && (!($this instanceof ItemPrototypeAssist)
                                || ((new CLLDMacroFunctionParser())->parse($params[1]) != CParser::PARSE_SUCCESS
                                    && (new CLLDMacroParser())->parse($params[1]) != CParser::PARSE_SUCCESS))) {
                            self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                ['field' => 'params', 'error' => t('zapi', 'a numeric value is expected')]
                            ));
                        }

                        if (is_numeric($params[0]) && is_numeric($params[1]) && $params[0] > $params[1]) {
                            self::exception(60750003, t('zapi',
                                'Incorrect value for field "{field}": {error}.',
                                [
                                    'field' => 'params',
                                    'error' => t('zapi', '"{value1}" value must be less than or equal to "{value2}" value', ['value1' => t('zapi', 'min'), 'value2' => t('zapi', 'max')])
                                ]
                            ));
                        }
                        break;

                    case PRS_PREPROC_BOOL2DEC:
                    case PRS_PREPROC_OCT2DEC:
                    case PRS_PREPROC_HEX2DEC:
                    case PRS_PREPROC_THROTTLE_VALUE:
                        // Check if 'params' is empty, because it must be empty.
                        if (is_array($preprocessing['params'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif ($preprocessing['params'] !== '' && $preprocessing['params'] !== null
                            && $preprocessing['params'] !== false) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'should be empty')])
                            );
                        }

                        if ($preprocessing['type'] == PRS_PREPROC_THROTTLE_VALUE) {
                            if ($throttling) {
                                self::exception(60750003, t('zapi', 'Only one throttling step is allowed.'));
                            } else {
                                $throttling = true;
                            }
                        }
                        break;

                    case PRS_PREPROC_DELTA_VALUE:
                    case PRS_PREPROC_DELTA_SPEED:
                    case PRS_PREPROC_XML_TO_JSON:
                        // Check if 'params' is empty, because it must be empty.
                        if (is_array($preprocessing['params'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif ($preprocessing['params'] !== '' && $preprocessing['params'] !== null
                            && $preprocessing['params'] !== false) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'should be empty')])
                            );
                        }

                        if ($preprocessing['type'] == PRS_PREPROC_DELTA_VALUE
                            || $preprocessing['type'] == PRS_PREPROC_DELTA_SPEED) {
                            // Check if one of the deltas (Delta per second or Delta value) already exists.
                            if ($delta) {
                                self::exception(60750003, t('zapi', 'Only one change step is allowed.'));
                            } else {
                                $delta = true;
                            }
                        }
                        break;

                    case PRS_PREPROC_THROTTLE_TIMED_VALUE:
                        $api_input_rules = [
                            'params' => [
                                TimeUnitValidator::class,
                                'flags' => ($this instanceof ItemAssist)
                                    ? API_NOT_EMPTY | API_ALLOW_USER_MACRO
                                    : API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO,
                                'in' => '1:' . PRS_MAX_TIMESHIFT
                            ]
                        ];

                        if (!ValidateHelper::validateObject($preprocessing, $api_input_rules, ['flags' => API_ALLOW_UNEXPECTED], $error)) {
                            self::exception(60750003, $error);
                        }

                        if ($throttling) {
                            self::exception(60750003, t('zapi', 'Only one throttling step is allowed.'));
                        } else {
                            $throttling = true;
                        }
                        break;

                    case PRS_PREPROC_PROMETHEUS_PATTERN:
                    case PRS_PREPROC_PROMETHEUS_TO_JSON:
                        if ($prometheus) {
                            self::exception(60750003, t('zapi', 'Only one Prometheus step is allowed.'));
                        }

                        $prometheus = true;

                        if (is_array($preprocessing['params'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        }

                        if ($preprocessing['type'] == PRS_PREPROC_PROMETHEUS_PATTERN) {
                            if ($preprocessing['params'] === '' || $preprocessing['params'] === null
                                || $preprocessing['params'] === false) {
                                self::exception(60750003,
                                    t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'cannot be empty')])
                                );
                            }

                            $params = explode("\n", $preprocessing['params']);

                            if ($params[0] === '') {
                                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                    ['field' => 'params', 'error' => t('zapi', 'first parameter is expected')]
                                ));
                            } elseif (!array_key_exists(1, $params)) {
                                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                    ['field' => 'params', 'error' => t('zapi', 'second parameter is expected')]
                                ));
                            } elseif (!array_key_exists(2, $params)
                                && ($params[1] === PRS_PREPROC_PROMETHEUS_LABEL
                                    || $params[1] === PRS_PREPROC_PROMETHEUS_FUNCTION)) {
                                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                    ['field' => 'params', 'error' => t('zapi', 'third parameter is expected')]
                                ));
                            }

                            if ($prometheus_pattern_parser->parse($params[0]) != CParser::PARSE_SUCCESS) {
                                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                    ['field' => 'params', 'error' => t('zapi', 'invalid Prometheus pattern')]
                                ));
                            }

                            if (!in_array($params[1], [PRS_PREPROC_PROMETHEUS_VALUE, PRS_PREPROC_PROMETHEUS_LABEL,
                                PRS_PREPROC_PROMETHEUS_FUNCTION])) {
                                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                    ['field' => 'params', 'error' => t('zapi', 'invalid aggregation method')]
                                ));
                            }

                            switch ($params[1]) {
                                case PRS_PREPROC_PROMETHEUS_VALUE:
                                    if (array_key_exists(2, $params) && $params[2] !== '') {
                                        self::exception(60750003,
                                            t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'invalid Prometheus output')])
                                        );
                                    }
                                    break;

                                case PRS_PREPROC_PROMETHEUS_LABEL:
                                    if ($prometheus_output_parser->parse($params[2]) != CParser::PARSE_SUCCESS) {
                                        self::exception(60750003,
                                            t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'invalid Prometheus output')])
                                        );
                                    }
                                    break;

                                case PRS_PREPROC_PROMETHEUS_FUNCTION:
                                    if (!in_array($params[2], [PRS_PREPROC_PROMETHEUS_SUM, PRS_PREPROC_PROMETHEUS_MIN,
                                        PRS_PREPROC_PROMETHEUS_MAX, PRS_PREPROC_PROMETHEUS_AVG,
                                        PRS_PREPROC_PROMETHEUS_COUNT])) {
                                        self::exception(60750003,
                                            t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'unsupported Prometheus function')])
                                        );
                                    }
                                    break;
                            }
                        } // Prometheus to JSON can be empty and has only one parameter.
                        elseif ($preprocessing['params'] !== '') {
                            if ($prometheus_pattern_parser->parse($preprocessing['params']) != CParser::PARSE_SUCCESS) {
                                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                    ['field' => 'params', 'error' => t('zapi', 'invalid Prometheus pattern')]
                                ));
                            }
                        }
                        break;

                    case PRS_PREPROC_CSV_TO_JSON:
                        if (is_array($preprocessing['params'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif ($preprocessing['params'] === '' || $preprocessing['params'] === null
                            || $preprocessing['params'] === false) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'cannot be empty')])
                            );
                        }

                        $params = explode("\n", $preprocessing['params']);

                        $params_cnt = count($params);
                        if ($params_cnt > 3) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif ($params_cnt == 1) {
                            self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                ['field' => 'params', 'error' => t('zapi', 'second parameter is expected')]
                            ));
                        } elseif ($params_cnt == 2) {
                            self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                ['field' => 'params', 'error' => t('zapi', 'third parameter is expected')]
                            ));
                        } else {
                            // Correct amount of parameters, but check if they are valid.

                            if (mb_strlen($params[0]) > 1) {
                                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                    ['field' => 'params', 'error' => t('zapi', 'value of first parameter is too long')]
                                ));
                            }

                            if (mb_strlen($params[1]) > 1) {
                                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                    ['field' => 'params', 'error' => t('zapi', 'value of second parameter is too long')]
                                ));
                            }

                            $with_header_row_validator = new CLimitedSetValidator([
                                'values' => [PRS_PREPROC_CSV_NO_HEADER, PRS_PREPROC_CSV_HEADER]
                            ]);

                            if (!$with_header_row_validator->validate($params[2])) {
                                self::exception(60750003,
                                    t('zapi', 'Incorrect value for field "{field}": {error}.', [
                                        'field' => 'params',
                                        'error' => t('zapi', 'value of third parameter must be one of {list}', ['list' => implode(', ', [PRS_PREPROC_CSV_NO_HEADER, PRS_PREPROC_CSV_HEADER])])
                                    ])
                                );
                            }
                        }
                        break;

                    case PRS_PREPROC_VALIDATE_NOT_SUPPORTED:
                        // Check if 'params' is empty, because it must be empty.
                        if (is_array($preprocessing['params'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif ($preprocessing['params'] !== '' && $preprocessing['params'] !== null
                            && $preprocessing['params'] !== false) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'should be empty')])
                            );
                        }

                        $preprocessing_types = array_column($item['preprocessing'], 'type');

                        if (count(array_keys($preprocessing_types, PRS_PREPROC_VALIDATE_NOT_SUPPORTED)) > 1) {
                            self::exception(60750003,
                                t('zapi', 'Only one not supported value check is allowed.')
                            );
                        }
                        break;

                    case PRS_PREPROC_SNMP_WALK_VALUE:
                        if (is_array($preprocessing['params'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif ($preprocessing['params'] === '' || $preprocessing['params'] === null
                            || $preprocessing['params'] === false) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'cannot be empty')])
                            );
                        }

                        $params = explode("\n", $preprocessing['params']);

                        if ($params[0] === '') {
                            self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                ['field' => 'params', 'error' => t('zapi', 'first parameter is expected')]
                            ));
                        }

                        if (!array_key_exists(1, $params) || $params[1] === '') {
                            self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                                ['field' => 'params', 'error' => t('zapi', 'second parameter is expected')]
                            ));
                        }

                        if (!in_array($params[1], [PRS_PREPROC_SNMP_UNCHANGED, PRS_PREPROC_SNMP_UTF8_FROM_HEX,
                            PRS_PREPROC_SNMP_MAC_FROM_HEX, PRS_PREPROC_SNMP_INT_FROM_BITS])) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'incorrect value')])
                            );
                        }
                        break;

                    case PRS_PREPROC_SNMP_WALK_TO_JSON:
                        $params = explode("\n", $preprocessing['params']);

                        $api_input_rules = ['params' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY | API_NORMALIZE,
                            'length' => 255
                        ]];

                        $var = ['params' => $params];
                        if (!ValidateHelper::validateObject($var, $api_input_rules, [], $error)) {
                            self::exception(60750003, $error);
                        }

                        if (count($params) % 3 !== 0) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'cannot be empty')])
                            );
                        }

                        for ($n = 1; $n <= count($params); $n++) {
                            $param = $params[$n - 1];

                            if ($param === '') {
                                self::exception(60750003,
                                    t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'cannot be empty')])
                                );
                            }

                            // Field "Treat as" every 3rd value. Check that field is correct.
                            if ($n % 3 === 0) {
                                if (!in_array($param, [PRS_PREPROC_SNMP_UNCHANGED, PRS_PREPROC_SNMP_UTF8_FROM_HEX,
                                    PRS_PREPROC_SNMP_MAC_FROM_HEX, PRS_PREPROC_SNMP_INT_FROM_BITS])) {
                                    self::exception(60750003,
                                        t('zapi', 'Incorrect value for field "{field}": {error}.', ['field' => 'params', 'error' => t('zapi', 'incorrect value')])
                                    );
                                }
                            }
                        }
                        break;
                }

                switch ($preprocessing['type']) {
                    case PRS_PREPROC_RTRIM:
                    case PRS_PREPROC_LTRIM:
                    case PRS_PREPROC_TRIM:
                    case PRS_PREPROC_THROTTLE_VALUE:
                    case PRS_PREPROC_THROTTLE_TIMED_VALUE:
                    case PRS_PREPROC_SCRIPT:
                    case PRS_PREPROC_STR_REPLACE:
                        if (is_array($preprocessing['error_handler'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif ($preprocessing['error_handler'] != PRS_PREPROC_FAIL_DEFAULT) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', [
                                    'field' => 'error_handler',
                                    'error' => t('zapi', 'unexpected value "{value}"', ['value' => $preprocessing['error_handler']])
                                ])
                            );
                        }

                        if (is_array($preprocessing['error_handler_params'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif ($preprocessing['error_handler_params'] !== ''
                            && $preprocessing['error_handler_params'] !== null
                            && $preprocessing['error_handler_params'] !== false) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', [
                                    'field' => 'error_handler_params',
                                    'error' => t('zapi', 'should be empty')
                                ])
                            );
                        }
                        break;

                    case PRS_PREPROC_VALIDATE_NOT_SUPPORTED:
                        if (is_array($preprocessing['error_handler'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif (!$unsupported_error_handler_validator->validate($preprocessing['error_handler'])) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', [
                                    'field' => 'error_handler',
                                    'error' => t('zapi', 'unexpected value "{value}"', ['value' => $preprocessing['error_handler']])
                                ])
                            );
                        }

                        if (is_array($preprocessing['error_handler_params'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif ($preprocessing['error_handler'] == PRS_PREPROC_FAIL_DISCARD_VALUE
                            && $preprocessing['error_handler_params'] !== ''
                            && $preprocessing['error_handler_params'] !== null
                            && $preprocessing['error_handler_params'] !== false) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', [
                                    'field' => 'error_handler_params',
                                    'error' => t('zapi', 'should be empty')
                                ])
                            );
                        } elseif ($preprocessing['error_handler'] == PRS_PREPROC_FAIL_SET_ERROR
                            && ($preprocessing['error_handler_params'] === ''
                                || $preprocessing['error_handler_params'] === null
                                || $preprocessing['error_handler_params'] === false)) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', [
                                    'field' => 'error_handler_params',
                                    'error' => t('zapi', 'cannot be empty')
                                ])
                            );
                        }
                        break;

                    default:
                        if (is_array($preprocessing['error_handler'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif (!$error_handler_validator->validate($preprocessing['error_handler'])) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', [
                                    'field' => 'error_handler',
                                    'error' => t('zapi', 'unexpected value "{value}"', ['value' => $preprocessing['error_handler']])
                                ])
                            );
                        }

                        if (is_array($preprocessing['error_handler_params'])) {
                            self::exception(60750003, t('zapi', 'Incorrect arguments passed to function.'));
                        } elseif (($preprocessing['error_handler'] == PRS_PREPROC_FAIL_DEFAULT
                                || $preprocessing['error_handler'] == PRS_PREPROC_FAIL_DISCARD_VALUE)
                            && $preprocessing['error_handler_params'] !== ''
                            && $preprocessing['error_handler_params'] !== null
                            && $preprocessing['error_handler_params'] !== false) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', [
                                    'field' => 'error_handler_params',
                                    'error' => t('zapi', 'should be empty')
                                ])
                            );
                        } elseif ($preprocessing['error_handler'] == PRS_PREPROC_FAIL_SET_ERROR
                            && ($preprocessing['error_handler_params'] === ''
                                || $preprocessing['error_handler_params'] === null
                                || $preprocessing['error_handler_params'] === false)) {
                            self::exception(60750003,
                                t('zapi', 'Incorrect value for field "{field}": {error}.', [
                                    'field' => 'error_handler_params',
                                    'error' => t('zapi', 'cannot be empty')
                                ])
                            );
                        }
                }
            }
        }
    }

    /**
     * Method validates preprocessing steps independently from other item properties.
     *
     * @param array $preprocessing_steps An array of item pre-processing step details.
     *                                       See self::validateItemPreprocessing for details.
     *
     * @return bool|string
     */
    public function validateItemPreprocessingSteps(array $preprocessing_steps)
    {
        try {
            $this->validateItemPreprocessing(['preprocessing' => $preprocessing_steps]);

            return true;
        } catch (Exception $error) {
            return $error->getMessage();
        }
    }

    /**
     * Insert item pre-processing data into DB.
     *
     * @param array $items An array of items.
     * @param string $items []['itemid']
     * @param array $items []['preprocessing']  An array of item pre-processing data.
     */
    protected function createItemPreprocessing(array $items)
    {
        $item_preproc = [];

        foreach ($items as $item) {
            if (array_key_exists('preprocessing', $item)) {
                $item['preprocessing'] = $this->normalizeItemPreprocessingSteps($item['preprocessing']);
                $step = 1;

                foreach ($item['preprocessing'] as $preprocessing) {
                    $item_preproc[] = [
                        'itemid' => $item['itemid'],
                        'step' => ($preprocessing['type'] == PRS_PREPROC_VALIDATE_NOT_SUPPORTED) ? 0 : $step++,
                        'type' => $preprocessing['type'],
                        'params' => $preprocessing['params'],
                        'error_handler' => $preprocessing['error_handler'],
                        'error_handler_params' => $preprocessing['error_handler_params']
                    ];
                }
            }
        }

        if ($item_preproc) {
            DB::insertBatch('item_preproc', $item_preproc);
        }
    }

    /**
     * Update item pre-processing data in DB. Delete old records and create new ones.
     *
     * @param array $items
     * @param string $items []['itemid']
     * @param array $items []['preprocessing']
     * @param int $items []['preprocessing'][]['type']
     * @param string $items []['preprocessing'][]['params']
     * @param int $items []['preprocessing'][]['error_handler']
     * @param string $items []['preprocessing'][]['error_handler_params']
     */
    protected function updateItemPreprocessing(array $items)
    {
        $item_preprocs = [];

        foreach ($items as $item) {
            if (array_key_exists('preprocessing', $item)) {
                $item['preprocessing'] = $this->normalizeItemPreprocessingSteps($item['preprocessing']);
                $item_preprocs[$item['itemid']] = [];
                $step = 1;

                foreach ($item['preprocessing'] as $item_preproc) {
                    $curr_step = ($item_preproc['type'] == PRS_PREPROC_VALIDATE_NOT_SUPPORTED) ? 0 : $step++;
                    $item_preprocs[$item['itemid']][$curr_step] = [
                        'type' => $item_preproc['type'],
                        'params' => $item_preproc['params'],
                        'error_handler' => $item_preproc['error_handler'],
                        'error_handler_params' => $item_preproc['error_handler_params']
                    ];
                }
            }
        }

        if (!$item_preprocs) {
            return;
        }

        $ins_item_preprocs = [];
        $upd_item_preprocs = [];
        $del_item_preprocids = [];

        $db_item_preprocs = (new Query())->from('item_preproc')
            ->select(['item_preprocid', 'itemid', 'step', 'type', 'params', 'error_handler', 'error_handler_params'])
            ->where(['itemid' => array_keys($item_preprocs)])
            ->all();

        foreach ($db_item_preprocs as $db_item_preproc) {
            if (array_key_exists($db_item_preproc['step'], $item_preprocs[$db_item_preproc['itemid']])) {
                $item_preproc = $item_preprocs[$db_item_preproc['itemid']][$db_item_preproc['step']];
                $upd_item_preproc = [];

                if ($item_preproc['type'] != $db_item_preproc['type']) {
                    $upd_item_preproc['type'] = $item_preproc['type'];
                }
                if ($item_preproc['params'] !== $db_item_preproc['params']) {
                    $upd_item_preproc['params'] = $item_preproc['params'];
                }
                if ($item_preproc['error_handler'] != $db_item_preproc['error_handler']) {
                    $upd_item_preproc['error_handler'] = $item_preproc['error_handler'];
                }
                if ($item_preproc['error_handler_params'] !== $db_item_preproc['error_handler_params']) {
                    $upd_item_preproc['error_handler_params'] = $item_preproc['error_handler_params'];
                }

                if ($upd_item_preproc) {
                    $upd_item_preprocs[] = [
                        'values' => $upd_item_preproc,
                        'where' => ['item_preprocid' => $db_item_preproc['item_preprocid']]
                    ];
                }
                unset($item_preprocs[$db_item_preproc['itemid']][$db_item_preproc['step']]);
            } else {
                $del_item_preprocids[] = $db_item_preproc['item_preprocid'];
            }
        }

        foreach ($item_preprocs as $itemid => $preprocs) {
            foreach ($preprocs as $step => $preproc) {
                $ins_item_preprocs[] = [
                        'itemid' => $itemid,
                        'step' => $step
                    ] + $preproc;
            }
        }

        if ($del_item_preprocids) {
            DB::delete('item_preproc', ['item_preprocid' => $del_item_preprocids]);
        }

        if ($upd_item_preprocs) {
            DB::update('item_preproc', $upd_item_preprocs);
        }

        if ($ins_item_preprocs) {
            DB::insert('item_preproc', $ins_item_preprocs);
        }
    }

    /**
     * Create item parameters.
     *
     * @param array $items Array of items.
     * @param array $items []['parameters']             Item parameters.
     * @param array $items []['parameters'][]['name']   Parameter name.
     * @param array $items []['parameters'][]['value']  Parameter value.
     * @param array $itemids Array of item IDs that were created before.
     */
    protected function createItemParameters(array $items, array $itemids): void
    {
        $item_parameters = [];

        foreach ($items as $key => $item) {
            $items[$key]['itemid'] = $itemids[$key];

            if (!array_key_exists('parameters', $item) || !$item['parameters']) {
                continue;
            }

            foreach ($item['parameters'] as $parameter) {
                $item_parameters[] = [
                    'itemid' => $items[$key]['itemid'],
                    'name' => $parameter['name'],
                    'value' => $parameter['value']
                ];
            }
        }

        if ($item_parameters) {
            DB::insertBatch('item_parameter', $item_parameters);
        }
    }

    /**
     * Update item parameters.
     *
     * @param array $items Array of items.
     * @param int|string $items []['itemid']                 Item ID.
     * @param int|string $items []['type']                   Item type.
     * @param array $items []['parameters']             Item parameters.
     * @param array $items []['parameters'][]['name']   Parameter name.
     * @param array $items []['parameters'][]['value']  Parameter value.
     */
    protected function updateItemParameters(array $items): void
    {
        $db_item_parameters_by_itemid = [];

        foreach ($items as $item) {
            if ($item['type'] != ITEM_TYPE_SCRIPT || array_key_exists('parameters', $item)) {
                $db_item_parameters_by_itemid[$item['itemid']] = [];
            }
        }

        if (!$db_item_parameters_by_itemid) {
            return;
        }

        $result = (new Query())->from('item_parameter')
            ->select(['item_parameterid', 'itemid', 'name', 'value'])
            ->where(['itemid' => array_keys($db_item_parameters_by_itemid)])
            ->all();

        foreach ($result as $row) {
            $db_item_parameters_by_itemid[$row['itemid']][$row['name']] = [
                'item_parameterid' => $row['item_parameterid'],
                'value' => $row['value']
            ];
        }

        $ins_item_parameters = [];
        $upd_item_parameters = [];
        $del_item_parameterids = [];

        foreach ($db_item_parameters_by_itemid as $itemid => $db_item_parameters) {
            $item = $items[$itemid];

            if ($item['type'] == ITEM_TYPE_SCRIPT && array_key_exists('parameters', $item)) {
                foreach ($item['parameters'] as $parameter) {
                    if (array_key_exists($parameter['name'], $db_item_parameters)) {
                        if ($db_item_parameters[$parameter['name']]['value'] !== $parameter['value']) {
                            $upd_item_parameters[] = [
                                'values' => ['value' => $parameter['value']],
                                'where' => [
                                    'item_parameterid' => $db_item_parameters[$parameter['name']]['item_parameterid']
                                ]
                            ];
                        }
                        unset($db_item_parameters[$parameter['name']]);
                    } else {
                        $ins_item_parameters[] = [
                            'itemid' => $itemid,
                            'name' => $parameter['name'],
                            'value' => $parameter['value']
                        ];
                    }
                }
            }

            $del_item_parameterids = array_merge($del_item_parameterids,
                array_column($db_item_parameters, 'item_parameterid')
            );
        }

        if ($del_item_parameterids) {
            DB::delete('item_parameter', ['item_parameterid' => $del_item_parameterids]);
        }

        if ($upd_item_parameters) {
            DB::update('item_parameter', $upd_item_parameters);
        }

        if ($ins_item_parameters) {
            DB::insertBatch('item_parameter', $ins_item_parameters);
        }
    }

    /**
     * Check if any item from list already exists.
     * If items have item ids it will check for existing item with different itemid.
     *
     * @throw APIException
     *
     * @param array $items
     */
    protected function checkExistingItems(array $items)
    {
        $itemKeysByHostId = [];
        $itemIds = [];
        foreach ($items as $item) {
            if (!isset($itemKeysByHostId[$item['hostid']])) {
                $itemKeysByHostId[$item['hostid']] = [];
            }
            $itemKeysByHostId[$item['hostid']][] = $item['key_'];

            if (isset($item['itemid'])) {
                $itemIds[] = $item['itemid'];
            }
        }

        $sqlWhere = [];
        foreach ($itemKeysByHostId as $hostId => $keys) {
            $sqlWhere[] = ['i.hostid' => (int)$hostId, 'i.key_' => $keys];
        }

        if ($sqlWhere) {
            array_unshift($sqlWhere, 'or');
            $dbItems = (new Query())->select(['i.key_', 'h.host'])
                ->from(['i' => 'items', 'h' => 'hosts'])
                ->where('i.hostid=h.hostid')
                ->andWhere($sqlWhere)
                ->andFilterWhere($itemIds ? ['not in', 'i.itemid', $itemIds] : [])
                ->all();
            foreach ($dbItems as $dbItem) {
                self::exception(60750003,
                    t('zapi', 'Item with key "{value}" already exists on "{host}".', ['value' => $dbItem['key_'], 'host' => $dbItem['host']]));
            }
        }
    }

    /**
     * Validate items with type ITEM_TYPE_DEPENDENT for create or update operation.
     *
     * @param array $items
     * @param string $items []['itemid']         (mandatory for updated items and item prototypes)
     * @param string $items []['hostid']
     * @param int $items []['type']
     * @param string $items []['master_itemid']  (mandatory for ITEM_TYPE_DEPENDENT)
     * @param int $items []['flags']          (mandatory for items)
     *
     * @throws ValidateException for invalid data.
     */
    protected function validateDependentItems(array $items)
    {
        $dep_items = [];
        $upd_itemids = [];

        foreach ($items as $item) {
            if ($item['type'] == ITEM_TYPE_DEPENDENT) {
                if ($this instanceof DiscoverRuleAssist || $this instanceof ItemPrototypeAssist
                    || $item['flags'] == PRS_FLAG_DISCOVERY_NORMAL) {
                    $dep_items[] = $item;
                }

                if (array_key_exists('itemid', $item)) {
                    $upd_itemids[] = $item['itemid'];
                }
            }
        }

        if (!$dep_items) {
            return;
        }

        if ($this instanceof ItemPrototypeAssist && $upd_itemids) {
            $db_links = (new Query())->select('id.itemid,id.parent_itemid AS ruleid')
                ->from(['id' => 'item_discovery'])
                ->where(['id.itemid' => $upd_itemids])
                ->all();

            $links = [];

            foreach ($db_links as $db_link) {
                $links[$db_link['itemid']] = $db_link['ruleid'];
            }

            foreach ($dep_items as &$dep_item) {
                if (array_key_exists('itemid', $dep_item)) {
                    $dep_item['ruleid'] = $links[$dep_item['itemid']];
                }
            }
            unset($dep_item);
        }

        $master_itemids = [];

        foreach ($dep_items as $dep_item) {
            $master_itemids[$dep_item['master_itemid']] = true;
        }

        $master_items = [];

        // Fill relations array by master items (item prototypes). Discovery rule should not be master item.
        do {
            if ($this instanceof ItemPrototypeAssist) {
                $db_master_items = (new Query())
                    ->select(['i.itemid', 'i.hostid', 'i.master_itemid', 'i.flags', 'ruleid' => 'id.parent_itemid'])
                    ->from(['i' => 'items'])
                    ->leftJoin(['id' => 'item_discovery'], 'i.itemid=id.itemid')
                    ->where(['i.itemid' => array_keys($master_itemids)])
                    ->andWhere(['i.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_PROTOTYPE]])
                    ->all();
            } // CDiscoveryRule, CItem
            else {
                $db_master_items = (new Query())
                    ->select(['i.itemid', 'i.hostid', 'i.master_itemid'])
                    ->from(['i' => 'items'])
                    ->where(['i.itemid' => array_keys($master_itemids)])
                    ->andWhere(['i.flags' => [PRS_FLAG_DISCOVERY_NORMAL]])
                    ->all();
            }

            foreach ($db_master_items as $db_master_item) {
                $master_items[$db_master_item['itemid']] = $db_master_item;

                unset($master_itemids[$db_master_item['itemid']]);
            }

            if ($master_itemids) {
                reset($master_itemids);

                self::exception(PRS_API_ERROR_PERMISSIONS,
                    t('zapi', 'Incorrect value for field "{field}": {error}.', [
                            'field' => 'master_itemid',
                            'error' => t('zapi', 'Item "{name}" does not exist or you have no access to this item', ['name' => key($master_itemids)])
                        ]
                    )
                );
            }

            $master_itemids = [];

            foreach ($master_items as $master_item) {
                if ($master_item['master_itemid'] != 0
                    && !array_key_exists($master_item['master_itemid'], $master_items)) {
                    $master_itemids[$master_item['master_itemid']] = true;
                }
            }
        } while ($master_itemids);

        foreach ($dep_items as $dep_item) {
            $master_item = $master_items[$dep_item['master_itemid']];

            if ($dep_item['hostid'] != $master_item['hostid']) {
                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                    ['field' => 'master_itemid', 'error' => t('zapi', '"hostid" of dependent item and master item should match')]
                ));
            }

            if ($this instanceof ItemPrototypeAssist && $master_item['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE
                && $dep_item['ruleid'] != $master_item['ruleid']) {
                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                    ['field' => 'master_itemid', 'error' => t('zapi', '"ruleid" of dependent item and master item should match')]
                ));
            }

            if (array_key_exists('itemid', $dep_item)) {
                $master_itemid = $dep_item['master_itemid'];

                while ($master_itemid != 0) {
                    if ($master_itemid == $dep_item['itemid']) {
                        self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                            ['field' => 'master_itemid', t('zapi', 'circular item dependency is not allowed')]
                        ));
                    }

                    $master_itemid = $master_items[$master_itemid]['master_itemid'];
                }
            }
        }

        // Fill relations array by dependent items (item prototypes).
        $root_itemids = [];

        foreach ($master_items as $master_item) {
            if ($master_item['master_itemid'] == 0) {
                $root_itemids[] = $master_item['itemid'];
            }
        }

        $dependent_items = [];

        foreach ($dep_items as $dep_item) {
            if (array_key_exists('itemid', $dep_item)) {
                $dependent_items[$dep_item['master_itemid']][] = $dep_item['itemid'];
            }
        }

        $master_itemids = $root_itemids;

        do {
            $db_items = (new Query())->select('i.master_itemid,i.itemid')
                ->from(['i' => 'items'])
                ->where(['i.master_itemid' => $master_itemids])
                ->andFilterWhere($upd_itemids ? ['not in', 'i.itemid', $upd_itemids] : [])
                ->all();

            foreach ($db_items as $db_item) {
                $dependent_items[$db_item['master_itemid']][] = $db_item['itemid'];
            }

            $_master_itemids = $master_itemids;
            $master_itemids = [];

            foreach ($_master_itemids as $master_itemid) {
                if (array_key_exists($master_itemid, $dependent_items)) {
                    $master_itemids = array_merge($master_itemids, $dependent_items[$master_itemid]);
                }
            }
        } while ($master_itemids);

        foreach ($dep_items as $dep_item) {
            if (!array_key_exists('itemid', $dep_item)) {
                $dependent_items[$dep_item['master_itemid']][] = false;
            }
        }

        foreach ($root_itemids as $root_itemid) {
            self::checkDependencyDepth($dependent_items, $root_itemid);
        }
    }

    /**
     * @param array $dependent_items
     * @param $root_itemid
     * @param int $level
     * @return int
     * @throws ValidateException
     */
    private static function checkDependencyDepth(array $dependent_items, $root_itemid, $level = 0): int
    {
        $count = 0;

        if (array_key_exists($root_itemid, $dependent_items)) {
            if (++$level > PRS_DEPENDENT_ITEM_MAX_LEVELS) {
                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                    ['field' => 'master_itemid', 'error' => t('zapi', 'maximum number of dependency levels reached')]
                ));
            }

            foreach ($dependent_items[$root_itemid] as $master_itemid) {
                $count++;

                if ($master_itemid !== false) {
                    $count += self::checkDependencyDepth($dependent_items, $master_itemid, $level);
                }
            }

            if ($count > PRS_DEPENDENT_ITEM_MAX_COUNT) {
                self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                    ['field' => 'master_itemid', 'error' => t('zapi', 'maximum dependent items count reached')]
                ));
            }
        }

        return $count;
    }

    /**
     * Normalize preprocessing step parameters.
     *
     * @param array $preprocessing Preprocessing steps.
     * @param string $preprocessing [<num>]['params']  Preprocessing step parameters.
     * @param int $preprocessing [<num>]['type']    Preprocessing step type.
     *
     * @return array
     */
    protected function normalizeItemPreprocessingSteps(array $preprocessing): array
    {
        foreach ($preprocessing as &$step) {
            $step['params'] = str_replace("\r\n", "\n", $step['params']);
            $params = explode("\n", $step['params']);

            switch ($step['type']) {
                case PRS_PREPROC_PROMETHEUS_PATTERN:
                    if (!array_key_exists(2, $params)) {
                        $params[2] = '';
                    }
                    break;
            }

            $step['params'] = implode("\n", $params);
        }
        unset($step);

        return $preprocessing;
    }

    /**
     * Check that valuemap belong to same host as item.
     *
     * @param array $items
     */
    protected function validateValueMaps(array $items): void
    {
        $valuemapids_by_hostid = [];

        foreach ($items as $item) {
            if (array_key_exists('valuemapid', $item) && $item['valuemapid'] != 0) {
                $valuemapids_by_hostid[$item['hostid']][$item['valuemapid']] = true;
            }
        }

        $sql_where = [];
        foreach ($valuemapids_by_hostid as $hostid => $valuemapids) {
            $sql_where[] = ['vm.hostid' => ZSqlHelper::zbxDbstr($hostid), 'vm.valuemapid' => array_keys($valuemapids)];
        }

        if ($sql_where) {
            array_unshift($sql_where, 'or');
            $rows = (new Query())->select([' vm.valuemapid', 'vm.hostid'])
                ->from(['vm' => 'valuemap'])
                ->where($sql_where)
                ->all();
            foreach ($rows as $row) {
                unset($valuemapids_by_hostid[$row['hostid']][$row['valuemapid']]);

                if (!$valuemapids_by_hostid[$row['hostid']]) {
                    unset($valuemapids_by_hostid[$row['hostid']]);
                }
            }

            if ($valuemapids_by_hostid) {
                $hostid = key($valuemapids_by_hostid);
                $valuemapid = key($valuemapids_by_hostid[$hostid]);

                $host_row = Hosts::find()->where(['h.hostid' => ZSqlHelper::zbxDbstr($hostid)])->asArray()->one();
                self::exception(60750003, t('zapi', 'Valuemap with ID "{value}" is not available on "{host}".',
                    ['value' => $valuemapid, 'host' => $host_row['host']]
                ));
            }
        }
    }

    /**
     * Validate item with type ITEM_TYPE_HTTPAGENT.
     *
     * @param array $item Array of item fields.
     * @param array $db_item Array of item database fields for update action or empty array for create action.
     *
     * @throws ValidateException
     */
    protected function validateHTTPCheck(array $item, array $db_item)
    {
        $rules = [
            'timeout' => [
                TimeUnitValidator::class, 'flags' => ($this instanceof ItemPrototypeAssist)
                    ? API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO
                    : API_NOT_EMPTY | API_ALLOW_USER_MACRO,
                'in' => '1:' . SEC_PER_MIN
            ],
            'url' => [
                Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY,
                'length' => DB::getFieldLength('items', 'url')
            ],
            'status_codes' => [
                Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'status_codes')
            ],
            'follow_redirects' => [
                Int32Validator::class,
                'in' => implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON])
            ],
            'post_type' => [
                Int32Validator::class,
                'in' => implode(',', [PRS_POSTTYPE_RAW, PRS_POSTTYPE_JSON, PRS_POSTTYPE_XML])
            ],
            'http_proxy' => [
                Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'http_proxy')
            ],
            'headers' => [
                Utf8StringsValidator::class
            ],
            'retrieve_mode' => [
                Int32Validator::class,
                'in' => implode(',', [
                    HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS,
                    HTTPTEST_STEP_RETRIEVE_MODE_BOTH
                ])
            ],
            'request_method' => [
                Int32Validator::class,
                'in' => implode(',', [
                    HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD
                ])
            ],
            'output_format' => [
                Int32Validator::class,
                'in' => implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON])
            ],
            'allow_traps' => [
                Int32Validator::class,
                'in' => implode(',', [HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON])
            ],
            'ssl_cert_file' => [
                Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'ssl_cert_file')
            ],
            'ssl_key_file' => [
                Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'ssl_key_file')
            ],
            'ssl_key_password' => [
                Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'ssl_key_password')
            ],
            'verify_peer' => [
                Int32Validator::class,
                'in' => implode(',', [PRS_HTTP_VERIFY_PEER_OFF, PRS_HTTP_VERIFY_PEER_ON])
            ],
            'verify_host' => [
                Int32Validator::class,
                'in' => implode(',', [PRS_HTTP_VERIFY_HOST_OFF, PRS_HTTP_VERIFY_HOST_ON])
            ],
            'authtype' => [
                Int32Validator::class,
                'in' => implode(',', [
                    PRS_HTTP_AUTH_NONE, PRS_HTTP_AUTH_BASIC, PRS_HTTP_AUTH_NTLM, PRS_HTTP_AUTH_KERBEROS,
                    PRS_HTTP_AUTH_DIGEST
                ])
            ]
        ];

        $data = $item + $db_item;

        if (array_key_exists('authtype', $data)
            && ($data['authtype'] == PRS_HTTP_AUTH_BASIC || $data['authtype'] == PRS_HTTP_AUTH_NTLM
                || $data['authtype'] == PRS_HTTP_AUTH_KERBEROS || $data['authtype'] == PRS_HTTP_AUTH_DIGEST)) {
            $rules += [
                'username' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'username')],
                'password' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'password')]
            ];
        }

        // Strict validation for 'retrieve_mode' only for create action.
        if (array_key_exists('request_method', $data) && $data['request_method'] == HTTPCHECK_REQUEST_HEAD
            && array_key_exists('retrieve_mode', $item)) {
            $rules['retrieve_mode']['in'] = (string)HTTPTEST_STEP_RETRIEVE_MODE_HEADERS;
        }

        if (array_key_exists('post_type', $data)
            && ($data['post_type'] == PRS_POSTTYPE_JSON || $data['post_type'] == PRS_POSTTYPE_XML)) {
            $rules['posts'] = [
                Utf8StringValidator::class,
                'length' => DB::getFieldLength('items', 'posts')
            ];
        }

        if (array_key_exists('templateid', $data) && $data['templateid']) {
            $rules['interfaceid'] = [
                IdValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY
            ];

            if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
                unset($rules['interfaceid']['flags']);
            }
        }

        if (array_key_exists('trapper_hosts', $item) && $item['trapper_hosts'] !== ''
            && (!array_key_exists('allow_traps', $data) || $data['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_OFF)) {
            self::exception(60750003, t('zapi', 'Incorrect value for field "{field}": {error}.',
                ['field' => 'trapper_hosts', 'error' => t('zapi', 'cannot be empty')]
            ));
        }

        // Keep values only for fields with defined validation rules.
        $data = array_intersect_key($data, $rules);

        if (!ValidateHelper::validateObject($data, $rules, [], $error)) {
            self::exception(60750003, $error);
        }

        if (array_key_exists('query_fields', $item)) {
            if (!is_array($item['query_fields'])) {
                self::exception(60750003,
                    t('zapi', 'Invalid parameter "{param}": {error}.', ['param' => 'query_fields', 'error' => t('zapi', 'an array is expected')])
                );
            }

            foreach ($item['query_fields'] as $v) {
                if (!is_array($v) || count($v) > 1 || key($v) === '') {
                    self::exception(60750003,
                        t('zapi', 'Invalid parameter "{param}": {error}.', ['param' => 'query_fields', 'error' => t('zapi', 'nonempty key and value pair expected')])
                    );
                }
            }

            if (strlen(json_encode($item['query_fields'])) > DB::getFieldLength('items', 'query_fields')) {
                self::exception(60750003,
                    t('zapi', 'Invalid parameter "{param}": {error}.', ['param' => 'query_fields', 'error' => t('zapi', 'cannot convert to JSON, result value too long')])
                );
            }
        }

        if (array_key_exists('headers', $item)) {
            if (!is_array($item['headers'])) {
                self::exception(60750003,
                    t('zapi', 'Invalid parameter "{param}": {error}.', ['param' => 'headers', 'error' => t('zapi', 'an array is expected')])
                );
            }

            foreach ($item['headers'] as $k => $v) {
                if (trim($k) === '' || !is_string($v) || $v === '') {
                    self::exception(60750003,
                        t('zapi', 'Invalid parameter "{param}": {error}.', ['param' => 'headers', 'error' => t('zapi', 'nonempty key and value pair expected')])
                    );
                }
            }
        }

        if (array_key_exists('status_codes', $item) && $item['status_codes']) {
            $ranges_parser = new CRangesParser([
                'usermacros' => true,
                'lldmacros' => ($this instanceof ItemPrototypeAssist)
            ]);

            if ($ranges_parser->parse($item['status_codes']) != CParser::PARSE_SUCCESS) {
                self::exception(60750003,
                    t('zapi', 'Incorrect value "{value}" for "{field}" field.', ['value' => $item['status_codes'], 'field' => 'status_codes'])
                );
            }
        }

        if ((array_key_exists('post_type', $item) || array_key_exists('posts', $item))
            && ($data['post_type'] == PRS_POSTTYPE_JSON || $data['post_type'] == PRS_POSTTYPE_XML)) {
            $posts = array_key_exists('posts', $data) ? $data['posts'] : '';
            libxml_use_internal_errors(true);

            if ($data['post_type'] == PRS_POSTTYPE_XML
                && simplexml_load_string($posts, null, LIBXML_IMPORT_FLAGS) === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();

                if (!$errors) {
                    self::exception(60750003,
                        t('zapi', 'Invalid parameter "{param}": {error}.', ['param' => 'posts', 'error' => t('zapi', 'XML is expected')])
                    );
                } else {
                    $error = reset($errors);
                    self::exception(60750003, t('zapi', 'Invalid parameter "{param}": {error}.', [
                        'param' => 'posts',
                        'error' => t('zapi', '{error} [Line: {line} | Column: {column}]', ['error' => '(' . $error->code . ') ' . trim($error->message), 'line' => $error->line, 'column' => $error->column])
                    ]));
                }
            }

            if ($data['post_type'] == PRS_POSTTYPE_JSON) {
                if (trim($posts, " \r\n") === '') {
                    self::exception(60750003,
                        t('zapi', 'Invalid parameter "{param}": {error}.', ['param' => 'posts', 'error' => t('zapi', 'JSON is expected')])
                    );
                }

                $types = [
                    'usermacros' => true,
                    'macros_n' => [
                        '{HOST.IP}', '{HOST.CONN}', '{HOST.DNS}', '{HOST.HOST}', '{HOST.NAME}', '{ITEM.ID}',
                        '{ITEM.KEY}'
                    ]
                ];

                if ($this instanceof ItemPrototypeAssist) {
                    $types['lldmacros'] = true;
                }

                $matches = CMacrosResolverGeneral::getMacroPositions($posts, $types);

                $shift = 0;

                foreach ($matches as $pos => $substr) {
                    $posts = substr_replace($posts, '1', $pos + $shift, strlen($substr));
                    $shift = $shift + 1 - strlen($substr);
                }

                json_decode($posts);

                if (json_last_error()) {
                    self::exception(60750003,
                        t('zapi', 'Invalid parameter "{param}": {error}.', ['param' => 'posts', 'error' => t('zapi', 'JSON is expected')])
                    );
                }
            }
        }
    }
}