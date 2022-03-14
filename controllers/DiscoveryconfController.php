<?php

namespace app\customs\zabbix\controllers;

use Yii;
use yii\web\Response;

class DiscoveryconfController extends Controller
{
    /**
     * @return Response
     */
    public function actionDiscoveryconf()
    {
        return $this->renderNormal(['file' => $this->id]);
    }
}
