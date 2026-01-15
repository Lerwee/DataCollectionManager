<?php

namespace app\customs\zapi\controllers;

use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\helpers\ProxyHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\services\ScriptService;
use Yii;
use app\common\base\BaseController;
use yii\web\Response;

/**
 * Class ScriptController
 * @package app\customs\zapi\controllers
 */
class ScriptController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'delete',
        'execute',
    ];

    /**
     * @return Response
     */
    public function actionGet(): Response
    {
        $params = Yii::$app->request->get();
        $result = ScriptService::instance()->getScript($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionInfo(): Response
    {
        $params = Yii::$app->request->get();
        $result = ScriptService::instance()->infoScript($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionCreate(): Response
    {
        $params = Yii::$app->request->post();
        $result = ScriptService::instance()->createScript($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionUpdate(): Response
    {
        $params = Yii::$app->request->post();
        $result = ScriptService::instance()->updateScript($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionDelete(): Response
    {
        $params = Yii::$app->request->post();
        $result = ScriptService::instance()->deleteScript($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionExecute(): Response
    {
        $params = Yii::$app->request->post();
        $result = ScriptService::instance()->executeScript($params);
        return $this->autoReturn($result);
    }


    public function actionProfile()
    {
        return $this->success([
            'groups' => GroupHelper::getHostGroups(['output' => ['groupid', 'name']]),
        ]);
    }
}
