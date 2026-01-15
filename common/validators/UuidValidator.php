<?php
namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Model;

/**
 * API_UUID
 * Class UuidValidator
 * @package app\customs\zapi\common\validators
 */
class UuidValidator extends BaseZValidator
{
    /**
     * @var null|int
     */
    public $length = null;

    /**
     * @param $model
     * @param $attribute
     * @return array|null
     */
    public function validateModelValue($model, $attribute): ?array
    {
        $value = $model->$attribute;
        $utfValidator = new Utf8StringValidator(['flags' => API_NOT_EMPTY]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }

        if (mb_strlen($value) != 32) {
            return $this->setValueError(t('zapi', 'must be {len} characters long', ['len' => 32]));
        }

        if (!ctype_xdigit($value)) {
            return $this->setValueError(t('zapi', 'UUIDv4 is expected'));
        }

        $binary = hex2bin($value);
        if ((ord($binary[6]) & 0xf0) != 0x40 || (ord($binary[8]) & 0xc0) != 0x80) {
            return $this->setValueError(t('zapi', 'UUIDv4 is expected'));
        }
        $model->{$attribute} = strtolower($value);
        return null;
    }
}