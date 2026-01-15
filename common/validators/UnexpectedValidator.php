<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * Class UnexpectedValidator
 * @package app\customs\zapi\common\validators
 */
class UnexpectedValidator extends BaseZValidator
{
    public $error_type = null;

    /**
     * @param $model
     * @param $attribute
     * @return array|null
     */
    public function validateModelValue($model, $attribute): ?array
    {
        if (!property_exists($model, $attribute)) {
            return null;
        }
        if (!$this->error_type) {
            return $this->setValueError(t('zapi', 'unexpected parameter "{parameter}"', ['parameter' => $attribute]));
        }

        switch ($this->error_type) {
            case API_ERR_INHERITED:
                $error = t('zapi', 'cannot update readonly parameter "{parameter}" of inherited object', ['parameter' => $attribute]);
                break;

            case API_ERR_DISCOVERED:
                $error = t('zapi', 'cannot update readonly parameter "{parameter}" of discovered object', ['parameter' => $attribute]);
                break;

            default:
                $error = t('zapi', 'Incorrect validation rules.');
        }

        return $this->setValueError($error);
    }
}