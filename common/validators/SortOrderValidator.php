<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_SORTORDER
 * Class SortOrderValidator
 * @package app\customs\zapi\common\validators
 */
class SortOrderValidator extends BaseZValidator
{
    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        $utfValidator = new Utf8StringValidator(['in' => [PRS_SORT_UP, PRS_SORT_DOWN]]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            if (is_string($value)) {
                return $result;
            }

            if (!is_array($value)) {
                return $this->setValueError(t('zapi', 'an array or a character string is expected'));
            }

            $data = array_values($value);
            foreach ($data as $index => $valueItem) {
                $result = $utfValidator->validateValue($valueItem);
                if (!empty($result)) {
                    return $result;
                }
            }
        }
        return null;
    }
}