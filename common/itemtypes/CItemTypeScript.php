<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;

class CItemTypeScript extends CItemType
{

    /**
     * @inheritDoc
     */
    const TYPE = ITEM_TYPE_SCRIPT;

    /**
     * @inheritDoc
     */
    const FIELD_NAMES = ['parameters', 'params', 'timeout', 'delay'];

    /**
     * @inheritDoc
     */
    public static function getCreateValidationRules(array $item): array
    {
        return [
            'parameters' => [ObjectsValidator::class, 'flags' => API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
                'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_parameter', 'name')],
                'value' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('item_parameter', 'value')]
            ]],
            'params' => self::getCreateFieldRule('params', $item),
            'timeout' => self::getCreateFieldRule('timeout', $item),
            'delay' => self::getCreateFieldRule('delay', $item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRules(array $db_item): array
    {
        return [
            'parameters' => [ObjectsValidator::class, 'flags' => API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
                'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_parameter', 'name')],
                'value' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('item_parameter', 'value')]
            ]],
            'params' => self::getUpdateFieldRule('params', $db_item),
            'timeout' => self::getUpdateFieldRule('timeout', $db_item),
            'delay' => self::getUpdateFieldRule('delay', $db_item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRulesInherited(array $db_item): array
    {
        return [
            'parameters' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'params' => self::getUpdateFieldRuleInherited('params', $db_item),
            'timeout' => self::getUpdateFieldRuleInherited('timeout', $db_item),
            'delay' => self::getUpdateFieldRuleInherited('delay', $db_item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRulesDiscovered(): array
    {
        return [
            'parameters' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'params' => self::getUpdateFieldRuleDiscovered('params'),
            'timeout' => self::getUpdateFieldRuleDiscovered('timeout'),
            'delay' => self::getUpdateFieldRuleDiscovered('delay')
        ];
    }
}
