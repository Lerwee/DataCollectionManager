<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

class CItemTypePerseus extends CItemType
{

    /**
     * @inheritDoc
     */
    const TYPE = ITEM_TYPE_PERSEUS;

    /**
     * @inheritDoc
     */
    const FIELD_NAMES = ['interfaceid', 'delay'];

    /**
     * @inheritDoc
     */
    public static function getCreateValidationRules(array $item): array
    {
        return [
            'interfaceid' => self::getCreateFieldRule('interfaceid', $item),
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
            'delay' => self::getUpdateFieldRuleDiscovered('delay')
        ];
    }
}
