<?php
namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CUserMacroParser;
use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Model;

/**
 * API_USER_MACRO
 * Class UserMacroValidator
 * @package app\customs\zapi\common\validators
 */
class UserMacroValidator extends BaseZValidator
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

        $user_macro_parser = new CUserMacroParser();
        if ($user_macro_parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError($user_macro_parser->getError());
        }

        return null;
    }
}