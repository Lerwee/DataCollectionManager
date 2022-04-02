<?php

namespace app\customs\zabbix\controllers;

use app\customs\zabbix\services\SysinfoService;
use Yii;
use yii\web\Response;

class SiteController extends Controller
{
    /**
     * {@inheritDoc}
     */
    public $enableHacker = true;

    /**
     * {@inheritDoc}
     */
    public function actions()
    {
        $actions = parent::actions();
        if (isset($actions[$this->id])) {
            unset($actions[$this->id]);
        }
        return $actions;
    }

    /**
     * 系统信息
     *
     * @return Response
     */
    public function actionSysinfo()
    {
        return $this->success(SysinfoService::instance()->delegate());
    }

    /**
     * 路由解析
     *
     * @return Response
     */
    public function actionParser()
    {
        $alias = (string) strtolower(Yii::$app->request->get('alias'));
        $controllerClass = __NAMESPACE__ . '\\' . ucfirst($alias) . 'Controller';
        if (!class_exists($controllerClass)) {
            $msg = Yii::t('yii', 'Invalid data received for parameter "{param}".', [
                'param' => 'alias'
            ]);
            return $this->error(10000404, $msg);
        }
        $class = new $controllerClass($alias, $this->module);
        $class->enableLoader = false;
        $action = current(array_keys($class->actions()));

        $url = '/'. $this->module->id . '/' . $alias;
        if ($action) {
            $url .= '/'. $action;
        }

        return $this->success([
            'alias' => $alias,
            'title' => Yii::t($this->module->id, ucfirst($alias)),
            'url' => $url
        ]);
    }
}
