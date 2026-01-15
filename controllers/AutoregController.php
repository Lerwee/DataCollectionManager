<?php
namespace app\customs\zapi\controllers;

use app\customs\zapi\services\AutoRegService;
use app\customs\zapi\services\RegexpService;
use Yii;
use app\common\base\BaseController;
use yii\db\Exception;
use yii\web\Response;

/**
 * Class ItemController
 * @package app\customs\zapi\controllers
 */
class AutoregController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'delete',
    ];

    /**
     * @return Response
     */
    public function actionInfo(): Response
    {
        $result = AutoRegService::instance()->getInfo();
        return $this->autoReturn($result);
    }


    /**
     * @return Response
     */
    public function actionUpdate(): Response
    {
        $params = Yii::$app->request->post();
        $result = AutoRegService::instance()->saveConfig($params);
        return $this->autoReturn($result);
    }
}