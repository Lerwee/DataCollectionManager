<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\helpers\CCuid;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_CUID
 * Class CuidValidator
 * @package app\customs\zapi\common\validators
 */
class CuidValidator extends BaseZValidator
{
    /**
     * @var null|int
     */
    public $length = null;

    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        $utfValidator = new Utf8StringValidator();
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }

        if (!CCuid::checkLength($value)) {
            return $this->setValueError(t('zapi', 'must be {length} characters long', ['length' => CCuid::LENGTH]));
        }

        if (!CCuid::isCuid($value)) {
            return $this->setValueError(t('zapi', 'CUID is expected'));
        }

        return null;
    }
}