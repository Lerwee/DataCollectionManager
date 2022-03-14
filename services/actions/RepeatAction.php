<?php

namespace app\customs\zabbix\services\actions;

use yii\base\Action;

class RepeatAction extends Action
{
    /**
     * run action
     *
     * 适用控制器
     * - `ActionconfController`
     * - `DiscoveryconfController`
     * - `HostsController`
     * - `TemplatesController`
     */
    public function run()
    {
        return $this->controller->render('/common/normal', [
            'file' => $this->controller->id
        ]);
    }
}
