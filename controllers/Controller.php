<?php

namespace app\customs\zabbix\controllers;

use app\common\base\BaseController;
use app\common\helpers\SqlHelper;
use app\customs\zabbix\components\Hacker;
use app\customs\zabbix\services\actions\JsLoader;
use app\customs\zabbix\services\actions\JsRpc;
use app\customs\zabbix\services\actions\RepeatAction;
use app\customs\zabbix\services\actions\ReuseAction;
use app\models\User;
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
     * {@inheritDoc}
     */
    public function behaviors(): array
    {
        if ($this->enableHacker) {
            Yii::$app->response->format = Response::FORMAT_HTML;
            $this->hackerZabbix();
            $this->restfulActions[$this->id] = ['POST', 'GET'];
        }

        if (!config('zabbix.enable_access_authorization', true)) {
            return parent::behaviors();
        }

        $routeA = '/' . $this->module->id . '/' . $this->id . '/' . $this->action->id;
        $routeB = '/' . $this->module->id . '/' . $this->id . '/*';
        $routeC = '/' . $this->module->id . '/*';
        $routes = $this->getAccessRoutes();
        if (in_array($routeA, $routes) || in_array($routeB, $routes) || in_array($routeC, $routes)) {
            $allow = true;
        } else {
            $allow = false;
            Yii::$app->response->format = Response::FORMAT_JSON;
        }
        return \yii\helpers\ArrayHelper::merge(parent::behaviors(), [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'only' => [$this->action->id],
                'rules' => [
                    [
                        'allow' => $allow,
                        'roles' => ['@'],
                    ]
                ]
            ]
        ]);
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
     * 图片地址
     */
    public function actionImgstore()
    {
        #Yii::$app->response->getHeaders()->set('Content-Type', 'image/png');
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
        if (Yii::$app->user->isGuest) {
            $authHeader = Yii::$app->request->getHeaders()->get('authorization');
            if ($authHeader && preg_match('/^Bearer\s+(.*?)$/', $authHeader, $matches)) {
                $token = $matches[1];
            } else {
                $token = isset($_COOKIE['_token']) ? $_COOKIE['_token'] : null;
                if (!$token) {
                    $token = isset($_COOKIE['accessToken']) ? $_COOKIE['accessToken'] : null;
                }
            }

            if ($token && $user = User::findIdentityByAccessToken($token)) {
                $user = User::findIdentityByAccessToken($token);
                Yii::$app->language = $user->lang;
                Yii::$app->user->login($user);
            }
        } else {
            /** @var User $user */
            $user = Yii::$app->user->identity;
            Yii::$app->language = $user->lang;
        }
        $data = \Yii::$app->db->createCommand('SELECT * FROM users WHERE userid=:userid LIMIT 1')
            ->bindValue(':userid', User::SYSTEM_USER_ID)
            ->queryOne();
        Hacker::login($data);

        global $page;
        // see z/include/classes/html/CForm.php #42 setAction
        $page['file'] = $this->action->id;

        $page['menu'] = '';
    }

    /**
     * 返回可访问的路由
     *
     * @return string[]
     */
    protected function getAccessRoutes()
    {
        /** @var User $user */
        $user = Yii::$app->user->getIdentity();
        if (empty($user) || $user->getIsAdministrator()) {
            return ['/zbx/*'];
        }

        $roles = $user->roles;
        $rules = [];
        foreach ($roles as $role) {
            $rules = array_merge($rules, $role->getPermissionIds());
        }
        foreach ($rules as $i => $rule) {
            if (strlen($rule) !== 8) {
                unset($rules[$i]);
            }
        }

        $query = \app\modules\auth\models\RuleItem::find()
            ->select("route")
            ->where(SqlHelper::whereIn('ruleid', array_unique($rules)));
        return $query->column();
    }

    /**
     * @throws \yii\web\NotFoundHttpException
     */
    protected function pageNotFound()
    {
        throw new \yii\web\NotFoundHttpException(Yii::t('yii', 'Page not found.'));
    }
}
