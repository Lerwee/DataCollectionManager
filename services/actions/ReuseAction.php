<?php

namespace app\customs\zabbix\services\actions;

use yii\base\Action;

class ReuseAction extends Action
{
    /**
     * run action
     */
    public function run()
    {
        return $this->controller->render('/common/normal', [
            'file' => $this->id
        ]);
    }
}
