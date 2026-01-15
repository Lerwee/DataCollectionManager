<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\helpers\ValueCheckHelper;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_TIMESTAMP
 * Class TimestampValidator
 * @package app\customs\zapi\common\validators
 */
class TimestampValidator extends BaseZValidator
{
    /**
     * @var int
     */
    protected $inType = self::IN_TYPE_NUMBER;

    /**
     * .e.g
     * [1, [1, 10]]
     * @var array
     */
    public $in = [];

    /**
     * ['operator' => '>', 'field' => 'active_since']
     * @var array
     */
    public $compare = [];

    /**
     * @var null
     */
    public $format = 'Y-m-d H:i:s';

    /**
     * @var null
     */
    public $timezone = null;

    /**
     * @param $model
     * @param $attribute
     * @return array|null
     */
    public function validateModelValue($model, $attribute): ?array
    {
        $value = $model->$attribute;
        if (($this->flags & API_ALLOW_NULL) && $value === null) {
            return null;
        }

        if (!is_scalar($value) || is_bool($value) || is_double($value) || !ctype_digit(strval($value))) {
            return $this->setValueError(t('zapi', 'an unsigned integer is expected'));
        }

        if (bccomp($value, PRS_MAX_DATE) > 0) {
            return $this->setValueError(t('zapi', 'a timestamp is too large'));
        }

        if (!ValueCheckHelper::checkTimestampIn($value, $this->in, $this->format, $this->timezone, $error)) {
            return $this->setValueError($error);
        }

        if (!empty($this->compare) && !empty($this->compare['field']) && property_exists($model, $this->compare['field'])) {
            $compare = [
                'operator' => $this->compare['operator'] ?? '',
                'value' => $model->{$this->compare['field']}
            ];
            if (!ValueCheckHelper::checkCompare($value, $compare, $error)) {
                return $this->setValueError($error);
            }
        }

        if (is_string($value)) {
            $value = (int)$value;
        }
        $model->$attribute = $value;

        return null;
    }
}