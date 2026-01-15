<?php
namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CHostGroupNameParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_TG_NAME
 * Class TGNameValidator
 * @package app\customs\zapi\common\validators
 */
class TGNameValidator extends BaseZValidator
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

        $user_macro_parser = new CHostGroupNameParser();
        if ($user_macro_parser->parse($value) != CParser::PARSE_SUCCESS) {
            $error = t('zapi', 'Invalid parameter {parameter}, {error}', [
                'parameter' => implode('/', $this->getPath()),
                'error' => t('zapi', 'invalid template group name'),
            ]);
            return $this->setValueError($error);
        }

        return null;
    }
}
