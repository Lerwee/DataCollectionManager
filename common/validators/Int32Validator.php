<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\helpers\ValueCheckHelper;
use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Model;

/**
 * Class Int32Validator
 * @package app\customs\zapi\common\validators
 */
class Int32Validator extends BaseZValidator
{
    /**
     * @var int
     */
    protected $inType = self::IN_TYPE_NUMBER;

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
        $result = $this->validateValue($value);
        if ($result === null) {
            if (is_string($value)) {
                $model->$attribute = (int)$value;
            }
        }
        return $result;
    }

    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        if (($this->flags & API_ALLOW_NULL) && $value === null) {
            return null;
        }

        if ((!is_int($value) && !is_string($value)) || !preg_match('/^'.PRS_PREG_INT.'$/', strval($value))) {
            return $this->setValueError(t('zapi', 'an integer is expected'));
        }

        if ($value < PRS_MIN_INT32 || $value > PRS_MAX_INT32) {
            return $this->setValueError(t('zapi', 'a number is too large'));
        }

        $error = '';
        if (!ValueCheckHelper::checkInt32In($value, $this->in, $error)) {
            return [$error, []];
        }
        return null;
    }
}