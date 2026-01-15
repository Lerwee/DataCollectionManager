<?php

namespace app\customs\zapi\controllers;

use app\common\base\BaseController;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\services\HostGroupService;
use app\customs\zapi\services\MonitorService;
use yii\db\Exception;
use yii\web\Response;

/**
 * Class CommonController
 * @package app\customs\zapi\controllers
 */
class CommonController extends BaseController
{
    /**
     * @return Response
     */
    public function actionTemplateGroups(): Response
    {
        $data = HostGroupService::instance()->getGroups([
            'type' => HOST_GROUP_TYPE_TEMPLATE_GROUP
        ], ['groupid', 'name']);
        return $this->success($data);
    }

    /**
     * @return Response
     */
    public function actionHostGroups(): Response
    {
        $data = HostGroupService::instance()->getGroups([
            'type' => HOST_GROUP_TYPE_HOST_GROUP
        ], ['groupid', 'name']);
        return $this->success($data);
    }

    /**
     * @return Response
     */
    public function actionTemplates(): Response
    {
        $params = \Yii::$app->request->get();
        $data = TemplateHelper::getTemplates([
            'output' => ['template', 'name']
        ] + $params);
        return $this->success($data);
    }

    /**
     * @return Response
     */
    public function actionHosts(): Response
    {
        $params = \Yii::$app->request->get();
        $data = HostHelper::getHosts([
                'output' => ['host', 'name']
            ] + $params);
        return $this->success($data);
    }

    /**
     * @return Response
     */
    public function actionTemplateItems(): Response
    {
        $params = \Yii::$app->request->get();
        if (empty($params['templateid'])) {
            return $this->error(error_code(10000021, ['param' => 'templateid']));
        }
        $result = MonitorService::instance()->getTemplateItems((int)$params['templateid']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     * @throws Exception
     */
    public function actionItemPrototypes(): Response
    {
        $params = \Yii::$app->request->get();
        if (empty($params['parent_discoveryid'])) {
            return $this->error(error_code(10000021, ['param' => 'parent_discoveryid']));
        }
        $result = MonitorService::instance()->getItemPrototypes((int)$params['parent_discoveryid'], $params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     * @throws Exception
     */
    public function actionTemplateTriggers(): Response
    {
        $params = \Yii::$app->request->get();
        if (empty($params['templateid'])) {
            return $this->error(error_code(10000021, ['param' => 'templateid']));
        }
        $result = MonitorService::instance()->getTemplateTriggers((int)$params['templateid']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionItems(): Response
    {
        $params = \Yii::$app->request->get();
        if (empty($params['hostid'])) {
            return $this->error(error_code(10000021, ['param' => 'hostid']));
        }
        $result = MonitorService::instance()->getHostItems((int)$params['hostid']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     * @throws Exception
     */
    public function actionTriggers(): Response
    {
        $params = \Yii::$app->request->get();
        if (empty($params['hostid'])) {
            return $this->error(error_code(10000021, ['param' => 'hostid']));
        }
        $result = MonitorService::instance()->getHostTriggers((int)$params['hostid'], $params);
        return $this->autoReturn($result);
    }


    /**
     * @return Response
     * @throws Exception
     */
    public function actionTriggerPrototypes(): Response
    {
        $params = \Yii::$app->request->get();
        if (empty($params['parent_discoveryid'])) {
            return $this->error(error_code(10000021, ['param' => 'parent_discoveryid']));
        }
        $result = MonitorService::instance()->getTriggerPrototypes((int)$params['parent_discoveryid'], $params);
        return $this->autoReturn($result);
    }
}