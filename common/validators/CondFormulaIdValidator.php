<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CExpressionParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\validators\base\BaseZValidator;
use app\customs\zapi\common\validators\z\CExpressionValidator;

/**
 * Class CondFormulaIdValidator
 * @package app\customs\zapi\common\validators
 */
class CondFormulaIdValidator extends BaseZValidator
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
        $utfValidator = new Utf8StringValidator(['flags' => API_NOT_EMPTY, 'length' => $this->length]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }

        if ($this->length !== null && mb_strlen($value) > $this->length) {
            return [t('zapi', 'Invalid parameter {attribute}, {error}'), ['attribute' => $this->getPath(), 'error' => t('zapi', 'value is too long')]];
        }

        if (preg_match('/^[A-Z]+$/', $value) !== 1) {
            return [t('zapi', 'Invalid parameter {attribute}, {error}'), ['attribute' => $this->getPath(), 'error' => t('zapi', 'uppercase identifier expected')]];
        }

        return null;
    }
}
