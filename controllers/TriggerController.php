<?php
namespace app\customs\zapi\controllers;

use app\customs\zapi\services\TriggerService;
use Yii;
use app\common\base\BaseController;
use yii\db\Exception;
use yii\web\Response;

/**
 * Class TriggerController
 * @package app\customs\zapi\controllers
 */
class TriggerController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'delete',
        'info',
        'prototype-info',
        'set-status',
        'create-prototype',
        'update-prototype',
        'set-prototype-status',
        'set-discover-status',
        'delete-prototype',
        'popup-expression',
    ];

    /**
     * @return Response
     * @throws Exception
     */
    public function actionList(): Response
    {
        $params = Yii::$app->request->get();
        $result = TriggerService::instance()->getList($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionInfo(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['triggerid'])) {
            return $this->error(error_code(10000021, ['param' => 'triggerid']));
        }
        $result = TriggerService::instance()->getTriggerInfo($params);
        return $this->autoReturn($result);
    }


    /**
     * @return Response
     */
    public function actionCreate(): Response
    {
        $params = Yii::$app->request->post();
        $internal = $params['internal'] ?? 0;
        $result = TriggerService::instance()->createTrigger($params, (bool)$internal);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionUpdate(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['triggerid'])) {
            return $this->error(error_code(10000021, ['param' => 'triggerid']));
        }
        $internal = $params['internal'] ?? 0;
        $result = TriggerService::instance()->updateTrigger((int)$params['triggerid'], $params, (bool)$internal);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     * @throws Exception
     */
    public function actionSetStatus(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['triggerid'])) {
            return $this->error(error_code(10000021, ['param' => 'triggerid']));
        }
        if (!isset($params['status'])) {
            return $this->error(error_code(10000021, ['param' => 'status']));
        }
        $result = TriggerService::instance()->setTriggerStatus($params['triggerid'], (int)$params['status']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionDelete(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['triggerid'])) {
            return $this->error(error_code(10000021, ['param' => 'triggerid']));
        }
        $result = TriggerService::instance()->deleteTriggers($params['triggerid']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     * @throws Exception
     */
    public function actionPrototypeList(): Response
    {
        $params = Yii::$app->request->get();
        $result = TriggerService::instance()->getPrototypeList($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionPrototypeInfo(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['triggerid'])) {
            return $this->error(error_code(10000021, ['param' => 'triggerid']));
        }
        $result = TriggerService::instance()->getTriggerPrototypeInfo($params);
        return $this->autoReturn($result);
    }


    /**
     * @return Response
     */
    public function actionCreatePrototype(): Response
    {
        $params = Yii::$app->request->post();
        $internal = $params['internal'] ?? 0;
        $result = TriggerService::instance()->createTriggerPrototype($params, (bool)$internal);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionUpdatePrototype(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['triggerid'])) {
            return $this->error(error_code(10000021, ['param' => 'triggerid']));
        }
        $internal = $params['internal'] ?? 0;
        $result = TriggerService::instance()->updateTriggerPrototype((int)$params['triggerid'], $params, (bool)$internal);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     * @throws Exception
     */
    public function actionSetPrototypeStatus(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['triggerid'])) {
            return $this->error(error_code(10000021, ['param' => 'triggerid']));
        }
        if (!isset($params['status'])) {
            return $this->error(error_code(10000021, ['param' => 'status']));
        }
        $result = TriggerService::instance()->setTriggerPrototypeStatus($params['triggerid'], (int)$params['status']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionSetDiscoverStatus(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['triggerid'])) {
            return $this->error(error_code(10000021, ['param' => 'triggerid']));
        }
        if (!isset($params['status'])) {
            return $this->error(error_code(10000021, ['param' => 'status']));
        }
        $result = TriggerService::instance()->setTriggerDiscoverStatus($params['triggerid'], (int)$params['status']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionDeletePrototype(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['triggerid'])) {
            return $this->error(error_code(10000021, ['param' => 'triggerid']));
        }
        $result = TriggerService::instance()->deleteTriggerPrototypes($params['triggerid']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionPopupExpression(): Response
    {
        $params = Yii::$app->request->post();
        $result = TriggerService::instance()->popupTriggerExpr($params);
        return $this->autoReturn($result);
    }
}