<?php

namespace app\customs\zapi\controllers;

use app\customs\zapi\services\ConfigurationService;
use Yii;
use yii\web\Response;
use app\common\base\BaseController;
use app\customs\zapi\services\ProblemService;

/**
 * Class ConfigurationController
 * @package app\customs\zapi\controllers
 */
class ConfigurationController extends BaseController
{
    /**
     * @return Response
     */
    public function actionImport(): Response
    {
        $params = Yii::$app->request->get();
        $result = ConfigurationService::instance()->import($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionExport(): Response
    {
        $params = Yii::$app->request->get();
        $result = ConfigurationService::instance()->export($params);
        return $this->autoReturn($result);
    }
}
