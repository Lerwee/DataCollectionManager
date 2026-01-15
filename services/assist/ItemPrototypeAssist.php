<?php

namespace app\customs\zapi\services\assist;

use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\common\itemtypes\CItemType;
use app\customs\zapi\common\managers\GraphPrototypeManager;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\ItemKeyValidator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\TimeUnitValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\UuidValidator;
use app\modules\libzbx\models\Items;
use yii\base\NotSupportedException;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;

/**
 * Class ItemPrototypeAssist
 * @package app\customs\zapi\services\assist
 */
class ItemPrototypeAssist extends BaseItemAssist
{
    /**
     * @inheritDoc
     */
    public const SUPPORTED_PREPROCESSING_TYPES = [
        PRS_PREPROC_MULTIPLIER, PRS_PREPROC_RTRIM, PRS_PREPROC_LTRIM, PRS_PREPROC_TRIM, PRS_PREPROC_REGSUB,
        PRS_PREPROC_BOOL2DEC, PRS_PREPROC_OCT2DEC, PRS_PREPROC_HEX2DEC, PRS_PREPROC_DELTA_VALUE,
        PRS_PREPROC_DELTA_SPEED, PRS_PREPROC_XPATH, PRS_PREPROC_JSONPATH, PRS_PREPROC_VALIDATE_RANGE,
        PRS_PREPROC_VALIDATE_REGEX, PRS_PREPROC_VALIDATE_NOT_REGEX, PRS_PREPROC_ERROR_FIELD_JSON,
        PRS_PREPROC_ERROR_FIELD_XML, PRS_PREPROC_ERROR_FIELD_REGEX, PRS_PREPROC_THROTTLE_VALUE,
        PRS_PREPROC_THROTTLE_TIMED_VALUE, PRS_PREPROC_SCRIPT, PRS_PREPROC_PROMETHEUS_PATTERN,
        PRS_PREPROC_PROMETHEUS_TO_JSON, PRS_PREPROC_CSV_TO_JSON, PRS_PREPROC_STR_REPLACE,
        PRS_PREPROC_VALIDATE_NOT_SUPPORTED, PRS_PREPROC_XML_TO_JSON, PRS_PREPROC_SNMP_WALK_VALUE,
        PRS_PREPROC_SNMP_WALK_TO_JSON
    ];

    /**
     * @inheritDoc
     */
    protected const PREPROC_TYPES_WITH_PARAMS = [
        PRS_PREPROC_MULTIPLIER, PRS_PREPROC_RTRIM, PRS_PREPROC_LTRIM, PRS_PREPROC_TRIM, PRS_PREPROC_REGSUB,
        PRS_PREPROC_XPATH, PRS_PREPROC_JSONPATH, PRS_PREPROC_VALIDATE_RANGE, PRS_PREPROC_VALIDATE_REGEX,
        PRS_PREPROC_VALIDATE_NOT_REGEX, PRS_PREPROC_ERROR_FIELD_JSON, PRS_PREPROC_ERROR_FIELD_XML,
        PRS_PREPROC_ERROR_FIELD_REGEX, PRS_PREPROC_THROTTLE_TIMED_VALUE, PRS_PREPROC_SCRIPT,
        PRS_PREPROC_PROMETHEUS_PATTERN, PRS_PREPROC_PROMETHEUS_TO_JSON, PRS_PREPROC_CSV_TO_JSON,
        PRS_PREPROC_STR_REPLACE, PRS_PREPROC_SNMP_WALK_VALUE, PRS_PREPROC_SNMP_WALK_TO_JSON
    ];

    /**
     * @inheritDoc
     */
    protected const PREPROC_TYPES_WITH_ERR_HANDLING = [
        PRS_PREPROC_MULTIPLIER, PRS_PREPROC_REGSUB, PRS_PREPROC_BOOL2DEC, PRS_PREPROC_OCT2DEC, PRS_PREPROC_HEX2DEC,
        PRS_PREPROC_DELTA_VALUE, PRS_PREPROC_DELTA_SPEED, PRS_PREPROC_XPATH, PRS_PREPROC_JSONPATH,
        PRS_PREPROC_VALIDATE_RANGE, PRS_PREPROC_VALIDATE_REGEX, PRS_PREPROC_VALIDATE_NOT_REGEX,
        PRS_PREPROC_ERROR_FIELD_JSON, PRS_PREPROC_ERROR_FIELD_XML, PRS_PREPROC_ERROR_FIELD_REGEX,
        PRS_PREPROC_PROMETHEUS_PATTERN, PRS_PREPROC_PROMETHEUS_TO_JSON, PRS_PREPROC_CSV_TO_JSON,
        PRS_PREPROC_VALIDATE_NOT_SUPPORTED, PRS_PREPROC_XML_TO_JSON, PRS_PREPROC_SNMP_WALK_VALUE,
        PRS_PREPROC_SNMP_WALK_TO_JSON
    ];

    /**
     * @inheritDoc
     */
    protected const SUPPORTED_ITEM_TYPES = [
        ITEM_TYPE_PERSEUS, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_PERSEUS_ACTIVE,
        ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED,
        ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
    ];

    /**
     * @inheritDoc
     */
    protected const VALUE_TYPE_FIELD_NAMES = [
        ITEM_VALUE_TYPE_FLOAT => ['units', 'trends', 'valuemapid'],
        ITEM_VALUE_TYPE_STR => ['valuemapid'],
        ITEM_VALUE_TYPE_LOG => ['logtimefmt'],
        ITEM_VALUE_TYPE_UINT64 => ['units', 'trends', 'valuemapid'],
        ITEM_VALUE_TYPE_TEXT => []
    ];


    /**
     * 创建监控项
     * @param array $items
     * @return Result
     */
    public function create(array $items): Result
    {
        try {
            self::validateCreate($items);
            self::createItems($items);
            self::inherit($items);
            return $this->success(['itemids' => array_column($items, 'itemid')]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (\yii\base\Exception $e) {
            return $this->error(60750204, $e->getMessage());
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function createRules(): array
    {
        return [
            'host_status' => ['safe'],
            'flags' => ['safe'],
            'uuid' => [
                MultipleValidator::class,
                'rules' => [UuidValidator::class, 'when' => function ($model) {
                    return $model->host_status == HOST_STATUS_TEMPLATE;
                }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'uuid'), 'unset' => true]
            ],
            'hostid' => ['safe'],
            'ruleid' => [IdValidator::class, 'flags' => API_REQUIRED],
            'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
            'type' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
            'key_' => [ItemKeyValidator::class, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('items', 'key_')],
            'value_type' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])],
            'units' => [MultipleValidator::class,
                'rules' => [Utf8StringValidator::class, 'length' => 255, 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
                }],
                'else' => [Utf8StringValidator::class, 'default' => '', 'in' => [DB::getDefault('items', 'units')]]
            ],
            'history' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,' . implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
            'trends' => [MultipleValidator::class,
                'rules' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => [0, [SEC_PER_HOUR, 25 * SEC_PER_YEAR]], 'length' => 255, 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
                }],
                'else' => [TimeUnitValidator::class, 'default' => 0, 'in' => [0]]
            ],
            'valuemapid' => [MultipleValidator::class,
                'rules' => [IdValidator::class, 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64]);
                }],
                'else' => [IdValidator::class, 'default' => 0, 'in' => [0]]
            ],
            'logtimefmt' => [MultipleValidator::class,
                'rules' => [Utf8StringValidator::class, 'length' => 64, 'when' => function ($model) {
                    return $model->value_type == ITEM_VALUE_TYPE_LOG;
                }],
                'else' => [Utf8StringValidator::class, 'in' => [DB::getDefault('items', 'logtimefmt')]]
            ],
            'description' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'description')],
            'status' => [Int32Validator::class, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
            'discover' => [Int32Validator::class, 'in' => implode(',', [ITEM_DISCOVER, ITEM_NO_DISCOVER])],
            'tags' => self::getTagsValidationRules(),
            'preprocessing' => self::getPreprocessingValidationRules()
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getPreprocessingValidationRules(int $flags = 0x00): array {
        return parent::getPreprocessingValidationRules(API_ALLOW_LLD_MACRO);
    }

    /**
     * @param array $items
     * @throws ValidateException
     * @throws \yii\base\Exception
     * @throws NotSupportedException
     * @throws Exception
     */
    protected function validateCreate(array &$items): void
    {
        if (!ValidateHelper::validateObjects($items, ['hostid' => [IdValidator::class, 'flags' => API_REQUIRED]], ['flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED], $error)) {
            throw new ValidateException(60750201, $error);

        }
        $this->checkItem($items, PRS_FLAG_DISCOVERY_PROTOTYPE);

        $fieldRules = $this->createRules();

        $bool = ValidateHelper::validateObjects($items, $fieldRules, ['flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid'], ['hostid', 'key_']]], $error);
        if (!$bool) {
            throw new ValidateException(60750201, $error);
        }

        self::validateByType(array_keys($this->createRules()), $items);
        self::addUuid($items);
        self::checkUuidDuplicates($items);
        self::checkDuplicates($items);
        self::checkDiscoveryRules($items);
        self::checkValueMaps($items);
        self::checkHostInterfaces($items);
        self::checkDependentItems($items);
        self::checkPreprocessingSteps($items);
    }

    /**
     * @param array $items
     * @throws Exception
     */
    public static function createItems(array &$items): void
    {
        $itemids = DB::insert('items', $items);

        $ins_items_discovery = [];
        $host_statuses = [];
        $flags = [];

        foreach ($items as &$item) {
            $item['itemid'] = array_shift($itemids);

            if ($item['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                $ins_items_discovery[] = [
                    'itemid' => $item['itemid'],
                    'parent_itemid' => $item['ruleid']
                ];
            }

            $host_statuses[] = $item['host_status'];
            $flags[] = $item['flags'];
            unset($item['host_status'], $item['flags']);
        }
        unset($item);

        if ($ins_items_discovery) {
            DB::insertBatch('item_discovery', $ins_items_discovery);
        }

        self::updateParameters($items);
        self::updatePreprocessing($items);
        self::updateTags($items);

        foreach ($items as &$item) {
            $item['host_status'] = array_shift($host_statuses);
            $item['flags'] = array_shift($flags);
        }
        unset($item);
    }

    /**
     * @param array $items
     * @return Result
     */
    public function update(array $items): Result
    {
        try {
            $this->validateUpdate($items, $db_items);
            $itemids = array_column($items, 'itemid');
            self::updateItems($items, $db_items);
            self::inherit($items, $db_items);
            return $this->success(['itemids' => $itemids]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (\yii\base\Exception $e) {
            return $this->error(60750205, $e->getMessage());
        }
    }

    /**
     * @param array $items
     * @param array|null $db_items
     * @throws Exception
     * @throws NotSupportedException
     * @throws ValidateException
     * @throws \yii\base\Exception
     */
    protected function validateUpdate(array &$items, ?array &$db_items): void
    {
        $rules = ['itemid' => [IdValidator::class, 'flags' => API_REQUIRED]];
        $bool = ValidateHelper::validateObjects($items, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['itemid']]], $error);
        if (!$bool) {
            throw new ValidateException(60750201, $error);
        }

        $count = Items::find()->where(SqlHelper::whereIn('itemid', array_column($items, 'itemid')))->count();
        if ($count != count($items)) {
            throw new ValidateException(60750203);
        }

        /*
         * The fields "headers" and "query_fields" in API are arrays, but there is necessary to get the values of these
         * fields as they stored in database.
         */
        $db_items = Items::find()
            ->select(array_merge(['uuid', 'itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history',
                'trends', 'valuemapid', 'logtimefmt', 'description', 'status', 'discover'
            ], array_diff(CItemType::FIELD_NAMES, ['parameters'])))
            ->where(SqlHelper::whereIn('itemid', array_column($items, 'itemid')))
            ->indexBy('itemid')
            ->asArray()->all();

        self::addInternalFields($db_items);

        foreach ($items as $i => &$item) {
            $db_item = $db_items[$item['itemid']];
            $item['host_status'] = $db_item['host_status'];

            if ($db_item['templateid'] != 0) {
                $rules = ['value_type' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED]];
                $bool = ValidateHelper::validateObject($item, $rules, ['flags' => API_ALLOW_UNEXPECTED], $error);
                if (!$bool) {
                    throw new ValidateException(60750201, $error);
                }

                $item += array_intersect_key($db_item, array_flip(['value_type']));
                $fieldRules = self::getInheritedValidationRules();
            } else {
                $item += array_intersect_key($db_item, array_flip(['value_type']));
                $fieldRules = self::getValidationRules();
            }

            if (!ValidateHelper::validateObject($item, $fieldRules, ['flags' => API_ALLOW_UNEXPECTED], $error)) {
                throw new ValidateException(60750201, $error);
            }
        }
        unset($item);

        $items = $this->extendObjectsByKey($items, $db_items, 'itemid', ['type', 'key_']);

        self::validateByType(array_keys($fieldRules), $items, $db_items);

        $items = $this->extendObjectsByKey($items, $db_items, 'itemid', ['hostid', 'flags', 'ruleid']);

        self::validateUniqueness($items);

        self::addAffectedObjects($items, $db_items);

        self::checkUuidDuplicates($items, $db_items);
        self::checkDuplicates($items, $db_items);
        self::checkValueMaps($items, $db_items);
        self::checkHostInterfaces($items, $db_items);
        self::checkDependentItems($items, $db_items);
        self::checkPreprocessingSteps($items);
    }

    /**
     * @return array
     * @throws Exception
     */
    private static function getValidationRules(): array
    {
        return [
            'host_status' => ['safe'],
            'uuid' => [MultipleValidator::class,
                'rules' => [UuidValidator::class, 'when' => function ($model) {
                    return $model->host_status == HOST_STATUS_TEMPLATE;
                }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'uuid'), 'unset' => true]
            ],
            'itemid' => ['safe'],
            'name' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
            'type' => [Int32Validator::class, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
            'key_' => [ItemKeyValidator::class, 'flags' => API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('items', 'key_')],
            'value_type' => [Int32Validator::class, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])],
            'units' => [MultipleValidator::class,
                'rules' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'units'), 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
                }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'units')]
            ],
            'history' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,' . implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
            'trends' => [MultipleValidator::class,
                'rules' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,' . implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends'), 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
                }],
                'else' => [TimeUnitValidator::class, 'in' => '0']
            ],
            'valuemapid' => [MultipleValidator::class,
                'rules' => [IdValidator::class, 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64]);
                }],
                'else' => [IdValidator::class, 'in' => '0']
            ],
            'logtimefmt' => [MultipleValidator::class,
                'rules' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'logtimefmt'), 'when' => function ($model) {
                    return $model->value_type == ITEM_VALUE_TYPE_LOG;
                }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'logtimefmt')]
            ],
            'description' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'description')],
            'status' => [Int32Validator::class, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
            'discover' => [Int32Validator::class, 'in' => implode(',', [ITEM_DISCOVER, ITEM_NO_DISCOVER])],
            'tags' => self::getTagsValidationRules(),
            'preprocessing' => self::getPreprocessingValidationRules()
        ];
    }

    /**
     * @return array
     */
    private static function getInheritedValidationRules(): array
    {
        return [
            'host_status' => ['safe'],
            'uuid' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'itemid' => ['safe'],
            'name' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'type' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'key_' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'value_type' => ['safe'],
            'units' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'history' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,' . implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
            'trends' => [MultipleValidator::class,
                'rules' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,' . implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends'), 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
                }],
                'else' => [TimeUnitValidator::class, 'in' => '0']
            ],
            'valuemapid' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'logtimefmt' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'description' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'description')],
            'status' => [Int32Validator::class, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
            'discover' => [Int32Validator::class, 'in' => implode(',', [ITEM_DISCOVER, ITEM_NO_DISCOVER])],
            'tags' => self::getTagsValidationRules(),
            'preprocessing' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED]
        ];
    }


    /**
     * @param array $items
     * @param array $db_items
     * @throws Exception
     * @throws NotSupportedException
     */
    public static function updateItems(array &$items, array &$db_items): void
    {
        // Helps to avoid deadlocks.
        ArrayHelper::multisort($items, 'itemid', SORT_DESC);

        self::addFieldDefaultsByType($items, $db_items);

        $upd_items = [];
        $upd_itemids = [];

        $internal_fields = array_flip(['itemid', 'type', 'key_', 'hostid', 'flags', 'host_status']);
        $nested_object_fields = array_flip(['tags', 'preprocessing', 'parameters']);

        foreach ($items as $i => &$item) {
            $upd_item = DB::getUpdatedValues('items', $item, $db_items[$item['itemid']]);

            if ($upd_item) {
                $upd_items[] = [
                    'values' => $upd_item,
                    'where' => ['itemid' => $item['itemid']]
                ];

                if (array_key_exists('type', $item) && $item['type'] == ITEM_TYPE_HTTPAGENT) {
                    $item = array_intersect_key($item,
                        array_flip(['authtype']) + $internal_fields + $upd_item + $nested_object_fields
                    );
                } else {
                    $item = array_intersect_key($item, $internal_fields + $upd_item + $nested_object_fields);
                }

                $upd_itemids[$i] = $item['itemid'];
            } else {
                $item = array_intersect_key($item, $internal_fields + $nested_object_fields);
            }
        }
        unset($item);

        if ($upd_items) {
            DB::update('items', $upd_items);
        }

        self::updateTags($items, $db_items, $upd_itemids);
        self::updatePreprocessing($items, $db_items, $upd_itemids);
        self::updateParameters($items, $db_items, $upd_itemids);
        self::updateDiscoveredItems($items, $db_items);

        $items = array_intersect_key($items, $upd_itemids);
        $db_items = array_intersect_key($db_items, array_flip($upd_itemids));
    }

    /**
     * @param array $item_prototypes
     * @param array $db_item_prototypes
     * @throws Exception
     * @throws NotSupportedException
     */
    private static function updateDiscoveredItems(array $item_prototypes, array $db_item_prototypes): void
    {
        foreach ($item_prototypes as $i => $item_prototype) {
            if (!in_array($item_prototype['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])
                || !array_key_exists('update_discovered_items', $db_item_prototypes[$item_prototype['itemid']])) {
                unset($item_prototype[$i]);
                continue;
            }
        }

        if (!$item_prototypes) {
            return;
        }
        $result = (new Query())->select(['id.itemid', 'i.name', 'i.valuemapid'])
            ->from(['id' => 'item_discovery', 'i' => 'items'])
            ->where('id.itemid=i.itemid')
            ->andWhere(SqlHelper::whereIn('id.parent_itemid', array_column($item_prototypes, 'itemid')))
            ->all();

        $items = [];
        $db_items = [];

        foreach ($result as $row) {
            $items[] = [
                'itemid' => $row['itemid'],
                'valuemapid' => 0
            ];

            $db_items[$row['itemid']] = $row;
        }

        if ($items) {
            ItemAssist::updateItems($items, $db_items);
        }
    }

    /**
     * @param array $items
     * @param array $db_items
     * @param array|null $hostids
     * @param bool $is_dep_items
     * @throws Exception
     * @throws NotSupportedException
     * @throws \yii\base\Exception
     */
    protected static function inherit(array $items, array $db_items = [], array $hostids = null, bool $is_dep_items = false)
    {
        $tpl_links = self::getTemplateLinks($items, $hostids);

        if ($hostids === null) {
            self::filterObjectsToInherit($items, $db_items, $tpl_links);

            if (!$items) {
                return;
            }
        }

        self::checkDoubleInheritedNames($items, $db_items, $tpl_links);

        if ($hostids !== null && !$is_dep_items) {
            $dep_items_to_link = [];

            $item_indexes = array_flip(array_column($items, 'itemid'));

            foreach ($items as $i => $item) {
                if ($item['type'] == ITEM_TYPE_DEPENDENT && array_key_exists($item['master_itemid'], $item_indexes)) {
                    $dep_items_to_link[$item_indexes[$item['master_itemid']]][$i] = $item;

                    unset($items[$i]);
                }
            }
        }

        $chunks = self::getInheritChunks($items, $tpl_links);

        foreach ($chunks as $chunk) {
            $_items = array_intersect_key($items, array_flip($chunk['item_indexes']));
            $_db_items = array_intersect_key($db_items, array_flip(array_column($_items, 'itemid')));
            $_hostids = array_keys($chunk['hosts']);

            self::inheritChunk($_items, $_db_items, $tpl_links, $_hostids);
        }

        if ($hostids !== null && !$is_dep_items) {
            self::inheritDependentItems($dep_items_to_link, $items, $hostids);
        }
    }

    /**
     * @param array $items
     * @param array $db_items
     * @param array $tpl_links
     * @param array $hostids
     * @throws Exception
     * @throws NotSupportedException
     * @throws \yii\base\Exception
     */
    protected static function inheritChunk(array $items, array $db_items, array $tpl_links, array $hostids): void
    {
        $items_to_link = [];
        $items_to_update = [];

        foreach ($items as $i => $item) {
            if (!array_key_exists($item['itemid'], $db_items)) {
                $items_to_link[] = $item;
            } else {
                $items_to_update[] = $item;
            }

            unset($items[$i]);
        }

        $ins_items = [];
        $upd_items = [];
        $upd_db_items = [];

        if ($items_to_link) {
            $lld_links = self::getLldLinks($items_to_link);

            $upd_db_items = self::getChildObjectsUsingName($items_to_link, $hostids, $lld_links);

            if ($upd_db_items) {
                $upd_items = self::getUpdChildObjectsUsingName($items_to_link, $upd_db_items);
            }

            $ins_items = self::getInsChildObjects($items_to_link, $upd_db_items, $tpl_links, $hostids, $lld_links);
        }

        if ($items_to_update) {
            $_upd_db_items = self::getChildObjectsUsingTemplateid($items_to_update, $db_items, $hostids);
            $_upd_items = self::getUpdChildObjectsUsingTemplateid($items_to_update, $db_items, $_upd_db_items);

            self::checkDuplicates($_upd_items, $_upd_db_items);

            $upd_items = array_merge($upd_items, $_upd_items);
            $upd_db_items += $_upd_db_items;
        }

        self::setChildMasterItemIds($upd_items, $ins_items, $hostids);

        $edit_items = array_merge($upd_items, $ins_items);

        self::checkDependentItems($edit_items, $upd_db_items, true);

        self::addInterfaceIds($upd_items, $upd_db_items, $ins_items);

        if ($upd_items) {
            self::updateItems($upd_items, $upd_db_items);
        }

        if ($ins_items) {
            self::createItems($ins_items);
        }

        self::inherit(array_merge($upd_items, $ins_items), $upd_db_items);
    }

    /**
     * @param array $items
     * @return array
     */
    private static function getLldLinks(array $items): array
    {
        $items = Items::find()->select(['templateid', 'hostid', 'itemid'])
            ->where(SqlHelper::whereIn('templateid', array_unique(array_column($items, 'ruleid'))))
            ->asArray()->all();
        $lld_links = [];

        foreach ($items as $row) {
            $lld_links[$row['templateid']][$row['hostid']] = $row['itemid'];
        }

        return $lld_links;
    }

    /**
     * @param array $items
     * @param array $hostids
     * @param array $lld_links
     * @return array
     * @throws \yii\base\Exception
     */
    private static function getChildObjectsUsingName(array $items, array $hostids, array $lld_links): array
    {
        $result = (new Query())
            ->select(['i.itemid', 'ht.hostid', 'i.key_', 'i.templateid', 'i.flags', 'host_status' => 'h.status', 'parent_hostid' => 'ht.templateid', 'ruleid' => 'id.parent_itemid'])
            ->addSelect([ZSqlHelper::dbConditionCoalesce('id.parent_itemid', 0, 'ruleid')])
            ->from(['ht' => 'hosts_templates'])
            ->innerJoin(['i' => 'items'], 'ht.hostid=i.hostid')
            ->innerJoin(['h' => 'hosts'], 'ht.hostid=h.hostid')
            ->leftJoin(['id' => 'item_discovery'], 'i.itemid=id.itemid')
            ->where(SqlHelper::whereIn('ht.templateid', array_unique(array_column($items, 'hostid'))))
            ->andWhere(['i.key_' => array_unique(array_column($items, 'key_'))])
            ->andWhere(['ht.hostid' => $hostids])
            ->all();


        $upd_db_items = [];
        $parent_indexes = [];

        foreach ($result as $row) {
            foreach ($items as $i => $item) {
                if (bccomp($row['parent_hostid'], $item['hostid']) == 0 && $row['key_'] === $item['key_']) {
                    if ($row['flags'] == $item['flags'] && $row['templateid'] == 0
                        && bccomp($row['ruleid'], $lld_links[$item['ruleid']][$row['hostid']]) == 0) {
                        $upd_db_items[$row['itemid']] = $row;
                        $parent_indexes[$row['itemid']] = $i;
                    } else {
                        self::showObjectMismatchError($item, $row);
                    }
                }
            }
        }

        if (!$upd_db_items) {
            return [];
        }

        $result = Items::find()
            ->select(array_merge(['uuid', 'itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history',
                'trends', 'valuemapid', 'logtimefmt', 'description', 'status', 'discover'
            ], array_diff(CItemType::FIELD_NAMES, ['parameters'])))
            ->where(SqlHelper::whereIn('itemid', array_keys($upd_db_items)))
            ->asArray()->all();

        foreach ($result as $row) {
            $upd_db_items[$row['itemid']] = $row + $upd_db_items[$row['itemid']];
        }

        $upd_items = [];

        foreach ($upd_db_items as $upd_db_item) {
            $item = $items[$parent_indexes[$upd_db_item['itemid']]];

            $upd_items[] = [
                'itemid' => $upd_db_item['itemid'],
                'type' => $item['type'],
                'tags' => [],
                'preprocessing' => [],
                'parameters' => []
            ];
        }

        self::addAffectedObjects($upd_items, $upd_db_items);

        return $upd_db_items;
    }

    /**
     * @param array $items
     * @param array $upd_db_items
     *
     * @return array
     */
    private static function getUpdChildObjectsUsingName(array $items, array $upd_db_items): array
    {
        $parent_indexes = [];

        foreach ($items as $i => &$item) {
            $item['uuid'] = '';
            $item = self::unsetNestedObjectIds($item);

            $parent_indexes[$item['hostid']][$item['key_']] = $i;
        }
        unset($item);

        $upd_items = [];

        foreach ($upd_db_items as $upd_db_item) {
            $item = $items[$parent_indexes[$upd_db_item['parent_hostid']][$upd_db_item['key_']]];

            $upd_item = [
                    'itemid' => $upd_db_item['itemid'],
                    'hostid' => $upd_db_item['hostid'],
                    'templateid' => $item['itemid'],
                    'host_status' => $upd_db_item['host_status'],
                    'ruleid' => $upd_db_item['ruleid']
                ] + $item;

            $upd_item += [
                'tags' => [],
                'preprocessing' => [],
                'parameters' => []
            ];

            $upd_items[] = $upd_item;
        }

        return $upd_items;
    }

    /**
     * @param array $items
     * @param array $upd_db_items
     * @param array $tpl_links
     * @param array $hostids
     * @param array $lld_links
     *
     * @return array
     */
    private static function getInsChildObjects(array $items, array $upd_db_items, array $tpl_links, array $hostids, array $lld_links): array
    {
        $ins_items = [];

        $upd_item_keys = [];

        foreach ($upd_db_items as $upd_db_item) {
            $upd_item_keys[$upd_db_item['hostid']][] = $upd_db_item['key_'];
        }

        foreach ($items as $item) {
            $item['uuid'] = '';
            $item = self::unsetNestedObjectIds($item);

            foreach ($tpl_links[$item['hostid']] as $host) {
                if (!in_array($host['hostid'], $hostids)
                    || (array_key_exists($host['hostid'], $upd_item_keys)
                        && in_array($item['key_'], $upd_item_keys[$host['hostid']]))) {
                    continue;
                }

                $ins_items[] = [
                        'hostid' => $host['hostid'],
                        'templateid' => $item['itemid'],
                        'host_status' => $host['status'],
                        'ruleid' => $lld_links[$item['ruleid']][$host['hostid']]
                    ] + array_diff_key($item, array_flip(['itemid']));
            }
        }

        return $ins_items;
    }

    /**
     * @param array $items
     * @param array $db_items
     * @param array $hostids
     *
     * @return array
     */
    private static function getChildObjectsUsingTemplateid(array $items, array $db_items, array $hostids): array
    {
        $upd_db_items = Items::find()
            ->select(array_merge(['itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history', 'trends',
                'valuemapid', 'logtimefmt', 'description', 'status', 'discover'
            ], array_diff(CItemType::FIELD_NAMES, ['parameters'])))
            ->where(SqlHelper::whereIn('templateid', array_keys($db_items)))
            ->andWhere(['hostid' => $hostids])
            ->indexBy('itemid')
            ->asArray()->all();

        self::addInternalFields($upd_db_items);

        if ($upd_db_items) {
            $parent_indexes = array_flip(array_column($items, 'itemid'));
            $upd_items = [];

            foreach ($upd_db_items as &$upd_db_item) {
                $item = $items[$parent_indexes[$upd_db_item['templateid']]];
                $db_item = $db_items[$upd_db_item['templateid']];

                if (array_key_exists('update_discovered_items', $db_item)) {
                    $upd_db_item['update_discovered_items'] = true;
                }

                $upd_item = [
                    'itemid' => $upd_db_item['itemid'],
                    'type' => $item['type']
                ];

                $upd_item += array_intersect_key([
                    'tags' => [],
                    'preprocessing' => [],
                    'parameters' => []
                ], $db_item);

                $upd_items[] = $upd_item;
            }
            unset($upd_db_item);

            self::addAffectedObjects($upd_items, $upd_db_items);
        }

        return $upd_db_items;
    }

    /**
     * @param array $items
     * @param array $db_items
     * @param array $upd_db_items
     *
     * @return array
     */
    private static function getUpdChildObjectsUsingTemplateid(array $items, array $db_items, array $upd_db_items): array
    {
        $parent_indexes = array_flip(array_column($items, 'itemid'));

        foreach ($items as &$item) {
            unset($item['uuid']);
            $item = self::unsetNestedObjectIds($item);
        }
        unset($item);

        $upd_items = [];

        foreach ($upd_db_items as $upd_db_item) {
            $item = $items[$parent_indexes[$upd_db_item['templateid']]];

            $upd_items[] = array_intersect_key($upd_db_item,
                    array_flip(['itemid', 'hostid', 'templateid', 'host_status', 'ruleid'])
                ) + $item;
        }

        return $upd_items;
    }


    /**
     * @param array $items
     * @throws ValidateException
     */
    private static function checkDiscoveryRules(array $items): void
    {
        $ruleids = array_unique(array_column($items, 'ruleid'));
        $db_discovery_rules = Items::find()->select(['itemid', 'hostid'])
            ->where([
                'flags' => PRS_FLAG_DISCOVERY_RULE,
                'itemid' => $ruleids
            ])
            ->indexBy('itemid')
            ->asArray()->all();

        if (count($db_discovery_rules) != count($ruleids)) {
            throw new ValidateException(60750203);
        }

        foreach ($items as $item) {
            if (bccomp($db_discovery_rules[$item['ruleid']]['hostid'], $item['hostid']) != 0) {
                throw new ValidateException(60750203);
            }
        }
    }

    /**
     * @param array $itemIds
     * @return Result
     */
    public function delete(array $itemIds): Result
    {
        try {
            self::validateDelete($itemIds,$db_items);
            self::deleteItems($db_items);
            return $this->success(['prototypeids' => $itemIds]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (\yii\base\Exception $e) {
            return $this->error(60750206, $e->getMessage());
        }
    }

    /**
     * @param array $itemIds
     * @param array|null $items
     * @throws ValidateException
     */
    private function validateDelete(array $itemIds, ?array &$items): void
    {
        $itemIds = array_unique($itemIds);
        $items = Items::find()->select(['itemid', 'name', 'templateid'])
            ->where(SqlHelper::whereIn('itemid', $itemIds))
            ->indexBy('itemid')->asArray()->all();
        if (count($itemIds) != count($items)) {
            throw new ValidateException(60750203);
        }
        foreach ($items as $item) {
            if (!empty($item['templateid'])) {
                throw new ValidateException(60750201, t('zapi', 'cannot delete inherited prototype'));
            }
        }
    }


    /**
     * @param array $db_items
     * @throws Exception
     */
    public static function deleteItems(array $db_items): void {
        self::addInheritedItems($db_items);
        self::addDependentItems($db_items);

        $del_itemids = array_keys($db_items);

        // Lock item prototypes before delete to prevent server from adding new LLD elements.
        $sql = (new Query())->select(new Expression('NULL'))->from(['i' => 'items'])
            ->where(SqlHelper::whereIn('i.itemid', $del_itemids))->createCommand()->getRawSql();
        \Yii::$app->db->createCommand($sql . ' FOR UPDATE')->execute();

        self::deleteAffectedGraphPrototypes($del_itemids);
        self::resetGraphsYAxis($del_itemids);

        self::deleteDiscoveredItems($del_itemids);

        self::deleteAffectedTriggers($del_itemids);

        DB::delete('graphs_items', ['itemid' => $del_itemids]);
        DB::delete('widget_field', ['value_itemid' => $del_itemids]);
        DB::delete('item_discovery', ['itemid' => $del_itemids]);
        DB::delete('item_parameter', ['itemid' => $del_itemids]);
        DB::delete('item_preproc', ['itemid' => $del_itemids]);
        DB::delete('item_tag', ['itemid' => $del_itemids]);
        DB::update('items', [
            'values' => ['templateid' => 0, 'master_itemid' => 0],
            'where' => ['itemid' => $del_itemids]
        ]);
        DB::delete('items', ['itemid' => $del_itemids]);
    }

    /**
     * Delete graph prototypes, which would remain without item prototypes after the given item prototypes deletion.
     *
     * @param array $del_itemids
     */
    private static function deleteAffectedGraphPrototypes(array $del_itemids): void
    {
        $subQuery = (new Query())->select(new Expression('NULL'))
            ->from(['gii' => 'graphs_items', 'i' => 'items'])
            ->where('gi.graphid=gii.graphid')
            ->andWhere(SqlHelper::whereIn('gii.itemid', $del_itemids, true))
            ->andWhere(['i.flags' => [PRS_FLAG_DISCOVERY_PROTOTYPE]]);
        $del_graphids = (new Query())->select(['gi.graphid'])
            ->from(['gi' => 'graphs_items'])
            ->where(SqlHelper::whereIn('gi.itemid', $del_itemids))
            ->andWhere(['NOT EXISTS', $subQuery])
            ->distinct()
            ->column();

        if ($del_graphids) {
            GraphPrototypeManager::delete($del_graphids);
        }
    }

    /**
     * Delete discovered items of the given item prototypes.
     *
     * @param array $del_itemids
     */
    private static function deleteDiscoveredItems(array $del_itemids): void
    {
        $db_items = (new Query())->select(['id.itemid','i.name'])
            ->from(['id' => 'item_discovery', 'i' => 'items'])
            ->where('id.itemid=i.itemid')
            ->andWhere(SqlHelper::whereIn('id.parent_itemid', $del_itemids))
            ->indexBy('itemid')
            ->all();

        if ($db_items) {
            ItemAssist::deleteItems($db_items);
        }
    }
	
	/**
     * @param array $templateIds
     * @param array $hostIds
     */
    public static function linkTemplateObjects(array $templateIds, array $hostIds): void
    {
        $db_items = Items::find()
            ->select(array_merge([
                'itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history', 'trends',
                'valuemapid', 'logtimefmt', 'description', 'status', 'discover'
            ], array_diff(CItemType::FIELD_NAMES, ['interfaceid', 'parameters'])))
            ->where([
                'flags' => PRS_FLAG_DISCOVERY_PROTOTYPE,
                'hostid' => $templateIds,
            ])
            ->indexBy('itemid')
            ->asArray()
            ->all();

        if (!$db_items) {
            return;
        }

        self::addInternalFields($db_items);

        $items = [];

        foreach ($db_items as $db_item) {
            $item = array_intersect_key($db_item, array_flip(['itemid', 'type']));

            if ($db_item['type'] == ITEM_TYPE_SCRIPT) {
                $item += ['parameters' => []];
            }

            $items[] = $item + [
                'preprocessing' => [],
                'tags' => []
            ];
        }

        self::addAffectedObjects($items, $db_items);

        $items = array_values($db_items);

        foreach ($items as &$item) {
            if (array_key_exists('parameters', $item)) {
                $item['parameters'] = array_values($item['parameters']);
            }

            $item['preprocessing'] = array_values($item['preprocessing']);
            $item['tags'] = array_values($item['tags']);
        }
        unset($item);

        self::inherit($items, [], $hostIds);
    }
	
	
    /**
     * {@inheritDoc}
     */
    protected static function addInternalFields(array &$db_items): void
    {
        $rows = (new Query())->select(['i.itemid', 'i.hostid', 'i.templateid', 'i.flags', 'host_status' => 'h.status', 'ruleid' => 'id.parent_itemid'])
            ->from(['i' => 'items', 'h' => 'hosts', 'id' => 'item_discovery'])
            ->where('i.hostid=h.hostid')
            ->andWhere('i.itemid=id.itemid')
            ->andWhere(SqlHelper::whereIn('i.itemid', array_keys($db_items)))
            ->all();
        foreach ($rows as $row) {
            $db_items[$row['itemid']] += $row;
        }
    }
}
