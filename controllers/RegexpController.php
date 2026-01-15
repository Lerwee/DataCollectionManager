<?php
namespace app\customs\zapi\controllers;

use app\customs\zapi\services\RegexpService;
use Yii;
use app\common\base\BaseController;
use yii\db\Exception;
use yii\web\Response;

/**
 * Class ItemController
 * @package app\customs\zapi\controllers
 */
class RegexpController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'delete',
        'test',
    ];

    /**
     * @return Response
     */
    public function actionOptions(): Response
    {
        $result = RegexpService::instance()->getOptions();
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     * @throws Exception
     */
    public function actionList(): Response
    {
        $params = Yii::$app->request->get();
        $result = RegexpService::instance()->getList($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionInfo(): Response
    {
        $params = Yii::$app->request->get();
        if (empty($params['regexpid'])) {
            return $this->error(error_code(10000021, ['param' => 'regexpid']));
        }
        $result = RegexpService::instance()->getInfo($params['regexpid']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionCreate(): Response
    {
        $params = Yii::$app->request->post();
        $result = RegexpService::instance()->createRegexp($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionUpdate(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['regexpid'])) {
            return $this->error(error_code(10000021, ['param' => 'regexpid']));
        }
        $result = RegexpService::instance()->updateRegexp((int)$params['regexpid'], $params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionDelete(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['regexpid'])) {
            return $this->error(error_code(10000021, ['param' => 'regexpid']));
        }
        $result = RegexpService::instance()->deleteRegexp($params['regexpid']);
        return $this->autoReturn($result);
    }


    public function actionTest(): Response
    {
        $params = Yii::$app->request->post();
        if (!isset($params['testString'])) {
            return $this->error(error_code(10000021, ['param' => 'testString']));
        }
        if (empty($params['expressions'])) {
            return $this->error(error_code(10000021, ['param' => 'expressions']));
        }
        $result = RegexpService::instance()->testRegex($params['testString'], $params['expressions']);
        return $this->autoReturn($result);
    }
}