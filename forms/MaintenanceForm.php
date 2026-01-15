<?php

namespace app\customs\zapi\forms;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\TimestampValidator;
use app\customs\zapi\common\validators\TimeUnitValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\services\HostGroupService;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\Maintenances;
use app\modules\libzbx\models\zbx\MaintenanceTag;
use app\modules\libzbx\models\zbx\Timeperiods;

class MaintenanceForm extends BaseForm
{
    /**
     * @return array
     */
    public static function getValidationRules(string $method = 'create'): array
    {
        $rules = [
            'maintenanceid' => [IdValidator::class],
            'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength(Maintenances::tableName(), 'name')],
            'maintenance_type' => [Int32Validator::class, 'in' => [MAINTENANCE_TYPE_NORMAL, MAINTENANCE_TYPE_NODATA], 'default' => DB::getDefault(Maintenances::tableName(), 'maintenance_type')],
            'description' => [Utf8StringValidator::class, 'length' => DB::getFieldLength(Maintenances::tableName(), 'description')],
            'active_since' => [TimestampValidator::class, 'flags' => API_REQUIRED],
            'active_till' => [TimestampValidator::class, 'flags' => API_REQUIRED, 'compare' => ['operator' => '>', 'field' => 'active_since']],
            'tags_evaltype' => [
                MultipleValidator::class,
                'rules' => [
                    'validator' => Int32Validator::class,
                    'in' => [MAINTENANCE_TAG_EVAL_TYPE_AND_OR, MAINTENANCE_TAG_EVAL_TYPE_OR],
                    'when' => function ($model) {
                        return $model->maintenance_type == MAINTENANCE_TYPE_NORMAL;
                    }
                ],
                'else' => ['validator' => UnexpectedValidator::class]

            ],
            'tags' => [
                MultipleValidator::class,
                'rules' => [
                    'validator' => ObjectsValidator::class,
                    'flags' => API_NORMALIZE,
                    'uniq' => [['tag', 'operator', 'value']],
                    'when' => function ($model) {
                        return $model->maintenance_type == MAINTENANCE_TYPE_NORMAL;
                    },
                    'fields' => [
                        'tag' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength(MaintenanceTag::tableName(), 'tag')],
                        'operator' => [Int32Validator::class, 'in' => [MAINTENANCE_TAG_OPERATOR_EQUAL, MAINTENANCE_TAG_OPERATOR_LIKE], 'default' => DB::getDefault(MaintenanceTag::tableName(), 'operator')],
                        'value' => [Utf8StringValidator::class, 'length' => DB::getFieldLength(MaintenanceTag::tableName(), 'value'), 'default' => DB::getDefault(MaintenanceTag::tableName(), 'value')]
                    ]
                ],
                'else' => ['validator' => UnexpectedValidator::class]
            ],
            'groupids' => [IdsValidator::class, 'flags' => API_DEPRECATED, 'uniq' => true],
            'hostids' => [IdsValidator::class, 'flags' => API_DEPRECATED, 'uniq' => true],
            'groups' => [
                ObjectsValidator::class,
                'flags' => API_NORMALIZE, 'uniq' => [['groupid']],
                'fields' => [
                    'groupid' => [IdValidator::class, 'flags' => API_REQUIRED]
                ]
            ],
            'hosts' => [
                ObjectsValidator::class,
                'flags' => API_NORMALIZE,
                'uniq' => [['hostid']],
                'fields' => [
                    'hostid' => [IdValidator::class, 'flags' => API_REQUIRED]
                ]
            ],
            'timeperiods' => self::getTimePeriodsValidationRules($method),
        ];

        if ($method == 'create') {
            unset($rules['maintenanceid']);
        } elseif ($method == 'update') {
            unset($rules['groupids']);
            unset($rules['hostids']);
        }

        return $rules;
    }

    public static function getTimePeriodsValidationRules(string $method = 'create'): array
    {
        return [
            ObjectsValidator::class,
            'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE,
            'fields' => [
                'period' => [TimeUnitValidator::class, 'in' => [[5 * SEC_PER_MIN, PRS_MAX_INT32]], 'default' => SEC_PER_HOUR],
                'timeperiod_type' => [
                    Int32Validator::class,
                    'in' => [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY],
                    'default' => DB::getDefault(Timeperiods::tableName(), 'timeperiod_type')
                ],
                'start_date' => [
                    MultipleValidator::class, 'default' => time(),
                    'rules' => ['validator' => TimestampValidator::class, 'when' => function ($model) {
                        return true;
                    }],
                    'else' => ['validator' => UnexpectedValidator::class]
                ],
                'start_time' => [
                    MultipleValidator::class,
                    'default' => time(),
                    'rules' => [
                        'validator' => TimestampValidator::class,
                        'format' => 'H:i', 'timezone' => 'UTC',
                        'in' => [0, [0, SEC_PER_DAY - SEC_PER_MIN]],
                        'default' => DB::getDefault(Timeperiods::tableName(), 'start_time'),
                        'when' => function ($model) {
                            return in_array($model->timeperiod_type, [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]);
                        }
                    ],
                    'else' => ['validator' => UnexpectedValidator::class]
                ],
                'every' => [
                    MultipleValidator::class,
                    'rules' => [
                        [
                            'validator' => Int32Validator::class,
                            'in' => [[1, PRS_MAX_INT32]],
                            'default' => DB::getDefault(Timeperiods::tableName(), 'every'),
                            'when' => function ($model) {
                                return in_array($model->timeperiod_type, [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]);
                            }
                        ],
                        [
                            'validator' => Int32Validator::class,
                            'in' => [MONTH_WEEK_FIRST, MONTH_WEEK_SECOND, MONTH_WEEK_THIRD, MONTH_WEEK_FOURTH, MONTH_WEEK_LAST],
                            'default' => DB::getDefault(Timeperiods::tableName(), 'every'),
                            'when' => function ($model) {
                                return in_array($model->timeperiod_type, [TIMEPERIOD_TYPE_MONTHLY]);
                            }
                        ]
                    ],
                    'else' => ['validator' => UnexpectedValidator::class]
                ],
                'day' => [
                    MultipleValidator::class,
                    'default' => time(),
                    'rules' => [
                        'validator' => Int32Validator::class,
                        'in' => [[0, MONTH_MAX_DAY]],
                        'when' => function ($model) {
                            return in_array($model->timeperiod_type, [TIMEPERIOD_TYPE_MONTHLY]);
                        }
                    ],
                    'else' => ['validator' => UnexpectedValidator::class]
                ],
                'dayofweek' => [
                    MultipleValidator::class,
                    'default' => time(),
                    'rules' => [
                        [
                            'validator' => Int32Validator::class,
                            'flags' => API_REQUIRED,
                            'in' => [[0b0000001, 0b1111111]],
                            'when' => function ($model) {
                                return in_array($model->timeperiod_type, [TIMEPERIOD_TYPE_WEEKLY]);
                            }
                        ],
                        [
                            'validator' => Int32Validator::class,
                            'in' => [[0, 0b1111111]],
                            'when' => function ($model) {
                                return in_array($model->timeperiod_type, [TIMEPERIOD_TYPE_MONTHLY]);
                            }
                        ]
                    ],
                    'else' => ['validator' => UnexpectedValidator::class]
                ],

                'month' => [
                    MultipleValidator::class,
                    'default' => time(),
                    'rules' => [
                        'validator' => Int32Validator::class,
                        'flags' => API_REQUIRED,
                        'in' => [[0b000000000001, 0b111111111111]],
                        'when' => function ($model) {
                            return in_array($model->timeperiod_type, [TIMEPERIOD_TYPE_MONTHLY]);
                        }
                    ],
                    'else' => ['validator' => UnexpectedValidator::class]
                ],
            ]
        ];
    }

    /**
     * @return array
     */
    public static function getIdsValidationRules(): array
    {
        return [
            'maintenanceid' => [IdValidator::class, 'flags' => API_REQUIRED],
            'groupids' => [IdsValidator::class, 'flags' => API_DEPRECATED, 'uniq' => true],
            'hostids' => [IdsValidator::class, 'flags' => API_DEPRECATED, 'uniq' => true]
        ];
    }

    /**
     * @param  array      $maintenances
     * @param  array|null $dbMaintenances
     * @return void
     */
    public static function validateGroupsAndHosts(array &$maintenances, ?array $dbMaintenances = null)
    {
        foreach ($maintenances as &$maintenance) {
            $maintenance['active_since'] -= $maintenance['active_since'] % SEC_PER_MIN;
            $maintenance['active_till'] -= $maintenance['active_till'] % SEC_PER_MIN;

            if (null  === $dbMaintenances) {
                if ((!array_key_exists('groups', $maintenance) || !$maintenance['groups'])
                    && (!array_key_exists('hosts', $maintenance) || !$maintenance['hosts'])
                ) {
                    self::exception(60750001, t('zapi', 'At least one host group or host must be selected.'));
                }
            } else {
                if (
                    $maintenance['maintenance_type'] != $dbMaintenances[$maintenance['maintenanceid']]['maintenance_type']
                    && $maintenance['maintenance_type'] == MAINTENANCE_TYPE_NODATA
                ) {
                    $maintenance['tags_evaltype'] = DB::getDefault(Maintenances::tableName(), 'tags_evaltype');
                }

                if (array_key_exists('groups', $maintenance) || array_key_exists('hosts', $maintenance)) {
                    $groups = array_key_exists('groups', $maintenance)
                        ? $maintenance['groups']
                        : $dbMaintenances[$maintenance['maintenanceid']]['groups'];

                    $hosts = array_key_exists('hosts', $maintenance)
                        ? $maintenance['hosts']
                        : $dbMaintenances[$maintenance['maintenanceid']]['hosts'];

                    if (!$groups && !$hosts) {
                        self::exception(60750001, t('zapi', 'At least one host group or host must be selected.'));
                    }
                }
            }
        }
        unset($maintenance);
    }


    /**
     * Validate time periods of given maintenances.
     *
     * @param array $maintenances
     * @return array Array of validated maintenances.
     * @throws ValidateException
     */
    public static function validateTimePeriods(array $maintenances)
    {
        foreach ($maintenances as &$maintenance) {
            if (!array_key_exists('timeperiods', $maintenance)) {
                continue;
            }
            foreach ($maintenance['timeperiods'] as &$timePeriod) {
                $timePeriod['period'] = timeUnitToSeconds($timePeriod['period'], true);
                $timePeriod['period'] -= $timePeriod['period'] % SEC_PER_MIN;

                if ($timePeriod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME) {
                    $timePeriod['start_date'] -= $timePeriod['start_date'] % SEC_PER_MIN;
                } else {
                    $timePeriod['start_time'] -= $timePeriod['start_time'] % SEC_PER_MIN;
                }
                if ($timePeriod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY) {
                    if ((!array_key_exists('day', $timePeriod) || $timePeriod['day'] == 0)
                        && (!array_key_exists('dayofweek', $timePeriod) || $timePeriod['dayofweek'] == 0)
                    ) {
                        self::exception(60750001, t('zapi', 'At least one day of the week or day of the month must be specified.'));
                    } elseif (
                        array_key_exists('day', $timePeriod) && $timePeriod['day'] != 0
                        && array_key_exists('dayofweek', $timePeriod) && $timePeriod['dayofweek'] != 0
                    ) {
                        self::exception(60750001, t('zapi', 'Day of the week and day of the month cannot be specified simultaneously.'));
                    }
                }
            }
            unset($timePeriod);
        }
        unset($maintenance);

        return $maintenances;
    }

    /**
     * Check for valid hosts / host groups.
     *
     * @param array      $maintenances
     * @param array|null $dbMaintenances
     * @param string     $field [hosts, groups]
     *
     * @throws ValidateException if hosts are not valid.
     */
    public static function checkAvailable(array $maintenances, array $dbMaintenances = null, $field = 'groups'): void
    {
        $editIds = [];

        $keys = [
            'groups' => 'groupid',
            'hosts' => 'hostid',
        ];

        foreach ($maintenances as $maintenance) {
            if (!array_key_exists($field, $maintenance)) {
                continue;
            }

            $ids = array_column($maintenance[$field], 'hostid');

            if ($dbMaintenances === null) {
                $editIds += array_flip($ids);
            } else {
                $dbIds = array_column($dbMaintenances[$maintenance['maintenanceid']][$field], $keys[$field]);
                $insIds = array_flip(array_diff($ids, $dbIds));
                $delIds = array_flip(array_diff($dbIds, $ids));

                $editIds += $insIds + $delIds;
            }
        }

        if (!$editIds) {
            return;
        }

        if ($field == 'groups') {
            $count = HostGroupService::instance()->count([
                'groupids' => array_keys($editIds),
                'type' => HOST_GROUP_TYPE_HOST_GROUP
            ]);
        } else {
            $count = Hosts::find()
                ->where(SqlHelper::whereIn('hostid', array_keys($editIds)))
                ->andWhere(['status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]])
                ->asArray()
                ->count('1');
        }

        if ($count != count($editIds)) {
            self::exception(PRS_API_ERROR_PERMISSIONS, t('zapi', 'No permissions to referred object or it does not exist!'));
        }
    }

    /**
     * 检查是否存在废弃属性
     *
     * @param  array $maintenances
     * @throws ValidateException if has deprecate attribute
     */
    public static function checkDeprecates(array &$maintenances)
    {
        foreach ($maintenances as &$maintenance) {
            if (array_key_exists('groupids', $maintenance)) {
                if (array_key_exists('groups', $maintenance)) {
                    self::exception(60750001, t('zapi', 'Parameter "{param}" is deprecated.', ['param' => 'groupids']));
                }

                $maintenance['groups'] = prs_toObject($maintenance['groupids'], 'groupid');
                unset($maintenance['groupids']);
            }

            if (array_key_exists('hostids', $maintenance)) {
                if (array_key_exists('hosts', $maintenance)) {
                    self::exception(60750001, t('zapi', 'Parameter "{param}" is deprecated.', ['param' => 'hostids']));
                }

                $maintenance['hosts'] = prs_toObject($maintenance['hostids'], 'hostid');
                unset($maintenance['hostids']);
            }
        }
        unset($maintenance);
    }



    /**
     * Check for unique maintenance names.
     *
     * @param array      $maintenances
     * @param array|null $dbMaintenances
     *
     * @throws ValidateException if names is duplicated.
     *  
     */
    public static function checkDuplicates(array $maintenances, array $dbMaintenances = null)
    {
        $names = [];

        foreach ($maintenances as $maintenance) {
            if (!array_key_exists('name', $maintenance)) {
                continue;
            }

            if (
                $dbMaintenances === null
                || $maintenance['name'] !== $dbMaintenances[$maintenance['maintenanceid']]['name']
            ) {
                $names[] = $maintenance['name'];
            }
        }

        if ($names && ($diff = self::getOneByValues($names))) {
            self::exception(60750001, t('zapi', 'Maintenance "{name}" already exists.', ['name' => current($diff)]));
        }
    }

    /**
     * 指定名称查找主机
     * 
     * @param string[]|string $values
     * @param string $field
     * @return array|null
     */
    public static function getOneByValues($values, string $field = 'name', $index = 'maintenanceid')
    {
        $query = Maintenances::find();
        $query->select($field);
        $query->where([$field => $values]);
        $query->indexBy($index)
            ->asArray();
        return $query->limit(1)->one();
    }
}
