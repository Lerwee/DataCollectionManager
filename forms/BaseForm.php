<?php

namespace app\customs\zapi\forms;

use app\common\base\BaseModel;
use app\customs\zapi\common\exceptions\ValidateException;

/**
 * Class BaseForm
 * @package app\customs\zapi\models
 */
class BaseForm extends BaseModel
{
    /**
     * 清理属性
     *
     * @param array|null $names
     * @param array $except
     */
    public function clearAttributes($names = null, $except = [])
    {
        $attributes = $this->getAttributes($names, $except);
        foreach ($attributes as $attribute => $value) {
            $this->{$attribute} = null;
        }
    }

    /**
     * @param int $errCode
     * @param string|null $errMsg
     * @throws ValidateException
     */
    protected static function exception(int $errCode, ?string $errMsg = null)
    {
        throw new ValidateException($errCode, $errMsg ?: t('zapi', 'Incorrect arguments passed to function.'));
    }
}
