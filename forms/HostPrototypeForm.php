<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\validators\BooleanValidator;
use app\customs\zapi\common\validators\DnsValidator;
use app\customs\zapi\common\validators\FlagValidator;
use app\customs\zapi\common\validators\HostGroupNameValidator;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\IpValidator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\ObjectValidator;
use app\customs\zapi\common\validators\OutputValidator;
use app\customs\zapi\common\validators\PortValidator;
use app\customs\zapi\common\validators\SortOrderValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\Utf8StringsValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\UuidValidator;
use app\customs\zapi\forms\hosts\HostForm;
use app\modules\libzbx\models\zbx\HostDiscovery;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\Items;
use yii\db\Query;
use yii\validators\FilterValidator;
use app\customs\zapi\common\validators\HostNameValidator;

class HostPrototypeForm extends BaseForm
{
    protected static function getCheckQuery(bool $inherited = false): Query
    {
        $query = new Query();
        $tables = [
            'i' => Items::tableName(),
            'hd' => HostDiscovery::tableName(),
            'h' => Hosts::tableName(),
        ];
        $selects = ['rule' => 'i.name', 'h.host'];

        $query->where('i.itemid=hd.parent_itemid')
            ->andWhere('hd.hostid=h.hostid');

        if ($inherited) {
            $tables['hh'] = Hosts::tableName();
            $selects['parent_host'] = 'hh.host';
            $selects[] = 'hh.status';
            $query->andWhere('i.hostid=hh.hostid');
        }

        $query->from($tables)->select($selects);

        return $query;
    }

    /**
     * Check for unique host prototype names per LLD rule.
     *
     * @param array      $hosts
     * @param array|null $db_hosts
     * @param bool       $inherited
     *
     * @throws ValidateException
     */
    public static function checkDuplicates(array $hosts, $db_hosts = null, bool $inherited = false)
    {
        $rule2names = [];

        foreach ($hosts as $host) {
            if (array_key_exists('host', $host)) {
                if ($db_hosts === null || $host['host'] !== $db_hosts[$host['hostid']]['host']) {
                    $rule2names['host'][$host['ruleid']][] = $host['host'];
                }
            }

            if (array_key_exists('name', $host)) {
                if ($db_hosts === null || $host['name'] !== $db_hosts[$host['hostid']]['name']) {
                    $rule2names['name'][$host['ruleid']][] = $host['name'];
                }
            }
        }

        $message = $inherited
        ? 'Host prototype with {src} "{src_name}" already exists in discovery rule "{dst_name}" of {target} "{target_name}".'
        : 'Host prototype with {src} "{src_name}" already exists in discovery rule "{dst_name}".';

        $flags = [
            'host' => t('zapi', 'host name'),
            'name' => t('zapi', 'visible name'),
        ];

        foreach ($rule2names as $field => $data) {
            $where = ['OR'];
            $query = self::getCheckQuery($inherited);
            foreach ($data as $ruleId => $names) {
                $where[] = ['i.itemid' => $ruleId, "{{h}}.{$field}" => $names];
            }
            $query->andWhere($where);
            $query->limit(1);
            if ($duplicate = $query->one()) {
                $params = [
                    'src' => $flags[$field],
                    'src_name' => $duplicate['host'],
                    'dst_name' => $duplicate['rule'],
                ];
                if ($inherited) {
                    $params['target'] = $duplicate['status'] == HOST_STATUS_TEMPLATE ? 'template' : 'host';
                    $params['target_name'] = $duplicate['parent_host'];
                }
                throw new ValidateException(10000021, t('zapi', $message, $params));
            }
        }
    }

    public static function getValidationRules(string $method = 'create', bool $full = true): array
    {
        if ($method == 'get') {
            $hosts_fields = array_keys(DB::getSchema('hosts')['fields']);
            $output_fields = [
                'hostid',
                'host',
                'name',
                'status',
                'templateid',
                'inventory_mode',
                'discover',
                'custom_interfaces',
                'uuid',
            ];
            $discovery_fields = array_keys(DB::getSchema('items')['fields']);
            $hostmacro_fields = array_keys(DB::getSchema('hostmacro')['fields']);
            $interface_fields = ['type', 'useip', 'ip', 'dns', 'port', 'main', 'details'];
            $fields = [
                // filter
                'hostids' => [IdsValidator::class, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
                'discoveryids' => [IdsValidator::class, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
                'filter' => [FilterValidator::class, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['hostid', 'host', 'name', 'status', 'templateid', 'inventory_mode']],
                'search' => [FilterValidator::class, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['host', 'name']],
                'searchByAny' => [BooleanValidator::class, 'default' => false],
                'startSearch' => [FlagValidator::class, 'default' => false],
                'excludeSearch' => [FlagValidator::class, 'default' => false],
                'searchWildcardsEnabled' => [BooleanValidator::class, 'default' => false],
                // output
                'output' => [OutputValidator::class, 'in' => $output_fields, 'default' => $output_fields],
                'countOutput' => [FlagValidator::class, 'default' => false],
                'groupCount' => [FlagValidator::class, 'default' => false],
                'selectGroupLinks' => [OutputValidator::class, 'flags' => API_ALLOW_NULL, 'in' => ['groupid'], 'default' => null],
                'selectGroupPrototypes' => [OutputValidator::class, 'flags' => API_ALLOW_NULL, 'in' => ['group_prototypeid', 'name'], 'default' => null],
                'selectDiscoveryRule' => [OutputValidator::class, 'flags' => API_ALLOW_NULL, 'in' => $discovery_fields, 'default' => null],
                'selectParentHost' => [OutputValidator::class, 'flags' => API_ALLOW_NULL, 'in' => $hosts_fields, 'default' => null],
                'selectInterfaces' => [OutputValidator::class, 'flags' => API_ALLOW_NULL, 'in' => $interface_fields, 'default' => null],
                'selectTemplates' => [OutputValidator::class, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => $hosts_fields, 'default' => null],
                'selectMacros' => [OutputValidator::class, 'flags' => API_ALLOW_NULL, 'in' => $hostmacro_fields, 'default' => null],
                'selectTags' => [OutputValidator::class, 'flags' => API_ALLOW_NULL, 'in' => ['tag', 'value'], 'default' => null],
                // sort and limit
                'sortfield' => [Utf8StringsValidator::class, API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => ['hostid', 'host', 'name', 'status', 'discover'], 'uniq' => true, 'default' => []],
                'sortorder' => [SortOrderValidator::class, 'default' => []],
                'limit' => [Int32Validator::class, 'flags' => API_ALLOW_NULL, 'in' => '1:' . PRS_MAX_INT32, 'default' => null],
                // flags
                'inherited' => [BooleanValidator::class, 'flags' => API_ALLOW_NULL, 'default' => null],
                'editable' => [BooleanValidator::class, 'default' => false],
                'preservekeys' => [BooleanValidator::class, 'default' => false],
                'nopermissions' => [BooleanValidator::class, 'default' => false], // TODO: This property and frontend usage SHOULD BE removed.
            ];

            if ($full) {
                return [
                    ObjectsValidator::class,
                    'fields' => $fields,
                ];
            }
        }
        $groupLinks = [ObjectsValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['groupid']], 'fields' => [
            'groupid' => [IdValidator::class, 'flags' => API_REQUIRED],
        ]];
        $templateRules = [ObjectsValidator::class, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
            'templateid' => [IdValidator::class, 'flags' => API_REQUIRED],
        ]];
        $macroRules = HostMacroForm::getValidationRules($method, true);
        $tagRules = HostForm::getTagsValidationRules();
        $tagRules['flags'] = API_NORMALIZE;
        if ($method == 'create') {
            unset($macroRules['fields']['hostid']);
            $macroRules['uniq'] = [['macro']];
            $macroRules['flags'] = API_NORMALIZE;
            $fields = [
                'host_status' => ['safe'],
                'uuid' => static::getUuidValidationRules(),
                'ruleid' => [IdValidator::class, 'flags' => API_REQUIRED],
                'host' => [HostNameValidator::class, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hosts', 'host')],
                //    'name' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name'), 'default_source' => 'host'],
                'name' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name')],
                'custom_interfaces' => [Int32Validator::class, 'in' => [HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM], 'default' => DB::getDefault('hosts', 'custom_interfaces')],
                'status' => [Int32Validator::class, 'in' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]],
                'discover' => [Int32Validator::class, 'in' => [PRS_PROTOTYPE_DISCOVER, PRS_PROTOTYPE_NO_DISCOVER]],
                'interfaces' => self::getInterfacesValidationRules(),
                'groupLinks' => $groupLinks,
                'groupPrototypes' => [ObjectsValidator::class, 'uniq' => [['name']], 'fields' => self::getGroupPrototypeValidationFields()],
                'templates' => $templateRules,
                'tags' => $tagRules,
                'macros' => $macroRules,
                'inventory_mode' => [Int32Validator::class, 'in' => [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]],
            ];

            if ($full) {
                return [
                    ObjectsValidator::class,
                    'uniq' => [['uuid'], ['ruleid', 'host'], ['ruleid', 'name']],
                    'fields' => $fields,
                ];
            }
        } elseif ($method == 'update') {
            $groupLinks['flags'] = API_NOT_EMPTY;
            unset($macroRules['fields']['hostmacroid']['flags']);
            $fields = [
                'host_status' => ['safe'],
                'uuid' => static::getUuidValidationRules(),
                'hostid' => ['safe'],
                'host' => [HostGroupNameValidator::class, 'flags' => API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hosts', 'host')],
                'name' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name')],
                'custom_interfaces' => [Int32Validator::class, 'in' => [HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM]],
                'status' => [Int32Validator::class, 'in' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]],
                'discover' => [Int32Validator::class, 'in' => [PRS_PROTOTYPE_DISCOVER, PRS_PROTOTYPE_NO_DISCOVER]],
                'interfaces' => self::getInterfacesValidationRules(),
                'groupLinks' => $groupLinks,
                'groupPrototypes' => ['safe'],
                'templates' => $templateRules,
                'tags' => $tagRules,
                'macros' => $macroRules,
                'inventory_mode' => [Int32Validator::class, 'in' => [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]],
            ];

            if ($full) {
                return [
                    ObjectsValidator::class,
                    'fields' => $fields,
                ];
            }
        } else {
            $fields = [];
        }
        return $fields;
    }

    private static function getUuidValidationRules()
    {
        return [
            MultipleValidator::class,
            'rules' => [
                UuidValidator::class,
                'when' => function ($model) {
                    return $model['host_status'] == HOST_STATUS_TEMPLATE;
                },
            ],
            'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('hosts', 'uuid'), 'unset' => true],
        ];
    }

    /**
     * @return array
     */
    public static function getInterfacesValidationRules(): array
    {
        return [
            MultipleValidator::class,
            'rules' => [
                ObjectsValidator::class,
                'flags' => API_NORMALIZE,
                'when' => function ($model) {
                    return $model['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM;
                },
                'fields' => [
                    'type' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX]],
                    'useip' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [INTERFACE_USE_DNS, INTERFACE_USE_IP]],
                    'ip' => [
                        MultipleValidator::class,
                        'rules' => [
                            IpValidator::class,
                            'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO,
                            'length' => DB::getFieldLength('interface', 'ip'),
                            'when' => function ($model) {
                                $model['useip'] == INTERFACE_USE_IP;
                            },
                        ],
                        'else' => [IpValidator::class, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'ip')],
                    ],
                    'dns' => [
                        MultipleValidator::class,
                        'rules' => [
                            DnsValidator::class,
                            'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO,
                            'length' => DB::getFieldLength('interface', 'dns'),
                            'when' => function ($model) {
                                $model['useip'] == INTERFACE_USE_DNS;
                            },
                        ],
                        'else' => [DnsValidator::class, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'dns')],
                    ],
                    'port' => [PortValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'length' => DB::getFieldLength('interface', 'port')],
                    'main' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [INTERFACE_SECONDARY, INTERFACE_PRIMARY]],
                    'details' => static::getDetailsValidationRules(),
                ],
            ],
            'else' => [ObjectsValidator::class, 'length' => 0],
        ];
    }

    public static function getDetailsValidationRules(): array
    {
        return [
            MultipleValidator::class,
            'rules' => [
                ObjectValidator::class,
                'flags' => API_REQUIRED,
                'when' => function ($model) {
                    return $model['type'] == INTERFACE_TYPE_SNMP;
                },
                'fields' => [
                    'version' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [SNMP_V1, SNMP_V2C, SNMP_V3]],
                    'bulk' => [Int32Validator::class, 'in' => [SNMP_BULK_DISABLED, SNMP_BULK_ENABLED]],
                    'community' => [
                        MultipleValidator::class,
                        'rules' => [
                            Utf8StringValidator::class,
                            'flags' => API_REQUIRED | API_NOT_EMPTY,
                            'length' => DB::getFieldLength('interface_snmp', 'community'),
                            'when' => function ($model) {
                                return in_array($model['version'], [SNMP_V1, SNMP_V2C]);
                            },
                        ],
                        'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('interface_snmp', 'community')],
                    ],
                ],
                'max_repetitions' => [
                    MultipleValidator::class,
                    'rules' => [
                        Int32Validator::class,
                        'in' => [[1, PRS_MAX_INT32]],
                        'when' => function ($model) {
                            return in_array($model['version'], [SNMP_V2C, SNMP_V3]);
                        },
                    ],
                    'else' => [Int32Validator::class, 'in' => DB::getDefault('interface_snmp', 'max_repetitions')],
                ],
                'contextname' => [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class,
                        'length' => DB::getFieldLength('interface_snmp', 'contextname'),
                        'when' => function ($model) {
                            return in_array($model['version'], [SNMP_V3]);
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('interface_snmp', 'contextname')],
                ],
                'securityname' => [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class,
                        'length' => DB::getFieldLength('interface_snmp', 'securityname'),
                        'when' => function ($model) {
                            return in_array($model['version'], [SNMP_V3]);
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('interface_snmp', 'securityname')],
                ],
                'securitylevel' => [
                    MultipleValidator::class,
                    'rules' => [
                        Int32Validator::class,
                        'in' => [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV],
                        'default' => DB::getDefault('interface_snmp', 'securitylevel'),
                        'when' => function ($model) {
                            return in_array($model['version'], [SNMP_V3]);
                        },
                    ],
                    'else' => [
                        Int32Validator::class,
                        'in' => DB::getDefault('interface_snmp', 'securitylevel'),
                    ],
                ],
                'authprotocol' => [
                    MultipleValidator::class,
                    'rules' => [
                        MultipleValidator::class,
                        'rules' => [
                            Int32Validator::class,
                            'in' => array_keys(ItemHelper::getSnmpV3AuthProtocols()),
                            'when' => function ($model) {
                                return in_array($model['securitylevel'], [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]);
                            },
                        ],
                        'else' => [Int32Validator::class, 'in' => DB::getDefault('interface_snmp', 'authprotocol')],
                        'when' => function ($model) {
                            return in_array($model['version'], [SNMP_V3]);
                        },
                    ],
                    'else' => [Int32Validator::class, 'in' => DB::getDefault('interface_snmp', 'authprotocol')],
                ],
                'authpassphrase' => [
                    MultipleValidator::class,
                    'rules' => [
                        MultipleValidator::class,
                        'rules' => [
                            Utf8StringValidator::class,
                            'length' => DB::getFieldLength('interface_snmp', 'authpassphrase'),
                            'when' => function ($model) {
                                return in_array($model['securitylevel'], [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]);
                            },
                        ],
                        'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('interface_snmp', 'authpassphrase')],
                        'when' => function ($model) {
                            return in_array($model['version'], [SNMP_V3]);
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('interface_snmp', 'authpassphrase')],
                ],
                'privprotocol' => [
                    MultipleValidator::class,
                    'rules' => [
                        MultipleValidator::class,
                        'rules' => [
                            Int32Validator::class,
                            'in' => array_keys(ItemHelper::getSnmpV3PrivProtocols()),
                            'when' => function ($model) {
                                return in_array($model['securitylevel'], [ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]);
                            },
                        ],
                        'else' => [Int32Validator::class, 'in' => DB::getDefault('interface_snmp', 'privprotocol')],
                        'when' => function ($model) {
                            return in_array($model['version'], [SNMP_V3]);
                        },
                    ],
                    'else' => [Int32Validator::class, 'in' => DB::getDefault('interface_snmp', 'privprotocol')],
                ],

                'privpassphrase' => [
                    MultipleValidator::class,
                    'rules' => [
                        MultipleValidator::class,
                        'rules' => [
                            Utf8StringValidator::class,
                            'length' => DB::getFieldLength('interface_snmp', 'privpassphrase'),
                            'when' => function ($model) {
                                return in_array($model['securitylevel'], [ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]);
                            },
                        ],
                        'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('interface_snmp', 'privpassphrase')],
                        'when' => function ($model) {
                            return in_array($model['version'], [SNMP_V3]);
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('interface_snmp', 'privpassphrase')],
                ],
            ],
            'else' => [ObjectValidator::class, 'fields' => []],
        ];
    }

    /**
     * @param bool $is_update
     */
    public static function getGroupPrototypeValidationFields(bool $is_update = false): array
    {
        $api_required = $is_update ? 0 : API_REQUIRED;

        return ($is_update ? ['group_prototypeid' => ['safe']] : []) + [
            'name' => [HostGroupNameValidator::class, 'flags' => $api_required | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('group_prototype', 'name')],
        ];
    }

    /**
     * @return array
     */
    public static function getInheritedValidationRules(): array
    {
        return [
            ObjectValidator::class,
            'fields' => [
                'host_status' => ['safe'],
                'uuid' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
                'hostid' => ['safe'],
                'host' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
                'name' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
                'custom_interfaces' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
                'status' => [Int32Validator::class, 'in' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]],
                'discover' => [Int32Validator::class, 'in' => [PRS_PROTOTYPE_DISCOVER, PRS_PROTOTYPE_NO_DISCOVER]],
                'interfaces' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
                'groupLinks' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
                'groupPrototypes' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
                'templates' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
                'tags' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
                'macros' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
                'inventory_mode' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            ],
        ];
    }
}
