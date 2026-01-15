<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;

class CItemTypeSnmp extends CItemType
{

    /**
     * @inheritDoc
     */
    const TYPE = ITEM_TYPE_SNMP;

    /**
     * @inheritDoc
     */
    const FIELD_NAMES = ['interfaceid', 'snmp_oid', 'delay'];

    /**
     * @inheritDoc
     */
    public static function getCreateValidationRules(array $item): array
    {
        return [
            'interfaceid' => self::getCreateFieldRule('interfaceid', $item),
            'snmp_oid' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'snmp_oid')],
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
            'snmp_oid' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'snmp_oid')],
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
            'snmp_oid' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
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
            'snmp_oid' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'delay' => self::getUpdateFieldRuleDiscovered('delay')
        ];
    }
}
