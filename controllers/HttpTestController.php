<?php

namespace app\customs\zapi\controllers;

use app\common\base\BaseController;
use app\customs\zapi\services\HttpTestService;
use Yii;
use yii\web\Response;

class HttpTestController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'delete',
    ];

    /**
     * create
     *
     * @return Response
     */
    public function actionCreate(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(HttpTestService::instance()->create($params, false));
    }

    /**
     * update
     *
     * @return Response
     */
    public function actionUpdate(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(HttpTestService::instance()->update($params, false));
    }

    /**
     * delete
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $ids = Yii::$app->request->post('group_httptestid', Yii::$app->request->post('httptestid', []));
        return $this->autoReturn(HttpTestService::instance()->delete($ids, false));
    }
}
