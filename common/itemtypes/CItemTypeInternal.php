<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

class CItemTypeInternal extends CItemType
{

    /**
     * @inheritDoc
     */
    const TYPE = ITEM_TYPE_INTERNAL;

    /**
     * @inheritDoc
     */
    const FIELD_NAMES = ['delay'];

    /**
     * @inheritDoc
     */
    public static function getCreateValidationRules(array $item): array
    {
        return [
            'delay' => self::getCreateFieldRule('delay', $item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRules(array $db_item): array
    {
        return [
            'delay' => self::getUpdateFieldRule('delay', $db_item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRulesInherited(array $db_item): array
    {
        return [
            'delay' => self::getUpdateFieldRuleInherited('delay', $db_item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRulesDiscovered(): array
    {
        return [
            'delay' => self::getUpdateFieldRuleDiscovered('delay')
        ];
    }
}
