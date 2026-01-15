<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;

class CItemTypeJmx extends CItemType
{

    /**
     * @inheritDoc
     */
    const TYPE = ITEM_TYPE_JMX;

    /**
     * @inheritDoc
     */
    const FIELD_NAMES = ['interfaceid', 'jmx_endpoint', 'username', 'password', 'delay'];

    /**
     * @inheritDoc
     */
    public static function getCreateValidationRules(array $item): array
    {
        return [
            'interfaceid' => self::getCreateFieldRule('interfaceid', $item),
            'jmx_endpoint' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'jmx_endpoint'), 'default' => PRS_DEFAULT_JMX_ENDPOINT],
            'username' => self::getCreateFieldRule('username', $item),
            'password' => self::getCreateFieldRule('password', $item),
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
            'jmx_endpoint' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'jmx_endpoint')],
            'username' => self::getUpdateFieldRule('username', $db_item),
            'password' => self::getUpdateFieldRule('password', $db_item),
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
            'jmx_endpoint' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'jmx_endpoint')],
            'username' => self::getUpdateFieldRuleInherited('username', $db_item),
            'password' => self::getUpdateFieldRuleInherited('password', $db_item),
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
            'jmx_endpoint' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'username' => self::getUpdateFieldRuleDiscovered('username'),
            'password' => self::getUpdateFieldRuleDiscovered('password'),
            'delay' => self::getUpdateFieldRuleDiscovered('delay')
        ];
    }
}
