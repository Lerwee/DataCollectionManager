<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\InvalidConfigException;

/**
 * Class ZFilterValidator
 * @package app\customs\zapi\common\validators
 */
class ZFilterValidator extends BaseZValidator
{
    /**
     * ['name', 'status']
     * @var array
     */
    public $fields = [];

    /**
     * @param mixed $value
     * @return array|null
     * @throws InvalidConfigException
     */
    public function validateValue($value): ?array
    {
        $rules = [];
        foreach ($this->fields as $field) {
            $rules[$field] = [FilterValuesValidator::class, 'flags' => API_ALLOW_NULL | API_NORMALIZE];
        }
        if (!ValidateHelper::validateObject($value, $rules, ['flags' => $this->flags], $error)) {
            return $this->setValueError($error);
        }
        return null;
    }
}