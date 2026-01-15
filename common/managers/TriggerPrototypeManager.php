<?php

namespace app\customs\zapi\common\managers;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\managers\base\BaseManager;
use app\modules\libzbx\models\zbx\Functions;
use app\modules\libzbx\models\zbx\TriggerDepends;
use app\modules\libzbx\models\zbx\TriggerDiscovery;
use app\modules\libzbx\models\zbx\Triggers;
use app\modules\libzbx\models\zbx\TriggerTag;
use yii\db\Query;

/**
 * 触发器原型
 */
class TriggerPrototypeManager extends BaseManager
{
    /**
     * 删除触发器原型
     * @param array $triggerIds 触发器原型ID
     * @return void
     */
    public static function delete(array $triggerIds)
    {
        // 查询所有继承的触发器原型
        $delTriggerIds = self::getInheritedOrDependentIds($triggerIds, Triggers::tableName(), 'templateid', 'triggerid');

        $table = Triggers::tableName();
        $triggerIdWhere = SqlHelper::whereIn('triggerid', $delTriggerIds);
        // Lock trigger prototypes before delete to prevent server from adding new LLD elements.
        $sql = "SELECT NULL FROM {$table} WHERE {$triggerIdWhere} FOR UPDATE";
        Triggers::getDb()->createCommand($sql)->execute();

        // 删除发现触发器
        $query = new Query();
        $query->from(TriggerDiscovery::tableName());
        $query->where(str_replace('triggerid', 'parent_triggerid', $triggerIdWhere));
        $query->select(['triggerid']);

        if ($discoveryTriggerIds = $query->column()) {
            TriggerManager::delete($discoveryTriggerIds);
        }

        Functions::deleteAll($triggerIdWhere);
        TriggerDepends::deleteAll(str_replace('triggerid', 'triggerid_down', $triggerIdWhere));
        TriggerDepends::deleteAll(str_replace('triggerid', 'triggerid_up', $triggerIdWhere));
        TriggerTag::deleteAll($triggerIdWhere);
        Triggers::updateAll(['templateid' => null], $triggerIdWhere);
        Triggers::deleteAll($triggerIdWhere);
    }
}
