<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;
use app\customs\zapi\common\validators\z\CEventNameValidator;

/**
 * API_EVENT_NAME
 * Class EventNameValidator
 * @package app\customs\zapi\common\validators
 */
class EventNameValidator extends BaseZValidator
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
        $utfValidator = new Utf8StringValidator(['flags' => 0, 'length' => $this->length]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }

        $eventname_validator = new CEventNameValidator();

        if (!$eventname_validator->validate($value)) {
            return $this->setValueError(t('zapi', $eventname_validator->getError()));
        }
        return null;
    }
}