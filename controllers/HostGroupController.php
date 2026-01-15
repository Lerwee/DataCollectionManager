<?php

namespace app\customs\zapi\controllers;

use app\common\base\BaseController;
use app\customs\zapi\services\HostGroupService;
use Yii;
use yii\web\Response;

class HostGroupController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'delete',
    ];

    /**
     * 主机分组列表
     *
     * @return Response
     */
    public function actionList(): Response
    {
        $params = Yii::$app->request->get();
        return $this->autoReturn(HostGroupService::instance()->getList($params));
    }

    /**
     * 主机分组新增
     * 
     * @return Response
     */
    public function actionCreate(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(HostGroupService::instance()->create($params));
    }

    /**
     * 主机分组编辑
     * 
     * @return Response
     */
    public function actionUpdate(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(HostGroupService::instance()->update($params));
    }

    /**
     * 主机分组删除
     * 
     * @return Response
     */
    public function actionDelete(): Response
    {
        $ids = Yii::$app->request->post('groupids');
        if (empty($ids)) {
            return $this->error(error_code(10000021, ['param' => 'groupids']));
        }

        return $this->autoReturn(HostGroupService::instance()->delete((array) $ids));
    }
}
