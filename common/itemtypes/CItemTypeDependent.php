<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;

class CItemTypeDependent extends CItemType
{

    /**
     * @inheritDoc
     */
    const TYPE = ITEM_TYPE_DEPENDENT;

    /**
     * @inheritDoc
     */
    const FIELD_NAMES = ['master_itemid'];

    /**
     * @inheritDoc
     */
    public static function getCreateValidationRules(array $item): array
    {
        return [
            'master_itemid' => [IdValidator::class, 'flags' => API_REQUIRED]
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRules(array $db_item): array
    {
        return [
            'master_itemid' => [IdValidator::class]
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRulesInherited(array $db_item): array
    {
        return [
            'master_itemid' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED]
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRulesDiscovered(): array
    {
        return [
            'master_itemid' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED]
        ];
    }
}
