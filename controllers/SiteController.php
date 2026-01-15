<?php

namespace app\customs\zapi\controllers;

use app\common\base\BaseController;
use app\customs\zapi\services\SysInfoService;
use Yii;
use yii\web\Response;

class SiteController extends BaseController
{

    /**
     * 系统信息
     *
     * @return Response
     */
    public function actionInfo()
    {
        return $this->success(SysInfoService::instance()->delegate());
    }
}
