<?php
namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Model;

/**
 * API_STRINGS_UTF8
 * Class Utf8StringValidator
 * @package app\customs\zapi\common\validators
 */
class Utf8StringValidator extends BaseZValidator
{
    /**
     * @var null|int
     */
    public $length = null;

    /**
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
            $model->$attribute = (string)$value;
        }
        return $result;
    }

    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        if (!is_string($value) && !is_numeric($value)) {
            return $this->setValueError(t('zapi', 'a character string is expected'));
        }
        $value = (string)$value;
        if (mb_check_encoding($value, 'UTF-8') !== true) {
            return $this->setValueError(t('zapi', 'invalid byte sequence in UTF-8'));
        }

        if (($this->flags & API_NOT_EMPTY) && $value === '') {
            return $this->setValueError(t('zapi', 'cannot be empty'));
        }

        if (is_numeric($this->length) && mb_strlen($value) > $this->length) {
            return $this->setValueError(t('zapi', 'value is too long'));
        }

        if (!empty($this->in)) {
            $range = $this->in;
            if (!in_array($value, $range)) {
                if (($i = array_search('', $range)) !== false) {
                    unset($range[$i]);
                }
                if ($i === false) {
                    $error = tn('zapi', ['value must be {value}', 'value must be one of {value}', count($range)], ['or' => '"'.implode('", "', $range).'"']);
                }
                elseif ($range) {
                    $error = tn('zapi', ['value must be empty or {value}', 'value must be empty or one of {value}', count($range)], ['or' => '"'.implode('", "', $range).'"']);
                } else {
                    $error = t('zapi', 'value must be empty');
                }
                return [$error, []];
            }
        }
        return null;
    }
}