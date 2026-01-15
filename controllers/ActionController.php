<?php

namespace app\customs\zapi\controllers;

use app\customs\zapi\services\ActionService;
use Yii;
use app\common\base\BaseController;
use yii\web\Response;

/**
 * Class ActionController
 * @package app\customs\zapi\controllers
 */
class ActionController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'delete',
    ];

    /**
     * @return Response
     */
    public function actionGet(): Response
    {
        $params = Yii::$app->request->get();
        $result = ActionService::instance()->getAction($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionCreate(): Response
    {
        $params = Yii::$app->request->post();
        $result = ActionService::instance()->createAction($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionUpdate(): Response
    {
        $params = Yii::$app->request->post();
        $result = ActionService::instance()->updateAction($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionDelete(): Response
    {
        $params = Yii::$app->request->post();
        $result = ActionService::instance()->deleteAction($params);
        return $this->autoReturn($result);
    }

}
