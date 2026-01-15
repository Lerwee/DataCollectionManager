<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;

class CItemTypeSsh extends CItemType
{

    /**
     * @inheritDoc
     */
    const TYPE = ITEM_TYPE_SSH;

    /**
     * @inheritDoc
     */
    const FIELD_NAMES = ['interfaceid', 'authtype', 'username', 'publickey', 'privatekey', 'password', 'params',
        'delay'
    ];

    /**
     * @inheritDoc
     */
    public static function getCreateValidationRules(array $item): array
    {
        return [
            'interfaceid' => self::getCreateFieldRule('interfaceid', $item),
            'authtype' => self::getCreateFieldRule('authtype', $item),
            'username' => self::getCreateFieldRule('username', $item),
            'publickey' => [MultipleValidator::class,
                'rules' => [
                    Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'publickey'), 'when' => function ($model) {
                        return $model->authtype == ITEM_AUTHTYPE_PUBLICKEY;
                    }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'publickey')]
            ],
            'privatekey' => [MultipleValidator::class,
                'rules' => [
                    Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'privatekey'), 'when' => function ($model) {
                        return $model->authtype == ITEM_AUTHTYPE_PUBLICKEY;
                    }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'privatekey')]
            ],
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
            'authtype' => self::getUpdateFieldRule('authtype', $db_item),
            'username' => self::getUpdateFieldRule('username', $db_item),
            'publickey' => [MultipleValidator::class, 'rules' => [
                Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'publickey'), 'when' => function ($model) {
                    return $model->authtype == ITEM_AUTHTYPE_PUBLICKEY;
                }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'publickey')]
            ],
            'privatekey' => [MultipleValidator::class, 'rules' => [
                Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'privatekey'), 'when' => function ($model) {
                    return $model->authtype == ITEM_AUTHTYPE_PUBLICKEY;
                }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'privatekey')]
            ],
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
            'authtype' => self::getUpdateFieldRuleInherited('authtype', $db_item),
            'username' => self::getUpdateFieldRuleInherited('username', $db_item),
            'publickey' => [MultipleValidator::class, 'rules' => [
                Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'publickey'), 'when' => function ($model) {
                    return $model->authtype == ITEM_AUTHTYPE_PUBLICKEY;
                }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'publickey')]
            ],
            'privatekey' => [MultipleValidator::class, 'rules' => [
                Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'privatekey'), 'when' => function ($model) {
                    return $model->authtype == ITEM_AUTHTYPE_PUBLICKEY;
                }],
                'else' => [Utf8StringValidator::class, 'in' => DB::getDefault('items', 'privatekey')]
            ],
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
            'authtype' => self::getUpdateFieldRuleDiscovered('authtype'),
            'username' => self::getUpdateFieldRuleDiscovered('username'),
            'publickey' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'privatekey' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'password' => self::getUpdateFieldRuleDiscovered('password'),
            'params' => self::getUpdateFieldRuleDiscovered('params'),
            'delay' => self::getUpdateFieldRuleDiscovered('delay')
        ];
    }
}
