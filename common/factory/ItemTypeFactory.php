<?php

namespace app\customs\zapi\common\factory;

use app\customs\zapi\common\itemtypes\CItemType;
use app\customs\zapi\common\itemtypes\CItemTypeCalculated;
use app\customs\zapi\common\itemtypes\CItemTypeDbMonitor;
use app\customs\zapi\common\itemtypes\CItemTypeDependent;
use app\customs\zapi\common\itemtypes\CItemTypeExternal;
use app\customs\zapi\common\itemtypes\CItemTypeHttpAgent;
use app\customs\zapi\common\itemtypes\CItemTypeInternal;
use app\customs\zapi\common\itemtypes\CItemTypeIpmi;
use app\customs\zapi\common\itemtypes\CItemTypeJmx;
use app\customs\zapi\common\itemtypes\CItemTypeScript;
use app\customs\zapi\common\itemtypes\CItemTypeSimple;
use app\customs\zapi\common\itemtypes\CItemTypeSnmp;
use app\customs\zapi\common\itemtypes\CItemTypeSnmpTrap;
use app\customs\zapi\common\itemtypes\CItemTypeSsh;
use app\customs\zapi\common\itemtypes\CItemTypeTelnet;
use app\customs\zapi\common\itemtypes\CItemTypeTrapper;
use app\customs\zapi\common\itemtypes\CItemTypePerseus;
use app\customs\zapi\common\itemtypes\CItemTypePerseusActive;
use yii\base\NotSupportedException;
use yii\base\StaticInstanceTrait;

/**
 * Class ItemTypeFactory
 * @package app\customs\zapi\common\helpers
 */
class ItemTypeFactory
{
    use StaticInstanceTrait;

    /**
     * An array of created object instances.
     *
     * @param array
     */
    private static $instances = [];

    /**
     * @param int $type
     * @return CItemType
     * @throws NotSupportedException
     */
    public static function getObject(int $type): CItemType
    {
        if (array_key_exists($type, self::$instances)) {
            return self::$instances[$type];
        }

        switch ($type) {
            case ITEM_TYPE_PERSEUS:
                return self::$instances[$type] = new CItemTypePerseus();

            case ITEM_TYPE_TRAPPER:
                return self::$instances[$type] = new CItemTypeTrapper();

            case ITEM_TYPE_SIMPLE:
                return self::$instances[$type] = new CItemTypeSimple();

            case ITEM_TYPE_INTERNAL:
                return self::$instances[$type] = new CItemTypeInternal();

            case ITEM_TYPE_PERSEUS_ACTIVE:
                return self::$instances[$type] = new CItemTypePerseusActive();

            case ITEM_TYPE_EXTERNAL:
                return self::$instances[$type] = new CItemTypeExternal();

            case ITEM_TYPE_DB_MONITOR:
                return self::$instances[$type] = new CItemTypeDbMonitor();

            case ITEM_TYPE_IPMI:
                return self::$instances[$type] = new CItemTypeIpmi();

            case ITEM_TYPE_SSH:
                return self::$instances[$type] = new CItemTypeSsh();

            case ITEM_TYPE_TELNET:
                return self::$instances[$type] = new CItemTypeTelnet();

            case ITEM_TYPE_CALCULATED:
                return self::$instances[$type] = new CItemTypeCalculated();

            case ITEM_TYPE_JMX:
                return self::$instances[$type] = new CItemTypeJmx();

            case ITEM_TYPE_SNMPTRAP:
                return self::$instances[$type] = new CItemTypeSnmpTrap();

            case ITEM_TYPE_DEPENDENT:
                return self::$instances[$type] = new CItemTypeDependent();

            case ITEM_TYPE_HTTPAGENT:
                return self::$instances[$type] = new CItemTypeHttpAgent();

            case ITEM_TYPE_SNMP:
                return self::$instances[$type] = new CItemTypeSnmp();

            case ITEM_TYPE_SCRIPT:
                return self::$instances[$type] = new CItemTypeScript();
        }

        throw new NotSupportedException('Incorrect item type.');
    }
}