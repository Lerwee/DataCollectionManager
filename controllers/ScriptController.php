<?php

namespace app\customs\zabbix\controllers;

class ScriptController extends Controller
{
    /**
     * 脚本管理
     * @return Response
     */
    public function actionList()
    {
        $_GET['action'] = "{$this->id}.{$this->action->id}";
        $_GET['fullscreen'] = 1;
        return $this->renderNormal(['file' => 'zabbix']);
    }
}
