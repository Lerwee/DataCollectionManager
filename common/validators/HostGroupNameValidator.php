<?php
namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CHostGroupNameParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\validators\base\BaseZValidator;
use yii\base\Model;

/**
 * API_HG_NAME
 * Class HostGroupNameValidator
 * @package app\customs\zapi\common\validators
 */
class HostGroupNameValidator extends BaseZValidator
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

        $host_group_name_parser = new CHostGroupNameParser(['lldmacros' => ($this->flags & API_REQUIRED_LLD_MACRO)]);

        if ($host_group_name_parser->parse($value) != CParser::PARSE_SUCCESS) {
            return $this->setValueError(t('zapi', 'invalid host group name'));
        }

        if (($this->flags & API_REQUIRED_LLD_MACRO) && !$host_group_name_parser->getMacros()) {
            return $this->setValueError(t('zapi', 'must contain at least one low-level discovery macro'));
        }
        return null;
    }
}