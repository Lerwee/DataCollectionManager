<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;

/**
 * EventAcknowledgeForm - 事件确认参数验证模型
 */
class EventAcknowledgeForm extends BaseForm
{
    public $eventids;
    public $action;
    public $message;
    public $severity;
    public $suppress_until;
    public $cause_eventid;

    /**
     * 获取验证规则
     *
     * @return array
     */
    public static function getValidationRules(): array
    {
        return [
            'eventids' => ['type' => API_IDS, 'flags' => API_REQUIRED | API_NORMALIZE],
            'action' => ['type' => API_INT32, 'flags' => API_REQUIRED],
            'message' => [
                'type' => API_STRING_UTF8,
                'flags' => API_ALLOW_NULL,
                'default' => DB::getDefault('acknowledges', 'message'),
                'length' => DB::getFieldLength('acknowledges', 'message')
            ],
            'severity' => [
                'type' => API_INT32,
                'flags' => API_ALLOW_NULL,
                'default' => DB::getDefault('acknowledges', 'new_severity')
            ],
            'suppress_until' => ['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
            'cause_eventid' => [
                'type' => API_MULTIPLE,
                'rules' => [
                    [
                        IdValidator::class,
                        'when' => function ($model) {
                            return ($model->action & PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM) != 0;
                        },
                        'flags' => API_REQUIRED
                    ],
                    ['validator' => UnexpectedValidator::class, 'when' => function ($model) {
                        return true;
                    }]
                ]
            ]
        ];
    }

    /**
     * 验证确认参数
     *
     * @param array $data
     * @param int $time
     * @throws \app\customs\zapi\common\exceptions\ValidateException
     */
    public static function validate(array $data, int $time): void
    {
        $fields = self::getValidationRules();

        if (!ValidateHelper::validate(['type' => API_OBJECT, 'fields' => $fields], $data, '/', $error)) {
            self::exception(PRS_API_ERROR_PARAMETERS, $error);
        }

        self::validateAction($data);
        self::validateActionConflicts($data);
        self::validateSuppressTime($data, $time);
    }

    /**
     * 验证 action 参数
     *
     * @param array $data
     * @throws \app\customs\zapi\common\exceptions\ValidateException
     */
    protected static function validateAction(array $data): void
    {
        $action_mask = PRS_PROBLEM_UPDATE_CLOSE | PRS_PROBLEM_UPDATE_ACKNOWLEDGE | PRS_PROBLEM_UPDATE_MESSAGE
            | PRS_PROBLEM_UPDATE_SEVERITY | PRS_PROBLEM_UPDATE_UNACKNOWLEDGE | PRS_PROBLEM_UPDATE_SUPPRESS
            | PRS_PROBLEM_UPDATE_UNSUPPRESS | PRS_PROBLEM_UPDATE_RANK_TO_CAUSE
            | PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM;

        if (($data['action'] & $action_mask) != $data['action']) {
            self::exception(
                PRS_API_ERROR_PARAMETERS,
                t('zapi',
                    'Incorrect value for field "{field}": {error}.',
                    [
                        'field' => 'action',
                        'error' => t('zapi', 'unexpected value "{value}"', ['value' => $data['action']])
                    ]
                )
            );
        }
    }

    /**
     * 验证 action 冲突
     *
     * @param array $data
     * @throws \app\customs\zapi\common\exceptions\ValidateException
     */
    protected static function validateActionConflicts(array $data): void
    {
        $has_ack_action = (($data['action'] & PRS_PROBLEM_UPDATE_ACKNOWLEDGE) == PRS_PROBLEM_UPDATE_ACKNOWLEDGE);
        $has_unack_action = (($data['action'] & PRS_PROBLEM_UPDATE_UNACKNOWLEDGE) == PRS_PROBLEM_UPDATE_UNACKNOWLEDGE);
        $has_suppress_action = (($data['action'] & PRS_PROBLEM_UPDATE_SUPPRESS) == PRS_PROBLEM_UPDATE_SUPPRESS);
        $has_unsuppress_action = (($data['action'] & PRS_PROBLEM_UPDATE_UNSUPPRESS) == PRS_PROBLEM_UPDATE_UNSUPPRESS);
        $has_close_action = (($data['action'] & PRS_PROBLEM_UPDATE_CLOSE) == PRS_PROBLEM_UPDATE_CLOSE);
        $has_rank_change_to_cause_action = (($data['action'] & PRS_PROBLEM_UPDATE_RANK_TO_CAUSE) == PRS_PROBLEM_UPDATE_RANK_TO_CAUSE);
        $has_change_rank_to_symptom_action = (($data['action'] & PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM) == PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM);

        if ($has_ack_action && $has_unack_action) {
            self::exception(
                PRS_API_ERROR_PARAMETERS,
                t('zapi',
                    'Incorrect value for field "{field}": {error}.',
                    [
                        'field' => 'action',
                        'error' => t('zapi',
                            'value must be one of {range}', [
                                'range' => implode(', ', [
                                    PRS_PROBLEM_UPDATE_ACKNOWLEDGE,
                                    PRS_PROBLEM_UPDATE_UNACKNOWLEDGE
                                ])
                            ]
                        )
                    ]
                )
            );
        }

        if ($has_suppress_action && $has_unsuppress_action) {
            self::exception(
                PRS_API_ERROR_PARAMETERS,
                t('zapi',
                    'Incorrect value for field "{field}": {error}.',
                    [
                        'field' => 'action',
                        'error' => t('zapi',
                            'value must be one of {range}', [
                                'range' => implode(', ', [
                                    PRS_PROBLEM_UPDATE_SUPPRESS,
                                    PRS_PROBLEM_UPDATE_UNSUPPRESS
                                ])
                            ]
                        )
                    ]
                )
            );
        }

        if ($has_close_action && ($has_suppress_action || $has_unsuppress_action)) {
            $action = $has_suppress_action ? PRS_PROBLEM_UPDATE_SUPPRESS : PRS_PROBLEM_UPDATE_UNSUPPRESS;
            self::exception(
                PRS_API_ERROR_PARAMETERS,
                t('zapi',
                    'Incorrect value for field "{field}": {error}.',
                    [
                        'field' => 'action',
                        'error' => t('zapi', 'value must be one of {range}', ['range' => implode(', ', [PRS_PROBLEM_UPDATE_CLOSE, $action])])
                    ]
                )
            );
        }

        if ($has_rank_change_to_cause_action && $has_change_rank_to_symptom_action) {
            self::exception(
                PRS_API_ERROR_PARAMETERS,
                t('zapi',
                    'Incorrect value for field "{field}": {error}.',
                    [
                        'field' => 'action',
                        'error' => t('zapi', 'value must be one of {range}', ['range' => implode(', ', [PRS_PROBLEM_UPDATE_RANK_TO_CAUSE, PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM])])
                    ]
                )
            );
        }
    }

    /**
     * 验证抑制时间
     *
     * @param array $data
     * @param int $time
     * @throws \app\customs\zapi\common\exceptions\ValidateException
     */
    protected static function validateSuppressTime(array $data, int $time): void
    {
        $has_suppress_action = (($data['action'] & PRS_PROBLEM_UPDATE_SUPPRESS) == PRS_PROBLEM_UPDATE_SUPPRESS);

        if ($has_suppress_action && $data['suppress_until'] <= $time && $data['suppress_until'] != 0) {
            self::exception(
                PRS_API_ERROR_PARAMETERS,
                t('zapi',
                    'Incorrect value for field "{field}": {error}.',
                    [
                        'field' => 'suppress_until',
                        'error' => t('zapi', 'unexpected value "{value}"', ['value' => $data['suppress_until']])
                    ]
                )
            );
        }
    }

    /**
     * 验证消息不能为空
     *
     * @param array $data
     * @throws \app\customs\zapi\common\exceptions\ValidateException
     */
    public static function validateMessage(array $data): void
    {
        $has_message_action = (($data['action'] & PRS_PROBLEM_UPDATE_MESSAGE) == PRS_PROBLEM_UPDATE_MESSAGE);

        if ($has_message_action && $data['message'] === '') {
            self::exception(
                PRS_API_ERROR_PARAMETERS,
                t('zapi',
                    'Incorrect value for field "{field}": {error}.',
                    [
                        'field' => 'message',
                        'error' => t('zapi', 'cannot be empty')
                    ]
                )
            );
        }
    }

    /**
     * 检查是否有关闭操作
     */
    public static function hasCloseAction(array $data): bool
    {
        return (($data['action'] & PRS_PROBLEM_UPDATE_CLOSE) == PRS_PROBLEM_UPDATE_CLOSE);
    }

    /**
     * 检查是否有确认操作
     */
    public static function hasAcknowledgeAction(array $data): bool
    {
        return (($data['action'] & PRS_PROBLEM_UPDATE_ACKNOWLEDGE) == PRS_PROBLEM_UPDATE_ACKNOWLEDGE);
    }

    /**
     * 检查是否有取消确认操作
     */
    public static function hasUnacknowledgeAction(array $data): bool
    {
        return (($data['action'] & PRS_PROBLEM_UPDATE_UNACKNOWLEDGE) == PRS_PROBLEM_UPDATE_UNACKNOWLEDGE);
    }

    /**
     * 检查是否有消息操作
     */
    public static function hasMessageAction(array $data): bool
    {
        return (($data['action'] & PRS_PROBLEM_UPDATE_MESSAGE) == PRS_PROBLEM_UPDATE_MESSAGE);
    }

    /**
     * 检查是否有严重性操作
     */
    public static function hasSeverityAction(array $data): bool
    {
        return (($data['action'] & PRS_PROBLEM_UPDATE_SEVERITY) == PRS_PROBLEM_UPDATE_SEVERITY);
    }

    /**
     * 检查是否有抑制操作
     */
    public static function hasSuppressAction(array $data): bool
    {
        return (($data['action'] & PRS_PROBLEM_UPDATE_SUPPRESS) == PRS_PROBLEM_UPDATE_SUPPRESS);
    }

    /**
     * 检查是否有取消抑制操作
     */
    public static function hasUnsuppressAction(array $data): bool
    {
        return (($data['action'] & PRS_PROBLEM_UPDATE_UNSUPPRESS) == PRS_PROBLEM_UPDATE_UNSUPPRESS);
    }

    /**
     * 检查是否有转换为原因操作
     */
    public static function hasRankToCauseAction(array $data): bool
    {
        return (($data['action'] & PRS_PROBLEM_UPDATE_RANK_TO_CAUSE) == PRS_PROBLEM_UPDATE_RANK_TO_CAUSE);
    }

    /**
     * 检查是否有转换为症状操作
     */
    public static function hasRankToSymptomAction(array $data): bool
    {
        return (($data['action'] & PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM) == PRS_PROBLEM_UPDATE_RANK_TO_SYMPTOM);
    }
}
