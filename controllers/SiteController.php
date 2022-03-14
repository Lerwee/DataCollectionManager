<?php

namespace app\customs\zabbix\controllers;

use app\customs\zabbix\services\SysinfoService;
use yii\web\Response;

class SiteController extends Controller
{
    /**
     * {@inheritDoc}
     */
    public function actions()
    {
        $actions = parent::actions();
        if (isset($actions[$this->id])) {
            unset($actions[$this->id]);
        }
        return $actions;
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
