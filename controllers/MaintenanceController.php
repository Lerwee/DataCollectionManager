<?php

namespace app\customs\zapi\controllers;

use app\common\base\BaseController;
use app\customs\zapi\services\MaintenanceService;
use Yii;
use yii\web\Response;

/**
 * 维护模式
 */
class MaintenanceController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'delete',
    ];

    /**
     * 新增
     * 
     * @return Response
     */
    public function actionCreate(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(MaintenanceService::instance()->create($params, false));
    }

    /**
     * 编辑
     * 
     * @return Response
     */
    public function actionUpdate(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(MaintenanceService::instance()->update($params, false));
    }

    /**
     * 删除
     * 
     * @return Response
     */
    public function actionDelete(): Response
    {
        $ids = Yii::$app->request->post('maintenanceids');
        if (empty($ids)) {
            return $this->error(error_code(10000021, ['param' => 'maintenanceids']));
        }
        return $this->autoReturn(MaintenanceService::instance()->delete((array) $ids));
    }
}
