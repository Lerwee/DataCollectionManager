<?php

namespace app\customs\zapi\models\db;

use Yii;

/**
 * This is the model class for table "{{%perseus_notifies}}".
 *
 * @property int $id
 * @property int $userid 用户ID
 * @property int $event_type 通知类型 10-down 11-ip改变
 * @property int $media_type 媒介类型 1-email 2-sms 3-wechat 4-dingtalk
 * @property string $media_extra 媒介扩展参数
 * @property int $status 通知状态 0-未发送 1-发送成功 2-发送失败
 * @property string $send_to 通知账号
 * @property string $subject 通知主题
 * @property string $message 通知内容
 * @property string|null $error 错误信息
 * @property int $created_at 通知产生时间
 * @property int $sended_at 发送时间
 */
class LwNotify extends \app\common\base\BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%perseus_notifies}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['userid', 'send_to', 'message', 'created_at'], 'required'],
            [['userid', 'event_type', 'media_type', 'status', 'created_at', 'sended_at'], 'integer'],
            [['error'], 'string'],
            [['media_extra', 'send_to'], 'string', 'max' => 255],
            [['subject'], 'string', 'max' => 512],
            [['message'], 'string', 'max' => 1024],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('zapi', 'ID'),
            'userid' => Yii::t('zapi', 'Userid'),
            'event_type' => Yii::t('zapi', 'Event Type'),
            'media_type' => Yii::t('zapi', 'Media Type'),
            'media_extra' => Yii::t('zapi', 'Media Extra'),
            'status' => Yii::t('zapi', 'Status'),
            'send_to' => Yii::t('zapi', 'Send To'),
            'subject' => Yii::t('zapi', 'Subject'),
            'message' => Yii::t('zapi', 'Message'),
            'error' => Yii::t('zapi', 'Error'),
            'created_at' => Yii::t('zapi', 'Created At'),
            'sended_at' => Yii::t('zapi', 'Sended At'),
        ];
    }
}
