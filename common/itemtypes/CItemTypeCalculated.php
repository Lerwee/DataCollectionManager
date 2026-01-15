<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

class CItemTypeCalculated extends CItemType
{

    /**
     * @inheritDoc
     */
    const TYPE = ITEM_TYPE_CALCULATED;

    /**
     * @inheritDoc
     */
    const FIELD_NAMES = ['params', 'delay'];

    /**
     * @inheritDoc
     */
    public static function getCreateValidationRules(array $item): array
    {
        return [
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
            'params' => self::getUpdateFieldRuleDiscovered('params'),
            'delay' => self::getUpdateFieldRuleDiscovered('delay')
        ];
    }
}
