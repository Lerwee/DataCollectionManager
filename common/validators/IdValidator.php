<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Model;

/**
 * API_ID
 * Class IdValidator
 * @package app\customs\zapi\common\validators
 */
class IdValidator extends BaseZValidator
{
    /**
     * @var int
     */
    protected $inType = self::IN_TYPE_CUSTOM;

    /**
     * 0 or null
     * @var array
     */
    public $in = null;

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
            $value = (string)$value;
            if ($value[0] === '0') {
                $value = ltrim($value, '0');
                if ($value === '') {
                    $value = '0';
                }
            }
            $model->$attribute = $value;
        }
        return $result;
    }

    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        if (!is_scalar($value) || is_bool($value) || is_double($value) || !ctype_digit(strval($value))) {
            return $this->setValueError(t('zapi', 'a number is expected'));
        }

        if (bccomp($value, PRS_DB_MAX_ID) > 0) {
            return $this->setValueError(t('zapi', 'a number is too large'));
        }

        $value = (string)$value;
        if ($value[0] === '0') {
            $value = ltrim($value, '0');
            if ($value === '') {
                $value = '0';
            }
        }

        if ($this->in !== null) {
            if ($this->in != 0 && !(is_array($this->in) && in_array(0, $this->in))) {
                return $this->setValueError(t('zapi', 'Incorrect validation rules'));
            }
            if ($value != 0) {
                return $this->setValueError(t('zapi', 'value must be {value}', ['value' => '0']));
            }
        }
        return null;
    }
}