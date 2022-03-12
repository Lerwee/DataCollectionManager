<?php

namespace app\customs\zabbix\controllers;

use yii\web\Response;

class HostsController extends Controller
{
    /**
     * 主机管理
     * @return Response
     */
    public function actionHosts()
    {
        return $this->renderNormal();
    }
}
