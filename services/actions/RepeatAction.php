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
        if ($this->controller->hasProperty('getParams') && ($getParams = $this->controller->getParams)) {
            $getParams = array_merge($getParams, $_GET);
            foreach ($getParams as $property => $value) {
                $_GET[$property] = $value;
            }
        }
        return $this->controller->render('/common/normal', [
            'file' => $this->controller->id
        ]);
    }
}
