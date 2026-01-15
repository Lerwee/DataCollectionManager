<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\SettingHelper;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\UserMacroValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\VaultSecretValidator;
use app\modules\libzbx\models\zbx\Hostmacro;

class HostMacroForm extends BaseForm
{
    /**
     * @param string $method
     * @return array
     */
    public static function getValidationRules(string $method = 'create', bool $full = false): array
    {
        $rules = [
            'hostmacroid' => [IdValidator::class, 'flags' => API_REQUIRED],
            'hostid' => [IdValidator::class, 'flags' => API_REQUIRED],
            'macro' => [UserMacroValidator::class, 'length' => DB::getFieldLength(Hostmacro::tableName(), 'macro')],
            'type' => [Int32Validator::class, 'in' => [PRS_MACRO_TYPE_TEXT, PRS_MACRO_TYPE_SECRET, PRS_MACRO_TYPE_VAULT], 'default' => PRS_MACRO_TYPE_TEXT],
            'value' => static::getMacroValueValidationRules(),
            'description' => [Utf8StringValidator::class, 'length' => DB::getFieldLength(Hostmacro::tableName(), 'description')],
            'automatic' => [Int32Validator::class, 'in'   => [PRS_USERMACRO_MANUAL]]
        ];

        if ($method == 'create') {
            unset($rules['hostmacroid']);
            unset($rules['automatic']);

            if ($full) {
                return [
                    ObjectsValidator::class,
                    'flags' => API_NOT_EMPTY | API_NORMALIZE,
                    'uniq' => [['hostid', 'macro']],
                    'fields' => $rules,
                ];
            }
        } elseif ($method == 'update') {
            unset($rules['hostid']);


            if ($full) {
                unset($rules['automatic']);
                return [
                    ObjectsValidator::class,
                    'flags' => API_NORMALIZE,
                    'uniq' => [['hostmacroid']],
                    'fields' => $rules,
                ];
            }
        }

        return $rules;
    }

    public static function getUniqueValidationRules()
    {
        return [
            'hostid' => [IdValidator::class],
            'macro' => [UserMacroValidator::class],
        ];
    }

    public static function getMacroValueValidationRules(): array
    {
        return [
            MultipleValidator::class,
            'rules' => [
                [
                    Utf8StringValidator::class,
                    'when' => function ($model) {
                        return in_array($model->type, [PRS_MACRO_TYPE_TEXT, PRS_MACRO_TYPE_SECRET]);
                    },
                    'length' => DB::getFieldLength(Hostmacro::tableName(), 'value')
                ],
                [
                    VaultSecretValidator::class,
                    'when' => function ($model) {
                        return $model->type == PRS_MACRO_TYPE_VAULT;
                    },
                    'provider' => SettingHelper::get(SettingHelper::VAULT_PROVIDER),
                    'length' => DB::getFieldLength(Hostmacro::tableName(), 'value')
                ]
            ]
        ];
    }
}
