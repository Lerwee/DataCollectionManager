<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\TGNameValidator;
use app\customs\zapi\common\validators\UuidValidator;

class TemplateGroupForm extends BaseForm
{
    /**
     * @return array
     */
    public static function getValidationRules(string $method = 'create'): array
    {
        $rules = [
            'uuid' => [UuidValidator::class],
            'groupid' => [IdValidator::class, 'flags' => API_REQUIRED],
            'name' => [TGNameValidator::class, 'length' => DB::getFieldLength('hstgrp', 'name')],
        ];

        if ($method == 'create') {
            unset($rules['groupid']);
            $rules['name']['flags'] = API_REQUIRED;
        }

        return $rules;
    }
}
