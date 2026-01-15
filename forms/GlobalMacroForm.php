<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\SettingsHelper;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\UserMacroValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\VaultSecretValidator;
use app\modules\libzbx\models\zbx\Globalmacro;


class GlobalMacroForm extends BaseForm
{
    /**
     * @param string $method
     * @return array
     */
    public static function getValidationRules(string $method = 'create', bool $full = false): array
    {
        $rules = [
            'globalmacroid' => [IdValidator::class, 'flags' => API_REQUIRED],
            'macro' => [UserMacroValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength(Globalmacro::tableName(), 'macro')],
            'type' => [Int32Validator::class, 'in' => [PRS_MACRO_TYPE_TEXT, PRS_MACRO_TYPE_SECRET, PRS_MACRO_TYPE_VAULT], 'default' => PRS_MACRO_TYPE_TEXT],
            'value' => static::getMacroValueValidationRules($method),
            'description' => [Utf8StringValidator::class, 'length' => DB::getFieldLength(Globalmacro::tableName(), 'description')],
        ];

        if ($method == 'create') {
            unset($rules['globalmacroid']);
            if ($full) {
                return [
                    ObjectsValidator::class,
                    'flags' => API_NOT_EMPTY | API_NORMALIZE,
                    'uniq' => [['macro']],
                    'fields' => $rules,
                ];
            }
        } elseif ($method == 'update') {
            unset($rules['macro']['flags']);
            if ($full) {
                return [
                    ObjectsValidator::class,
                    'flags' => API_NOT_EMPTY | API_NORMALIZE,
                    'uniq' => [['globalmacroid'], ['macro']],
                    'fields' => $rules,
                ];
            }
        }

        return $rules;
    }

    public static function getMacroValueValidationRules(string $method): array
    {
        if ($method == 'update') {
            return [Utf8StringValidator::class, 'length' => DB::getFieldLength(Globalmacro::tableName(), 'value')];
        }

        return [
            MultipleValidator::class,
            'flags' => API_REQUIRED,
            'rules' => [
                [
                    Utf8StringValidator::class,
                    'when' => function ($model) {
                        return in_array($model->type, [PRS_MACRO_TYPE_TEXT, PRS_MACRO_TYPE_SECRET]);
                    },
                    'length' => DB::getFieldLength(Globalmacro::tableName(), 'value')
                ],
                [
                    VaultSecretValidator::class,
                    'when' => function ($model) {
                        return $model->type == PRS_MACRO_TYPE_VAULT;
                    },
                    'provider' => SettingsHelper::get(SettingsHelper::VAULT_PROVIDER),
                    'length' => DB::getFieldLength(Globalmacro::tableName(), 'value')
                ]
            ]
        ];
    }

}