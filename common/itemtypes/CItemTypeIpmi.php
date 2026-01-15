<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;

class CItemTypeIpmi extends CItemType
{

    /**
     * @inheritDoc
     */
    const TYPE = ITEM_TYPE_IPMI;

    /**
     * @inheritDoc
     */
    const FIELD_NAMES = ['interfaceid', 'ipmi_sensor', 'delay'];

    /**
     * @inheritDoc
     */
    public static function getCreateValidationRules(array $item): array
    {
        return [
            'interfaceid' => self::getCreateFieldRule('interfaceid', $item),
            'ipmi_sensor' => [MultipleValidator::class,
                'rules' => [
                    Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'ipmi_sensor'), 'when' => function ($model) {
                        return $model->key_ == 'ipmi.get';
                    }],
                'else' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'ipmi_sensor')]
            ],
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
            'ipmi_sensor' => [MultipleValidator::class,
                'rules' => [
                    Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'ipmi_sensor'), 'when' => function ($model) {
                        return $model->key_ == 'ipmi.get';
                    }],
                'else' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'ipmi_sensor')]
            ],
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
            'ipmi_sensor' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
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
            'ipmi_sensor' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'delay' => self::getUpdateFieldRuleDiscovered('delay')
        ];
    }
}
