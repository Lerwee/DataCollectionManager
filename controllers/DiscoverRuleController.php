<?php
namespace app\customs\zapi\controllers;

use app\customs\zapi\services\DiscoverRuleService;
use Yii;
use app\common\base\BaseController;
use yii\db\Exception;
use yii\web\Response;

/**
 * Class DiscoverRuleController
 * @package app\customs\zapi\controllers
 */
class DiscoverRuleController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'delete',
        'set-status',
    ];

    /**
     * @return Response
     * @throws Exception
     */
    public function actionList(): Response
    {
        $params = Yii::$app->request->get();
        $result = DiscoverRuleService::instance()->getList($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     * @throws Exception
     */
    public function actionInfo(): Response
    {
        $params = Yii::$app->request->get();
        if (empty($params['itemid'])) {
            return $this->error(error_code(10000021, ['param' => 'itemid']));
        }
        $result = DiscoverRuleService::instance()->getInfo($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionCreateForm(): Response
    {
        $params = Yii::$app->request->get();
        $result = DiscoverRuleService::instance()->getCreateForm($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionCreate(): Response
    {
        $params = Yii::$app->request->post();
        $internal = $params['internal'] ?? 0;
        $result = DiscoverRuleService::instance()->createDiscoverRule($params, (bool)$internal);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionUpdate(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['itemid'])) {
            return $this->error(error_code(10000021, ['param' => 'itemid']));
        }
        $internal = $params['internal'] ?? 0;
        $result = DiscoverRuleService::instance()->updateDiscoverRule((int)$params['itemid'], $params, (bool)$internal);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     * @throws Exception
     */
    public function actionSetStatus(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['itemid'])) {
            return $this->error(error_code(10000021, ['param' => 'itemid']));
        }
        if (!isset($params['status'])) {
            return $this->error(error_code(10000021, ['param' => 'status']));
        }
        $result = DiscoverRuleService::instance()->setStatus($params['itemid'], (int)$params['status']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionDelete(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['itemid'])) {
            return $this->error(error_code(10000021, ['param' => 'itemid']));
        }
        $result = DiscoverRuleService::instance()->deleteDiscoverRules($params['itemid']);
        return $this->autoReturn($result);
    }

}