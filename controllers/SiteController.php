<?php

namespace app\customs\zabbix\controllers;

use app\customs\zabbix\services\SysinfoService;
use yii\web\Response;

class SiteController extends Controller
{
    /**
     * @return Response
     */
    public function actionIndex()
    {
    }

    /**
     * 系统信息
     *
     * @return Response
     */
    public function actionSysinfo()
    {
        return $this->success(SysinfoService::instance()->delegate());
    }
}
