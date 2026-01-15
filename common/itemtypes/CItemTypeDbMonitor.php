<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

class CItemTypeDbMonitor extends CItemType
{

    /**
     * @inheritDoc
     */
    const TYPE = ITEM_TYPE_DB_MONITOR;

    /**
     * @inheritDoc
     */
    const FIELD_NAMES = ['username', 'password', 'params', 'delay'];

    /**
     * @inheritDoc
     */
    public static function getCreateValidationRules(array $item): array
    {
        return [
            'username' => self::getCreateFieldRule('username', $item),
            'password' => self::getCreateFieldRule('password', $item),
            'params' => self::getCreateFieldRule('params', $item),
            'delay' => self::getCreateFieldRule('delay', $item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRules(array $db_item): array
    {
        return [
            'username' => self::getUpdateFieldRule('username', $db_item),
            'password' => self::getUpdateFieldRule('password', $db_item),
            'params' => self::getUpdateFieldRule('params', $db_item),
            'delay' => self::getUpdateFieldRule('delay', $db_item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRulesInherited(array $db_item): array
    {
        return [
            'username' => self::getUpdateFieldRuleInherited('username', $db_item),
            'password' => self::getUpdateFieldRuleInherited('password', $db_item),
            'params' => self::getUpdateFieldRuleInherited('params', $db_item),
            'delay' => self::getUpdateFieldRuleInherited('delay', $db_item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRulesDiscovered(): array
    {
        return [
            'username' => self::getUpdateFieldRuleDiscovered('username'),
            'password' => self::getUpdateFieldRuleDiscovered('password'),
            'params' => self::getUpdateFieldRuleDiscovered('params'),
            'delay' => self::getUpdateFieldRuleDiscovered('delay')
        ];
    }
}
