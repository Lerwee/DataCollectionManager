<?php

namespace app\customs\zapi\controllers;

use app\common\base\BaseController;
use app\customs\zapi\services\UserMacroService;
use Yii;
use yii\web\Response;

class GlobalMacroController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'delete',
    ];

    /**
     * @return Response
     */
    public function actionGet():Response
    {
        return $this->autoReturn(UserMacroService::instance()->getGlobalMacros());
    }

    /**
     * 更新
     *
     * @return Response
     */
    public function actionUpdate(): Response
    {
        $macros = (array) Yii::$app->request->post('macros', []);
        return $this->autoReturn(UserMacroService::instance()->update($macros));
    }
}
