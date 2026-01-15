<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\base\BaseZValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;

/**
 * API_HTTP_POST
 * Class HttpPostValidator
 * @package app\customs\zapi\common\validators
 */
class HttpPostValidator extends BaseZValidator
{
    /**
     * @var null|int
     */
    public $length = null;

    /**
     * @var null|int
     */
    public $nameLength;

    /**
     * @var null|int
     */
    public $valueLength;

    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        if (is_array($value)) {
            $rules =  [
                'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY],
                'value' => [Utf8StringValidator::class, 'flags' => API_REQUIRED]
            ];

            if ($this->nameLength) {
                $rules['name']['length'] = $this->nameLength;
            }

            if ($this->valueLength) {
                $rules['value']['length'] = $this->valueLength;
            }

            if (!ValidateHelper::validateObjects($value, $rules, [], $error)) {
                return $this->setValueError($error);
            }
        } else {
            $utfValidator = new Utf8StringValidator(['length' => $this->length]);
            $result = $utfValidator->validateValue($value);
            if (!empty($result)) {
                return $result;
            }
        }

        return null;
    }
}
