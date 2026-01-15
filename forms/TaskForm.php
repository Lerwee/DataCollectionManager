<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\parsers\CConditionFormula;
use app\customs\zapi\common\parsers\CIPRangeParser;
use app\customs\zapi\common\validators\CondFormulaIdValidator;
use app\customs\zapi\common\validators\CondFormulaValidator;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32RangesValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\IpRangesValidator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\ObjectValidator;
use app\customs\zapi\common\validators\OutputValidator;
use app\customs\zapi\common\validators\TimePeriodValidator;
use app\customs\zapi\common\validators\TimeUnitValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\modules\libzbx\models\zbx\Actions;

class TaskForm extends BaseForm
{


    /**
     * @return array
     */
    public static function getValidationRules(): array
    {
        return [
            'type' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => [PRS_TM_DATA_TYPE_DIAGINFO, PRS_TM_DATA_TYPE_PROXY_HOSTIDS, PRS_TM_TASK_CHECK_NOW]],
            'request' => [MultipleValidator::class, 'flags' => API_REQUIRED, 'rules' => [
                [
                    'validator' => ObjectValidator::class,
                    'when' => function ($model) {
                        return in_array($model->type, [PRS_TM_DATA_TYPE_DIAGINFO]);
                    },
                    'fields' => [
                        'historycache' => ['validator' => ObjectValidator::class, 'fields' => [
                            'stats' => [OutputValidator::class, 'in' => implode(',', ['items', 'values', 'memory', 'memory.data', 'memory.index']), 'default' => API_OUTPUT_EXTEND],
                            'top' => ['validator' => ObjectValidator::class, 'fields' => [
                                'values' => ['validator' => Int32Validator::class]
                            ]]
                        ]],
                        'valuecache' => ['validator' => ObjectValidator::class, 'fields' => [
                            'stats' => ['validator' => OutputValidator::class, 'in' => implode(',', ['items', 'values', 'memory', 'mode']), 'default' => API_OUTPUT_EXTEND],
                            'top' => ['validator' => ObjectValidator::class, 'fields' => [
                                'values' => ['validator' => Int32Validator::class],
                                'request.values' => ['validator' => Int32Validator::class]
                            ]]
                        ]],
                        'preprocessing' => ['validator' => ObjectValidator::class, 'fields' => [
                            'stats' => ['validator' => OutputValidator::class, 'in' => implode(',', ['values', 'preproc.values']), 'default' => API_OUTPUT_EXTEND],
                            'top' => ['validator' => ObjectValidator::class, 'fields' => [
                                'values' => ['validator' => Int32Validator::class]
                            ]]
                        ]],
                        'alerting' => ['validator' => ObjectValidator::class, 'fields' => [
                            'stats' => ['validator' => OutputValidator::class, 'in' => 'alerts', 'default' => API_OUTPUT_EXTEND],
                            'top' => ['validator' => ObjectValidator::class, 'fields' => [
                                'media.alerts' => ['validator' => Int32Validator::class],
                                'source.alerts' => ['validator' => Int32Validator::class]
                            ]]
                        ]],
                        'lld' => ['validator' => ObjectValidator::class, 'fields' => [
                            'stats' => ['validator' => OutputValidator::class, 'in' => implode(',', ['rules', 'values']), 'default' => API_OUTPUT_EXTEND],
                            'top' => ['validator' => ObjectValidator::class, 'fields' => [
                                'values' => ['validator' => Int32Validator::class]
                            ]]
                        ]]
                    ]],
                [
                    'when' => function ($model) {
                        return in_array($model->type, [PRS_TM_DATA_TYPE_PROXY_HOSTIDS]);
                    },
                    'validator' => ObjectValidator::class, 'fields' => [
                    'proxy_hostids' => ['validator' => IdsValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => true]
                ]],
                [
                    'when' => function ($model) {
                        return in_array($model->type, [PRS_TM_TASK_CHECK_NOW]);
                    },
                    'validator' => ObjectValidator::class, 'fields' => [
                    'itemid' => ['validator' => IdValidator::class, 'flags' => API_REQUIRED]
                ]]
            ]],
            'proxy_hostid' => [IdValidator::class, 'default' => 0]
        ];

    }
}
