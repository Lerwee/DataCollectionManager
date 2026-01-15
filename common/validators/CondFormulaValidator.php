<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CConditionFormula;
use app\customs\zapi\common\parsers\CExpressionParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\validators\base\BaseZValidator;
use app\customs\zapi\common\validators\z\CExpressionValidator;

/**
 * Class CondFormulaValidator
 * @package app\customs\zapi\common\validators
 */
class CondFormulaValidator extends BaseZValidator
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

        $condition_formula_parser = new CConditionFormula();
        if (!$condition_formula_parser->parse($value)) {
            return [t('zapi', 'Invalid parameter {attribute}, {error}'), ['attribute' => $this->getPath(), 'error' =>$condition_formula_parser->error]];

        }
        return null;
    }
}
