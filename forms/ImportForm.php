<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\import\readers\CImportReaderFactory;
use app\customs\zapi\common\validators\BooleanValidator;
use app\customs\zapi\common\validators\ObjectValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;

class ImportForm extends BaseForm
{

    /**
     * @return array
     */
    public static function getValidationRules(): array
    {
        $rules = [
            'format' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'in' => [CImportReaderFactory::YAML, CImportReaderFactory::XML, CImportReaderFactory::JSON]],
            'source' => [Utf8StringValidator::class, 'flags' => API_REQUIRED],
            'rules' => static::getParamsValidationRules()
        ];

        return $rules;
    }


    public static function getParamsValidationRules(bool $full = true)
    {
        $rules = [
            'discoveryRules' =>        [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false],
                'deleteMissing' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'graphs' =>                [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false],
                'deleteMissing' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'host_groups' =>        [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'template_groups' =>    [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'hosts' =>                [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'httptests' =>            [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false],
                'deleteMissing' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'images' =>                [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'items' =>                [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false],
                'deleteMissing' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'maps' =>                [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'mediaTypes' =>            [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'templateLinkage' =>    [ObjectValidator::class, 'default' => [], 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'deleteMissing' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'templates' =>            [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'templateDashboards' =>    [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false],
                'deleteMissing' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'triggers' =>            [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false],
                'deleteMissing' =>        [BooleanValidator::class, 'default' => false]
            ]],
            'valueMaps' =>            [ObjectValidator::class, 'fields' => [
                'createMissing' =>        [BooleanValidator::class, 'default' => false],
                'updateExisting' =>        [BooleanValidator::class, 'default' => false],
                'deleteMissing' =>        [BooleanValidator::class, 'default' => false]
            ]]
        ];

        if ($full) {
            return [
                ObjectValidator::class,
                'flags' => API_REQUIRED,
                'fields' => $rules
            ];
        }

        return $rules;
    }


    /**
     * @param  string $preset
     * @return array
     */
    public static function getDefaultParams(string $preset = 'template'): array
    {
        $rules = [
            'host_groups' => ['updateExisting' => false, 'createMissing' => false],
            'template_groups' => ['updateExisting' => false, 'createMissing' => false],
            'hosts' => ['updateExisting' => false, 'createMissing' => false],
            'templates' => ['updateExisting' => false, 'createMissing' => false],
            'templateDashboards' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
            'templateLinkage' => ['createMissing' => false, 'deleteMissing' => false],
            'items' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
            'discoveryRules' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
            'triggers' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
            'graphs' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
            'httptests' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
            'maps' => ['updateExisting' => false, 'createMissing' => false],
            'images' => ['updateExisting' => false, 'createMissing' => false],
            'mediaTypes' => ['updateExisting' => false, 'createMissing' => false],
            'valueMaps' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false]
        ];

        if ($preset === 'template') {
            $rules['host_groups'] = ['updateExisting' => true, 'createMissing' => true];
            $rules['template_groups'] = ['updateExisting' => true, 'createMissing' => true];
            $rules['templates'] = ['updateExisting' => true, 'createMissing' => true];
            $rules['templateDashboards'] = [
                'updateExisting' => true,
                'createMissing' => true,
                'deleteMissing' => true
            ];
            $rules['items'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true];
            $rules['discoveryRules'] = [
                'updateExisting' => true,
                'createMissing' => true,
                'deleteMissing' => true
            ];
            $rules['triggers'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true];
            $rules['graphs'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true];
            $rules['httptests'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true];
            $rules['templateLinkage'] = ['createMissing' => true, 'deleteMissing' => true];
            $rules['valueMaps'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true];
        }
        return $rules;
    }
}
