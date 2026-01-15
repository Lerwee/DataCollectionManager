<?php

namespace app\customs\zapi\services\assist;

use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\factory\ItemTypeFactory;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\itemtypes\CItemType;
use app\customs\zapi\common\managers\TriggerManager;
use app\customs\zapi\common\managers\TriggerPrototypeManager;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\PreProcParamsValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Interfaces;
use app\modules\libzbx\models\Items;
use app\modules\libzbx\models\zbx\ItemParameter;
use app\modules\libzbx\models\zbx\ItemPreproc;
use app\modules\libzbx\models\zbx\ItemTag;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\db\Query;

/**
 * Class BaseItemForm
 * @package app\customs\zapi\forms\item
 */
abstract class BaseItemAssist extends BaseAssist
{
    public const ACCESS_RULES = [
        'get' => ['min_user_type' => USER_TYPE_PERSEUS_USER],
        'create' => ['min_user_type' => USER_TYPE_PERSEUS_ADMIN],
        'update' => ['min_user_type' => USER_TYPE_PERSEUS_ADMIN],
        'delete' => ['min_user_type' => USER_TYPE_PERSEUS_ADMIN]
    ];

    public const INTERFACE_TYPES_BY_PRIORITY = [
        INTERFACE_TYPE_AGENT,
        INTERFACE_TYPE_SNMP,
        INTERFACE_TYPE_JMX,
        INTERFACE_TYPE_IPMI
    ];

    /**
     * A list of supported preprocessing types.
     *
     * @var array
     */
    public const SUPPORTED_PREPROCESSING_TYPES = [];

    /**
     * A list of preprocessing types that supports the "params" field.
     *
     * @var array
     */
    protected const PREPROC_TYPES_WITH_PARAMS = [];

    /**
     * A list of preprocessing types that supports the error handling.
     *
     * @var array
     */
    protected const PREPROC_TYPES_WITH_ERR_HANDLING = [];

    /**
     * A list of supported item types.
     *
     * @var array
     */
    protected const SUPPORTED_ITEM_TYPES = [];

    /**
     * A list of field names for each of value types.
     *
     * @var array
     */
    protected const VALUE_TYPE_FIELD_NAMES = [];

    /**
     * Maximum number of inheritable items per iteration.
     *
     * @var int
     */
    protected const INHERIT_CHUNK_SIZE = 1000;

    /**
     * @param array $items
     * @throws Exception
     */
    protected static function validateUniqueness(array &$items)
    {
        $fieldRules = [
            'uuid' => ['safe'],
            'hostid' => ['safe'],
            'key_' => ['safe']
        ];
        if (ValidateHelper::validateObjects($items, $fieldRules, ['uniq' => [['uuid'], ['hostid', 'key_']]], $error)) {
            throw new ValidateException(60750201, $error);
        }
    }

    /**
     * @return array
     */
    protected static function getTagsValidationRules(): array
    {
        return [
            ObjectsValidator::class,
            'uniq' => [['tag', 'value']],
            'fields' => [
                'tag' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
                'value' => [Utf8StringValidator::class, 'length' => 255]
            ]
        ];
    }

    /**
     * @param int $flags
     *
     * @return array
     * @throws \yii\db\Exception
     */
    public static function getPreprocessingValidationRules(int $flags = 0x00): array
    {
        return [
            ObjectsValidator::class,
            'uniq_by_values' => [
                ['type' => [PRS_PREPROC_DELTA_VALUE, PRS_PREPROC_DELTA_SPEED]],
                ['type' => [PRS_PREPROC_THROTTLE_VALUE, PRS_PREPROC_THROTTLE_TIMED_VALUE]],
                ['type' => [PRS_PREPROC_PROMETHEUS_PATTERN, PRS_PREPROC_PROMETHEUS_TO_JSON]],
                ['type' => [PRS_PREPROC_VALIDATE_NOT_SUPPORTED]]
            ],
            'fields' => [
                'type' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => static::SUPPORTED_PREPROCESSING_TYPES],
                'params' => [
                    MultipleValidator::class,
                    'rules' => [
                        PreProcParamsValidator::class, 'flags' => API_REQUIRED | API_ALLOW_USER_MACRO | ($flags & API_ALLOW_LLD_MACRO), 'preproc_type' => ['field' => 'type'], 'when' => function ($model) {
                            return in_array($model->type, static::PREPROC_TYPES_WITH_PARAMS);
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => [DB::getDefault('item_preproc', 'params')]]
                ],
                'error_handler' => [
                    MultipleValidator::class,
                    'rules' =>
                        [
                            [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [PRS_PREPROC_FAIL_DEFAULT, PRS_PREPROC_FAIL_DISCARD_VALUE, PRS_PREPROC_FAIL_SET_VALUE, PRS_PREPROC_FAIL_SET_ERROR], 'when' => function ($model) {
                                return in_array($model->type, array_diff(static::PREPROC_TYPES_WITH_ERR_HANDLING, [PRS_PREPROC_VALIDATE_NOT_SUPPORTED]));
                            }],
                            [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [PRS_PREPROC_FAIL_DISCARD_VALUE, PRS_PREPROC_FAIL_SET_VALUE, PRS_PREPROC_FAIL_SET_ERROR], 'when' => function ($model) {
                                return $model->type == PRS_PREPROC_VALIDATE_NOT_SUPPORTED;
                            }]
                        ],
                    'else' => [Int32Validator::class, 'in' => [DB::getDefault('item_preproc', 'error_handler')]]
                ],
                'error_handler_params' => [
                    MultipleValidator::class,
                    'rules' => [
                        [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'length' => 255, 'when' => function ($model) {
                            return $model->error_handler == PRS_PREPROC_FAIL_SET_VALUE;
                        }],
                        [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255, 'when' => function ($model) {
                            return $model->error_handler == PRS_PREPROC_FAIL_SET_ERROR;
                        }]
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => [DB::getDefault('item_preproc', 'error_handler_params')]]
                ]
            ]
        ];
    }

    /**
     * @param $data
     * @param int $flags
     * @throws ValidateException
     */
    protected function checkItem(&$data, int $flags = PRS_FLAG_DISCOVERY_NORMAL)
    {
        $hostIds = array_unique(ArrayHelper::getColumn($data, 'hostid'));
        list($templates, $hosts) = ItemHelper::getItemHostsAndTemplates($hostIds);
        $currentHostIds = array_unique(array_keys($templates) + array_keys($hosts));
        if (!$currentHostIds || count($currentHostIds) != count($hostIds)) {
            throw new ValidateException(60750203);
        }
        foreach ($data as &$datum) {
            $datum['host_status'] = isset($templates[$datum['hostid']]) ? HOST_STATUS_TEMPLATE : $hosts[$datum['hostid']]['status'];
            $datum['flags'] = $flags;
        }
    }

    /**
     * @param array $fieldNames
     * @param array $items
     * @param array|null $dbItems
     * @throws Exception
     * @throws NotSupportedException
     * @throws \yii\db\Exception
     */
    protected static function validateByType(array $fieldNames, array &$items, array $dbItems = null)
    {
        foreach ($items as $i => &$item) {
            $fieldRules = array_fill_keys($fieldNames, ['safe']);
            $dbItem = ($dbItems === null) ? null : $dbItems[$item['itemid']];
            $itemType = ItemTypeFactory::getObject($item['type']);

            if ($dbItem === null) {
                $fieldRules += $itemType::getCreateValidationRules($item);
            } elseif ($dbItem['templateid'] != 0) {
                if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
                    $item += array_intersect_key($dbItem, array_flip(['allow_traps']));
                } elseif ($item['type'] == ITEM_TYPE_SSH) {
                    $item += array_intersect_key($dbItem, array_flip(['authtype']));
                }

                if ($item['type'] === ITEM_TYPE_SSH && $item['authtype'] == ITEM_AUTHTYPE_PUBLICKEY
                    && $dbItem['authtype'] != ITEM_AUTHTYPE_PUBLICKEY) {
                    $item += array_intersect_key($dbItem, array_flip(['publickey', 'privatekey']));
                }

                $fieldRules += $itemType::getUpdateValidationRulesInherited($dbItem);
            } elseif ($dbItem['flags'] == PRS_FLAG_DISCOVERY_CREATED) {
                $fieldRules += $itemType::getUpdateValidationRulesDiscovered();
            } else {
                if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
                    $item += array_intersect_key($dbItem, array_flip(
                        ['request_method', 'post_type', 'authtype', 'allow_traps']
                    ));
                } elseif ($item['type'] == ITEM_TYPE_SSH) {
                    $item += array_intersect_key($dbItem, array_flip(['authtype']));
                }

                $interfaceTypes = [ITEM_TYPE_PERSEUS, ITEM_TYPE_SIMPLE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_IPMI,
                    ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_HTTPAGENT,
                    ITEM_TYPE_SNMP
                ];

                if (in_array($item['type'], $interfaceTypes)) {
                    $optInterfaceTypes = [ITEM_TYPE_SIMPLE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
                        ITEM_TYPE_HTTPAGENT
                    ];

                    if (in_array($dbItem['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])
                        && (!in_array($dbItem['type'], $interfaceTypes)
                            || (in_array($item['type'], array_diff($interfaceTypes, $optInterfaceTypes))
                                && in_array($dbItem['type'], $optInterfaceTypes)
                                && $dbItem['interfaceid'] == 0))) {
                        $item += array_intersect_key($dbItem, array_flip(['interfaceid']));
                    }
                }

                $usernameTypes = [ITEM_TYPE_SIMPLE, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
                    ITEM_TYPE_JMX, ITEM_TYPE_HTTPAGENT
                ];

                if (in_array($item['type'], [ITEM_TYPE_SSH, ITEM_TYPE_TELNET])) {
                    $optUsernameTypes = array_diff($usernameTypes, [ITEM_TYPE_SSH, ITEM_TYPE_TELNET]);

                    if (!in_array($dbItem['type'], $usernameTypes)
                        || (in_array($dbItem['type'], $optUsernameTypes) && $dbItem['username'] === '')) {
                        $item += array_intersect_key($dbItem, array_flip(['username']));
                    }
                }

                $paramsTypes = [ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED,
                    ITEM_TYPE_SCRIPT
                ];

                if (in_array($item['type'], $paramsTypes) && !in_array($dbItem['type'], $paramsTypes)) {
                    $item += array_intersect_key($dbItem, array_flip(['params']));
                }

                $delayTypes = [ITEM_TYPE_PERSEUS, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_PERSEUS_ACTIVE,
                    ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
                    ITEM_TYPE_CALCULATED, ITEM_TYPE_JMX, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
                ];

                if (in_array($item['type'], $delayTypes)) {
                    if (!in_array($dbItem['type'], $delayTypes)
                        || ($dbItem['type'] == ITEM_TYPE_PERSEUS_ACTIVE
                            && strncmp($dbItem['key_'], 'mqtt.get', 8) === 0)) {
                        $item += array_intersect_key($dbItem, array_flip(['delay']));
                    }
                }

                if ($item['type'] == ITEM_TYPE_DEPENDENT && $dbItem['type'] != ITEM_TYPE_DEPENDENT) {
                    $item += array_intersect_key($dbItem, array_flip(['master_itemid']));
                }

                if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
                    if ($dbItem['type'] != ITEM_TYPE_HTTPAGENT) {
                        $item += array_intersect_key($dbItem, array_flip(['url']));
                    }

                    $postTypes = [PRS_POSTTYPE_JSON, PRS_POSTTYPE_XML];

                    if (in_array($item['post_type'], $postTypes) && !in_array($dbItem['post_type'], $postTypes)) {
                        $item += array_intersect_key($dbItem, array_flip(['posts']));
                    }
                }

                if ($item['type'] == ITEM_TYPE_IPMI
                    && ($dbItem['type'] != ITEM_TYPE_IPMI
                        || ($item['key_'] !== $dbItem['key_'] && $dbItem['key_'] === 'ipmi.get'))) {
                    $item += array_intersect_key($dbItem, array_flip(['ipmi_sensor']));
                }

                if ($item['type'] == ITEM_TYPE_JMX && $dbItem['type'] != ITEM_TYPE_JMX) {
                    $item += array_intersect_key($dbItem, array_flip(['jmx_endpoint']));
                }

                if ($item['type'] == ITEM_TYPE_SNMP && $dbItem['type'] != ITEM_TYPE_SNMP) {
                    $item += array_intersect_key($dbItem, array_flip(['snmp_oid']));
                }

                if ($item['type'] === ITEM_TYPE_SSH && $item['authtype'] == ITEM_AUTHTYPE_PUBLICKEY
                    && $dbItem['authtype'] != ITEM_AUTHTYPE_PUBLICKEY) {
                    $item += array_intersect_key($dbItem, array_flip(['publickey', 'privatekey']));
                }

                $fieldRules += $itemType::getUpdateValidationRules($dbItem);
            }

            $fieldRules += CItemType::getDefaultValidationRules();
            if (!ValidateHelper::validateObject($item, $fieldRules, ['_path' => $i + 1], $error)) {
                throw new ValidateException(60750201, $error);
            }

            if ($item['type'] == ITEM_TYPE_JMX) {
                if (array_key_exists('username', $item) || array_key_exists('password', $item)
                    || ($dbItem !== null && $dbItem['type'] != ITEM_TYPE_JMX)) {
                    $_item = array_intersect_key($item, array_flip(['username', 'password']));

                    if ($dbItem === null) {
                        $_item += array_fill_keys(['username', 'password'], '');
                    } else {
                        $_item += array_intersect_key($dbItem, array_flip(['username', 'password']));
                    }

                    if (($_item['username'] === '') !== ($_item['password'] === '')) {
                        $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1), 'error' => t('zapi', 'both username and password should be either present or empty')]);
                        throw new ValidateException(60750201, $error);
                    }
                }
            }

            if (array_key_exists('query_fields', $item)) {
                foreach ($item['query_fields'] as $query_field) {
                    if (count($query_field) != 1 || key($query_field) === '') {
                        $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/query_fields', 'error' => t('zapi', 'nonempty key and value pair expected')]);
                        throw new ValidateException(60750201, $error);
                    }
                }

                $item['query_fields'] = $item['query_fields'] ? json_encode($item['query_fields']) : '';

                if (strlen($item['query_fields']) > DB::getFieldLength('items', 'query_fields')) {
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/query_fields', 'error' => t('zapi', 'value is too long')]);
                    throw new ValidateException(60750201, $error);
                }
            }

            if (array_key_exists('headers', $item)) {
                foreach ($item['headers'] as $name => $value) {
                    if (trim($name) === '' || !is_string($value) || $value === '') {
                        $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/headers', 'error' => t('zapi', 'nonempty key and value pair expected')]);
                        throw new ValidateException(60750201, $error);
                    }
                }

                $item['headers'] = self::headersArrayToString($item['headers']);

                if (strlen($item['headers']) > DB::getFieldLength('items', 'headers')) {
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/headers', 'error' => t('zapi', 'value is too long')]);
                    throw new ValidateException(60750201, $error);
                }
            }
        }
        unset($item);
    }

    /**
     * Converts headers fields hash to string.
     * @param array $headers Array of headers where key is header name.
     * @return string
     */
    protected static function headersArrayToString(array $headers): string
    {
        $result = [];

        foreach ($headers as $k => $v) {
            $result[] = $k . ': ' . $v;
        }

        return implode("\r\n", $result);
    }

    /**
     * Add the UUID to those of the given items that belong to a template and don't have the 'uuid' parameter set.
     *
     * @param array $items
     */
    protected static function addUuid(array &$items): void
    {
        foreach ($items as &$item) {
            if ($item['host_status'] == HOST_STATUS_TEMPLATE && !array_key_exists('uuid', $item)) {
                $item['uuid'] = generateUuidV4();
            }
        }
        unset($item);
    }


    /**
     * @param array $items
     * @param array|null $dbItems
     * @throws Exception
     */
    protected static function checkUuidDuplicates(array $items, ?array $dbItems = null)
    {
        $itemIndexes = [];
        foreach ($items as $i => $item) {
            if (!array_key_exists('uuid', $item)) {
                continue;
            }

            if ($dbItems === null || $item['uuid'] !== $dbItems[$item['itemid']]['uuid']) {
                $itemIndexes[$item['uuid']] = $i;
            }
        }

        if (!$itemIndexes) {
            return;
        }

        $flags = $items[reset($itemIndexes)]['flags'];
        $duplicates = Items::find()->select(['uuid'])->where(['flags' => $flags, 'uuid' => array_keys($itemIndexes)])->limit(1)->asArray()->one();

        if ($duplicates) {
            switch ($flags) {
                case PRS_FLAG_DISCOVERY_NORMAL:
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($itemIndexes[$duplicates['uuid']] + 1), 'error' => t('zapi', 'item with the same UUID already exists')]);
                    break;

                case PRS_FLAG_DISCOVERY_PROTOTYPE:
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($itemIndexes[$duplicates['uuid']] + 1), 'error' => t('zapi', 'item prototype with the same UUID already exists')]);
                    break;
            }
            throw new ValidateException(60750201, $error);
        }
    }

    /**
     * @param array $items
     * @param array|null $dbItems
     * @throws Exception
     */
    protected static function checkDuplicates(array $items, array $dbItems = null)
    {
        $hostKeys = [];

        foreach ($items as $item) {
            if ($dbItems === null || $item['key_'] !== $dbItems[$item['itemid']]['key_']) {
                $hostKeys[$item['hostid']][] = $item['key_'];
            }
        }

        if (!$hostKeys) {
            return;
        }

        $where = ['or'];
        foreach ($hostKeys as $hostid => $keys) {
            $where[] = ['i.hostid' => $hostid, 'i.key_' => $keys];
        }

        $duplicates = (new Query())->select(['i.key_', 'i.flags', 'h.host', 'h.status'])->from(['i' => Items::tableName(), 'h' => Hosts::tableName()])
            ->where('i.hostid=h.hostid')
            ->andWhere($where)
            ->limit(1)
            ->one();

        if ($duplicates) {
            $target_is_template = ($duplicates['status'] == HOST_STATUS_TEMPLATE);

            $errorTmp = '';
            switch ($duplicates['flags']) {
                case PRS_FLAG_DISCOVERY_NORMAL:
                case PRS_FLAG_DISCOVERY_CREATED:
                    $errorTmp = $target_is_template
                        ? t('zapi', 'An item with key "{key}" already exists on the template "{name}".')
                        : t('zapi', 'An item with key "{key}" already exists on the host "{name}".');
                    break;

                case PRS_FLAG_DISCOVERY_PROTOTYPE:
                    $errorTmp = $target_is_template
                        ? t('zapi', 'An item prototype with key "{key}" already exists on the template "{name}".')
                        : t('zapi', 'An item prototype with key "{key}" already exists on the host "{name}".');
                    break;

                case PRS_FLAG_DISCOVERY_RULE:
                    $errorTmp = $target_is_template
                        ? t('zapi', 'An LLD rule with key "{key}" already exists on the template "{name}".')
                        : t('zapi', 'An LLD rule with key "{key}" already exists on the host "{name}".');
                    break;
            }
            $error = t('zapi', $errorTmp, ['key' => $duplicates['key_'], 'name' => $duplicates['host']]);
            throw new ValidateException(60750201, $error);
        }
    }

    /**
     * @param array $items
     * @param array|null $dbItems
     * @throws Exception
     */
    protected static function checkValueMaps(array $items, array $dbItems = null)
    {
        $itemIndexes = [];
        foreach ($items as $i => $item) {
            if (array_key_exists('valuemapid', $item) && $item['valuemapid'] != 0
                && ($dbItems === null
                    || bccomp($item['valuemapid'], $dbItems[$item['itemid']]['valuemapid']) != 0)) {
                $itemIndexes[$item['valuemapid']][] = $i;
            }
        }

        if (!$itemIndexes) {
            return;
        }

        $valueMaps = (new Query())->select(['valuemapid', 'hostid'])->from('valuemap')->where(['valuemapid' => array_keys($itemIndexes)])->all();
        foreach ($valueMaps as $valueMap) {
            foreach ($itemIndexes[$valueMap['valuemapid']] as $i) {
                if (bccomp($valueMap['hostid'], $items[$i]['hostid']) != 0) {
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/valuemapid', 'error' => t('zapi', 'cannot be a value map ID from another host or template')]);
                    throw new ValidateException(60750201, $error);
                }
            }
        }
    }

    /**
     * @param array $items
     * @param array|null $db_items
     * @throws Exception
     */
    protected static function checkHostInterfaces(array $items, array $db_items = null)
    {
        foreach ($items as $i => &$item) {
            $interface_type = ItemHelper::itemTypeInterface($item['type']);

            if (!in_array($item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])
                || $interface_type === false) {
                unset($items[$i]);
                continue;
            }

            $check = false;

            if ($db_items === null) {
                if (array_key_exists('interfaceid', $item)) {
                    if ($item['interfaceid'] != 0) {
                        $check = true;
                    } elseif ($interface_type != INTERFACE_TYPE_OPT) {
                        $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/interfaceid', 'error' => t('zapi', 'the host interface ID is expected')]);
                        throw new ValidateException(60750201, $error);
                    }
                }
            } else {
                $db_item = $db_items[$item['itemid']];

                if ($item['type'] == $db_item['type']) {
                    if (array_key_exists('interfaceid', $item)) {
                        if ($item['interfaceid'] != 0) {
                            if (bccomp($item['interfaceid'], $db_item['interfaceid']) != 0) {
                                $check = true;
                            }
                        } elseif ($interface_type != INTERFACE_TYPE_OPT) {
                            $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/interfaceid', 'error' => t('zapi', 'the host interface ID is expected')]);
                            throw new ValidateException(60750201, $error);
                        }
                    }
                } else {
                    $db_interface_type = ItemHelper::itemTypeInterface($db_item['type']);
                    if (array_key_exists('interfaceid', $item)) {
                        if ($item['interfaceid'] != 0) {
                            if (bccomp($item['interfaceid'], $db_item['interfaceid']) != 0
                                || ($interface_type != INTERFACE_TYPE_OPT
                                    && $interface_type != $db_interface_type)) {
                                $check = true;
                            }
                        } elseif ($interface_type != INTERFACE_TYPE_OPT) {
                            $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/interfaceid', 'error' => t('zapi', 'the host interface ID is expected')]);
                            throw new ValidateException(60750201, $error);
                        }
                    } else {
                        if ($db_item['interfaceid'] != 0) {
                            if ($interface_type != INTERFACE_TYPE_OPT && $interface_type != $db_interface_type) {
                                $item += ['interfaceid' => $db_item['interfaceid']];
                                $check = true;
                            }
                        } elseif ($interface_type != INTERFACE_TYPE_OPT) {
                            $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1), 'error' => t('zapi', 'the parameter "{parameter}" is missing', ['parameter' => 'interfaceid'])]);
                            throw new ValidateException(60750201, $error);
                        }
                    }
                }
            }

            if (!$check) {
                unset($items[$i]);
            }
        }
        unset($item);

        if (!$items) {
            return;
        }

        $db_interfaces = (new Query())->select(['interfaceid', 'hostid', 'type'])
            ->from('interface')
            ->where(['interfaceid' => array_unique(array_column($items, 'interfaceid'))])
            ->indexBy(['interfaceid'])
            ->all();

        foreach ($items as $i => $item) {
            if (!array_key_exists($item['interfaceid'], $db_interfaces)) {
                $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/interfaceid', 'error' => t('zapi', 'the host interface ID is expected')]);
                throw new ValidateException(60750201, $error);
            }

            if (bccomp($db_interfaces[$item['interfaceid']]['hostid'], $item['hostid']) != 0) {
                $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/interfaceid', 'error' => t('zapi', 'cannot be the host interface ID from another host')]);
                throw new ValidateException(60750201, $error);
            }

            $interface_type = ItemHelper::itemTypeInterface($item['type']);

            if ($interface_type != INTERFACE_TYPE_OPT
                && $db_interfaces[$item['interfaceid']]['type'] != $interface_type) {
                $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/interfaceid', 'error' => t('zapi', 'the host interface ID of type "{type}" is expected', ['type' => ItemHelper::interfaceType2str($interface_type)])]);
                throw new ValidateException(60750201, $error);
            }
        }
    }

    /**
     * @param array $items
     * @param array|null $hostids
     *
     * @return array
     */
    protected static function getTemplateLinks(array $items, ?array $hostids): array
    {
        if ($hostids !== null) {
            $db_hosts = Hosts::find()->select(['hostid', 'status'])
                ->where(['hostid' => $hostids])
                ->indexBy('hostid')->asArray()->all();
            $tpl_links = [];
            foreach ($items as $item) {
                $tpl_links[$item['hostid']] = $db_hosts;
            }
        } else {
            $templateids = [];
            foreach ($items as $item) {
                if ($item['host_status'] == HOST_STATUS_TEMPLATE) {
                    $templateids[$item['hostid']] = true;
                }
            }

            if (!$templateids) {
                return [];
            }

            $rows = (new Query())->select(['ht.templateid', 'ht.hostid', 'h.status'])
                ->from(['ht' => 'hosts_templates', 'h' => 'hosts'])
                ->where('ht.hostid=h.hostid')
                ->andWhere(['ht.templateid' => array_keys($templateids)])
                ->andWhere(['h.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]])
                ->all();

            $tpl_links = [];
            foreach ($rows as $row) {
                $tpl_links[$row['templateid']][$row['hostid']] = [
                    'hostid' => $row['hostid'],
                    'status' => $row['status']
                ];
            }
        }
        return $tpl_links;
    }

    /**
     * Filter out inheritable items from the given items.
     *
     * @param array $items
     * @param array $db_items
     * @param array $tpl_links
     */
    protected static function filterObjectsToInherit(array &$items, array &$db_items, array $tpl_links): void
    {
        foreach ($items as $i => $item) {
            if (!array_key_exists($item['hostid'], $tpl_links)) {
                unset($items[$i]);

                if (array_key_exists($item['itemid'], $db_items)) {
                    unset($db_items[$item['itemid']]);
                }
            }
        }
    }

    /**
     * Check that no items with repeating keys would be inherited to a single host or template.
     *
     * @param array $items
     * @param array $db_items
     * @param array $tpl_links
     * @throws Exception
     */
    protected static function checkDoubleInheritedNames(array $items, array $db_items, array $tpl_links)
    {
        $item_indexes = [];
        foreach ($items as $i => $item) {
            if (array_key_exists($item['itemid'], $db_items) && $item['key_'] === $db_items[$item['itemid']]['key_']) {
                continue;
            }
            $item_indexes[$item['key_']][] = $i;
        }

        foreach ($item_indexes as $key => $indexes) {
            if (count($indexes) == 1) {
                continue;
            }

            $hostids = [];
            foreach ($indexes as $i) {
                $templateid = $items[$i]['hostid'];
                $same_hosts = array_intersect_key($tpl_links[$templateid], $hostids);

                if ($same_hosts) {
                    $same_host = reset($same_hosts);

                    $templateid_first = $hostids[$same_host['hostid']];
                    $templateid_second = $templateid;

                    $hosts = Hosts::find()->select(['hostid', 'host'])
                        ->where(['hostid' => [$templateid_first, $templateid_second, $same_host['hostid']]])
                        ->indexBy('hostid')
                        ->asArray()->all();

                    $target_is_host = in_array($same_host['status'],
                        [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]
                    );

                    $errorTmp = '';
                    switch ($items[$i]['flags']) {
                        case PRS_FLAG_DISCOVERY_NORMAL:
                            $errorTmp = $target_is_host
                                ? t('zapi', 'Cannot inherit items with key "{key1}" of both "{template1}" and "{template2}" templates, because the key must be unique on host "{name}".')
                                : t('zapi', 'Cannot inherit items with key "{key1}" of both "{template1}" and "{template2}" templates, because the key must be unique on template "{name}".');
                            break;

                        case PRS_FLAG_DISCOVERY_PROTOTYPE:
                            $errorTmp = $target_is_host
                                ? t('zapi', 'Cannot inherit item prototypes with key "{key}" of both "{template1}" and "{template2}" templates, because the key must be unique on host "{name}".')
                                : t('zapi', 'Cannot inherit item prototypes with key "{key}" of both "{template1}" and "{template2}" templates, because the key must be unique on template "{name}".');
                            break;

                        case PRS_FLAG_DISCOVERY_RULE:
                            $errorTmp = $target_is_host
                                ? t('zapi', 'Cannot inherit LDD rules with key "{key}" of both "{template1}" and "{template2}" templates, because the key must be unique on host "{name}".')
                                : t('zapi', 'Cannot inherit LDD rules with key "{key}" of both "{template1}" and "{template2}" templates, because the key must be unique on template "{name}".');
                            break;
                    }

                    throw new ValidateException(60750201, t('zapi', $errorTmp, [
                        'key' => $key,
                        'template1' => $hosts[$templateid_first]['host'],
                        'template2' => $hosts[$templateid_second]['host'],
                        'name' => $hosts[$same_host['hostid']]['host']
                    ]));
                }

                $hostids += array_fill_keys(array_keys($tpl_links[$templateid]), $templateid);
            }
        }
    }

    /**
     * Get item chunks to inherit.
     *
     * @param array $items
     * @param array $tpl_links
     *
     * @return array
     */
    protected static function getInheritChunks(array $items, array $tpl_links): array
    {
        $chunks = [
            [
                'item_indexes' => [],
                'hosts' => [],
                'size' => 0
            ]
        ];
        $last = 0;

        foreach ($items as $i => $item) {
            $hosts_chunks = array_chunk($tpl_links[$item['hostid']], self::INHERIT_CHUNK_SIZE, true);

            foreach ($hosts_chunks as $hosts) {
                if ($chunks[$last]['size'] < self::INHERIT_CHUNK_SIZE) {
                    $_hosts = array_slice($hosts, 0, self::INHERIT_CHUNK_SIZE - $chunks[$last]['size'], true);

                    $can_add_hosts = true;

                    foreach ($chunks[$last]['item_indexes'] as $_i) {
                        $new_hosts = array_diff_key($_hosts, $chunks[$last]['hosts']);

                        if (array_intersect_key($tpl_links[$items[$_i]['hostid']], $new_hosts)) {
                            $can_add_hosts = false;
                            break;
                        }
                    }

                    if ($can_add_hosts) {
                        $chunks[$last]['item_indexes'][] = $i;
                        $chunks[$last]['hosts'] += $_hosts;
                        $chunks[$last]['size'] += count($_hosts);

                        $hosts = array_diff_key($hosts, $_hosts);
                    }
                }

                if ($hosts) {
                    $chunks[++$last] = [
                        'item_indexes' => [$i],
                        'hosts' => $hosts,
                        'size' => count($hosts)
                    ];
                }
            }
        }

        return $chunks;
    }

    /**
     * Inherit dependent items in nesting order.
     *
     * @param array $dep_items_to_link [<master item index>][<dependent item index>]
     * @param array $items_to_link
     * @param array $hostids
     * @throws Exception
     */
    protected static function inheritDependentItems(array $dep_items_to_link, array $items_to_link, array $hostids)
    {
        while ($dep_items_to_link) {
            $items = [];

            foreach ($dep_items_to_link as $i => $_items) {
                if (array_key_exists($i, $items_to_link)) {
                    $items += $_items;
                    unset($dep_items_to_link[$i]);
                }
            }

            static::inherit(array_values($items), [], $hostids, true);
            $items_to_link = $items;
        }
    }

    /**
     * @param array $items
     * @param array $db_items
     * @param array|null $hostids
     * @param bool $is_dep_items Inherit called for dependent items.
     */
//    abstract protected static function inherit(array $items, array $db_items = [], array $hostids = null, bool $is_dep_items = false);

    /**
     * Add default values for fields that became unnecessary as the result of the change of the type fields.
     * @param array $items
     * @param array $db_items
     * @throws NotSupportedException
     * @throws \yii\db\Exception
     */
    protected static function addFieldDefaultsByType(array &$items, array $db_items): void {
        $type_field_defaults = [
            // The fields used for multiple item types.
            'interfaceid' => 0,
            'authtype' => DB::getDefault('items', 'authtype'),
            'username' => DB::getDefault('items', 'username'),
            'password' => DB::getDefault('items', 'password'),
            'params' => DB::getDefault('items', 'params'),
            'timeout' => DB::getDefault('items', 'timeout'),
            'delay' => DB::getDefault('items', 'delay'),
            'trapper_hosts' => DB::getDefault('items', 'trapper_hosts'),

            // Dependent item type specific fields.
            'master_itemid' => 0,

            // HTTP Agent item type specific fields.
            'url' => DB::getDefault('items', 'url'),
            'query_fields' => DB::getDefault('items', 'query_fields'),
            'request_method' => DB::getDefault('items', 'request_method'),
            'post_type' => DB::getDefault('items', 'post_type'),
            'posts' => DB::getDefault('items', 'posts'),
            'headers' => DB::getDefault('items', 'headers'),
            'status_codes' => DB::getDefault('items', 'status_codes'),
            'follow_redirects' => DB::getDefault('items', 'follow_redirects'),
            'retrieve_mode' => DB::getDefault('items', 'retrieve_mode'),
            'output_format' => DB::getDefault('items', 'output_format'),
            'http_proxy' => DB::getDefault('items', 'http_proxy'),
            'verify_peer' => DB::getDefault('items', 'verify_peer'),
            'verify_host' => DB::getDefault('items', 'verify_host'),
            'ssl_cert_file' => DB::getDefault('items', 'ssl_cert_file'),
            'ssl_key_file' => DB::getDefault('items', 'ssl_key_file'),
            'ssl_key_password' => DB::getDefault('items', 'ssl_key_password'),
            'allow_traps' => DB::getDefault('items', 'allow_traps'),

            // IPMI item type specific fields.
            'ipmi_sensor' => DB::getDefault('items', 'ipmi_sensor'),

            // JMX item type specific fields.
            'jmx_endpoint' => DB::getDefault('items', 'jmx_endpoint'),

            // Script item type specific fields.
            'parameters' => [],

            // SNMP item type specific fields.
            'snmp_oid' => DB::getDefault('items', 'snmp_oid'),

            // SSH item type specific fields.
            'publickey' => DB::getDefault('items', 'publickey'),
            'privatekey' => DB::getDefault('items', 'privatekey')
        ];

        $value_type_field_defaults = [
            'units' => DB::getDefault('items', 'units'),
            'trends' => DB::getDefault('items', 'trends'),
            'valuemapid' => 0,
            'logtimefmt' => DB::getDefault('items', 'logtimefmt'),
            'inventory_link' => DB::getDefault('items', 'inventory_link')
        ];

        foreach ($items as &$item) {
            if (!array_key_exists('type', $db_items[$item['itemid']])) {
                continue;
            }

            $db_item = $db_items[$item['itemid']];

            if ($item['type'] != $db_item['type']) {
                $type_field_names = ItemTypeFactory::getObject($item['type'])::FIELD_NAMES;
                $db_type_field_names = ItemTypeFactory::getObject($db_item['type'])::FIELD_NAMES;

                $field_names = array_flip(array_diff($db_type_field_names, $type_field_names));

                if ($item['type'] == ITEM_TYPE_PERSEUS_ACTIVE && strncmp($item['key_'], 'mqtt.get', 8) == 0) {
                    $field_names += array_flip(['delay']);
                }

                if (array_intersect([$item['type'], $db_item['type']], [ITEM_TYPE_SSH, ITEM_TYPE_HTTPAGENT])) {
                    $field_names += array_flip(['authtype']);
                }

                if ($item['host_status'] == HOST_STATUS_TEMPLATE && array_key_exists('interfaceid', $field_names)) {
                    unset($field_names['interfaceid']);
                }

                $item += array_intersect_key($type_field_defaults, $field_names);
            }
            elseif ($item['type'] == ITEM_TYPE_PERSEUS_ACTIVE) {
                if (array_key_exists('key_', $item) && $item['key_'] !== $db_item['key_']
                    && strncmp($item['key_'], 'mqtt.get', 8) == 0) {
                    $item += array_intersect_key($type_field_defaults, array_flip(['delay']));
                }
            }
            elseif ($item['type'] == ITEM_TYPE_SSH) {
                if (array_key_exists('authtype', $item) && $item['authtype'] !== $db_item['authtype']
                    && $item['authtype'] == ITEM_AUTHTYPE_PASSWORD) {
                    $item += array_intersect_key($type_field_defaults, array_flip(['publickey', 'privatekey']));
                }
            }
            elseif ($item['type'] == ITEM_TYPE_HTTPAGENT) {
                if (array_key_exists('request_method', $item) && $item['request_method'] != $db_item['request_method']
                    && $item['request_method'] == HTTPCHECK_REQUEST_HEAD) {
                    $item += ['retrieve_mode' => HTTPTEST_STEP_RETRIEVE_MODE_HEADERS];
                }

                if (array_key_exists('authtype', $item) && $item['authtype'] != $db_item['authtype']
                    && $item['authtype'] == PRS_HTTP_AUTH_NONE) {
                    $item += array_intersect_key($type_field_defaults, array_flip(['username', 'password']));
                }

                if (array_key_exists('allow_traps', $item) && $item['allow_traps'] != $db_item['allow_traps']
                    && $item['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_OFF) {
                    $item += array_intersect_key($type_field_defaults, array_flip(['trapper_hosts']));
                }
            }

            if (array_key_exists('value_type', $item) && $item['value_type'] != $db_item['value_type']) {
                $type_field_names = static::VALUE_TYPE_FIELD_NAMES[$item['value_type']];
                $db_type_field_names = static::VALUE_TYPE_FIELD_NAMES[$db_item['value_type']];

                $field_names = array_flip(array_diff($db_type_field_names, $type_field_names));

                if (array_key_exists('trends', $field_names)) {
                    $item += ['trends' => 0];
                }

                $item += array_intersect_key($value_type_field_defaults, $field_names);
            }
        }
        unset($item);
    }

    /**
     * @param array $item
     * @param array $upd_db_item
     *
     * @throws Exception
     */
    protected static function showObjectMismatchError(array $item, array $upd_db_item): void
    {
        $target_is_host = in_array($upd_db_item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);

        $hosts = Hosts::find()->select(['host', 'hostid'])
            ->where(['hostid' => [$item['hostid'], $upd_db_item['hostid']]])
            ->indexBy('hostid')->asArray()->all();

        $error = '';

        switch ($item['flags']) {
            case PRS_FLAG_DISCOVERY_NORMAL:
                switch ($upd_db_item['flags']) {
                    case PRS_FLAG_DISCOVERY_RULE:
                        $error = $target_is_host
                            ? t('zapi', 'Cannot inherit item with key "{key}" of template "{template}" to host "{name}", because an LLD rule with the same key already exists.')
                            : t('zapi', 'Cannot inherit item with key "{key}" of template "{template}" to template "{name}", because an LLD rule with the same key already exists.');
                        break 2;

                    case PRS_FLAG_DISCOVERY_PROTOTYPE:
                        $error = $target_is_host
                            ? t('zapi', 'Cannot inherit item with key "{key}" of template "{template}" to host "{name}", because an item prototype with the same key already exists.')
                            : t('zapi', 'Cannot inherit item with key "{key}" of template "{template}" to template "{name}", because an item prototype with the same key already exists.');
                        break 2;

                    case PRS_FLAG_DISCOVERY_CREATED:
                        $error = $target_is_host
                            ? t('zapi', 'Cannot inherit item with key "{key}" of template "{template}" to host "{name}", because a discovered item with the same key already exists.')
                            : t('zapi', 'Cannot inherit item with key "{key}" of template "{template}" to template "{name}", because a discovered item with the same key already exists.');
                        break 2;
                }
                break;

            case PRS_FLAG_DISCOVERY_RULE:
                switch ($upd_db_item['flags']) {
                    case PRS_FLAG_DISCOVERY_NORMAL:
                        $error = $target_is_host
                            ? t('zapi', 'Cannot inherit LLD rule with key "{key}" of template "{template}" to host "{name}", because an item with the same key already exists.')
                            : t('zapi', 'Cannot inherit LLD rule with key "{key}" of template "{template}" to template "{name}", because an item with the same key already exists.');
                        break 2;

                    case PRS_FLAG_DISCOVERY_PROTOTYPE:
                        $error = $target_is_host
                            ? t('zapi', 'Cannot inherit LLD rule with key "{key}" of template "{template}" to host "{name}", because an item prototype with the same key already exists.')
                            : t('zapi', 'Cannot inherit LLD rule with key "{key}" of template "{template}" to template "{name}", because an item prototype with the same key already exists.');
                        break 2;

                    case PRS_FLAG_DISCOVERY_CREATED:
                        $error = $target_is_host
                            ? t('zapi', 'Cannot inherit LLD rule with key "{key}" of template "{template}" to host "{name}", because a discovered item with the same key already exists.')
                            : t('zapi', 'Cannot inherit LLD rule with key "{key}" of template "{template}" to template "{name}", because a discovered item with the same key already exists.');
                        break 2;
                }
                break;

            case PRS_FLAG_DISCOVERY_PROTOTYPE:
                switch ($upd_db_item['flags']) {
                    case PRS_FLAG_DISCOVERY_NORMAL:
                        $error = $target_is_host
                            ? t('zapi', 'Cannot inherit item prototype with key "{key}" of template "{template}" to host "{name}", because an item with the same key already exists.')
                            : t('zapi', 'Cannot inherit item prototype with key "{key}" of template "{template}" to template "{name}", because an item with the same key already exists.');
                        break 2;

                    case PRS_FLAG_DISCOVERY_RULE:
                        $error = $target_is_host
                            ? t('zapi', 'Cannot inherit item prototype with key "{key}" of template "{template}" to host "{name}", because an LLD rule with the same key already exists.')
                            : t('zapi', 'Cannot inherit item prototype with key "{key}" of template "{template}" to template "{name}", because an LLD rule with the same key already exists.');
                        break 2;

                    case PRS_FLAG_DISCOVERY_CREATED:
                        $error = $target_is_host
                            ? t('zapi', 'Cannot inherit item prototype with key "{key}" of template "{template}" to host "{name}", because a discovered item with the same key already exists.')
                            : t('zapi', 'Cannot inherit item prototype with key "{key}" of template "{template}" to template "{name}", because a discovered item with the same key already exists.');
                        break 2;
                }
                break;
        }

        if ($error) {
            throw new ValidateException(60750201, t('zapi', $error, ['key' => $upd_db_item['key_'], 'template' => $hosts[$item['hostid']]['host'], 'name' => $hosts[$upd_db_item['hostid']]['host']]));
        }

        if ($upd_db_item['templateid'] == 0) {
            return;
        }

        $template = (new Query())->select(['h.host'])
            ->from(['i' => 'items', 'h' => 'hosts'])
            ->where('i.hostid=h.hostid')
            ->andWhere(['i.itemid' => [$upd_db_item['templateid']]])
            ->limit(1)
            ->one();

        switch ($item['flags']) {
            case PRS_FLAG_DISCOVERY_NORMAL:
                $error = $target_is_host
                    ? t('zapi', 'Cannot inherit item with key "{key}" of template "{template1}" to host "{name}", because an item with the same key is already inherited from template "{template2}".')
                    : t('zapi', 'Cannot inherit item with key "{key}" of template "{template1}" to template "{name}", because an item with the same key is already inherited from template "{template2}".');
                break;

            case PRS_FLAG_DISCOVERY_RULE:
                $error = $target_is_host
                    ? t('zapi', 'Cannot inherit LLD rule with key "{key}" of template "{template1}" to host "{name}", because an item with the same key is already inherited from template "{template2}".')
                    : t('zapi', 'Cannot inherit LLD rule with key "{key}" of template "{template1}" to template "{name}", because an item with the same key is already inherited from template "{template2}".');
                break;

            case PRS_FLAG_DISCOVERY_PROTOTYPE:
                $error = $target_is_host
                    ? t('zapi', 'Cannot inherit item prototype with key "{key}" of template "{template1}" to host "{name}", because an item with the same key is already inherited from template "{template2}".')
                    : t('zapi', 'Cannot inherit item prototype with key "{key}" of template "{template1}" to template "{name}", because an item with the same key is already inherited from template "{template2}".');
                break;
        }

        throw new ValidateException(60750201, t('zapi', $error, [
            'key' => $upd_db_item['key_'],
            'template1' => $hosts[$item['hostid']]['host'],
            'name' => $hosts[$upd_db_item['hostid']]['host'],
            'template2' => $template['host']
        ]));
    }

    /**
     * Note: instances may override this to add e.g. tags.
     *
     * @param array $items
     * @param array $db_items
     */
    protected static function addAffectedObjects(array $items, array &$db_items): void
    {
        self::addAffectedTags($items, $db_items);
        self::addAffectedPreprocessing($items, $db_items);
        self::addAffectedParameters($items, $db_items);
    }

    /**
     * @param array $items
     * @param array $db_items
     */
    protected static function addAffectedTags(array $items, array &$db_items): void
    {
        $itemids = [];

        foreach ($items as $item) {
            if (array_key_exists('tags', $item)) {
                $itemids[] = $item['itemid'];
                $db_items[$item['itemid']]['tags'] = [];
            }
        }

        if (!$itemids) {
            return;
        }

        $db_item_tags = ItemTag::find()->select(['itemtagid', 'itemid', 'tag', 'value'])->where(['itemid' => $itemids])->asArray()->all();

        foreach ($db_item_tags as $db_item_tag) {
            $db_items[$db_item_tag['itemid']]['tags'][$db_item_tag['itemtagid']] = array_diff_key($db_item_tag, array_flip(['itemid']));
        }
    }

    /**
     * @param array $items
     * @param array $db_items
     */
    protected static function addAffectedPreprocessing(array $items, array &$db_items): void
    {
        $itemids = [];

        foreach ($items as $item) {
            if (array_key_exists('preprocessing', $item)) {
                $itemids[] = $item['itemid'];
                $db_items[$item['itemid']]['preprocessing'] = [];
            }
        }

        if (!$itemids) {
            return;
        }

        $db_item_preprocs = ItemPreproc::find()
            ->select(['item_preprocid', 'itemid', 'step', 'type', 'params', 'error_handler', 'error_handler_params'])
            ->where(SqlHelper::whereIn('itemid', $itemids))->asArray()->all();

        foreach ($db_item_preprocs as $db_item_preproc) {
            $db_items[$db_item_preproc['itemid']]['preprocessing'][$db_item_preproc['item_preprocid']] =
                array_diff_key($db_item_preproc, array_flip(['itemid']));
        }
    }

    /**
     * @param array $items
     * @param array $db_items
     */
    protected static function addAffectedParameters(array $items, array &$db_items): void
    {
        $itemids = [];

        foreach ($items as $item) {
            $db_type = $db_items[$item['itemid']]['type'];

            if ((array_key_exists('parameters', $item) && $item['type'] == ITEM_TYPE_SCRIPT)
                || ($item['type'] != $db_type && $db_type == ITEM_TYPE_SCRIPT)) {
                $itemids[] = $item['itemid'];
                $db_items[$item['itemid']]['parameters'] = [];
            } elseif (array_key_exists('parameters', $item)) {
                $db_items[$item['itemid']]['parameters'] = [];
            }
        }

        if (!$itemids) {
            return;
        }

        $db_item_parameters = ItemParameter::find()
            ->select(['item_parameterid', 'itemid', 'name', 'value'])
            ->where(SqlHelper::whereIn('itemid', $itemids))->asArray()->all();
        foreach ($db_item_parameters as $db_item_parameter) {
            $db_items[$db_item_parameter['itemid']]['parameters'][$db_item_parameter['item_parameterid']] =
                array_diff_key($db_item_parameter, array_flip(['itemid']));
        }
    }

    /**
     * @param array $item
     *
     * @return array
     */
    protected static function unsetNestedObjectIds(array $item): array
    {
        if (array_key_exists('tags', $item)) {
            foreach ($item['tags'] as &$tag) {
                unset($tag['itemtagid']);
            }
            unset($tag);
        }

        if (array_key_exists('preprocessing', $item)) {
            foreach ($item['preprocessing'] as &$preprocessing) {
                unset($preprocessing['item_preprocid']);
            }
            unset($preprocessing);
        }

        if (array_key_exists('parameters', $item)) {
            foreach ($item['parameters'] as &$parameter) {
                unset($parameter['item_parameterid']);
            }
            unset($parameter);
        }

        return $item;
    }

    /**
     * Add the internally used fields to the given $db_items.
     *
     * @param array $db_items
     */
    protected static function addInternalFields(array &$db_items): void
    {
        $rows = (new Query())->select(['i.itemid', 'i.hostid', 'i.templateid', 'i.flags', 'host_status' => 'h.status'])
            ->from(['i' => 'items', 'h' => 'hosts'])
            ->where('i.hostid=h.hostid')
            ->andWhere(SqlHelper::whereIn('i.itemid', array_keys($db_items)))
            ->all();
        foreach ($rows as $row) {
            $db_items[$row['itemid']] += $row;
        }
    }

    /**
     * Update relation to master item for inherited dependent items.
     *
     * @param array $upd_items
     * @param array $ins_items
     * @param array $hostids
     */
    protected static function setChildMasterItemIds(array &$upd_items, array &$ins_items, array $hostids): void
    {
        $upd_item_indexes = [];
        $ins_item_indexes = [];

        foreach ($upd_items as $i => $upd_item) {
            if ($upd_item['type'] == ITEM_TYPE_DEPENDENT && array_key_exists('master_itemid', $upd_item)) {
                $upd_item_indexes[$upd_item['master_itemid']][$upd_item['hostid']][] = $i;
            }
        }

        foreach ($ins_items as $i => $ins_item) {
            if ($ins_item['type'] == ITEM_TYPE_DEPENDENT) {
                $ins_item_indexes[$ins_item['master_itemid']][$ins_item['hostid']][] = $i;
            }
        }

        if (!$upd_item_indexes && !$ins_item_indexes) {
            return;
        }

        $rows = Items::find()->select(['itemid', 'hostid', 'templateid'])
            ->where([
                'templateid' => array_keys($ins_item_indexes + $upd_item_indexes),
                'hostid' => $hostids
            ])
            ->asArray()
            ->all();

        foreach ($rows as $row) {
            if (array_key_exists($row['templateid'], $upd_item_indexes)
                && array_key_exists($row['hostid'], $upd_item_indexes[$row['templateid']])) {
                foreach ($upd_item_indexes[$row['templateid']][$row['hostid']] as $i) {
                    $upd_items[$i]['master_itemid'] = $row['itemid'];
                }
            }

            if (array_key_exists($row['templateid'], $ins_item_indexes)
                && array_key_exists($row['hostid'], $ins_item_indexes[$row['templateid']])) {
                foreach ($ins_item_indexes[$row['templateid']][$row['hostid']] as $i) {
                    $ins_items[$i]['master_itemid'] = $row['itemid'];
                }
            }
        }
    }

    /**
     * @param array $upd_items
     * @param array $upd_db_items
     * @param array $ins_items
     * @throws Exception
     */
    protected static function addInterfaceIds(array &$upd_items, array $upd_db_items, array &$ins_items)
    {
        $upd_item_indexes = [];
        $ins_item_indexes = [];
        $interface_types = [];

        $upd_item_indexes_by_interfaceid = [];

        foreach ($upd_items as $i => $upd_item) {
            if (!in_array($upd_item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])) {
                continue;
            }

            $interface_type = ItemHelper::itemTypeInterface($upd_item['type']);

            if ($interface_type === false) {
                continue;
            }

            if ($upd_db_items[$upd_item['itemid']]['interfaceid'] != 0) {
                $db_interface_type = ItemHelper::itemTypeInterface($upd_db_items[$upd_item['itemid']]['type']);

                if ($interface_type != $db_interface_type) {
                    if ($db_interface_type == INTERFACE_TYPE_OPT) {
                        $upd_item_indexes_by_interfaceid[$upd_db_items[$upd_item['itemid']]['interfaceid']][] = $i;
                    } elseif ($interface_type != INTERFACE_TYPE_OPT) {
                        $upd_item_indexes[$upd_item['hostid']][$interface_type][] = $i;

                        if ($interface_types !== null) {
                            $interface_types[$interface_type] = true;
                        }
                    }
                }
            } else {
                $upd_item_indexes[$upd_item['hostid']][$interface_type][] = $i;

                if ($interface_types !== null) {
                    if ($interface_type == INTERFACE_TYPE_OPT) {
                        $interface_types = null;
                    } else {
                        $interface_types[$interface_type] = true;
                    }
                }
            }
        }

        if ($upd_item_indexes_by_interfaceid) {
            $rows = Interfaces::find()->select(['interfaceid', 'type'])
                ->where(['interfaceid' => array_keys($upd_item_indexes_by_interfaceid)])
                ->asArray()->all();

            foreach ($rows as $row) {
                foreach ($upd_item_indexes_by_interfaceid[$row['interfaceid']] as $i) {
                    $upd_item = $upd_items[$i];
                    $interface_type = ItemHelper::itemTypeInterface($upd_item['type']);

                    if ($interface_type != $row['type']) {
                        $upd_item_indexes[$upd_item['hostid']][$interface_type][] = $i;

                        if ($interface_types !== null) {
                            $interface_types[$interface_type] = true;
                        }
                    }
                }
            }
        }

        foreach ($ins_items as $i => $ins_item) {
            if (!in_array($ins_item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])) {
                continue;
            }

            $interface_type = ItemHelper::itemTypeInterface($ins_item['type']);

            if ($interface_type === false) {
                continue;
            }

            $ins_item_indexes[$ins_item['hostid']][$interface_type][] = $i;

            if ($interface_types !== null) {
                if ($interface_type == INTERFACE_TYPE_OPT) {
                    $interface_types = null;
                } else {
                    $interface_types[$interface_type] = true;
                }
            }
        }

        if (!$upd_item_indexes && !$ins_item_indexes) {
            return;
        }

        $rows = Interfaces::find()->select(['interfaceid', 'hostid', 'type'])
            ->where([
                'hostid' => array_keys($upd_item_indexes + $ins_item_indexes),
                'main' => INTERFACE_PRIMARY
            ])
            ->andFilterWhere(['type' => $interface_types !== null ? array_keys($interface_types) : []])
            ->asArray()->all();

        $priority_interfaces = [];

        foreach ($rows as $row) {
            $has_opt_type_items = false;
            if (array_key_exists($row['hostid'], $upd_item_indexes)) {
                if (array_key_exists(INTERFACE_TYPE_OPT, $upd_item_indexes[$row['hostid']])) {
                    $has_opt_type_items = true;
                }

                if (array_key_exists($row['type'], $upd_item_indexes[$row['hostid']])) {
                    foreach ($upd_item_indexes[$row['hostid']][$row['type']] as $_i => $i) {
                        $upd_items[$i]['interfaceid'] = $row['interfaceid'];

                        unset($upd_item_indexes[$row['hostid']][$row['type']][$_i]);
                    }

                    if (!$upd_item_indexes[$row['hostid']][$row['type']]) {
                        unset($upd_item_indexes[$row['hostid']][$row['type']]);
                    }

                    if (!$upd_item_indexes[$row['hostid']]) {
                        unset($upd_item_indexes[$row['hostid']]);
                    }
                }
            }

            if (array_key_exists($row['hostid'], $ins_item_indexes)) {
                if (array_key_exists(INTERFACE_TYPE_OPT, $ins_item_indexes[$row['hostid']])) {
                    $has_opt_type_items = true;
                }

                if (array_key_exists($row['type'], $ins_item_indexes[$row['hostid']])) {
                    foreach ($ins_item_indexes[$row['hostid']][$row['type']] as $_i => $i) {
                        $ins_items[$i]['interfaceid'] = $row['interfaceid'];

                        unset($ins_item_indexes[$row['hostid']][$row['type']][$_i]);
                    }

                    if (!$ins_item_indexes[$row['hostid']][$row['type']]) {
                        unset($ins_item_indexes[$row['hostid']][$row['type']]);
                    }

                    if (!$ins_item_indexes[$row['hostid']]) {
                        unset($ins_item_indexes[$row['hostid']]);
                    }
                }
            }

            if ($has_opt_type_items) {
                $priority_index = array_search($row['type'], self::INTERFACE_TYPES_BY_PRIORITY);

                if (!array_key_exists($row['hostid'], $priority_interfaces)
                    || $priority_index < $priority_interfaces[$row['hostid']]['priority_index']) {
                    $priority_interfaces[$row['hostid']] = [
                        'interfaceid' => $row['interfaceid'],
                        'type' => $row['type'],
                        'priority_index' => $priority_index
                    ];
                }
            }
        }

        foreach ($upd_item_indexes as $hostid => $item_indexes) {
            if (!array_key_exists(INTERFACE_TYPE_OPT, $item_indexes)) {
                continue;
            }

            foreach ($item_indexes[INTERFACE_TYPE_OPT] as $i) {
                if (array_key_exists($hostid, $priority_interfaces)) {
                    $upd_items[$i]['interfaceid'] = $priority_interfaces[$hostid]['interfaceid'];
                }
            }

            unset($upd_item_indexes[$hostid][INTERFACE_TYPE_OPT]);

            if (!$upd_item_indexes[$hostid]) {
                unset($upd_item_indexes[$hostid]);
            }
        }

        foreach ($ins_item_indexes as $hostid => $item_indexes) {
            if (!array_key_exists(INTERFACE_TYPE_OPT, $item_indexes)) {
                continue;
            }

            foreach ($item_indexes[INTERFACE_TYPE_OPT] as $i) {
                if (array_key_exists($hostid, $priority_interfaces)) {
                    $ins_items[$i]['interfaceid'] = $priority_interfaces[$hostid]['interfaceid'];
                }
            }

            unset($ins_item_indexes[$hostid][INTERFACE_TYPE_OPT]);

            if (!$ins_item_indexes[$hostid]) {
                unset($ins_item_indexes[$hostid]);
            }
        }

        $item = $interface_type = null;

        if ($upd_item_indexes) {
            $hostid = key($upd_item_indexes);
            $interface_type = key($upd_item_indexes[$hostid]);
            $i = reset($upd_item_indexes[$hostid][$interface_type]);

            $item = $upd_items[$i];
        } elseif ($ins_item_indexes) {
            $hostid = key($ins_item_indexes);
            $interface_type = key($ins_item_indexes[$hostid]);
            $i = reset($ins_item_indexes[$hostid][$interface_type]);

            $item = $ins_items[$i];
        }

        if ($item === null) {
            return;
        }

        $template = (new Query())->from(['i' => 'items', 'h' => 'hosts'])
            ->select('h.host')
            ->where('i.hostid=h.hostid')
            ->andWhere(['i.itemid' => $item['templateid']])
            ->limit(1)->one();

        $host = Hosts::find()->select(['host'])->where(['hostid' => $item['hostid']])->asArray()->limit(1)->one();

        $error = '';
        switch ($item['flags']) {
            case PRS_FLAG_DISCOVERY_NORMAL:
                $error = t('zapi', 'Cannot inherit item with key "{key}" of template "{template}" to host "{name}", because a host interface of type "{type}" is required.');
                break;

            case PRS_FLAG_DISCOVERY_CREATED:
                $error = t('zapi', 'Cannot inherit LLD rule with key "{key}" of template "{template}" to host "{name}", because a host interface of type "{type}" is required.');
                break;

            case PRS_FLAG_DISCOVERY_PROTOTYPE:
                $error = t('zapi', 'Cannot inherit item prototype with key "{key}" of template "{template}" to host "{name}", because a host interface of type "{type}" is required.');
                break;
        }
        throw new ValidateException(60750201, t('zapi', $error, [
                'key' => $item['key_'], 'template' => $template['host'],
                'name' => $host['host'], 'type' => ItemHelper::interfaceType2str($interface_type)]
        ));
    }


    /**
     * Check that dependent items of given items are valid.
     * @param array $items
     * @param array $db_items
     * @param bool $inherited
     * @throws Exception
     */
    protected static function checkDependentItems(array $items, array $db_items = [], bool $inherited = false)
    {
        $del_links = [];

        foreach ($items as $i => $item) {
            $check = false;

            if ($item['type'] == ITEM_TYPE_DEPENDENT) {
                if (!array_key_exists('itemid', $item)) {
                    if ($item['master_itemid'] != 0) {
                        $check = true;
                    } else {
                        $subError = $item['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE
                            ? t('zapi', 'an item/item prototype ID is expected')
                            : t('zapi', 'an item ID is expected');
                        $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/master_itemid', 'error' => $subError]);
                        throw new ValidateException(60750201, $error);
                    }
                } else {
                    if (array_key_exists('master_itemid', $item)) {
                        if ($item['master_itemid'] != 0) {
                            if (bccomp($item['master_itemid'], $db_items[$item['itemid']]['master_itemid']) != 0) {
                                $check = true;

                                if ($db_items[$item['itemid']]['master_itemid'] != 0) {
                                    $del_links[$item['itemid']] = $db_items[$item['itemid']]['master_itemid'];
                                }
                            }
                        } else {
                            $subError = $item['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE
                                ? t('zapi', 'an item/item prototype ID is expected')
                                : t('zapi', 'an item ID is expected');
                            $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/master_itemid', 'error' => $subError]);
                            throw new ValidateException(60750201, $error);
                        }
                    }
                }
            } elseif (array_key_exists('itemid', $item) && $db_items[$item['itemid']]['type'] == ITEM_TYPE_DEPENDENT) {
                $del_links[$item['itemid']] = $db_items[$item['itemid']]['master_itemid'];
            }

            if (!$check) {
                unset($items[$i]);
            }
        }

        if (!$items) {
            return;
        }

        if (!$inherited) {
            self::checkMasterItems($items, $db_items);
        }

        $dep_item_links = self::getDependentItemLinks($items, $del_links);

        if (!$inherited && $db_items) {
            self::checkCircularDependencies($items, $dep_item_links);
        }

        $root_itemids = [];

        foreach ($dep_item_links as $itemid => $master_itemid) {
            if ($master_itemid == 0) {
                $root_itemids[] = $itemid;
            }
        }

        $master_item_links = self::getMasterItemLinks($items, $root_itemids, $del_links);

        foreach ($root_itemids as $root_itemid) {
            if (self::maxDependencyLevelExceeded($master_item_links, $root_itemid, $links_path)) {
                [$flags, $key, $master_flags, $master_key, $is_template, $host] =
                    self::getProblemCausedItemData($links_path, $items);

                $errorTmp = self::getDependentItemError($flags, $master_flags, $is_template);
                $error = t('zapi', $errorTmp, [
                    'key1' => $key, 'key2' => $master_key, 'name' => $host,
                    'error' => t('zapi', 'allowed count of dependency levels would be exceeded')
                ]);
                throw new ValidateException(60750201, $error);
            }

            if (self::maxDependentItemCountExceeded($master_item_links, $root_itemid, $links_path)) {
                [$flags, $key, $master_flags, $master_key, $is_template, $host] =
                    self::getProblemCausedItemData($links_path, $items);

                $errorTmp = self::getDependentItemError($flags, $master_flags, $is_template);

                $error = t('zapi', $errorTmp, [
                    'key1' => $key, 'key2' => $master_key, 'name' => $host,
                    'error' => t('zapi', 'allowed count of dependent items would be exceeded')
                ]);
                throw new ValidateException(60750201, $error);
            }
        }
    }

    /**
     * Check prerpocessing steps for specifics validation rules.
     * @param array $items
     * @throws Exception
     */
    protected static function checkPreprocessingSteps(array $items)
    {
        foreach ($items as $i => $item) {
            if (!array_key_exists('preprocessing', $item)) {
                continue;
            }

            foreach ($item['preprocessing'] as $j => $step) {
                if ($step['type'] == PRS_PREPROC_SNMP_WALK_TO_JSON) {
                    $params = explode("\n", $step['params']);

                    if (count($params) % 3 !== 0) {
                        $error = t('zapi', 'Incorrect value for field "{attribute}", {error}.', ['attribute' => '/' . ($i + 1) . '/preprocessing/' . ($j + 1) . '/params', 'error' => t('zapi', 'cannot be empty')]);
                        throw new ValidateException(60750201, $error);
                    }

                    for ($n = 1; $n <= count($params); $n++) {
                        $param = $params[$n - 1];

                        if ($param === '') {
                            $error = t('zapi', 'Incorrect value for field "{attribute}", {error}.', ['attribute' => '/' . ($i + 1) . '/preprocessing/' . ($j + 1) . '/params', 'error' => t('zapi', 'cannot be empty')]);
                            throw new ValidateException(60750201, $error);
                        }

                        // Field "Treat as" every 3rd value. Check that field is correct.
                        if ($n % 3 === 0) {
                            if (!in_array($param, [PRS_PREPROC_SNMP_UNCHANGED, PRS_PREPROC_SNMP_UTF8_FROM_HEX,
                                PRS_PREPROC_SNMP_MAC_FROM_HEX, PRS_PREPROC_SNMP_INT_FROM_BITS])) {
                                $error = t('zapi', 'Incorrect value for field "{attribute}", {error}.', ['attribute' => '/' . ($i + 1) . '/preprocessing/' . ($j + 1) . '/params', 'error' => t('zapi', 'incorrect value')]);
                                throw new ValidateException(60750201, $error);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Check that master item IDs of given dependent items are valid.
     * @param array $items
     * @param array $db_items
     * @throws Exception
     */
    private static function checkMasterItems(array $items, array $db_items)
    {
        $master_itemids = array_unique(array_column($items, 'master_itemid'));
        $flags = $items[key($items)]['flags'];

        if ($flags == PRS_FLAG_DISCOVERY_PROTOTYPE) {
            $db_master_items = Items::find()->alias('i')
                ->select(['i.itemid', 'i.hostid', 'i.master_itemid', 'i.flags', 'ruleid' => 'id.parent_itemid'])
                ->leftJoin(['id' => 'item_discovery'], 'i.itemid=id.itemid')
                ->where(SqlHelper::whereIn('i.itemid', $master_itemids))
                ->andWhere(['i.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_PROTOTYPE]])
                ->indexBy('itemid')
                ->asArray()->all();
        } else {
            $db_master_items = Items::find()->select(['itemid', 'hostid', 'master_itemid'])
                ->where(SqlHelper::whereIn('itemid', $master_itemids))
                ->andWhere(['flags' => PRS_FLAG_DISCOVERY_NORMAL])
                ->indexBy('itemid')
                ->asArray()->all();
        }

        foreach ($items as $i => $item) {
            if (!array_key_exists($item['master_itemid'], $db_master_items)) {
                $subError = $flags == PRS_FLAG_DISCOVERY_PROTOTYPE
                    ? t('zapi', 'an item/item prototype ID is expected')
                    : t('zapi', 'an item ID is expected');

                $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/master_itemid', 'error' => $subError]);
                throw new ValidateException(60750201, $error);
            }

            $db_master_item = $db_master_items[$item['master_itemid']];

            if (bccomp($db_master_item['hostid'], $item['hostid']) != 0) {
                $subError = $flags == PRS_FLAG_DISCOVERY_PROTOTYPE
                    ? t('zapi', 'cannot be an item/item prototype ID from another host or template')
                    : t('zapi', 'cannot be an item ID from another host or template');

                $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/master_itemid', 'error' => $subError]);
                throw new ValidateException(60750201, $error);
            }

            if ($flags == PRS_FLAG_DISCOVERY_PROTOTYPE && $db_master_item['ruleid'] != 0) {
                $item_ruleid = array_key_exists('itemid', $item)
                    ? $db_items[$item['itemid']]['ruleid']
                    : $item['ruleid'];

                if (bccomp($db_master_item['ruleid'], $item_ruleid) != 0) {
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/master_itemid', 'error' => t('zapi', 'cannot be an item prototype ID from another LLD rule')]);
                    throw new ValidateException(60750201, $error);
                }
            }
        }
    }

    /**
     * Get dependent item links starting from the given dependent items and till the highest dependency level.
     *
     * @param array $items
     * @param array $del_links
     *
     * @return array  Array of the links where each key contain the ID of dependent item and value contain the
     *                appropriate ID of the master item.
     */
    private static function getDependentItemLinks(array $items, array $del_links): array
    {
        $links = array_column($items, 'master_itemid', 'itemid');
        $master_itemids = array_flip(array_column($items, 'master_itemid'));

        while ($master_itemids) {
            $db_master_items = Items::find()->select(['itemid', 'hostid', 'master_itemid'])
                ->where(SqlHelper::whereIn('itemid', array_keys($master_itemids)))->asArray()->all();
            $master_itemids = [];
            foreach ($db_master_items as $db_master_item) {
                if (array_key_exists($db_master_item['itemid'], $del_links)
                    && bccomp($db_master_item['master_itemid'], $del_links[$db_master_item['itemid']]) == 0) {
                    $links[$db_master_item['itemid']] = 0;
                    continue;
                }

                $links[$db_master_item['itemid']] = $db_master_item['master_itemid'];

                if ($db_master_item['master_itemid'] != 0) {
                    $master_itemids[$db_master_item['master_itemid']] = true;
                }
            }
        }

        return $links;
    }


    /**
     * Check that the changed master item IDs of dependent items do not create a circular dependencies.
     * @param array $items
     * @param array $dep_item_links
     * @throws Exception
     */
    private static function checkCircularDependencies(array $items, array $dep_item_links)
    {
        foreach ($items as $i => $item) {
            $master_itemid = $item['master_itemid'];

            while ($master_itemid != 0) {
                if (bccomp($master_itemid, $item['itemid']) == 0) {
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/' . ($i + 1) . '/master_itemid', 'error' => t('zapi', 'circular item dependency is not allowed')]);
                    throw new ValidateException(60750201, $error);
                }
                $master_itemid = $dep_item_links[$master_itemid];
            }
        }
    }

    /**
     * Get master item links starting from the given master items and till the lowest level master items.
     *
     * @param array $items
     * @param array $master_itemids
     * @param array $del_links
     *
     * @return array  Array of the links where each key contain the ID of master item and value contain the array of
     *                appropriate dependent item IDs.
     */
    private static function getMasterItemLinks(array $items, array $master_itemids, array $del_links): array
    {
        $ins_links = [];
        $upd_item_links = [];

        foreach ($items as $item) {
            if (array_key_exists('itemid', $item)) {
                $upd_item_links[$item['master_itemid']][] = $item['itemid'];
            } else {
                $ins_links[$item['master_itemid']][] = 0;
            }
        }

        $links = [];

        do {
            $db_items = Items::find()->select(['master_itemid', 'itemid'])
                ->where(SqlHelper::whereIn('master_itemid', $master_itemids))->asArray()->all();

            $_master_itemids = [];

            foreach ($db_items as $db_item) {
                if (array_key_exists($db_item['itemid'], $del_links)
                    && bccomp($db_item['master_itemid'], $del_links[$db_item['itemid']]) == 0) {
                    continue;
                }

                $links[$db_item['master_itemid']][] = $db_item['itemid'];
                $_master_itemids[] = $db_item['itemid'];
            }

            foreach ($master_itemids as $master_itemid) {
                if (array_key_exists($master_itemid, $upd_item_links)) {
                    foreach ($upd_item_links[$master_itemid] as $itemid) {
                        $_master_itemids[] = $itemid;
                        $links[$master_itemid][] = $itemid;
                    }
                }
            }

            $master_itemids = $_master_itemids;
        } while ($master_itemids);

        foreach ($ins_links as $master_itemid => $ins_items) {
            $links[$master_itemid] = array_key_exists($master_itemid, $links)
                ? array_merge($links[$master_itemid], $ins_items)
                : $ins_items;
        }

        return $links;
    }


    /**
     * Check whether maximum number of dependency levels is exceeded.
     *
     * @param array $master_item_links
     * @param string $master_itemid
     * @param array|null $links_path
     * @param int $level
     *
     * @return bool
     */
    private static function maxDependencyLevelExceeded(array $master_item_links, string $master_itemid, array &$links_path = null, int $level = 0): bool
    {
        if (!array_key_exists($master_itemid, $master_item_links)) {
            return false;
        }

        if ($links_path === null) {
            $links_path = [];
        }

        $links_path[] = $master_itemid;
        $level++;

        if ($level > PRS_DEPENDENT_ITEM_MAX_LEVELS) {
            return true;
        }

        foreach ($master_item_links[$master_itemid] as $itemid) {
            $_links_path = $links_path;

            if (self::maxDependencyLevelExceeded($master_item_links, $itemid, $_links_path, $level)) {
                $links_path = $_links_path;

                return true;
            }
        }

        return false;
    }


    /**
     * Check whether maximum count of dependent items is exceeded.
     *
     * @param array $master_item_links
     * @param string $master_itemid
     * @param array|null $links_path
     * @param int $count
     *
     * @return bool
     */
    private static function maxDependentItemCountExceeded(array $master_item_links, string $master_itemid, array &$links_path = null, int &$count = 0): bool
    {
        if (!array_key_exists($master_itemid, $master_item_links)) {
            return false;
        }

        if ($links_path === null) {
            $links_path = [];
        }

        $links_path[] = $master_itemid;
        $count += count($master_item_links[$master_itemid]);

        if ($count > PRS_DEPENDENT_ITEM_MAX_COUNT) {
            return true;
        }

        foreach ($master_item_links[$master_itemid] as $itemid) {
            $_links_path = $links_path;

            if (self::maxDependentItemCountExceeded($master_item_links, $itemid, $_links_path, $count)) {
                $links_path = $_links_path;

                return true;
            }
        }

        return false;
    }

    /**
     * Get data for a dependent item that causes a problem, based on the given path where the problem was detected.
     *
     * @param array $links_path
     * @param array $items
     *
     * @return array
     */
    private static function getProblemCausedItemData(array $links_path, array $items): array
    {
        foreach ($items as $item) {
            if (in_array($item['master_itemid'], $links_path)) {
                break;
            }
        }

        $master_item_data = (new Query())->select(['i.flags', 'i.key_', 'h.host'])->from(['i' => 'items', 'h' => 'hosts'])
            ->where('i.hostid=h.hostid')
            ->andWhere(['i.itemid' => $item['master_itemid']])
            ->limit(1)
            ->one();

        $flags = $item['flags'];
        $key = $item['key_'];
        $master_flags = $master_item_data['flags'];
        $master_key = $master_item_data['key_'];
        $is_template = $item['host_status'] == HOST_STATUS_TEMPLATE;
        $host = $master_item_data['host'];

        return [$flags, $key, $master_flags, $master_key, $is_template, $host];
    }

    /**
     * Get the error message about problem with dependent item according to given data.
     *
     * @param int $flags
     * @param int $master_flags
     * @param bool $is_template
     *
     * @return string
     */
    private static function getDependentItemError(int $flags, int $master_flags, bool $is_template): string
    {
        if ($flags == PRS_FLAG_DISCOVERY_NORMAL) {
            return $is_template
                ? t('zapi', 'Cannot set dependency for item with key "{key1}" on the master item with key "{key2}" on the template "{name}": {error}.')
                : t('zapi', 'Cannot set dependency for item with key "{key1}" on the master item with key "{key2}" on the host "{name}": {error}.');
        } elseif ($flags == PRS_FLAG_DISCOVERY_PROTOTYPE) {
            if ($master_flags == PRS_FLAG_DISCOVERY_NORMAL) {
                return $is_template
                    ? t('zapi', 'Cannot set dependency for item prototype with key "{key1}" on the master item with key "{key2}" on the template "{name}": {error}.')
                    : t('zapi', 'Cannot set dependency for item prototype with key "{key1}" on the master item with key "{key2}" on the host "{name}": {error}.');
            } else {
                return $is_template
                    ? t('zapi', 'Cannot set dependency for item prototype with key "{key1}" on the master item prototype with key "{key2}" on the template "{name}": {error}.')
                    : t('zapi', 'Cannot set dependency for item prototype with key "{key1}" on the master item prototype with key "{key2}" on the host "{name}": {error}.');
            }
        } elseif ($flags == PRS_FLAG_DISCOVERY_RULE) {
            return $is_template
                ? t('zapi', 'Cannot set dependency for LLD rule with key "{key1}" on the master item with key "{key2}" on the template "{name}": {error}.')
                : t('zapi', 'Cannot set dependency for LLD rule with key "{key1}" on the master item with key "{key2}" on the host "{name}": {error}.');
        }
        return '';
    }

    /**
     * @param array $items
     * @param array|null $db_items
     * @param array|null $upd_itemids
     * @throws \yii\db\Exception
     */
    protected static function updateParameters(array &$items, array $db_items = null, array &$upd_itemids = null): void
    {
        $ins_item_parameters = [];
        $upd_item_parameters = [];
        $del_item_parameterids = [];

        foreach ($items as $i => &$item) {
            $update = false;

            if ($db_items === null) {
                if ($item['type'] == ITEM_TYPE_SCRIPT && array_key_exists('parameters', $item) && $item['parameters']) {
                    $update = true;
                }
            } else {
                if (!array_key_exists('type', $db_items[$item['itemid']])) {
                    continue;
                }

                if ($item['type'] == ITEM_TYPE_SCRIPT) {
                    if (array_key_exists('parameters', $item)) {
                        $update = true;
                    }
                } elseif ($db_items[$item['itemid']]['type'] == ITEM_TYPE_SCRIPT
                    && $db_items[$item['itemid']]['parameters']) {
                    $update = true;
                }
            }

            if (!$update) {
                continue;
            }

            $changed = false;
            $db_item_parameters = ($db_items !== null)
                ? array_column($db_items[$item['itemid']]['parameters'], null, 'name')
                : [];

            foreach ($item['parameters'] as &$item_parameter) {
                if (array_key_exists($item_parameter['name'], $db_item_parameters)) {
                    $db_item_parameter = $db_item_parameters[$item_parameter['name']];
                    $item_parameter['item_parameterid'] = $db_item_parameter['item_parameterid'];
                    unset($db_item_parameters[$db_item_parameter['name']]);

                    $upd_item_parameter = DB::getUpdatedValues('item_parameter', $item_parameter, $db_item_parameter);

                    if ($upd_item_parameter) {
                        $upd_item_parameters[] = [
                            'values' => $upd_item_parameter,
                            'where' => ['item_parameterid' => $db_item_parameter['item_parameterid']]
                        ];
                        $changed = true;
                    }
                } else {
                    $ins_item_parameters[] = ['itemid' => $item['itemid']] + $item_parameter;
                    $changed = true;
                }
            }
            unset($item_parameter);

            if ($db_item_parameters) {
                $del_item_parameterids =
                    array_merge($del_item_parameterids, array_column($db_item_parameters, 'item_parameterid'));
                $changed = true;
            }

            if ($db_items !== null) {
                if ($changed) {
                    $upd_itemids[$i] = $item['itemid'];
                } else {
                    unset($item['parameters']);
                }
            }
        }
        unset($item);

        if ($del_item_parameterids) {
            ItemParameter::deleteAll(['item_parameterid' => $del_item_parameterids]);
        }

        if ($upd_item_parameters) {
            DB::update('item_parameter', $upd_item_parameters);
        }

        if ($ins_item_parameters) {
            $item_parameterids = DB::insert('item_parameter', $ins_item_parameters);
        }

        foreach ($items as &$item) {
            if (!array_key_exists('parameters', $item)) {
                continue;
            }

            foreach ($item['parameters'] as &$item_parameter) {
                if (!array_key_exists('item_parameterid', $item_parameter)) {
                    $item_parameter['item_parameterid'] = array_shift($item_parameterids);
                }
            }
            unset($item_parameter);
        }
        unset($item);
    }

    /**
     * @param array $items
     * @param array|null $db_items
     * @param array|null $upd_itemids
     * @throws \yii\db\Exception
     */
    protected static function updatePreprocessing(array &$items, array $db_items = null, array &$upd_itemids = null): void
    {
        $ins_item_preprocs = [];
        $upd_item_preprocs = [];
        $del_item_preprocids = [];

        foreach ($items as $i => &$item) {
            if (!array_key_exists('preprocessing', $item)) {
                continue;
            }

            $changed = false;
            $db_item_preprocs = ($db_items !== null)
                ? array_column($db_items[$item['itemid']]['preprocessing'], null, 'step')
                : [];

            $step = 1;

            foreach ($item['preprocessing'] as &$item_preproc) {
                $item_preproc['step'] = ($item_preproc['type'] == PRS_PREPROC_VALIDATE_NOT_SUPPORTED) ? 0 : $step++;

                if (array_key_exists($item_preproc['step'], $db_item_preprocs)) {
                    $db_item_preproc = $db_item_preprocs[$item_preproc['step']];
                    $item_preproc['item_preprocid'] = $db_item_preproc['item_preprocid'];
                    unset($db_item_preprocs[$db_item_preproc['step']]);

                    $upd_item_preproc = DB::getUpdatedValues('item_preproc', $item_preproc, $db_item_preproc);

                    if ($upd_item_preproc) {
                        $upd_item_preprocs[] = [
                            'values' => $upd_item_preproc,
                            'where' => ['item_preprocid' => $db_item_preproc['item_preprocid']]
                        ];
                        $changed = true;
                    }
                } else {
                    $ins_item_preprocs[] = ['itemid' => $item['itemid']] + $item_preproc;
                    $changed = true;
                }
            }
            unset($item_preproc);

            if ($db_item_preprocs) {
                $del_item_preprocids =
                    array_merge($del_item_preprocids, array_column($db_item_preprocs, 'item_preprocid'));
                $changed = true;
            }

            if ($db_items !== null) {
                if ($changed) {
                    $upd_itemids[$i] = $item['itemid'];
                } else {
                    unset($item['preprocessing']);
                }
            }
        }
        unset($item);

        if ($del_item_preprocids) {
            DB::delete('item_preproc', ['item_preprocid' => $del_item_preprocids]);
        }

        if ($upd_item_preprocs) {
            DB::update('item_preproc', $upd_item_preprocs);
        }

        if ($ins_item_preprocs) {
            $item_preprocids = DB::insert('item_preproc', $ins_item_preprocs);
        }

        foreach ($items as &$item) {
            if (!array_key_exists('preprocessing', $item)) {
                continue;
            }

            foreach ($item['preprocessing'] as &$item_preproc) {
                if (!array_key_exists('item_preprocid', $item_preproc)) {
                    $item_preproc['item_preprocid'] = array_shift($item_preprocids);
                }
            }
            unset($item_preproc);
        }
        unset($item);
    }

    /**
     * @param array $items
     * @param array|null $db_items
     * @param array|null $upd_itemids
     * @throws \yii\db\Exception
     */
    protected static function updateTags(array &$items, array $db_items = null, array &$upd_itemids = null): void
    {
        $ins_tags = [];
        $del_itemtagids = [];

        foreach ($items as $i => &$item) {
            if (!array_key_exists('tags', $item)) {
                continue;
            }

            $changed = false;
            $db_tags = ($db_items !== null) ? $db_items[$item['itemid']]['tags'] : [];

            foreach ($item['tags'] as &$tag) {
                $db_itemtagid = key(array_filter($db_tags, static function (array $db_tag) use ($tag): bool {
                    return $tag['tag'] === $db_tag['tag']
                        && (!array_key_exists('value', $tag) || $tag['value'] === $db_tag['value']);
                }));

                if ($db_itemtagid !== null) {
                    $tag['itemtagid'] = $db_itemtagid;
                    unset($db_tags[$db_itemtagid]);
                } else {
                    $ins_tags[] = ['itemid' => $item['itemid']] + $tag;
                    $changed = true;
                }
            }
            unset($tag);

            if ($db_tags) {
                $del_itemtagids = array_merge($del_itemtagids, array_keys($db_tags));
                $changed = true;
            }

            if ($db_items !== null) {
                if ($changed) {
                    $upd_itemids[$i] = $item['itemid'];
                } else {
                    unset($item['tags']);
                }
            }
        }
        unset($item);

        if ($del_itemtagids) {
            DB::delete('item_tag', ['itemtagid' => $del_itemtagids]);
        }

        if ($ins_tags) {
            $itemtagids = DB::insert('item_tag', $ins_tags);
        }

        foreach ($items as &$item) {
            if (!array_key_exists('tags', $item)) {
                continue;
            }

            foreach ($item['tags'] as &$tag) {
                if (!array_key_exists('itemtagid', $tag)) {
                    $tag['itemtagid'] = array_shift($itemtagids);
                }
            }
            unset($tag);
        }
        unset($item);
    }

    /**
     * Add the inherited items of the given items to the given item array.
     *
     * @param array $dbItems
     */
    public static function addInheritedItems(array &$dbItems): void
    {
        $templateIds = ArrayHelper::getColumn($dbItems, 'itemid');

        do {
            $inheritedItems = Items::find()->select(['itemid', 'name'])
                ->where(SqlHelper::whereIn('templateid', $templateIds))
                ->asArray()->all();

            $templateIds = [];

            foreach($inheritedItems as $row) {
                if (!array_key_exists($row['itemid'], $dbItems)) {
                    $templateIds[] = $row['itemid'];
                    $dbItems[$row['itemid']] = $row;
                }
            }
        } while ($templateIds);
    }

    /**
     * Add the dependent items of the given items to the given item array. Also add the dependent LLD rules and item
     * prototypes to the given appropriate variables.
     *
     * @param array      $db_items
     * @param array|null $del_ruleids
     * @param array|null $db_item_prototypes
     */
    protected static function addDependentItems(array &$db_items, array &$del_ruleids = null, array &$db_item_prototypes = null): void
    {
        $del_ruleids = [];
        $db_item_prototypes = [];
        $master_itemids = array_keys($db_items);
        do {
            $dependItems = Items::find()->select(['itemid', 'name', 'flags'])
                ->where(SqlHelper::whereIn('master_itemid', $master_itemids))
                ->asArray()->all();

            $master_itemids = [];

            foreach ($dependItems as $row) {
                if ($row['flags'] == PRS_FLAG_DISCOVERY_RULE) {
                    $del_ruleids[] = $row['itemid'];
                }
                elseif ($row['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                    $master_itemids[] = $row['itemid'];

                    $db_item_prototypes[$row['itemid']] = array_diff_key($row, array_flip(['flags']));
                }
                else {
                    if (!array_key_exists($row['itemid'], $db_items)) {
                        $master_itemids[] = $row['itemid'];

                        $db_items[$row['itemid']] = array_diff_key($row, array_flip(['flags']));
                    }
                }
            }
        } while ($master_itemids);
    }

    /**
     * Reset the MIN and MAX values of Y axis in the graphs, if such are calculated using the given items.
     *
     * @param array $del_itemids
     */
    protected static function resetGraphsYAxis(array $del_itemids): void
    {
        DB::update('graphs', [
            'values' => [
                'ymin_type' => GRAPH_YAXIS_TYPE_CALCULATED,
                'ymin_itemid' => null
            ],
            'where' => ['ymin_itemid' => $del_itemids]
        ]);

        DB::update('graphs', [
            'values' => [
                'ymax_type' => GRAPH_YAXIS_TYPE_CALCULATED,
                'ymax_itemid' => null
            ],
            'where' => ['ymax_itemid' => $del_itemids]
        ]);
    }

    /**
     * Delete triggers and trigger prototypes, which contain the given items in the expression.
     *
     * @param array $del_itemids
     */
    protected static function deleteAffectedTriggers(array $del_itemids): void
    {
        $triggers = (new Query())->select(['f.triggerid', 't.flags'])
            ->from(['f' => 'functions', 't' => 'triggers'])
            ->where('f.triggerid=t.triggerid')
            ->andWhere(SqlHelper::whereIn('f.itemid', $del_itemids))
            ->distinct()
            ->all();

        $del_trigger_prototypeids = [];
        $del_triggerids = [];

        foreach ($triggers as $row) {
            if ($row['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                $del_trigger_prototypeids[] = $row['triggerid'];
            }
            else {
                $del_triggerids[] = $row['triggerid'];
            }
        }

        if ($del_triggerids) {
            TriggerManager::delete($del_triggerids);
        }

        if ($del_trigger_prototypeids) {
            TriggerPrototypeManager::delete($del_trigger_prototypeids);
        }
    }
}