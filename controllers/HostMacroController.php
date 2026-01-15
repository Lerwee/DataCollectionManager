<?php

namespace app\customs\zapi\controllers;

use app\common\base\BaseController;
use app\customs\zapi\components\data\HostMacroSearchRequestData;
use app\customs\zapi\services\HostMacroService;
use Yii;
use yii\web\Response;

class HostMacroController extends BaseController
{
    protected $restfulActions = [
        'list',
        'create',
        'update',
        'delete',
    ];

    public function actionList(): Response
    {
        $params = Yii::$app->request->post();
        $request = new HostMacroSearchRequestData(['data' => $params]);
        return $this->autoReturn($request->getResult());
    }

    /**
     * 主机宏新增
     * 
     * @return Response
     */
    public function actionCreate(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(HostMacroService::instance()->create($params));
    }

    /**
     * 主机宏编辑
     * 
     * @return Response
     */
    public function actionUpdate(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(HostMacroService::instance()->update($params));
    }

    /**
     * 主机宏删除
     * 
     * @return Response
     */
    public function actionDelete(): Response
    {
        $ids = Yii::$app->request->post('hostmacroids');
        if (empty($ids)) {
            return $this->error(error_code(10000021, ['param' => 'hostmacroids']));
        }

        return $this->autoReturn(HostMacroService::instance()->delete((array) $ids));
    }
}
