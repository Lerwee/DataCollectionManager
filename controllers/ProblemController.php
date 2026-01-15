<?php

namespace app\customs\zapi\controllers;

use Yii;
use yii\web\Response;
use app\common\base\BaseController;
use app\customs\zapi\services\ProblemService;

/**
 * Class ProblemController
 * @package app\customs\zapi\controllers
 */
class ProblemController extends BaseController
{
    /**
     * @return Response
     */
    public function actionGet(): Response
    {
        $params = Yii::$app->request->get();
        $result = ProblemService::instance()->getProblem($params);
        return $this->autoReturn($result);
    }
}
