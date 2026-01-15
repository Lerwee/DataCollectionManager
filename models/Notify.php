<?php

namespace app\customs\zapi\models;

use app\customs\zapi\models\db\LwNotify;
use app\customs\zapi\common\helpers\NotifyHelper;
use app\modules\auth\models\User;

/**
 * @property-read User $user
 */
class Notify extends LwNotify
{
    /**
     * 未发送
     */
    const STATUS_SEND_WAIT = 0;

    /**
     * 发送成功
     */
    const STATUS_SEND_OK = 1;

    /**
     * 发送失败
     */
    const STATUS_SEND_FAIL = 2;

    /**
     * unavailable
     */
    const EVENT_TYPE_UNAVAILABLE = 10;

    /**
     * IP Change
     */
    const EVENT_TYPE_IP_CHANGE = 11;

    /**
     * Query with [[User]]
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'userid']);
    }

    /**
     * batch insert
     *
     * @param  array $rows
     * @return bool
     */
    public static function batchInsert(array $rows)
    {
        $r = static::getDb()->createCommand()
            ->batchInsert(static::tableName(), array_keys(current($rows)), $rows)
            ->execute();
        
        NotifyHelper::resetNotifyPrimaryIncrement();
        return $r;
    }

    /**
     * 返回媒介扩展信息
     *
     * @return array
     */
    public function getMediaExtra()
    {
        return $this->media_extra ? json_decode($this->media_extra, true) : [];
    }
}
