<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\export\writers\CExportWriterFactory;
use app\customs\zapi\common\validators\BooleanValidator;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\ObjectValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;

class ExportForm extends BaseForm
{
    /**
     * @param  boolean $full
     * @return array
     */
    public static function getValidationRules(bool $with_unlinked_parent_templates = false, bool $full = false): array
    {
        $rules = [
            'format' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'in' => [CExportWriterFactory::YAML, CExportWriterFactory::XML, CExportWriterFactory::JSON, CExportWriterFactory::RAW]],
            'prettyprint' => [BooleanValidator::class, 'default' => false],
            'options' => [
                ObjectValidator::class,
                'flags' => API_REQUIRED,
                'fields' => [
                    'hosts' => [IdsValidator::class],
                    'images' => [IdsValidator::class],
                    'maps' => [IdsValidator::class],
                    'mediaTypes' => [IdsValidator::class],
                    'template_groups' => [IdsValidator::class],
                    'host_groups' => [IdsValidator::class],
                    'templates' => [IdsValidator::class]
                ]
            ]
        ];

        if ($with_unlinked_parent_templates) {
            $rules['unlink_parent_templates'] = [
                ObjectsValidator::class,
                'flags' => API_ALLOW_NULL,
                'default' => [],
                'fields' => [
                    'templateid' => [IdValidator::class],
                    'unlink_templateids' => [IdsValidator::class]
                ]
            ];
        }

        if ($full) {
            return [
                ObjectValidator::class,
                'fields' => $rules
            ];
        }
        return $rules;
    }
}
