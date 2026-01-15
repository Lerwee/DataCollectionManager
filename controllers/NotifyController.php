<?php

namespace app\customs\zapi\controllers;

use app\customs\zapi\services\NotifyService;
use Yii;
use yii\web\Response;

/**
 * This is the controller class for service "NotifyService".
 */
class NotifyController extends \app\common\base\BaseController
{
	/**
     * {@inheritDoc}
     */
    protected $restfulActions = [
        'setting' => ['GET', 'POST'],
	];
	
	/**
     * List action
     *
     * @return Response
     */
    public function actionList(): Response
    {
        $params = Yii::$app->request->get();
        return $this->autoReturn(NotifyService::instance()->getList($params));
    }
	
    /**
     * 配置
     *
     * @return Response
     */
    public function actionSetting(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(NotifyService::instance()->setting($params));
    }

    public function actionProfile():Response
    {
        return $this->autoReturn(NotifyService::instance()->getProfile());
    }
}
