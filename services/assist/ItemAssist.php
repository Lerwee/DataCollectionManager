<?php

namespace app\customs\zapi\services\assist;

use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\HousekeepingHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\itemtypes\CItemType;
use app\customs\zapi\common\managers\DiscoveryRuleManager;
use app\customs\zapi\common\managers\GraphManager;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\ItemKeyValidator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\TimeUnitValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\UuidValidator;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Items;
use app\modules\libzbx\models\zbx\GraphsItems;
use app\modules\libzbx\models\zbx\ItemDiscovery;
use app\modules\libzbx\models\zbx\ItemParameter;
use app\modules\libzbx\models\zbx\ItemPreproc;
use app\modules\libzbx\models\zbx\ItemRtdata;
use app\modules\libzbx\models\zbx\ItemTag;
use app\modules\libzbx\models\zbx\Profiles;
use app\modules\libzbx\models\zbx\WidgetField;
use Yii;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\db\Expression;
use yii\db\Query;

/**
 * Class ItemAssist
 * @package app\customs\zapi\service\assist
 */
class ItemAssist extends BaseItemAssist
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
    public const SUPPORTED_ITEM_TYPES = [
        ITEM_TYPE_PERSEUS, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_PERSEUS_ACTIVE,
        ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED,
        ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
    ];

    /**
     * @inheritDoc
     */
    protected const VALUE_TYPE_FIELD_NAMES = [
        ITEM_VALUE_TYPE_FLOAT => ['units', 'trends', 'valuemapid', 'inventory_link'],
        ITEM_VALUE_TYPE_STR => ['valuemapid', 'inventory_link'],
        ITEM_VALUE_TYPE_LOG => ['logtimefmt'],
        ITEM_VALUE_TYPE_UINT64 => ['units', 'trends', 'valuemapid', 'inventory_link'],
        ITEM_VALUE_TYPE_TEXT => ['inventory_link']
    ];

    /**
     * @throws \yii\db\Exception
     */
    protected function createRules(): array
    {
        return [
            'hostid' => ['safe'],
            'host_status' => ['safe'],
            'flags' => ['safe'],
            'uuid' => [MultipleValidator::class,
                'rules' => [UuidValidator::class, 'when' => function ($model) {
                    return $model->host_status == HOST_STATUS_TEMPLATE;
                }],
                'else' => [Utf8StringValidator::class, 'default' => DB::getDefault('items', 'uuid'), 'unset' => true]
            ],
            'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
            'type' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => self::SUPPORTED_ITEM_TYPES],
            'key_' => [ItemKeyValidator::class, 'flags' => API_REQUIRED, 'length' => 2048],
            'value_type' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT]],
            'units' => [MultipleValidator::class,
                'rules' => [Utf8StringValidator::class, 'length' => 255, 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
                }],
                'else' => [Utf8StringValidator::class, 'default' => '', 'in' => [DB::getDefault('items', 'units')]]
            ],
            'history' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => [0, [SEC_PER_HOUR, 25 * SEC_PER_YEAR]], 'length' => 255],
            'trends' => [MultipleValidator::class,
                'rules' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => [0, [SEC_PER_HOUR, 25 * SEC_PER_YEAR]], 'length' => 255, 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
                }],
                'else' => [TimeUnitValidator::class, 'default' => 0, 'in' => [0]]
            ],
            'valuemapid' => [MultipleValidator::class,
                'rules' => [IdValidator::class, 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64]);
                }],
                'else' => [IdValidator::class, 'default' => 0, 'in' => 0]
            ],
            'inventory_link' => [MultipleValidator::class,
                'rules' => [Int32Validator::class, 'in' => array_merge(array_keys(getHostInventories(), [0])), 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT]);
                }],
                'else' => [Int32Validator::class, 'in' => [DB::getDefault('items', 'inventory_link')]]
            ],
            'logtimefmt' => [MultipleValidator::class,
                'rules' => [Utf8StringValidator::class, 'length' => 64, 'when' => function ($model) {
                    return $model->value_type == ITEM_VALUE_TYPE_LOG;
                }],
                'else' => [Utf8StringValidator::class, 'in' => [DB::getDefault('items', 'logtimefmt')]]
            ],
            'description' => [Utf8StringValidator::class, 'length' => 1024],
            'status' => [Int32Validator::class, 'in' => [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]],
            'tags' => self::getTagsValidationRules(),
            'preprocessing' => self::getPreprocessingValidationRules()
        ];
    }

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
        } catch (Exception $e) {
            return $this->error(60750204, $e->getMessage());
        }
    }

    /**
     * @param array $items
     * @return Result|void
     * @throws Exception
     * @throws NotSupportedException
     * @throws ValidateException
     * @throws \yii\db\Exception
     */
    protected function validateCreate(array &$items)
    {
        $rules = ['hostid' => [IdValidator::class, 'flags' => API_REQUIRED]];
        $bool = ValidateHelper::validateObjects($items, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED], $error);
        if (!$bool) {
            throw new ValidateException(60750201, $error);
        }
        $this->checkItem($items);
        $bool = ValidateHelper::validateObjects($items, $this->createRules(), ['flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid'], ['hostid', 'key_']]], $error);
        if (!$bool) {
            throw new ValidateException(60750201, $error);
        }
        self::validateByType(array_keys($this->createRules()), $items);
        self::addUuid($items);
        self::checkUuidDuplicates($items);
        self::checkDuplicates($items);
        self::checkValueMaps($items);
        self::checkInventoryLinks($items);
        self::checkHostInterfaces($items);
        self::checkDependentItems($items);
        self::checkPreprocessingSteps($items);
    }

    /**
     * @param array $items
     * @throws \yii\db\Exception
     */
    public static function createItems(array &$items)
    {
        $itemids = DB::insert('items', $items);

        $ins_items_rtdata = [];
        $host_statuses = [];

        foreach ($items as &$item) {
            $item['itemid'] = array_shift($itemids);

            if (in_array($item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])) {
                $ins_items_rtdata[] = ['itemid' => $item['itemid']];
            }

            $host_statuses[] = $item['host_status'];
            unset($item['host_status']);
        }
        unset($item);

        if ($ins_items_rtdata) {
            DB::insertBatch('item_rtdata', $ins_items_rtdata, false);
        }

        self::updateParameters($items);
        self::updatePreprocessing($items);
        self::updateTags($items);

        foreach ($items as &$item) {
            $item['host_status'] = array_shift($host_statuses);
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
        } catch (Exception $e) {
            Yii::error(parse_exception($e));
            return $this->error(60750205, $e->getMessage());
        }
    }

    /**
     * @param array $items
     * @param array|null $db_items
     * @return Result|void
     * @throws Exception
     * @throws ValidateException
     * @throws NotSupportedException
     * @throws \yii\db\Exception
     */
    protected function validateUpdate(array &$items, ?array &$db_items)
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
                'trends', 'valuemapid', 'inventory_link', 'logtimefmt', 'description', 'status'
            ], array_diff(CItemType::FIELD_NAMES, ['parameters'])))
            ->where(SqlHelper::whereIn('itemid', array_column($items, 'itemid')))
            ->indexBy('itemid')
            ->asArray()->all();

        self::addInternalFields($db_items);

        $fieldRules = [];
        foreach ($items as $i => &$item) {
            $db_item = $db_items[$item['itemid']];
            $item['host_status'] = $db_item['host_status'];

            if ($db_item['templateid'] != 0) {
                $rules = ['value_type' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED]];
                $bool = ValidateHelper::validateObject($item, $rules, ['flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid'], ['hostid', 'key_']]], $error);
                if (!$bool) {
                    throw new ValidateException(60750201, $error);
                }

                $item += array_intersect_key($db_item, array_flip(['value_type']));

                $fieldRules = self::getInheritedValidationRules();
            } elseif ($db_item['flags'] == PRS_FLAG_DISCOVERY_CREATED) {
                $fieldRules = self::getDiscoveredValidationRules();
            } else {
                $item += array_intersect_key($db_item, array_flip(['value_type']));
                $fieldRules = self::getValidationRules();
            }
            $bool = ValidateHelper::validateObject($item, $fieldRules, ['flags' => API_ALLOW_UNEXPECTED], $error);
            if (!$bool) {
                throw new ValidateException(60750201, $error);
            }
        }
        unset($item);

        $items = $this->extendObjectsByKey($items, $db_items, 'itemid', ['type', 'key_']);

        self::validateByType(array_keys($fieldRules), $items, $db_items);

        $items = $this->extendObjectsByKey($items, $db_items, 'itemid', ['hostid', 'flags']);

        self::validateUniqueness($items);

        self::addAffectedObjects($items, $db_items);

        self::checkUuidDuplicates($items, $db_items);
        self::checkDuplicates($items, $db_items);
        self::checkValueMaps($items, $db_items);
        self::checkInventoryLinks($items, $db_items);
        self::checkHostInterfaces($items, $db_items);
        self::checkDependentItems($items, $db_items);
        self::checkPreprocessingSteps($items);
    }

    /**
     * @param array $items
     * @param array $db_items
     * @throws NotSupportedException
     * @throws \yii\db\Exception
     */
    public static function updateItems(array &$items, array &$db_items): void
    {
        // Helps to avoid deadlocks.
		CArrayHelper::sort($items, ['itemid'], PRS_SORT_DOWN);

        self::addFieldDefaultsByType($items, $db_items);

        $upd_items = [];
        $upd_itemids = [];

        $internal_fields = array_flip(['itemid', 'type', 'key_', 'value_type', 'hostid', 'flags', 'host_status']);
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

        $items = array_intersect_key($items, $upd_itemids);
        $db_items = array_intersect_key($db_items, array_flip($upd_itemids));

        //self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_ITEM, $items, $db_items);
    }

    /**
     * @return array
     * @throws \yii\db\Exception
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
            'key_' => [ItemKeyValidator::class, 'length' => DB::getFieldLength('items', 'key_')],
            'value_type' => [Int32Validator::class, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])],
            'units' => [MultipleValidator::class,
                'rules' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'units'), 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
                }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'units')]
            ],
            'history' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => ITEM_NO_STORAGE_VALUE . ',' . implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
            'trends' => [MultipleValidator::class,
                'rules' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,' . implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends'), 'when' => function ($model) {
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
            'inventory_link' => [MultipleValidator::class,
                'rules' => [Int32Validator::class, 'in' => '0,' . implode(',', array_keys(getHostInventories())), 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT]);
                }],
                'else' => [Int32Validator::class, 'in' => DB::getDefault('items', 'inventory_link')]
            ],
            'logtimefmt' => [MultipleValidator::class,
                'rules' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'logtimefmt'), 'when' => function ($model) {
                    return $model->value_type == ITEM_VALUE_TYPE_LOG;
                }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'logtimefmt')]
            ],
            'description' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'description')],
            'status' => [Int32Validator::class, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
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
            'history' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => ITEM_NO_STORAGE_VALUE . ',' . implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
            'trends' => [MultipleValidator::class,
                'rules' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,' . implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends'), 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
                }],
                'else' => [TimeUnitValidator::class, 'in' => '0']
            ],
            'valuemapid' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'inventory_link' => [MultipleValidator::class,
                'rules' => [Int32Validator::class, 'in' => '0,' . implode(',', array_keys(getHostInventories())), 'when' => function ($model) {
                    return in_array($model->value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT]);
                }],
                'else' => [Int32Validator::class, 'in' => DB::getDefault('items', 'inventory_link')]
            ],
            'logtimefmt' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'description' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'description')],
            'status' => [Int32Validator::class, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
            'tags' => self::getTagsValidationRules(),
            'preprocessing' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED]
        ];
    }

    /**
     * @return array
     */
    private static function getDiscoveredValidationRules(): array
    {
        return [
            'host_status' => ['safe'],
            'uuid' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'itemid' => ['safe'],
            'name' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'type' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'key_' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'value_type' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'units' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'history' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'trends' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'valuemapid' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'inventory_link' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'logtimefmt' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'description' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'status' => [Int32Validator::class, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
            'tags' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'preprocessing' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED]
        ];
    }

    /**
     * @param array $items
     * @param array $db_items
     * @param array|null $hostids
     * @param bool $is_dep_items
     * @throws Exception
     */
    protected static function inherit(array $items, array $db_items = [], array $hostids = null, bool $is_dep_items = false): void
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
            $upd_db_items = self::getChildObjectsUsingName($items_to_link, $hostids);

            if ($upd_db_items) {
                $upd_items = self::getUpdChildObjectsUsingName($items_to_link, $upd_db_items);
            }

            $ins_items = self::getInsChildObjects($items_to_link, $upd_db_items, $tpl_links, $hostids);
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
        self::checkInventoryLinks($edit_items, $upd_db_items, true);

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
     * @param array $hostids
     * @return array
     * @throws Exception
     */
    private static function getChildObjectsUsingName(array $items, array $hostids): array
    {
        $rows = (new Query())->select(['i.itemid', 'ht.hostid', 'i.key_', 'i.templateid', 'i.flags', 'host_status' => 'h.status', 'parent_hostid' => 'ht.templateid'])
            ->from(['ht' => 'hosts_templates', 'i' => 'items', 'h' => 'hosts'])
            ->where('ht.hostid=i.hostid')
            ->andWhere('ht.hostid=h.hostid')
            ->andWhere(['ht.templateid' => array_unique(array_column($items, 'hostid'))])
            ->andWhere(['i.key_' => array_unique(array_column($items, 'key_'))])
            ->andWhere(['ht.hostid' => $hostids])
            ->all();

        $upd_db_items = [];
        $parent_indexes = [];

        foreach ($rows as $row) {
            foreach ($items as $i => $item) {
                if (bccomp($row['parent_hostid'], $item['hostid']) == 0 && $row['key_'] === $item['key_']) {
                    if ($row['flags'] == $item['flags'] && $row['templateid'] == 0) {
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

        $rows = Items::find()->select(
            array_merge([
                'uuid', 'itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history',
                'trends', 'valuemapid', 'inventory_link', 'logtimefmt', 'description', 'status'
            ],
                array_diff(CItemType::FIELD_NAMES, ['parameters'])
            ))
            ->where(SqlHelper::whereIn('itemid', array_keys($upd_db_items)))
            ->asArray()
            ->all();

        foreach ($rows as $row) {
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
                    'host_status' => $upd_db_item['host_status']
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
     *
     * @return array
     */
    private static function getInsChildObjects(array $items, array $upd_db_items, array $tpl_links, array $hostids): array
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
                        'host_status' => $host['status']
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
                'valuemapid', 'inventory_link', 'logtimefmt', 'description', 'status'
            ], array_diff(CItemType::FIELD_NAMES, ['parameters'])))
            ->where(SqlHelper::whereIn('templateid', array_keys($db_items)))
            ->andWhere(['hostid' => $hostids])
            ->indexBy('itemid')
            ->asArray()
            ->all();

        self::addInternalFields($upd_db_items);

        if ($upd_db_items) {
            $parent_indexes = array_flip(array_column($items, 'itemid'));
            $upd_items = [];

            foreach ($upd_db_items as $upd_db_item) {
                $item = $items[$parent_indexes[$upd_db_item['templateid']]];
                $db_item = $db_items[$upd_db_item['templateid']];

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
                    array_flip(['itemid', 'hostid', 'templateid', 'host_status'])
                ) + $item;
        }

        return $upd_items;
    }


    /**
     * @param array $items
     * @param array $dbItems
     * @param bool $inherited
     * @param string|null $error
     * @return bool
     * @throws Exception
     */
    private static function checkInventoryLinks(array $items, array $dbItems = [], bool $inherited = false, ?string &$error = ''): bool
    {
        $itemIndexes = [];
        $delLinks = [];
        $value_types = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT];
        foreach ($items as $i => $item) {
            $check = false;
            if ($item['flags'] == PRS_FLAG_DISCOVERY_NORMAL && in_array($item['value_type'], $value_types)) {
                if (array_key_exists('inventory_link', $item)) {
                    if (!array_key_exists('itemid', $item)) {
                        if ($item['inventory_link'] != 0) {
                            $check = true;
                            $itemIndexes[$item['hostid']][] = $i;
                        }
                    } else {
                        if ($item['inventory_link'] != 0) {
                            if ($item['inventory_link'] != $dbItems[$item['itemid']]['inventory_link']) {
                                $check = true;
                                $itemIndexes[$item['hostid']][] = $i;

                                if ($dbItems[$item['itemid']]['inventory_link'] != 0) {
                                    $delLinks[$item['hostid']][] = $dbItems[$item['itemid']]['inventory_link'];
                                }
                            }
                        } elseif ($dbItems[$item['itemid']]['inventory_link'] != 0) {
                            $delLinks[$item['hostid']][] = $dbItems[$item['itemid']]['inventory_link'];
                        }
                    }
                }
            } elseif (array_key_exists('itemid', $item) && $dbItems[$item['itemid']]['inventory_link'] != 0) {
                $delLinks[$item['hostid']][] = $dbItems[$item['itemid']]['inventory_link'];
            }

            if (!$check) {
                unset($items[$i]);
            }
        }

        if (!$items) {
            return true;
        }

        $fieldRules = ['hostid' => ['safe'], 'inventory_link' => ['safe']];

        if (!ValidateHelper::validateObjects($items, $fieldRules, ['uniq' => [['hostid', 'inventory_link']]], $error)) {
            if ($inherited) {
                $_item_indexes = [];
                foreach ($items as $i => $item) {
                    if (array_key_exists($item['hostid'], $_item_indexes)
                        && array_key_exists($item['inventory_link'], $_item_indexes[$item['hostid']])) {
                        $errorTmp = $item['host_status'] == HOST_STATUS_TEMPLATE
                            ? t('zapi', 'Cannot inherit item with key "{key1}" of template "{template1}" and item with key "{key2}" of template "{template2}" to template "{name}", because they would populate the same inventory field "{inventory}".')
                            : t('zapi', 'Cannot inherit item with key "{key1}" of template "{template1}" and item with key "{key2}" of template "{template2}" to host "{name}", because they would populate the same inventory field "{inventory}".');

                        $_item = $items[$_item_indexes[$item['hostid']][$item['inventory_link']]];

                        $templates = (new Query())->select(['i.itemid', 'h.host'])->from(['i' => 'items', 'h' => 'hosts'])
                            ->where('i.hostid=h.hostid')
                            ->andWhere(['i.itemid' => [$_item['templateid'], $item['templateid']]])
                            ->indexBy('itemid')
                            ->all();

                        $host = Hosts::find()->select(['host'])->where(['hostid' => $item['hostid']])->asArray()->one();

                        $inventory_fields = getHostInventories();

                        $error = t('zapi', $errorTmp, [
                            'key1' => $_item['key_'],
                            'template1' => $templates[$_item['templateid']]['host'],
                            'key2' => $item['key_'],
                            'template2' => $templates[$item['templateid']]['host'],
                            'name' => $host['host'],
                            'inventory' => $inventory_fields[$item['inventory_link']]['title']
                        ]);
                        return false;
                    }

                    $_item_indexes[$item['hostid']][$item['inventory_link']] = $i;
                }
            }
            return false;
        }

        $itemList = Items::find()->select(['hostid', 'inventory_link', 'key_'])
            ->where([
                'hostid' => array_unique(array_column($items, 'hostid')),
                'inventory_link' => array_unique(array_column($items, 'inventory_link'))
            ])
            ->asArray()->all();

        foreach ($itemList as $row) {
            if (array_key_exists($row['hostid'], $delLinks)
                && in_array($row['inventory_link'], $delLinks[$row['hostid']])) {
                continue;
            }

            foreach ($itemIndexes[$row['hostid']] as $i) {
                if ($row['inventory_link'] == $items[$i]['inventory_link']) {
                    $item = $items[$i];

                    if ($inherited) {
                        $errorTmp = $item['host_status'] == HOST_STATUS_TEMPLATE
                            ? t('zapi', 'Cannot inherit item with key "{key1}" of template "{template}" to template "{name}", because its inventory field "{inventory}" is already populated by the item with key "{key2}".')
                            : t('zapi', 'Cannot inherit item with key "{key1}" of template "{template}" to host "{name}", because its inventory field "{inventory}" is already populated by the item with key "{key2}".');

                        $template = DBfetch(DBselect(
                            'SELECT h.host' .
                            ' FROM items i,hosts h' .
                            ' WHERE i.hostid=h.hostid' .
                            ' AND ' . dbConditionId('i.itemid', [$item['templateid']])
                        ));

                        $host = Hosts::find()->select(['host'])->where(['hostid' => $item['hostid']])->asArray()->one();

                        $inventory_fields = getHostInventories();
                        $error = t('zapi', $errorTmp, [
                            'key1' => $item['key_'], 'template' => $template['host'],
                            'name' => $host['host'], 'inventory' => $inventory_fields[$item['inventory_link']]['title'], 'key2' => $row['key_']
                        ]);
                        return false;
                    } else {
                        $errorTmp = $item['host_status'] == HOST_STATUS_TEMPLATE
                            ? t('zapi', 'Cannot assign the inventory field "{inventory}" to the item with key "{key1}" of template "{name}", because it is already populated by the item with key "{key2}".')
                            : t('zapi', 'Cannot assign the inventory field "{inventory}" to the item with key "{key1}" of host "{name}", because it is already populated by the item with key "{key2}".');

                        $inventory_fields = getHostInventories();

                        $host = Hosts::find()->select(['host'])->where(['hostid' => $item['hostid']])->asArray()->one();
                        $error = t('zapi', $errorTmp, [
                            'inventory' => $inventory_fields[$item['inventory_link']]['title'], 'key1' => $item['key_'],
                            'name' => $host['host'], 'key2' => $row['key_']
                        ]);
                        return false;
                    }
                }
            }
        }
        return true;
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
            return $this->success(['itemids' => $itemIds]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            Yii::error(parse_exception($e));
            return $this->error(60750206, $e->getMessage());
        }
    }

    /**
     * @param array $itemIds
     * @param array|null $items
     * @throws ValidateException
     */
    protected function validateDelete(array $itemIds, ?array &$items)
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
                throw new ValidateException(60750201, t('zapi', 'cannot delete inherited item'));
            }
        }
    }

    /**
     * @param array $db_items
     * @throws \yii\db\Exception
     */
    public static function deleteItems(array $db_items)
    {
        self::addInheritedItems($db_items);
        self::addDependentItems($db_items, $del_ruleids, $db_item_prototypes);

        if ($del_ruleids) {
            DiscoveryRuleManager::delete($del_ruleids);
        }

        if ($db_item_prototypes) {
            ItemPrototypeAssist::deleteItems($db_item_prototypes);
        }
        $del_itemids = array_keys($db_items);

        self::deleteAffectedGraphs($del_itemids);
        self::resetGraphsYAxis($del_itemids);
        self::deleteFromFavoriteGraphs($del_itemids);

        self::deleteAffectedTriggers($del_itemids);

        self::clearHistoryAndTrends($del_itemids);

        DB::delete('graphs_items', ['itemid' => $del_itemids]);
        DB::delete('widget_field', ['value_itemid' => $del_itemids]);
        DB::delete('item_discovery', ['itemid' => $del_itemids]);
        DB::delete('item_parameter', ['itemid' => $del_itemids]);
        DB::delete('item_preproc', ['itemid' => $del_itemids]);
        DB::delete('item_rtdata', ['itemid' => $del_itemids]);
        DB::delete('item_tag', ['itemid' => $del_itemids]);
        DB::update('items', [
            'values' => ['templateid' => 0, 'master_itemid' => 0],
            'where' => ['itemid' => $del_itemids]
        ]);
        DB::delete('items', ['itemid' => $del_itemids]);
    }

    /**
     * Delete graphs, which would remain without items after the given items deletion.
     *
     * @param array $del_itemids
     */
    private static function deleteAffectedGraphs(array $del_itemids): void
    {
        $delGraphIds = (new Query())->select(['gi.graphid'])
            ->from(['gi' => 'graphs_items'])
            ->where(SqlHelper::whereIn('gi.itemid', $del_itemids))
            ->andWhere(['NOT EXISTS', (new Query())->select(new Expression('NULL'))->from(['gii' => 'graphs_items'])
                ->where('gii.graphid=gi.graphid')
                ->andWhere(SqlHelper::whereIn('gii.itemid', $del_itemids, true))
            ])
            ->distinct()
            ->column();

        if ($delGraphIds) {
            GraphManager::delete($delGraphIds);
        }
    }

    /**
     * Delete the latest data graph of the given items from the favorites.
     *
     * @param array $del_itemids
     */
    private static function deleteFromFavoriteGraphs(array $del_itemids): void
    {
        DB::delete('profiles', [
            'idx' => 'web.favorite.graphids',
            'source' => 'itemid',
            'value_id' => $del_itemids
        ]);
    }

    /**
     * Clear the history and trends of the given items.
     *
     * @param array $del_itemids
     */
    private static function clearHistoryAndTrends(array $del_itemids): void
    {
        $table_names = ['events'];

        $timescale_extension = \Yii::$app->db->driverName === 'pgsql'
            && HousekeepingHelper::get(HousekeepingHelper::DB_EXTENSION) === PRS_DB_EXTENSION_TIMESCALEDB;

        if (HousekeepingHelper::get(HousekeepingHelper::HK_HISTORY_MODE) == 1
            && (!$timescale_extension || HousekeepingHelper::get(HousekeepingHelper::HK_HISTORY_GLOBAL) == 0)) {
            array_push($table_names, 'history', 'history_log', 'history_str', 'history_text', 'history_uint');
        }

        if (HousekeepingHelper::get(HousekeepingHelper::HK_TRENDS_MODE) == 1
            && (!$timescale_extension || HousekeepingHelper::get(HousekeepingHelper::HK_TRENDS_GLOBAL) == 0)) {
            array_push($table_names, 'trends', 'trends_uint');
        }

        $ins_housekeeper = [];

        foreach ($del_itemids as $del_itemid) {
            foreach ($table_names as $table_name) {
                $ins_housekeeper[] = [
                    'tablename' => $table_name,
                    'field' => 'itemid',
                    'value' => $del_itemid
                ];

                if (count($ins_housekeeper) == PRS_DB_MAX_INSERTS) {
                    DB::insertBatch('housekeeper', $ins_housekeeper);
                    $ins_housekeeper = [];
                }
            }
        }

        if ($ins_housekeeper) {
            DB::insertBatch('housekeeper', $ins_housekeeper);
        }
    }
	
	/**
     * @param array      $templateIds
     * @param array|null $hostIds
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
            ->andWhere(['i.flags' => PRS_FLAG_DISCOVERY_NORMAL])
            ->andWhere(['i.type' => self::SUPPORTED_ITEM_TYPES]);

        if ($hostIds) {
            $query->andWhere(SqlHelper::whereIn('ii.hostid', $hostIds));
        }

        $query->select(['ii.name', 'ii.itemid'])->indexBy('itemid');
        $items = $query->all();
        if ($items) {
            self::deleteItems($items);
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
                'valuemapid', 'inventory_link', 'logtimefmt', 'description', 'status'
            ], array_diff(CItemType::FIELD_NAMES, ['interfaceid', 'parameters'])))
            ->where([
                'flags' => PRS_FLAG_DISCOVERY_NORMAL,
                'hostid' => $templateIds,
                'type' => self::SUPPORTED_ITEM_TYPES
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
            ->andWhere(['i.flags' => PRS_FLAG_DISCOVERY_NORMAL])
            ->andWhere(['i.type' => self::SUPPORTED_ITEM_TYPES]);

        if ($hostIds) {
            $query->andWhere(SqlHelper::whereIn('ii.hostid', $hostIds));
        }

        $query->select(['ii.itemid', 'ii.name', 'ii.type', 'ii.key_', 'ii.value_type', 'ii.templateid', 'ii.uuid', 'ii.valuemapid', 'ii.hostid', 'host_status' => 'h.status']);

        $items = [];
        $db_items = [];
        $i = 0;
        $tpl_itemids = [];
        $internal_fields = array_flip(['key_', 'value_type', 'hostid', 'flags', 'host_status']);

        foreach ($query->each() as $row) {
            $item = [
                'itemid' => $row['itemid'],
                'type' => $row['type'],
                'templateid' => 0
            ];

            if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
                $item += ['uuid' => generateUuidV4()];
            }

            if ($row['valuemapid'] != 0) {
                $item += ['valuemapid' => 0];

                if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
                    $tpl_itemids[$i] = $row['itemid'];
                    $item += array_intersect_key($row, $internal_fields);
                }
            }

            $items[$i++] = $item;
            $db_items[$row['itemid']] = $row;
        }

        if ($items) {
            self::updateItems($items, $db_items);

            if ($tpl_itemids) {
                $items = array_intersect_key($items, $tpl_itemids);
                $db_items = array_intersect_key($db_items, array_flip($tpl_itemids));

                self::inherit($items, $db_items);
            }
        }
    }
}
