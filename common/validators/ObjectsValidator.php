<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Class ObjectsValidator
 * @package app\customs\zapi\common\validators
 */
class ObjectsValidator extends BaseZValidator
{
    /**
     * @var null|int
     */
    public $length = null;

    /**
     * @var array
     */
    public $fields = [];

    /**
     * @param $model
     * @param $attribute
     * @return array|null
     * @throws InvalidConfigException
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
        if (($this->flags & API_NOT_EMPTY) && !$value) {
            return $this->setValueError(t('zapi', 'cannot be empty'));
        }

        if (is_numeric($this->length) && count($value) > $this->length) {
            if ($this->length == 0) {
                return $this->setValueError(t('zapi', 'should be empty'));
            } else {
                return $this->setValueError(t('zapi', 'maximum number of array elements is {length}', ['length' => $this->length]));
            }
        }

        if (($this->flags & API_NORMALIZE) && $value) {
            reset($value);
            if (!is_int(key($value))) {
                $value = [$value];
            }
        }
        if (!($this->flags & API_PRESERVE_KEYS)) {
            $value = array_values($value);
        }

        foreach ($value as $index => &$valueItem) {
            $routes = array_merge($this->getPath(), [$index]);
            if (!ValidateHelper::validateObject($valueItem, $this->fields, ['flags' => ($this->flags & API_ALLOW_UNEXPECTED), '_path' => $routes], $error)) {
                return $this->setValueError($error);
            }
        }
        $model->$attribute = $value;
        return null;
    }
}