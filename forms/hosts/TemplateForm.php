<?php

namespace app\customs\zapi\forms\hosts;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\SettingHelper;
use app\customs\zapi\common\validators\HostNameValidator;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\UserMacrosValidator;
use app\customs\zapi\common\validators\UserMacroValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\UuidValidator;
use app\customs\zapi\common\validators\VaultSecretValidator;
use app\modules\libzbx\models\zbx\Hostmacro;
use app\modules\libzbx\models\zbx\Hosts;

class TemplateForm extends BaseHostForm
{
    /**
     * @return array
     */
    public static function getValidationRules(string $method = 'create'): array
    {
        $rules = [
            'uuid' => [UuidValidator::class],
            'templateid' => [IdValidator::class],
            'host' => [HostNameValidator::class, 'length' => DB::getFieldLength(Hosts::tableName(), 'host')],
            'name' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength(Hosts::tableName(), 'name')],
            'description' => [Utf8StringValidator::class, 'length' => DB::getFieldLength(Hosts::tableName(), 'description')],
            'vendor_name' => [Utf8StringValidator::class, 'length' => DB::getFieldLength(Hosts::tableName(), 'vendor_name')],
            'vendor_version' => [Utf8StringValidator::class, 'length' => DB::getFieldLength(Hosts::tableName(), 'vendor_version')],
            'groups' => self::getGroupsValidationRules(),
            'templates' => self::getTemplatesValidationRules(),
            'templates_clear' => self::getTemplatesValidationRules(),
            'tags' => self::getTagsValidationRules(),
            'macros' => self::getMacrosValidationRules($method),
            'valuemaps' => ['safe']
        ];

        if ($method == 'create') {
            unset($rules['templateid']);
            unset($rules['templates_clear']);
        }

        return $rules;
    }


    /**
     * Check vendor fields for update or create operation.
     *
     * @param array      $templates
     * @param array|null $db_templates
     *
     * @throws ValidateException
     */
    public static function checkVendorFields(array $templates, array $dbTemplates = null): void
    {
        $vendorFields = array_fill_keys(['vendor_name', 'vendor_version'], '');

        foreach ($templates as $i => $template) {
            if (!array_key_exists('vendor_name', $template) && !array_key_exists('vendor_version', $template)) {
                continue;
            }

            $_template = array_intersect_key($template, $vendorFields);

            if ($dbTemplates === null) {
                $_template += $vendorFields;
            } else {
                $_template += array_intersect_key($dbTemplates[$template['templateid']], $vendorFields);
            }

            if (($_template['vendor_name'] === '') !== ($_template['vendor_version'] === '')) {
                $msg = t('zapi', 'Invalid parameter {attribute}, {error}', [
                    'attribute' => '/' . ($i + 1),
                    'error' => t('zapi', 'both vendor_name and vendor_version should be either present or empty')
                ]);
                self::exception(60750001, $msg);
            }
        }
    }

    public static function getMassValidationRules(string $method = 'massAdd'): array
    {
        switch ($method) {
            case 'massRemove':
                return [
                    'templateids' => [
                        IdsValidator::class,
                        'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE,
                        'uniq' => true
                    ],
                    'groupids' => [IdsValidator::class,  'flags' => API_NORMALIZE, 'uniq' => true],
                    'macros' => [
                        UserMacrosValidator::class,
                        'flags' => API_NORMALIZE,
                        'uniq' => true,
                        'length' => DB::getFieldLength(Hostmacro::tableName(), 'macro')
                    ],
                    'groupids' => [IdsValidator::class,  'flags' => API_NORMALIZE, 'uniq' => true],
                    'templateids_link' => [IdsValidator::class,  'flags' => API_NORMALIZE, 'uniq' => true],
                    'templateids_clear' => [IdsValidator::class,  'flags' => API_NORMALIZE, 'uniq' => true],
                ];
                break;
            case 'massAdd':
            case 'massUpdate':
                $rules = [
                    'templates' =>
                    [
                        ObjectsValidator::class,
                        'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE,
                        'uniq' => [['templateid']],
                        'fields' => [
                            'templateid' => [IdValidator::class, 'flags' => API_REQUIRED]
                        ]
                    ],
                    'groups' => [
                        ObjectsValidator::class,
                        'flags' => API_NORMALIZE,
                        'uniq' => [['groupid']],
                        'fields' => [
                            'groupid' => [IdValidator::class, 'flags' => API_REQUIRED]
                        ]
                    ],
                    'macros' => [
                        ObjectsValidator::class,
                        'flags' => API_NORMALIZE,
                        'uniq' => [['macro']],
                        'fields' => [
                            'macro' => [
                                UserMacroValidator::class,
                                'flags' => API_REQUIRED,
                                'length' => DB::getFieldLength('hostmacro', 'macro')
                            ],
                            'type' => [
                                Int32Validator::class,
                                'in' => [PRS_MACRO_TYPE_TEXT, PRS_MACRO_TYPE_SECRET, PRS_MACRO_TYPE_VAULT],
                                'default' => PRS_MACRO_TYPE_TEXT
                            ],
                            'value' => [
                                MultipleValidator::class,
                                'flags' => API_REQUIRED,
                                'rules' => [
                                    VaultSecretValidator::class,
                                    'provider' => SettingHelper::get(SettingHelper::VAULT_PROVIDER),
                                    'length' => DB::getFieldLength('hostmacro', 'value'),
                                    'when' => function ($model) {
                                        return $model['type'] == PRS_MACRO_TYPE_VAULT;
                                    }
                                ],
                                'else' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('hostmacro', 'value')]
                            ],
                            'description' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('hostmacro', 'description')]
                        ]
                    ],
                    'templates_link' => [
                        ObjectsValidator::class,
                        'flags' => API_NORMALIZE,
                        'uniq' => [['templateid']],
                        'fields' => [
                            'templateid' => [IdValidator::class, 'flags' => API_REQUIRED]
                        ]
                    ]
                ];

                if ($method == 'massUpdate') {
                    $rules['templates_clear'] = [
                        ObjectsValidator::class,
                        'flags' => API_NORMALIZE,
                        'uniq' => [['templateid']],
                        'fields' => [
                            'templateid' => [IdValidator::class, 'flags' => API_REQUIRED]
                        ]
                    ];
                }
                return $rules;
        }
    }
}
