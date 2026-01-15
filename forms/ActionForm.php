<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\parsers\CConditionFormula;
use app\customs\zapi\common\parsers\CIPRangeParser;
use app\customs\zapi\common\validators\CondFormulaIdValidator;
use app\customs\zapi\common\validators\CondFormulaValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32RangesValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\IpRangesValidator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\ObjectValidator;
use app\customs\zapi\common\validators\TimePeriodValidator;
use app\customs\zapi\common\validators\TimeUnitValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\modules\libzbx\models\zbx\Actions;

class ActionForm extends BaseForm
{
    public const VALID_CONDITION_TYPES = [
        EVENT_SOURCE_TRIGGERS => [
            PRS_CONDITION_TYPE_HOST_GROUP, PRS_CONDITION_TYPE_HOST, PRS_CONDITION_TYPE_TRIGGER,
            PRS_CONDITION_TYPE_EVENT_NAME, PRS_CONDITION_TYPE_TRIGGER_SEVERITY, PRS_CONDITION_TYPE_TIME_PERIOD,
            PRS_CONDITION_TYPE_TEMPLATE, PRS_CONDITION_TYPE_SUPPRESSED, PRS_CONDITION_TYPE_EVENT_TAG,
            PRS_CONDITION_TYPE_EVENT_TAG_VALUE
        ],
        EVENT_SOURCE_DISCOVERY => [
            PRS_CONDITION_TYPE_DHOST_IP, PRS_CONDITION_TYPE_DSERVICE_TYPE, PRS_CONDITION_TYPE_DSERVICE_PORT,
            PRS_CONDITION_TYPE_DSTATUS, PRS_CONDITION_TYPE_DUPTIME, PRS_CONDITION_TYPE_DVALUE, PRS_CONDITION_TYPE_DRULE,
            PRS_CONDITION_TYPE_DCHECK, PRS_CONDITION_TYPE_PROXY, PRS_CONDITION_TYPE_DOBJECT
        ],
        EVENT_SOURCE_AUTOREGISTRATION => [
            PRS_CONDITION_TYPE_PROXY, PRS_CONDITION_TYPE_HOST_NAME, PRS_CONDITION_TYPE_HOST_METADATA
        ],
        EVENT_SOURCE_INTERNAL => [
            PRS_CONDITION_TYPE_HOST_GROUP, PRS_CONDITION_TYPE_HOST, PRS_CONDITION_TYPE_TEMPLATE,
            PRS_CONDITION_TYPE_EVENT_TYPE, PRS_CONDITION_TYPE_EVENT_TAG, PRS_CONDITION_TYPE_EVENT_TAG_VALUE
        ],
        EVENT_SOURCE_SERVICE => [
            PRS_CONDITION_TYPE_SERVICE, PRS_CONDITION_TYPE_SERVICE_NAME, PRS_CONDITION_TYPE_EVENT_TAG,
            PRS_CONDITION_TYPE_EVENT_TAG_VALUE
        ]
    ];
    /**
     * Operation group names.
     *
     * @var array
     */
    public const OPERATION_GROUPS = [
        ACTION_OPERATION => 'operations',
        ACTION_RECOVERY_OPERATION => 'recovery_operations',
        ACTION_UPDATE_OPERATION => 'update_operations'
    ];


    /**
     * @return array
     */
    public static function getValidationRules(string $method = 'create'): array
    {
        $rules = [
            'actionid' => [IdValidator::class],
            'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('actions', 'name')],
            'eventsource' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE]],
            'status' => [Int32Validator::class, 'in' => [ACTION_STATUS_ENABLED, ACTION_STATUS_DISABLED]],
            'esc_period' => [
                MultipleValidator::class,
                'rules' => [
                    [TimeUnitValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE]);
                    }, 'flags' => API_ALLOW_USER_MACRO, 'in' => SEC_PER_MIN . ':' . SEC_PER_WEEK, 'length' => DB::getFieldLength('actions', 'esc_period')],
                ],
                'else' => ['validator' => UnexpectedValidator::class]
            ],
            'pause_symptoms' => [
                MultipleValidator::class,
                'rules' => [
                    [Int32Validator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_TRIGGERS]);
                    }, 'in' => [ACTION_PAUSE_SYMPTOMS_FALSE, ACTION_PAUSE_SYMPTOMS_TRUE]],
                    ['validator' => UnexpectedValidator::class, 'when' => function ($model) {
                        return true;
                    }]
                ],
                'else' => ['validator' => UnexpectedValidator::class]
            ],
            'pause_suppressed' => [
                MultipleValidator::class,
                'rules' => [
                    [Int32Validator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_TRIGGERS]);
                    }, 'in' => [ACTION_PAUSE_SUPPRESSED_FALSE, ACTION_PAUSE_SUPPRESSED_TRUE]],
                ],
                'else' => ['validator' => UnexpectedValidator::class]

            ],
            'filter' => [
                MultipleValidator::class,
                'rules' => [
                    [ObjectValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_TRIGGERS]);
                    }, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_TRIGGERS)],
                    [ObjectValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_DISCOVERY]);
                    }, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_DISCOVERY)],
                    [ObjectValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_AUTOREGISTRATION]);
                    }, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_AUTOREGISTRATION)],
                    [ObjectValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_INTERNAL]);
                    }, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_INTERNAL)],
                    [ObjectValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_SERVICE]);
                    }, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_SERVICE)]
                ]],

            'operations' => [
                MultipleValidator::class,
                'rules' => [
                    [ObjectsValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_TRIGGERS]);
                    }, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_TRIGGERS)],
                    [ObjectsValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_DISCOVERY]);
                    }, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_DISCOVERY)],
                    [ObjectsValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_AUTOREGISTRATION]);
                    }, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_AUTOREGISTRATION)],
                    [ObjectsValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_INTERNAL]);
                    }, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_INTERNAL)],
                    [ObjectsValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_SERVICE]);
                    }, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_SERVICE)]
                ]],
            'recovery_operations' => [
                MultipleValidator::class,
                'rules' => [
                    [ObjectsValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_TRIGGERS]);
                    }, 'fields' => self::getOperationValidationRules(ACTION_RECOVERY_OPERATION, EVENT_SOURCE_TRIGGERS)],
                    [ObjectsValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_INTERNAL]);
                    }, 'fields' => self::getOperationValidationRules(ACTION_RECOVERY_OPERATION, EVENT_SOURCE_INTERNAL)],
                    [ObjectsValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_SERVICE]);
                    }, 'fields' => self::getOperationValidationRules(ACTION_RECOVERY_OPERATION, EVENT_SOURCE_SERVICE)],
                ],
                'else' => ['validator' => UnexpectedValidator::class]
            ],
            'update_operations' => [
                MultipleValidator::class,
                'rules' => [
                    [ObjectsValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_TRIGGERS]);
                    }, 'fields' => self::getOperationValidationRules(ACTION_UPDATE_OPERATION, EVENT_SOURCE_TRIGGERS)],
                    [ObjectsValidator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_SERVICE]);
                    }, 'fields' => self::getOperationValidationRules(ACTION_UPDATE_OPERATION, EVENT_SOURCE_SERVICE)],
                ],
                'else' => ['validator' => UnexpectedValidator::class]],
            'notify_if_canceled' => [
                MultipleValidator::class,
                'rules' => [
                    [Int32Validator::class, 'when' => function ($model) {
                        return in_array($model->eventsource, [EVENT_SOURCE_SERVICE]);
                    }, 'in' => [ACTION_NOTIFY_IF_CANCELED_FALSE, ACTION_NOTIFY_IF_CANCELED_TRUE]],

                ],
                'else' => ['validator' => UnexpectedValidator::class]]
        ];

        if ($method == 'create') {
            unset($rules['actionid']);
        }
        return $rules;
    }

    /**
     * Returns validation rules for objects of normal, recovery and update operations.
     *
     * @param int $recovery Action operation mode. Possible values:
     *                          ACTION_OPERATION, ACTION_RECOVERY_OPERATION, ACTION_UPDATE_OPERATION
     * @param int $eventsource Action event source. Possible values:
     *                          EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
     *                          EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
     *
     * @return array
     */
    private static function getOperationValidationRules(int $recovery, int $eventsource): array
    {
        $escalation_fields = [
            'esc_period' => ['validator' => TimeUnitValidator::class, 'flags' => API_ALLOW_USER_MACRO, 'in' => '0,' . SEC_PER_MIN . ':' . SEC_PER_WEEK, 'length' => DB::getFieldLength('operations', 'esc_period')],
            'esc_step_from' => ['validator' => Int32Validator::class, 'in' => '1:99999'],
            'esc_step_to' => ['validator' => Int32Validator::class, 'in' => '0:99999']
        ];
        $opmessage_fields = [
            'default_msg' => ['validator' => Int32Validator::class, 'in' => [0, 1], 'default' => DB::getDefault('opmessage', 'default_msg')],
            'subject' => ['validator' => MultipleValidator::class, 'rules' => [
                ['when' => function ($model) {
                    return in_array($model->default_msg, [0]);
                }, 'validator' => Utf8StringValidator::class, 'length' => DB::getFieldLength('opmessage', 'subject')],
                'else' => ['validator' => UnexpectedValidator::class]],
            ],
            'message' => ['validator' => MultipleValidator::class, 'rules' => [
                ['when' => function ($model) {
                    return in_array($model->default_msg, [0]);
                }, 'validator' => Utf8StringValidator::class, 'length' => DB::getFieldLength('opmessage', 'message')],
            ],
                'else' => ['validator' => UnexpectedValidator::class]],
        ];
        $all_opmessage_fields = [
            'opmessage' => ['validator' => MultipleValidator::class, 'rules' => [
                [
                    'when' => function ($model) {
                        return in_array($model->operationtype, [OPERATION_TYPE_MESSAGE, OPERATION_TYPE_UPDATE_MESSAGE]);
                    },
                    'validator' => ObjectValidator::class, 'flags' => API_REQUIRED, 'fields' => $opmessage_fields + [
                        'mediatypeid' => ['validator' => IdValidator::class]
                    ]],
                [
                    'when' => function ($model) {
                        return in_array($model->operationtype, [OPERATION_TYPE_RECOVERY_MESSAGE]);
                    },
                    'validator' => ObjectValidator::class, 'flags' => API_REQUIRED, 'fields' => $opmessage_fields],
            ],
                'else' => ['validator' => UnexpectedValidator::class]
            ],
            'opmessage_grp' => ['validator' => MultipleValidator::class, 'rules' => [
                [
                    'when' => function ($model) {
                        return in_array($model->operationtype, [OPERATION_TYPE_MESSAGE]);
                    },
                    'validator' => ObjectsValidator::class, 'uniq' => [['usrgrpid']], 'fields' => [
                    'usrgrpid' => ['validator' => IdValidator::class, 'flags' => API_REQUIRED]
                ]],
            ],
                'else' => ['validator' => UnexpectedValidator::class]
            ],
            'opmessage_usr' => ['validator' => MultipleValidator::class, 'rules' => [
                [
                    'when' => function ($model) {
                        return in_array($model->operationtype, [OPERATION_TYPE_MESSAGE]);
                    },
                    'validator' => ObjectsValidator::class, 'uniq' => [['userid']], 'fields' => [
                    'userid' => ['validator' => IdValidator::class, 'flags' => API_REQUIRED]
                ]],
            ],
                'else' => ['validator' => UnexpectedValidator::class]

            ]
        ];
        $opcommand_fields = [
            'opcommand' => ['validator' => MultipleValidator::class, 'rules' => [
                [
                    'when' => function ($model) {
                        return in_array($model->operationtype, [OPERATION_TYPE_COMMAND]);
                    },
                    'validator' => ObjectValidator::class, 'flags' => API_REQUIRED, 'fields' => [
                    'scriptid' => ['validator' => IdValidator::class, 'flags' => API_REQUIRED]
                ]],
            ],
                'else' => ['validator' => UnexpectedValidator::class]
            ]
        ];
        $common_fields = $all_opmessage_fields + $opcommand_fields + [
                'opcommand_grp' => ['validator' => MultipleValidator::class, 'rules' => [
                    [
                        'when' => function ($model) {
                            return in_array($model->operationtype, [OPERATION_TYPE_COMMAND]);
                        },
                        'validator' => ObjectsValidator::class, 'uniq' => [['groupid']], 'fields' => [
                        'groupid' => ['validator' => IdValidator::class, 'flags' => API_REQUIRED]
                    ]],
                ],
                    'else' => ['validator' => UnexpectedValidator::class]
                ],
                'opcommand_hst' => ['validator' => MultipleValidator::class, 'rules' => [
                    [
                        'when' => function ($model) {
                            return in_array($model->operationtype, [OPERATION_TYPE_COMMAND]);
                        },

                        'validator' => ObjectsValidator::class, 'uniq' => [['hostid']], 'fields' => [
                        'hostid' => ['validator' => IdValidator::class, 'flags' => API_REQUIRED]
                    ]],
                ],
                    'else' => ['validator' => UnexpectedValidator::class]
                ]
            ];

        $operationtype_field = [
            'operationtype' => ['validator' => Int32Validator::class, 'flags' => API_REQUIRED, 'in' => getAllowedOperations($eventsource)[$recovery]]
        ];

        switch ($recovery) {
            case ACTION_OPERATION:
                switch ($eventsource) {
                    case EVENT_SOURCE_TRIGGERS:
                        return $operationtype_field + $escalation_fields + [
                                'evaltype' => ['validator' => Int32Validator::class, 'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR]],
                                'opconditions' => ['validator' => ObjectsValidator::class, 'uniq' => [['value']], 'fields' => [
                                    'conditiontype' => ['validator' => Int32Validator::class, 'flags' => API_REQUIRED, 'in' => PRS_CONDITION_TYPE_EVENT_ACKNOWLEDGED],
                                    'value' => ['validator' => Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => [EVENT_NOT_ACKNOWLEDGED, EVENT_ACKNOWLEDGED], 'length' => DB::getFieldLength('opconditions', 'value')],
                                    'operator' => ['validator' => Int32Validator::class, 'in' => CONDITION_OPERATOR_EQUAL]
                                ]]
                            ] + $common_fields;

                    case EVENT_SOURCE_DISCOVERY:
                    case EVENT_SOURCE_AUTOREGISTRATION:
                        return $operationtype_field + $common_fields + [
                                'opgroup' => ['validator' => MultipleValidator::class, 'rules' => [
                                    [
                                        'when' => function ($model) {
                                            return in_array($model->operationtype, [OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE]);
                                        },
                                        'validator' => ObjectsValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['groupid']], 'fields' => [
                                        'groupid' => ['validator' => IdValidator::class, 'flags' => API_REQUIRED]
                                    ]],
                                ],
                                    'else' => ['validator' => UnexpectedValidator::class]
                                ],
                                'optemplate' => ['validator' => MultipleValidator::class, 'rules' => [
                                    [
                                        'when' => function ($model) {
                                            return in_array($model->operationtype, [OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE]);
                                        },
                                        'validator' => ObjectsValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['templateid']], 'fields' => [
                                        'templateid' => ['validator' => IdValidator::class, 'flags' => API_REQUIRED]
                                    ]],

                                ],
                                    'else' => ['validator' => UnexpectedValidator::class]
                                ],
                                'opinventory' => ['validator' => MultipleValidator::class, 'rules' => [
                                    [

                                        'when' => function ($model) {
                                            return in_array($model->operationtype, [OPERATION_TYPE_HOST_INVENTORY]);
                                        },
                                        'validator' => ObjectValidator::class, 'flags' => API_REQUIRED, 'fields' => [
                                        'inventory_mode' => ['validator' => Int32Validator::class, 'in' => [HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]]
                                    ]],
                                ],
                                    'else' => ['validator' => UnexpectedValidator::class]
                                ]
                            ];

                    case EVENT_SOURCE_INTERNAL:
                        return $operationtype_field + $escalation_fields + $all_opmessage_fields;

                    case EVENT_SOURCE_SERVICE:
                        return $operationtype_field + $escalation_fields + $all_opmessage_fields + $opcommand_fields;
                }
                break;

            case ACTION_RECOVERY_OPERATION:
                switch ($eventsource) {
                    case EVENT_SOURCE_TRIGGERS:
                        return $operationtype_field + $common_fields;

                    case EVENT_SOURCE_INTERNAL:
                        return $operationtype_field + $all_opmessage_fields;

                    case EVENT_SOURCE_SERVICE:
                        return $operationtype_field + $all_opmessage_fields + $opcommand_fields;
                }
                break;

            case ACTION_UPDATE_OPERATION:
                switch ($eventsource) {
                    case EVENT_SOURCE_TRIGGERS:
                        return $operationtype_field + $common_fields;

                    case EVENT_SOURCE_SERVICE:
                        return $operationtype_field + $all_opmessage_fields + $opcommand_fields;
                }
                break;
        }
        return [];
    }

    /**
     * Returns validation rules for the filter object.
     *
     * @param int $eventsource Action event source. Possible values:
     *                          EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
     *                          EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
     *
     * @return array
     */
    private static function getFilterValidationRules(int $eventsource): array
    {
        switch ($eventsource) {
            case EVENT_SOURCE_TRIGGERS:
                $value_rules = [
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_HOST_GROUP, PRS_CONDITION_TYPE_HOST, PRS_CONDITION_TYPE_TRIGGER, PRS_CONDITION_TYPE_TEMPLATE]);
                        },
                        'validator' => IdValidator::class, 'flags' => API_REQUIRED],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_NAME, PRS_CONDITION_TYPE_EVENT_TAG]);
                        },
                        'validator' => Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('conditions', 'value')],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_TRIGGER_SEVERITY]);
                        },
                        'validator' => Int32Validator::class, 'flags' => API_REQUIRED, 'in' => range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_TIME_PERIOD]);
                        },
                        'validator' => TimePeriodValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('conditions', 'value')],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_TAG_VALUE]);
                        },
                        'validator' => Utf8StringValidator::class, 'length' => DB::getFieldLength('conditions', 'value')],
                    ['validator' => UnexpectedValidator::class, 'when' => function ($model) {
                        return true;
                    }]
                ];
                $operator_rules = [
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_HOST_GROUP]);
                        },
                        'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_HOST_GROUP)],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_HOST]);
                        },
                        'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_HOST)],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_TRIGGER]);
                        },
                        'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_TRIGGER)],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_TEMPLATE]);
                        },
                        'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_TEMPLATE)],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_NAME]);
                        },
                        'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_EVENT_NAME)],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_TRIGGER_SEVERITY]);
                        },
                        'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_TRIGGER_SEVERITY)],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_TIME_PERIOD]);
                        },
                        'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_TIME_PERIOD)],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_SUPPRESSED]);
                        },
                        'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_SUPPRESSED)],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_TAG]);
                        },
                        'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_EVENT_TAG)],
                    [

                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_TAG_VALUE]);
                        },
                        'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_EVENT_TAG_VALUE)]
                ];
                break;

            case EVENT_SOURCE_DISCOVERY:
                $value_rules = [
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DHOST_IP]);
                        },
                        'validator' => IpRangesValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_RANGE, 'length' => DB::getFieldLength('conditions', 'value')],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DSERVICE_TYPE]);
                        },
                        'validator' => Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [SVC_SSH, SVC_LDAP, SVC_SMTP, SVC_FTP, SVC_HTTP, SVC_POP, SVC_NNTP, SVC_IMAP, SVC_TCP, SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2c, SVC_ICMPPING, SVC_SNMPv3, SVC_HTTPS, SVC_TELNET]],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DSERVICE_PORT]);
                        }, 'validator' => Int32RangesValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('conditions', 'value'), 'in' => PRS_MIN_PORT_NUMBER . ':' . PRS_MAX_PORT_NUMBER],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DSTATUS]);
                    }, 'validator' => Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [DOBJECT_STATUS_UP, DOBJECT_STATUS_DOWN, DOBJECT_STATUS_DISCOVER, DOBJECT_STATUS_LOST]],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DUPTIME]);
                    }, 'validator' => Int32Validator::class, 'flags' => API_REQUIRED, 'in' => '0:' . SEC_PER_MONTH],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DVALUE]);
                    }, 'validator' => Utf8StringValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('conditions', 'value')],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DRULE, PRS_CONDITION_TYPE_DCHECK, PRS_CONDITION_TYPE_PROXY]);
                        },
                        'validator' => IdValidator::class, 'flags' => API_REQUIRED],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DOBJECT]);
                    }, 'validator' => Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE]]
                ];
                $operator_rules = [
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DHOST_IP]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_DHOST_IP)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DSERVICE_TYPE]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_DSERVICE_TYPE)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DSERVICE_PORT]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_DSERVICE_PORT)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DSTATUS]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_DSTATUS)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DUPTIME]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_DUPTIME)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DVALUE]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_DVALUE)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DRULE]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_DRULE)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DCHECK]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_DCHECK)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_PROXY]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_PROXY)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_DOBJECT]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_DOBJECT)]
                ];
                break;

            case EVENT_SOURCE_AUTOREGISTRATION:
                $value_rules = [
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_PROXY]);
                    }, 'validator' => IdValidator::class, 'flags' => API_REQUIRED],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_HOST_NAME, PRS_CONDITION_TYPE_HOST_METADATA]);
                        },
                        'validator' => Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('conditions', 'value')]
                ];
                $operator_rules = [
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_PROXY]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_PROXY)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_HOST_NAME]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_HOST_NAME)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_HOST_METADATA]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_HOST_METADATA)]
                ];
                break;

            case EVENT_SOURCE_INTERNAL:
                $value_rules = [
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_HOST_GROUP, PRS_CONDITION_TYPE_HOST, PRS_CONDITION_TYPE_TEMPLATE]);
                        },
                        'validator' => IdValidator::class, 'flags' => API_REQUIRED],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_TYPE]);
                    }, 'validator' => Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [EVENT_TYPE_ITEM_NOTSUPPORTED, EVENT_TYPE_LLDRULE_NOTSUPPORTED, EVENT_TYPE_TRIGGER_UNKNOWN]],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_TAG]);
                    }, 'validator' => Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('conditions', 'value')],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_TAG_VALUE]);
                    }, 'validator' => Utf8StringValidator::class, 'length' => DB::getFieldLength('conditions', 'value')]
                ];
                $operator_rules = [
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_HOST_GROUP]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_HOST_GROUP)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_HOST]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_HOST)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_TEMPLATE]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_TEMPLATE)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_TYPE]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_EVENT_TYPE)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_TAG]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_EVENT_TAG)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_TAG_VALUE]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_EVENT_TAG_VALUE)]
                ];
                break;

            case EVENT_SOURCE_SERVICE:
                $value_rules = [
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_SERVICE]);
                    }, 'validator' => IdValidator::class, 'flags' => API_REQUIRED],
                    [
                        'when' => function ($model) {
                            return in_array($model->conditiontype, [PRS_CONDITION_TYPE_SERVICE_NAME, PRS_CONDITION_TYPE_EVENT_TAG]);
                        }, 'validator' => Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('conditions', 'value')],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_TAG_VALUE]);
                    }, 'validator' => Utf8StringValidator::class, 'length' => DB::getFieldLength('conditions', 'value')]
                ];
                $operator_rules = [
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_SERVICE]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_SERVICE)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_SERVICE_NAME]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_SERVICE_NAME)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_TAG]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_EVENT_TAG)],
                    ['when' => function ($model) {
                        return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_TAG_VALUE]);
                    }, 'validator' => Int32Validator::class, 'in' => get_operators_by_conditiontype(PRS_CONDITION_TYPE_EVENT_TAG_VALUE)]
                ];
                break;
        }

        $condition_fields = [
            'conditiontype' => ['validator' => Int32Validator::class, 'flags' => API_REQUIRED, 'in' => self::VALID_CONDITION_TYPES[$eventsource]],
            'operator' => ['validator' => MultipleValidator::class, 'flags' => API_REQUIRED, 'rules' => $operator_rules],
            'value' => ['validator' => MultipleValidator::class, 'rules' => $value_rules],
            'value2' => ['validator' => MultipleValidator::class, 'rules' => [
                ['when' => function ($model) {
                    return in_array($model->conditiontype, [PRS_CONDITION_TYPE_EVENT_TAG_VALUE]);
                }, 'validator' => Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('conditions', 'value2')],
                ['validator' => UnexpectedValidator::class, 'when' => function ($model) {
                    return true;
                }]
            ]]
        ];

        return [
            'evaltype' => ['validator' => Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION]],
            'formula' => ['validator' => MultipleValidator::class, 'rules' => [
                [
                    'when' => function ($model) {
                        return in_array($model->evaltype, [CONDITION_EVAL_TYPE_EXPRESSION]);
                    },
                    'validator' => CondFormulaValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('actions', 'formula')],
                ['validator' => UnexpectedValidator::class, 'when' => function ($model) {
                    return true;
                }]
            ]],
            'conditions' => ['validator' => MultipleValidator::class, 'rules' => [
                [
                    'when' => function ($model) {
                        return in_array($model->evaltype, [CONDITION_EVAL_TYPE_EXPRESSION]);
                    },
                    'validator' => ObjectsValidator::class, 'flags' => API_REQUIRED, 'uniq' => [['formulaid']], 'fields' => [
                        'formulaid' => ['validator' => CondFormulaIdValidator::class, 'flags' => API_REQUIRED]
                    ] + $condition_fields],
            ],
                'else' => ['validator' => ObjectsValidator::class, 'fields' => $condition_fields]

            ]
        ];
    }

    /**
     * Check for unique action names.
     *
     * @param array $actions
     * @param array|null $db_actions
     *
     * @throws APIException if action name is not unique.
     */
    public static function checkDuplicates(array $actions, array $db_actions = null): void
    {
        $names = [];

        foreach ($actions as $action) {
            if ($db_actions === null || $action['name'] !== $db_actions[$action['actionid']]['name']) {
                $names[] = $action['name'];
            }
        }

        if (!$names) {
            return;
        }

        $duplicates = Actions::find()->select('name')->andWhere(['name' => $names])->limit(1)->asArray()->all();
        if ($duplicates) {
            self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Action "{name}" already exists.', ['name' => $duplicates[0]['name']]));
        }
    }


    /**
     * @param array $actions
     *
     * @throws APIException
     */
    public static function checkFilter(array $actions): void
    {
        $condition_formula_parser = new CConditionFormula();
        $ip_range_parser = new CIPRangeParser(['v6' => PRS_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30]);

        foreach ($actions as $i => $action) {
            if (!array_key_exists('filter', $action)) {
                continue;
            }

            $path = '/' . ($i + 1) . '/filter';

            if ($action['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
                $condition_formula_parser->parse($action['filter']['formula']);

                $constants = array_column($condition_formula_parser->constants, 'value', 'value');

                if (count($action['filter']['conditions']) != count($constants)) {
                    self::exception(PRS_API_ERROR_PARAMETERS,
                        t('zapi', 'Invalid parameter "{attribute}": {error}.', ['attribute' => $path . '/conditions', 'error' => t('zapi', 'incorrect number of conditions')])
                    );
                }

                foreach ($action['filter']['conditions'] as $j => $condition) {
                    if (!array_key_exists($condition['formulaid'], $constants)) {
                        self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Invalid parameter "{attribute}": {error}.',
                            ['attribute' => $path . '/conditions/' . ($j + 1) . '/formulaid', 'error' => t('zapi', 'an identifier is not defined in the formula')]
                        ));
                    }
                }
            }

            if (array_key_exists('conditions', $action['filter'])) {
                foreach ($action['filter']['conditions'] as $j => $condition) {
                    if ($condition['conditiontype'] == PRS_CONDITION_TYPE_DHOST_IP) {
                        if (!$ip_range_parser->parse($condition['value'])) {
                            self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Invalid parameter "{attribute}": {error}.',
                                ['attribute' => $path . '/conditions/' . ($j + 1) . '/value', 'error' => $ip_range_parser->getError()]
                            ));
                        }
                    } elseif ($condition['conditiontype'] == PRS_CONDITION_TYPE_DVALUE) {
                        if ($condition['operator'] == CONDITION_OPERATOR_EQUAL
                            || $condition['operator'] == CONDITION_OPERATOR_NOT_EQUAL) {
                            continue;
                        }

                        if ($condition['value'] === '') {
                            self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Invalid parameter "{attribute}": {error}.',
                                ['attribute' => $path . '/conditions/' . ($j + 1) . '/value', 'error' => t('zapi', 'cannot be empty')]
                            ));
                        }
                    }
                }
            }
        }
    }

    /**
     * @param array $actions
     * @param array|null $db_actions
     *
     * @throws APIException
     */
    public static function checkOperations(array &$actions, array $db_actions = null): void
    {
        $is_update = ($db_actions !== null);

        foreach ($actions as &$action) {
            if ($is_update) {
                if (!array_intersect_key(array_flip(self::OPERATION_GROUPS), $action)) {
                    continue;
                }

                $db_action = $db_actions[$action['actionid']];
            } else {
                $db_action = [];
            }

            $operations = array_intersect_key($action + $db_action, array_flip(self::OPERATION_GROUPS));

            if (!array_filter($operations, 'boolval')) {
                self::exception(PRS_API_ERROR_PARAMETERS,
                    t('zapi', 'No operations defined for action "{name}".', ['name' => $action['name']])
                );
            }

            $unique_operations = [
                OPERATION_TYPE_HOST_ADD => 0,
                OPERATION_TYPE_HOST_REMOVE => 0,
                OPERATION_TYPE_HOST_ENABLE => 0,
                OPERATION_TYPE_HOST_DISABLE => 0,
                OPERATION_TYPE_HOST_INVENTORY => 0
            ];

            foreach (self::OPERATION_GROUPS as $recovery => $operation_group) {
                if (!array_key_exists($operation_group, $action)) {
                    continue;
                }

                foreach ($action[$operation_group] as &$operation) {
                    $operation['recovery'] = $recovery;

                    if ($recovery == ACTION_OPERATION) {
                        if (array_key_exists($operation['operationtype'], $unique_operations)) {
                            $unique_operations[$operation['operationtype']]++;

                            if ($unique_operations[$operation['operationtype']] > 1) {
                                self::exception(PRS_API_ERROR_PARAMETERS,
                                    t('zapi', 'Operation "{operation}" already exists for action "{action}".',
                                        ['operation' => operation_type2str($operation['operationtype']),
                                            'action' => $action['name']
                                        ]
                                    )
                                );
                            }
                        }

                        if (array_key_exists('esc_step_from', $operation)
                            || array_key_exists('esc_step_to', $operation)) {
                            if (!array_key_exists('esc_step_from', $operation)
                                || !array_key_exists('esc_step_to', $operation)) {
                                self::exception(PRS_API_ERROR_PARAMETERS,
                                    t('zapi', 'Parameters "esc_step_from" and "esc_step_to" must be set together.')
                                );
                            }

                            if ($operation['esc_step_from'] > $operation['esc_step_to']
                                && $operation['esc_step_to'] != 0) {
                                self::exception(PRS_API_ERROR_PARAMETERS,
                                    t('zapi', 'Incorrect action operation escalation step values.')
                                );
                            }
                        }
                    }

                    if ($operation['operationtype'] == OPERATION_TYPE_MESSAGE) {
                        $has_groups = array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp'];
                        $has_users = array_key_exists('opmessage_usr', $operation) && $operation['opmessage_usr'];

                        if (!$has_groups && !$has_users) {
                            self::exception(PRS_API_ERROR_PARAMETERS,
                                t('zapi', 'No recipients specified for action operation message.')
                            );
                        }
                    } elseif ($operation['operationtype'] == OPERATION_TYPE_COMMAND
                        && $action['eventsource'] != EVENT_SOURCE_SERVICE) {
                        $has_groups = array_key_exists('opcommand_grp', $operation) && $operation['opcommand_grp'];
                        $has_hosts = array_key_exists('opcommand_hst', $operation) && $operation['opcommand_hst'];

                        if (!$has_groups && !$has_hosts) {
                            self::exception(PRS_API_ERROR_PARAMETERS,
                                t('zapi', 'No targets specified for action operation global script.')
                            );
                        }
                    }
                }
                unset($operation);
            }
        }
        unset($action);
    }
}
