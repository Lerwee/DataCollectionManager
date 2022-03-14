<?php

namespace app\customs\zabbix\services\actions;

use yii\base\Action;

class ListAction extends Action
{
    /**
     * run action
     *
     * 适用控制器
     * - `ProxyController` - 代理管理 `proxy.list`
     * - `ScriptController` - 脚本管理 script.list``
     */
    public function run()
    {
        $_GET['action'] = "{$this->controller->id}.{$this->id}";
        $_GET['fullscreen'] = 1;
        return $this->controller->render('/common/normal', [
            'file' => 'zabbix'
        ]);
    }
}
