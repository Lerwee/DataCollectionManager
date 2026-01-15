<?php

namespace app\customs\zapi\controllers;

use app\common\base\BaseController;
use app\customs\zapi\services\QueueService;
use Yii;
use yii\web\Response;

/**
 * Class QueueController
 * @package app\customs\zapi\controllers
 */
class QueueController extends BaseController
{
    /**
     * @return Response
     */
    public function actionOverview(): Response
    {
        $params = Yii::$app->request->get();
        $result = QueueService::instance()->getOverview($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionDetail(): Response
    {
        $params = Yii::$app->request->get();
        $result = QueueService::instance()->getDetail($params);
        return $this->autoReturn($result);
    }
}
