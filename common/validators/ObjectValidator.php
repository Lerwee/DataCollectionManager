<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Exception;

/**
 * Class ObjectValidator
 * @package app\customs\zapi\common\validators
 */
class ObjectValidator extends BaseZValidator
{
    /**
     * @var array
     */
    public $fields = [];

    /**
     * @param $model
     * @param $attribute
     * @return array|null
     * @throws Exception
     */
    public function validateModelValue($model, $attribute): ?array
    {
        $value = $model->$attribute;
        if (($this->flags & API_ALLOW_NULL) && $value === null) {
            return null;
        }

        if (!is_array($value)) {
            return $this->setValueError(t('zapi', 'an array is expected'));
        }

        if (!ValidateHelper::validateObject($value, $this->fields, ['flags' => $this->flags, '_path' => $this->_path], $error)) {
            return $this->setValueError($error);
        }
        $model->$attribute = $value;
        return null;
    }
}