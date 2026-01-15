<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;
use app\customs\zapi\common\validators\z\CHtmlUrlValidator;

/**
 * API_URL
 * Class UrlValidator
 * @package app\customs\zapi\common\validators
 */
class UrlValidator extends BaseZValidator
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
        $utfValidator = new Utf8StringValidator(['flags' => $this->flags & API_NOT_EMPTY, 'length' => $this->length]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }

        $options = [
            'allow_user_macro' => (bool) ($this->flags & API_ALLOW_USER_MACRO),
            'allow_event_tags_macro' => (bool) ($this->flags & API_ALLOW_EVENT_TAGS_MACRO)
        ];

        if ($value !== '' && CHtmlUrlValidator::validate($value, $options) === false) {
            return $this->setValueError(t('zapi', 'unacceptable URL'));
        }
        return null;
    }
}