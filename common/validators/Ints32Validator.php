<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * Class Ints32Validator
 * @package app\customs\zapi\common\validators
 */
class Ints32Validator extends BaseZValidator
{
    /**
     * .e.g
     * [1, [1, 10]]
     * @var array
     */
    public $in = [];

    /**
     * @param $model
     * @param $attribute
     * @return array|null
     */
    public function validateModelValue($model, $attribute): ?array
    {
        $value = $model->$attribute;
        if (($this->flags & API_ALLOW_NULL) && $value === null) {
            return null;
        }
        $validator = new Int32Validator();
        if (($this->flags & API_NORMALIZE) && empty($validator->validateValue($value))) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return $this->setValueError(t('zapi', 'an array is expected'));
        }

        if (($this->flags & API_NOT_EMPTY) && !$value) {
            return $this->setValueError(t('zapi', 'cannot be empty'));
        }
        $validator->in = $this->in;
        $data = array_values($value);
        foreach ($data as $index => $value) {
            $result = $validator->validateValue($data);
            if (!empty($result)) {
                return $result;
            }
        }
        $model->$attribute = $data;
        return null;
    }
}