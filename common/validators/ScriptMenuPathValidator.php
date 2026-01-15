<?php
namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Model;

/**
 * API_SCRIPT_MENU_PATH
 * Class ScriptMenuPathValidator
 * @package app\customs\zapi\common\validators
 */
class ScriptMenuPathValidator extends BaseZValidator
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
        // Having only a root folder is the same as being empty. Temporary modify data to check if it is actually empty.
        $tmp_data = $value;
        if ($tmp_data === '/') {
            $tmp_data = '';
        }
        $utfValidator = new Utf8StringValidator(['flags' => $this->flags]);
        $result = $utfValidator->validateValue($tmp_data);
        if (!empty($result)) {
            return $result;
        }
        $value = (string)$value;
        if (is_numeric($this->length) && mb_strlen($value) > $this->length) {
            return $this->setValueError(t('zapi', 'value is too long'));
        }

        // If empty is allowed there is only root folder, return early.
        if ($value === '/') {
            return null;
        }

        $folders = splitPath($value);
        $folders = array_map('trim', $folders);
        $count = count($folders);

        // folder1/{empty}/name or folder1/folder2/{empty}
        foreach ($folders as $num => $folder) {
            // Allow the trailing slash.
            if ($folder === '' && $num != ($count - 1) && $num != 0) {
                return $this->setValueError(t('zapi', 'directory cannot be empty'));
            }
        }
        return null;
    }
}