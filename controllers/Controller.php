<?php

namespace app\customs\zabbix\controllers;

use app\common\base\BaseController;
use app\customs\zabbix\components\Hacker;
use app\customs\zabbix\services\actions\JsLoader;
use app\customs\zabbix\services\actions\JsRpc;
use app\customs\zabbix\services\actions\RepeatAction;
use app\customs\zabbix\services\actions\ReuseAction;
use Yii;
use yii\web\Response;

class Controller extends BaseController
{
    /**
     * {@inheritDoc}
     */
    public $restfulActions = [
        'zabbix' => ['POST', 'GET'],
        'jsrpc' => ['POST', 'GET'],
    ];

    /**
     * @var bool enable loader js
     */
    public $enableLoader = true;

    /**
     * @var array default `$_GET` params
     */
    public $getParams = [];

    /**
     * @var array allow actions
     */
    public $allowedActions = [];

    /**
     * @var bool hacker to zabbix
     */
    public $enableHacker = true;

    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        if ($this->enableHacker) {
            Yii::$app->response->format = Response::FORMAT_HTML;
            $this->hackerZabbix();
            $this->restfulActions[$this->id] = ['POST', 'GET'];
        }
        return parent::beforeAction($action);
    }

    /**
     * {@inheritDoc}
     */
    public function actions()
    {
        $buf = explode("/", Yii::$app->requestedRoute);
        $action = end($buf);
        if ($action == $this->id) {
            $actions = [
                $this->id => RepeatAction::class
            ];
        } else {
            $class = 'app\\customs\\zabbix\\services\\actions\\' . ucfirst($action) . 'Action';
            if (class_exists($class)) {
                $actions = [
                    $action => $class
                ];
                $this->restfulActions[$action] = ['POST', 'GET'];
            } elseif (in_array($action, $this->allowedActions)) {
                $actions = [
                    $action => ReuseAction::class
                ];
                $this->restfulActions[$action] = ['POST', 'GET'];
            } else {
                $actions = [];
            }
        }
        
        if ($this->enableLoader) {
            $actions['jsLoader'] = JsLoader::class;
            $actions['jsrpc'] = JsRpc::class;
        }
        return $actions;
    }
    
    /**
     * 兼容zabbix 4.X
     * 该文件从4.0开始引入
     */
    public function actionZabbix()
    {
        return $this->renderNormal();
    }

    /**
     * 标准视图
     *
     * @param array $params
     * @return Response
     */
    protected function renderNormal($params = [])
    {
        if (empty($params['file'])) {
            $params['file'] = $this->action->id;
        }
        return $this->render('/common/normal', $params);
    }

    /**
     * hacker to zabbix
     */
    public function hackerZabbix()
    {
        // TODO: 取登录用户
        $data = \Yii::$app->db->createCommand('SELECT * FROM users WHERE userid=:userid LIMIT 1')
                ->bindValue(':userid', \Yii::$app->user->getId() ? : 1)
                ->queryOne();
        Hacker::login($data);

        global $page;
        // see z/include/classes/html/CForm.php #42 setAction
        $page['file'] = $this->action->id;

        $page['menu'] = '';
    }

    /**
     * @throws \yii\web\NotFoundHttpException
     */
    protected function pageNotFound()
    {
        throw new \yii\web\NotFoundHttpException(Yii::t('yii', 'Page not found.'));
    }
}
