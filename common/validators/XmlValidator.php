<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\macros\CMacrosResolverGeneral;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * Class XmlValidator
 * @package app\customs\zapi\common\validators
 */
class XmlValidator extends BaseZValidator
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
        $utfValidator = new Utf8StringValidator(['flags' => $this->flags & API_NOT_EMPTY]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }
        if ($value === '') {
            return null;
        }
        if (is_numeric($this->length) && mb_strlen($value) > $this->length) {
            return $this->setValueError(t('zapi', 'value is too long'));
        }
        libxml_use_internal_errors(true);

        if (simplexml_load_string($value, null, LIBXML_IMPORT_FLAGS) === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            if ($errors) {
                $error = reset($errors);
                return $this->setValueError(t('zapi', '{message} [Line: {line} | Column: {column}]', ['message' => '('.$error->code.') '.trim($error->message), 'line' => $error->line, 'column' => $error->column]));
            }
            return $this->setValueError(t('zapi', 'XML is expected'));
        }
        return null;
    }
}