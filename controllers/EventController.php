<?php

namespace app\customs\zapi\controllers;

use Yii;
use yii\web\Response;
use app\common\base\BaseController;
use app\customs\zapi\services\EventService;

/**
 * Class EventController
 * @package app\customs\zapi\controllers
 */
class EventController extends BaseController
{
    protected $restfulActions = [
        'acknowledge',
    ];

    /**
     * @return Response
     */
    public function actionGet(): Response
    {
        $params = Yii::$app->request->get();
        $result = EventService::instance()->getEvent($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionAcknowledge(): Response
    {
        $params = Yii::$app->request->post();
        $result = EventService::instance()->updateAcknowledge($params);
        return $this->autoReturn($result);
    }
}
