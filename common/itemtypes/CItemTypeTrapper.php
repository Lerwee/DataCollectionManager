<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

class CItemTypeTrapper extends CItemType
{

    /**
     * @inheritDoc
     */
    const TYPE = ITEM_TYPE_TRAPPER;

    /**
     * @inheritDoc
     */
    const FIELD_NAMES = ['trapper_hosts'];

    /**
     * @inheritDoc
     */
    public static function getCreateValidationRules(array $item): array
    {
        return [
            'trapper_hosts' => self::getCreateFieldRule('trapper_hosts', $item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRules(array $db_item): array
    {
        return [
            'trapper_hosts' => self::getUpdateFieldRule('trapper_hosts', $db_item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRulesInherited(array $db_item): array
    {
        return [
            'trapper_hosts' => self::getUpdateFieldRuleInherited('trapper_hosts', $db_item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRulesDiscovered(): array
    {
        return [
            'trapper_hosts' => self::getUpdateFieldRuleDiscovered('trapper_hosts')
        ];
    }
}
