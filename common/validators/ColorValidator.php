<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_COLOR
 * Class ColorValidator
 * @package app\customs\zapi\common\validators
 */
class ColorValidator extends BaseZValidator
{
    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        $flags = $this->flags ?: 0x00;

		if (($flags & API_ALLOW_NULL) && $value === null) {
			return null;
		}
        
        if (($this->flags & API_NOT_EMPTY)) {
            $utfValidator = new Utf8StringValidator();
            $result = $utfValidator->validateValue($value);
            if ($result !== null) {
                return $result;
            }
        }


		if (($flags & API_NOT_EMPTY) == 0 && $value === '') {
			return null;
		}

		if (preg_match('/^[0-9a-f]{6}$/i', $value) !== 1) {
            return [t('zapi', 'Invalid parameter {attribute}, {error}'), ['attribute' => $this->getPath(), 'error' => t('zapi', 'a hexadecimal color code (6 symbols) is expected')], []];
		}

		return null;
    }
}