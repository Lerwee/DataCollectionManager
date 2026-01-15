<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\validators\BooleanValidator;
use app\customs\zapi\common\validators\FilterValueValidator;
use app\customs\zapi\common\validators\FlagValidator;
use app\customs\zapi\common\validators\FloatValidator;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\NumericRangesValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\OutputValidator;
use app\customs\zapi\common\validators\RegexValidator;
use app\customs\zapi\common\validators\SortOrderValidator;
use app\customs\zapi\common\validators\Utf8StringsValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\modules\libzbx\models\zbx\Valuemap;
use app\modules\libzbx\models\zbx\ValuemapMapping;

class ValueMapForm extends BaseForm
{
    public static function getBaseValidationRules(): array
    {
        return [
            'hostid' => [IdValidator::class],
            'name' => [Utf8StringValidator::class]
        ];
    }

    public static function getValidationRules(string $method = 'create'): array
    {
        if ($method == 'get') {
            return [
                // filter
                'valuemapids' =>			[IdsValidator::class, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
                'hostids' =>				[IdsValidator::class, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
                'filter' =>					[FilterValueValidator::class, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['valuemapid', 'hostid', 'name']],
                'search' =>					[FilterValueValidator::class, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name']],
                'searchByAny' =>			[BooleanValidator::class, 'default' => false],
                'startSearch' =>			[FlagValidator::class, 'default' => false],
                'excludeSearch' =>			[FlagValidator::class, 'default' => false],
                'searchWildcardsEnabled' =>	[BooleanValidator::class, 'default' => false],
                // output
                'output' =>					[OutputValidator::class, 'in' => ['valuemapid', 'uuid', 'name', 'hostid'], 'default' => API_OUTPUT_EXTEND],
                'selectMappings' =>			[OutputValidator::class, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => ['type', 'value', 'newvalue'], 'default' => null],
                'countOutput' =>			[FlagValidator::class, 'default' => false],
                // sort and limit
                'sortfield' =>				[Utf8StringsValidator::class, 'flags' => API_NORMALIZE, 'in' => ['valuemapid', 'name'], 'uniq' => true, 'default' => []],
                'sortorder' =>				[SortOrderValidator::class, 'default' => []],
                'limit' =>					[Int32Validator::class, 'flags' => API_ALLOW_NULL, 'in' => '1:'.PRS_MAX_INT32, 'default' => null],
                // flags
                'editable' =>				[BooleanValidator::class, 'default' => false],
                'preservekeys' =>			[BooleanValidator::class, 'default' => false]
            ];
        }

        $rules = [
            'uuid' => ['safe'],
            'name' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength(Valuemap::tableName(), 'name')],
            'mappings' => self::getMappingValidationRules()
        ];
        if ($method == 'update') {
            $rules['valuemapid'] = [IdValidator::class, 'flags' => API_REQUIRED];
        } else {
            $rules['hostid'] = [IdValidator::class, 'flags' => API_REQUIRED];
            $rules['name']['flags'] = API_REQUIRED | API_NOT_EMPTY;
        }

        return $rules;
    }

    public static function getMappingValidationRules(): array
    {
        return [
            ObjectsValidator::class,
            'flags' => API_REQUIRED | API_NOT_EMPTY,
            'fields' => [
                'type' => [
                    Int32Validator::class,
                    'default' => VALUEMAP_MAPPING_TYPE_EQUAL,
                    'in' => [VALUEMAP_MAPPING_TYPE_EQUAL, VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, VALUEMAP_MAPPING_TYPE_LESS_EQUAL, VALUEMAP_MAPPING_TYPE_IN_RANGE, VALUEMAP_MAPPING_TYPE_REGEXP, VALUEMAP_MAPPING_TYPE_DEFAULT]
                ],
                'value' => [
                    MultipleValidator::class,
                    'rules' => [
                        [
                            Utf8StringValidator::class,
                            'length' => DB::getFieldLength(ValuemapMapping::tableName(), 'value'),
                            'when' => function ($model) {
                                return $model->type == VALUEMAP_MAPPING_TYPE_EQUAL;
                            }
                        ],
                        [
                            FloatValidator::class,
                        //    'length' => DB::getFieldLength(ValuemapMapping::tableName(), 'value'),
                            'when' => function ($model) {
                                return in_array($model->type, [VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, VALUEMAP_MAPPING_TYPE_LESS_EQUAL]);
                            }
                        ],
                        [
                            NumericRangesValidator::class,
                            'flags' => API_NOT_EMPTY,
                            'length' => DB::getFieldLength(ValuemapMapping::tableName(), 'value'),
                            'when' => function ($model) {
                                return $model->type == VALUEMAP_MAPPING_TYPE_IN_RANGE;
                            }
                        ],
                        [
                            RegexValidator::class,
                            'flags' => API_NOT_EMPTY,
                            'length' => DB::getFieldLength(ValuemapMapping::tableName(), 'value'),
                            'when' => function ($model) {
                                return $model->type == VALUEMAP_MAPPING_TYPE_REGEXP;
                            }
                        ],
                        [
                            Utf8StringValidator::class,
                            'when' => function ($model) {
                                return $model->type == VALUEMAP_MAPPING_TYPE_DEFAULT;
                            }
                        ]
                    ]
                ],
                'newvalue' => [
                    Utf8StringValidator::class,
                    'flags' => API_REQUIRED | API_NOT_EMPTY,
                    'length' => DB::getFieldLength(ValuemapMapping::tableName(), 'newvalue')
                ]
            ]
        ];
    }
}
