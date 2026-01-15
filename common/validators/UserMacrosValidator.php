<?php
namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CUserMacroParser;
use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Model;

/**
 * API_USER_MACROS
 * Class UserMacrosValidator
 * @package app\customs\zapi\common\validators
 */
class UserMacrosValidator extends BaseZValidator
{
    /**
     * @param $model
     * @param $attribute
     * @return array|null
     */
    public function validateModelValue($model, $attribute): ?array
    {
        $value = $model->$attribute;
        $validator = new UserMacroValidator();
        if (($this->flags & API_NORMALIZE) && empty($validator->validateValue($value))) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return $this->setValueError(t('zapi', 'an array is expected'));
        }

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