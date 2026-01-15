<?php
namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Model;

/**
 * API_STRINGS_UTF8
 * Class Utf8StringsValidator
 * @package app\customs\zapi\common\validators
 */
class Utf8StringsValidator extends BaseZValidator
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
        if (($this->flags & API_ALLOW_NULL) && $value === null) {
            return null;
        }
        $utfValidator = new Utf8StringValidator();
        if (($this->flags & API_NORMALIZE) && empty($utfValidator->validateValue($value))) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return $this->setValueError(t('zapi', 'an array is expected'));
        }

        if (($this->flags & API_NOT_EMPTY) && !$value) {
            return $this->setValueError(t('zapi', 'cannot be empty'));
        }

        $data = array_values($value);
        $utfValidator->in = $this->in;
        foreach ($data as $index => $value) {
            $result = $utfValidator->validateValue($value);
            if (!empty($result)) {
                return $result;
            }
        }
        $model->$attribute = $data;
        return null;
    }
}