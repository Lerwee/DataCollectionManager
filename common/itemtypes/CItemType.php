<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\validators\CalcFormulaValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32RangesValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\IpRangesValidator;
use app\customs\zapi\common\validators\ItemDelayValidator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\ObjectValidator;
use app\customs\zapi\common\validators\TimeUnitValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;

abstract class CItemType
{

    /**
     * Item type.
     *
     * @var int|null
     */
    const TYPE = null;

    /**
     * Field names of specific type.
     *
     * @var array
     */
    const FIELD_NAMES = [
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
    ];

    /**
     * @param array $item
     *
     * @return array
     */
    abstract public static function getCreateValidationRules(array $item): array;

    /**
     * @param array $db_item
     *
     * @return array
     */
    abstract public static function getUpdateValidationRules(array $db_item): array;

    /**
     * @param array $db_item
     *
     * @return array
     */
    abstract public static function getUpdateValidationRulesInherited(array $db_item): array;

    /**
     * @return array
     */
    abstract public static function getUpdateValidationRulesDiscovered(): array;

    /**
     * @param string $field_name
     * @param array $item
     *
     * @return array
     */
    final protected static function getCreateFieldRule(string $field_name, array $item): array
    {
        $is_item_prototype = $item['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE;

        switch ($field_name) {
            case 'interfaceid':
                switch (static::TYPE) {
                    case ITEM_TYPE_SIMPLE:
                    case ITEM_TYPE_EXTERNAL:
                    case ITEM_TYPE_SSH:
                    case ITEM_TYPE_TELNET:
                    case ITEM_TYPE_HTTPAGENT:
                        return [MultipleValidator::class,
                            'rules' => [IdValidator::class, 'when' => function ($model) {
                                return in_array($model->host_status, [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);
                            }],
                            'else' => [IdValidator::class, 'in' => '0']
                        ];

                    default:
                        return [MultipleValidator::class,
                            'rules' => [IdValidator::class, 'flags' => API_REQUIRED, 'when' => function ($model) {
                                return in_array($model->host_status, [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);
                            }],
                            'else' => [IdValidator::class, 'in' => '0']
                        ];
                }

            case 'authtype':
                switch (static::TYPE) {
                    case ITEM_TYPE_HTTPAGENT:
                        return [Int32Validator::class, 'in' => implode(',', [PRS_HTTP_AUTH_NONE, PRS_HTTP_AUTH_BASIC, PRS_HTTP_AUTH_NTLM, PRS_HTTP_AUTH_KERBEROS, PRS_HTTP_AUTH_DIGEST]), 'default' => DB::getDefault('items', 'authtype')];

                    case ITEM_TYPE_SSH:
                        return [Int32Validator::class, 'in' => implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]), 'default' => DB::getDefault('items', 'authtype')];
                }

            case 'username':
                switch (static::TYPE) {
                    case ITEM_TYPE_HTTPAGENT:
                        return [MultipleValidator::class,
                            'rules' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'username'), 'when' => function ($model) {
                                return in_array($model->authtype, [PRS_HTTP_AUTH_BASIC, PRS_HTTP_AUTH_NTLM, PRS_HTTP_AUTH_KERBEROS, PRS_HTTP_AUTH_DIGEST]);
                            }],
                            'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'username')]
                        ];

                    case ITEM_TYPE_SSH:
                    case ITEM_TYPE_TELNET:
                        return [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'username')];

                    default:
                        return [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'username')];
                }

            case 'password':
                switch (static::TYPE) {
                    case ITEM_TYPE_HTTPAGENT:
                        return [MultipleValidator::class,
                            'rules' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'password'), 'when' => function ($model) {
                                return in_array($model->authtype, [PRS_HTTP_AUTH_BASIC, PRS_HTTP_AUTH_NTLM, PRS_HTTP_AUTH_KERBEROS, PRS_HTTP_AUTH_DIGEST]);
                            }],
                            'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'password')]
                        ];

                    default:
                        return [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'password')];
                }

            case 'params':
                switch (static::TYPE) {
                    case ITEM_TYPE_CALCULATED:
                        return [CalcFormulaValidator::class, 'flags' => API_REQUIRED | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'params')];

                    default:
                        return [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'params')];
                }

            case 'timeout':
                return [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'in' => '1:' . SEC_PER_MIN, 'length' => DB::getFieldLength('items', 'timeout')];

            case 'delay':
                switch (static::TYPE) {
                    case ITEM_TYPE_PERSEUS_ACTIVE:
                        return [MultipleValidator::class,
                            'rules' => [ItemDelayValidator::class, 'flags' => API_REQUIRED | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay'), 'when' => function ($model) {
                                return strncmp($model->key_, 'mqtt.get', 8) != 0;
                            }],
                            'else' => [TimeUnitValidator::class, 'in' => DB::getDefault('items', 'delay')]
                        ];

                    default:
                        return [ItemDelayValidator::class, 'flags' => API_REQUIRED | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')];
                }

            case 'trapper_hosts':
                switch (static::TYPE) {
                    case ITEM_TYPE_HTTPAGENT:
                        return [MultipleValidator::class,
                            'rules' => [IpRangesValidator::class, 'flags' => API_ALLOW_DNS | API_ALLOW_USER_MACRO, 'macros' => ['{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'], 'length' => DB::getFieldLength('items', 'trapper_hosts'), 'when' => function ($model) {
                                return $model->allow_traps == HTTPCHECK_ALLOW_TRAPS_ON;
                            }],
                            'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'trapper_hosts')]
                        ];

                    case  ITEM_TYPE_TRAPPER:
                        return [IpRangesValidator::class, 'flags' => API_ALLOW_DNS | API_ALLOW_USER_MACRO, 'macros' => ['{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'], 'length' => DB::getFieldLength('items', 'trapper_hosts')];
                }
        }
        return [];
    }

    /**
     * @param string $field_name
     * @param array $db_item
     *
     * @return array
     */
    final protected static function getUpdateFieldRule(string $field_name, array $db_item): array
    {
        $is_item_prototype = $db_item['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE;

        switch ($field_name) {
            case 'interfaceid':
                return [MultipleValidator::class,
                    'rules' => [IdValidator::class, 'when' => function ($model) {
                        return in_array($model->host_status, [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);
                    }],
                    'else' => [IdValidator::class, 'in' => '0']
                ];

            case 'authtype':
                switch (static::TYPE) {
                    case ITEM_TYPE_HTTPAGENT:
                        return [Int32Validator::class, 'in' => implode(',', [PRS_HTTP_AUTH_NONE, PRS_HTTP_AUTH_BASIC, PRS_HTTP_AUTH_NTLM, PRS_HTTP_AUTH_KERBEROS, PRS_HTTP_AUTH_DIGEST])];

                    case ITEM_TYPE_SSH:
                        return [Int32Validator::class, 'in' => implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY])];
                }

            case 'username':
                switch (static::TYPE) {
                    case ITEM_TYPE_HTTPAGENT:
                        return [MultipleValidator::class,
                            'rules' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'username'), 'when' => function ($model) {
                                return in_array($model->authtype, [PRS_HTTP_AUTH_BASIC, PRS_HTTP_AUTH_NTLM, PRS_HTTP_AUTH_KERBEROS, PRS_HTTP_AUTH_DIGEST]);
                            }],
                            'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'username')]
                        ];

                    case ITEM_TYPE_SSH:
                    case ITEM_TYPE_TELNET:
                        return [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'username')];

                    default:
                        return [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'username')];
                }

            case 'password':
                switch (static::TYPE) {
                    case ITEM_TYPE_HTTPAGENT:
                        return [MultipleValidator::class,
                            'rules' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'password'), 'when' => function ($model) {
                                return in_array($model->authtype, [PRS_HTTP_AUTH_BASIC, PRS_HTTP_AUTH_NTLM, PRS_HTTP_AUTH_KERBEROS, PRS_HTTP_AUTH_DIGEST]);
                            }],
                            'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'password')]
                        ];

                    default:
                        return [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'password')];
                }

            case 'params':
                switch (static::TYPE) {
                    case ITEM_TYPE_CALCULATED:
                        return [CalcFormulaValidator::class, 'flags' => ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'params')];

                    default:
                        return [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'params')];
                }

            case 'timeout':
                return [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'in' => '1:' . SEC_PER_MIN, 'length' => DB::getFieldLength('items', 'timeout')];

            case 'delay':
                switch (static::TYPE) {
                    case ITEM_TYPE_PERSEUS_ACTIVE:
                        return [MultipleValidator::class,
                            'rules' => [ItemDelayValidator::class, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay'), 'when' => function ($model) {
                                return strncmp($model->key_, 'mqtt.get', 8) !== 0;
                            }],
                            'else' => [TimeUnitValidator::class, 'in' => DB::getDefault('items', 'delay')]
                        ];

                    default:
                        return [ItemDelayValidator::class, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')];
                }

            case 'trapper_hosts':
                switch (static::TYPE) {
                    case ITEM_TYPE_HTTPAGENT:
                        return [MultipleValidator::class,
                            'rules' => [IpRangesValidator::class, 'flags' => API_ALLOW_DNS | API_ALLOW_USER_MACRO, 'macros' => ['{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'], 'length' => DB::getFieldLength('items', 'trapper_hosts'), 'when' => function ($model) {
                                return $model->allow_traps == HTTPCHECK_ALLOW_TRAPS_ON;
                            }],
                            'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'trapper_hosts')]
                        ];

                    case  ITEM_TYPE_TRAPPER:
                        return [IpRangesValidator::class, 'flags' => API_ALLOW_DNS | API_ALLOW_USER_MACRO, 'macros' => ['{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'], 'length' => DB::getFieldLength('items', 'trapper_hosts')];
                }
        }
        return [];
    }

    /**
     * @param string $field_name
     * @param array $db_item
     *
     * @return array
     */
    final protected static function getUpdateFieldRuleInherited(string $field_name, array $db_item): array
    {
        $is_item_prototype = $db_item['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE;

        switch ($field_name) {
            case 'interfaceid':
                return [MultipleValidator::class,
                    'rules' => [IdValidator::class, 'when' => function ($model) {
                        return in_array($model->host_status, [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);
                    }],
                    'else' => [IdValidator::class, 'in' => '0']
                ];

            case 'authtype':
                switch (static::TYPE) {
                    case ITEM_TYPE_HTTPAGENT:
                        return [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED];

                    case ITEM_TYPE_SSH:
                        return [Int32Validator::class, 'in' => implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY])];
                }

            case 'username':
                switch (static::TYPE) {
                    case ITEM_TYPE_HTTPAGENT:
                        return [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED];

                    case ITEM_TYPE_SSH:
                    case ITEM_TYPE_TELNET:
                        return [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'username')];

                    default:
                        return [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'username')];
                }

            case 'password':
                switch (static::TYPE) {
                    case ITEM_TYPE_HTTPAGENT:
                        return [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED];

                    default:
                        return [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'password')];
                }

            case 'params':
                switch (static::TYPE) {
                    case ITEM_TYPE_CALCULATED:
                        return [CalcFormulaValidator::class, 'flags' => ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'params')];

                    case ITEM_TYPE_SCRIPT:
                        return [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED];

                    default:
                        return [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'params')];
                }

            case 'timeout':
                return [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED];

            case 'delay':
                switch (static::TYPE) {
                    case ITEM_TYPE_PERSEUS_ACTIVE:
                        return [MultipleValidator::class,
                            'rules' => [ItemDelayValidator::class, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay'), 'when' => function ($model) {
                                return strncmp($model->key_, 'mqtt.get', 8) !== 0;
                            }],
                            'else' => [TimeUnitValidator::class, 'in' => DB::getDefault('items', 'delay')]
                        ];

                    default:
                        return [ItemDelayValidator::class, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'delay')];
                }

            case 'trapper_hosts':
                switch (static::TYPE) {
                    case ITEM_TYPE_HTTPAGENT:
                        return [MultipleValidator::class,
                            'rules' => [IpRangesValidator::class, 'flags' => API_ALLOW_DNS | API_ALLOW_USER_MACRO, 'macros' => ['{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'], 'length' => DB::getFieldLength('items', 'trapper_hosts'), 'when' => function ($model) {
                                return $model->allow_traps == HTTPCHECK_ALLOW_TRAPS_ON;
                            }],
                            'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'trapper_hosts')]
                        ];

                    case  ITEM_TYPE_TRAPPER:
                        return [IpRangesValidator::class, 'flags' => API_ALLOW_DNS | API_ALLOW_USER_MACRO, 'macros' => ['{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'], 'length' => DB::getFieldLength('items', 'trapper_hosts')];
                }
        }
        return [];
    }

    /**
     * @param string $field_name
     *
     * @return array
     */
    final protected static function getUpdateFieldRuleDiscovered(string $field_name): array
    {
        switch ($field_name) {
            case 'interfaceid':
            case 'authtype':
            case 'username':
            case 'password':
            case 'params':
            case 'timeout':
            case 'delay':
            case 'trapper_hosts':
                return [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED];
        }
        return [];
    }

    /**
     * @return array
     */
    final public static function getDefaultValidationRules(): array
    {
        return [
            // The fields used for multiple item types.
            'interfaceid' => [IdValidator::class, 'in' => '0'],
            'authtype' => [Int32Validator::class, 'in' => DB::getDefault('items', 'authtype')],
            'username' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'username')],
            'password' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'password')],
            'params' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'params')],
            'timeout' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'timeout')],
            'delay' => [TimeUnitValidator::class, 'in' => DB::getDefault('items', 'delay')],
            'trapper_hosts' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'trapper_hosts')],

            // Dependent item type specific fields.
            'master_itemid' => [IdValidator::class, 'in' => '0'],

            // HTTP Agent item type specific fields.
            'url' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'url')],
            'query_fields' => [ObjectsValidator::class, 'length' => 0],
            'request_method' => [Int32Validator::class, 'in' => DB::getDefault('items', 'request_method')],
            'post_type' => [Int32Validator::class, 'in' => DB::getDefault('items', 'post_type')],
            'posts' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'posts')],
            'headers' => [ObjectValidator::class, 'fields' => []],
            'status_codes' => [Int32RangesValidator::class, 'in' => DB::getDefault('items', 'status_codes')],
            'follow_redirects' => [Int32Validator::class, 'in' => DB::getDefault('items', 'follow_redirects')],
            'retrieve_mode' => [Int32Validator::class, 'in' => DB::getDefault('items', 'retrieve_mode')],
            'output_format' => [Int32Validator::class, 'in' => DB::getDefault('items', 'output_format')],
            'http_proxy' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'http_proxy')],
            'verify_peer' => [Int32Validator::class, 'in' => DB::getDefault('items', 'verify_peer')],
            'verify_host' => [Int32Validator::class, 'in' => DB::getDefault('items', 'verify_host')],
            'ssl_cert_file' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'ssl_cert_file')],
            'ssl_key_file' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'ssl_key_file')],
            'ssl_key_password' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'ssl_key_password')],
            'allow_traps' => [Int32Validator::class, 'in' => DB::getDefault('items', 'allow_traps')],

            // IPMI item type specific fields.
            'ipmi_sensor' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'ipmi_sensor')],

            // JMX item type specific fields.
            'jmx_endpoint' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'jmx_endpoint')],

            // Script item type specific fields.
            'parameters' => [ObjectsValidator::class, 'length' => 0],

            // SNMP item type specific fields.
            'snmp_oid' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'snmp_oid')],

            // SSH item type specific fields.
            'publickey' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'publickey')],
            'privatekey' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'privatekey')]
        ];
    }
}
