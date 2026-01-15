<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;


/**
 * API_OUTPUT
 * Class OutputValidator
 * @package app\customs\zapi\common\validators
 */
class OutputValidator extends BaseZValidator
{
    /**
     * @var array
     */
    public $in = [];

    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        if (($this->flags & API_NOT_EMPTY) == 0 && $value === null) {
            return null;
        }

        if (is_array($value)) {
            $utfValidator = new Utf8StringsValidator(['in' => $this->in, 'uniq' => true]);
            $result = $utfValidator->validateValue($value);
            if (!empty($result)) {
                return $result;
            }
            
            return null;
        }

        if (is_string($value)) {
			$in = ($this->flags & API_ALLOW_COUNT) ? [API_OUTPUT_EXTEND, API_OUTPUT_COUNT] : [API_OUTPUT_EXTEND];

            $utfValidator = new Utf8StringValidator(['in' => $in]);
            $result = $utfValidator->validateValue($value);
            if (!empty($result)) {
                return $result;
            }
            
            return null;
		}
 
        return $this->setValueError(t('zapi', 'Invalid parameter "{param}": {error}.'), [
            'param' => implode('/', $this->_path),
            'error' => t('zapi', 'an array or a character string is expected'),
        ]);
    }
}
