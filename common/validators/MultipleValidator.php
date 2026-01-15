<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\base\NotSupportedException;
use yii\validators\Validator;

/**
 * API_MULTIPLE
 * rules:
 * [
 *      'delay',
 *       MultipleValidator::class,
 *      'rules' =>  ['validator' => NumericValidator::class, 'when' => function($model) {return true;}],
 *      'else' => ['validator' => 'default', 'value' => 110]
 * ],
 * Class DnsValidator
 * @package app\customs\zapi\common\validators
 */
class MultipleValidator extends BaseZValidator
{
    /**
     * [
     *     ['validator' => NumericValidator::class, 'when' => function($model) {return false}],
     * ]
     * or:
     * ['validator' => NumericValidator::class, 'when' => function($model) {return false}]
     * or:
     * [NumericValidator::class, 'when' => function($model) {return false}]
     * @var array
     */
    public $rules = [];

    /**
     * ['validator' => 'default', 'value' => 10]
     * @var array
     */
    public $else = [];

    /**
     * @param Model $model
     * @param string $attribute
     * @return bool
     * @throws InvalidConfigException|NotSupportedException
     */
    public function validateAttribute($model, $attribute): bool
    {
        $hadChecked = false;
        if (isset($this->rules['when'])) {
            $this->rules = [$this->rules];
        }
        foreach ($this->rules as $rule) {
            if (empty($rule['validator']) && !empty($rule[0])) {
                $rule['validator'] = $rule[0];
                unset($rule[0]);
            }
            if (empty($rule['validator']) || (empty($rule['when']) || !($rule['when'] instanceof \Closure))) {
                throw new InvalidConfigException('Invalid validation rule: a rule must specify both attribute names and validator type.');
            }
            if ($rule['when']($model)) {
                $hadChecked = true;
                $params = array_diff_key($rule, array_flip(['validator', 'when']));
                $validator = Validator::createValidator($rule['validator'], $model, $attribute, $params);
                if ($validator instanceof BaseZValidator) {
                    $validator->setPath($this->getPath());
                }
                $validator->validateAttribute($model, $attribute);
                if ($model->hasErrors($attribute)) {
                    return false;
                }
                break;
            }
        }
        if (!$hadChecked) {
            if (empty($this->else['validator']) && !empty($this->else[0])) {
                $this->else['validator'] = $this->else[0];
                unset($this->else[0]);
            }
            if (empty($this->else['validator'])) {
                throw new InvalidConfigException('Invalid validation rule: a rule must specify both attribute names and validator type.');
            }
            $params = array_diff_key($this->else, array_flip(['validator', 'unset']));
            $validator = Validator::createValidator($this->else['validator'], $model, $attribute, $params);
            if ($validator instanceof BaseZValidator) {
                $validator->setPath($this->getPath());
            }
            $validator->validateAttribute($model, $attribute);
            if ($model->hasErrors($attribute)) {
                return false;
            }
            if (!empty($this->else['unset'])) {
                unset($model->{$attribute});
            }
        }
        return true;
    }
}