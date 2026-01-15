<?php

namespace app\customs\zapi\migrations;

use yii\db\Migration;
use app\common\components\console\Stdout;

/**
 * Handles the creation of table `{{%lw_perseus_notifies}}`.
 */
class m241030_022254_create_lw_perseus_notifies_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%perseus_notifies}}', true)) {
            Stdout::instance()->writeln("    > Table {{%perseus_notifies}} exists...");
            return;
        }

        $this->createTable('{{%perseus_notifies}}', [
            'id' => $this->primaryKey()->unsigned(),
            'userid' => $this->bigInteger()->notNull()->unsigned()->comment('用户ID'),
            'event_type' => $this->tinyInteger()->unsigned()->notNull()->defaultValue(10)->comment('通知类型 10-down 11-ip改变'),
            'media_type' => $this->tinyInteger()->unsigned()->notNull()->defaultValue(1)
                ->comment('媒介类型 1-email 2-sms 3-wechat 4-dingtalk'),
            'media_extra' => $this->string()->notNull()->defaultValue('')->comment('媒介扩展参数'),
            'status' => $this->tinyInteger()->unsigned()->notNull()->defaultValue(0)
                ->comment('通知状态 0-未发送 1-发送成功 2-发送失败'),
            'send_to' => $this->string()->notNull()->comment('通知账号'),
            'subject' => $this->string(512)->notNull()->defaultValue('')->comment('通知主题'),
            'message' => $this->string(1024)->notNull()->comment('通知内容'),
            'error' => $this->text()->comment('错误信息'),
            'created_at' => $this->integer()->unsigned()->notNull()->comment('通知产生时间'),
            'sended_at' => $this->integer()->unsigned()->notNull()->defaultValue(0)->comment('发送时间')
        ]);
        $this->createIndex('ik_lw_perseus_notifies_1', '{{%perseus_notifies}}', 'sended_at');
        $this->createIndex('ik_lw_perseus_notifies_2', '{{%perseus_notifies}}', ['status', 'sended_at']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%perseus_notifies}}');
    }
}
