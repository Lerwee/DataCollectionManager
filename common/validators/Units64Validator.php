<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\helpers\ValueCheckHelper;
use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Model;

/**
 * Class Units64Validator
 * @package app\customs\zapi\common\validators
 */
class Units64Validator extends BaseZValidator
{
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
        $validator = new Unit64Validator();
        if (($this->flags & API_NORMALIZE) && empty($validator->validateValue($value))) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return $this->setValueError(t('zapi', 'an array is expected'));
        }

        if (($this->flags & API_NOT_EMPTY) && !$value) {
            return $this->setValueError(t('zapi', 'cannot be empty'));
        }
        $data = array_values($value);
        foreach ($data as $index => &$value) {
            $result = $validator->validateValue($data);
            if (!empty($result)) {
                return $result;
            }
            $datum = (string)$value;
            if ($datum[0] === '0') {
                $datum = ltrim($datum, '0');
                if ($datum === '') {
                    $datum = '0';
                }
            }
            $value = floatval($datum);
        }
        $model->$attribute = $data;
        return null;
    }
}