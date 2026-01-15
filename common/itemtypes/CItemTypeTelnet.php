<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

class CItemTypeTelnet extends CItemType
{

    /**
     * @inheritDoc
     */
    const TYPE = ITEM_TYPE_TELNET;

    /**
     * @inheritDoc
     */
    const FIELD_NAMES = ['interfaceid', 'username', 'password', 'params', 'delay'];

    /**
     * @inheritDoc
     */
    public static function getCreateValidationRules(array $item): array
    {
        return [
            'interfaceid' => self::getCreateFieldRule('interfaceid', $item),
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
            'interfaceid' => self::getUpdateFieldRule('interfaceid', $db_item),
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
            'interfaceid' => self::getUpdateFieldRuleInherited('interfaceid', $db_item),
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
            'interfaceid' => self::getUpdateFieldRuleDiscovered('interfaceid'),
            'username' => self::getUpdateFieldRuleDiscovered('username'),
            'password' => self::getUpdateFieldRuleDiscovered('password'),
            'params' => self::getUpdateFieldRuleDiscovered('params'),
            'delay' => self::getUpdateFieldRuleDiscovered('delay')
        ];
    }
}
