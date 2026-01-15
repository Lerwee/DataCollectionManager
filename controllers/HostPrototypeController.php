<?php

namespace app\customs\zapi\controllers;

use app\common\base\BaseController;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\components\data\HostPrototypeFormRequestData;
use app\customs\zapi\components\data\HostPrototypeRequestData;
use app\customs\zapi\services\HostPrototypeService;
use Yii;
use yii\web\Response;

class HostPrototypeController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'delete',
        'mass',
    ];

    /**
     * 原型列表
     *
     * @return Response
     */
    public function actionList(): Response
    {
        $params = Yii::$app->request->get();
        $result = HostPrototypeService::instance()->getList($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionForm(): Response
    {
        $params = Yii::$app->request->get();
        $requestData = new HostPrototypeFormRequestData(['data' => $params]);
        return $this->autoReturn($requestData->getResult());
    }

    /**
     * Create
     *
     * @return Response
     */
    public function actionCreate(): Response
    {
        try {
            $params = Yii::$app->request->post();
            $requestData = new HostPrototypeRequestData(['data' => $params, 'action' => 'create']);
            $result = HostPrototypeService::instance()->create($requestData->getData());
            return $this->autoReturn($result);
        } catch (\Exception $e) {
            return $this->errorException($e);
        }
    }

    /**
     * Update
     *
     * @return Response
     */
    public function actionUpdate(): Response
    {
        try {
            $params = Yii::$app->request->post();
            $requestData = new HostPrototypeRequestData(['data' => $params, 'action' => 'update']);
            $result = HostPrototypeService::instance()->update($requestData->getData());
            return $this->autoReturn($result);
        } catch (\Exception $e) {
            return $this->errorException($e);
        }
    }

    /**
     * Delete
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        try {
            $params = Yii::$app->request->post();
            if (empty($params['group_hostid'])) {
                return $this->error(error_code(60750001, ['attribute' => 'group_hostid', 'error' => Yii::t('yii', '{attribute} cannot be blank.', ['attribute' => ''])]));
            }
            $result = HostPrototypeService::instance()->delete(filter_integer((array) $params['group_hostid']));
            return $this->autoReturn($result);
        } catch (\Exception $e) {
            return $this->errorException($e);
        }
    }

    /**
     * Undocumented function
     *
     * @return Response
     */
    public function actionMass(): Response
    {
        try {
            $params = Yii::$app->request->post();
            $result = HostPrototypeService::instance()->mass($params);
            return $this->autoReturn($result);
        } catch (\Exception $e) {
            return $this->errorException($e);
        }
    }

    /**
     * @param \Throwable   $e
     * @param integer|null $errCode
     * @param string|null  $errMsg
     * @return Response
     */
    protected function errorException($e, ?int $errCode = null, ?string $errMsg = null)
    {
        if ($e instanceof ValidateException) {
            return $this->error($e->getErrorCode(), $errMsg ?: $e->getMessage(), YII_DEBUG ? explode(PHP_EOL, (string) $e) : []);
        }

        \Yii::error(parse_exception($e));
        $data = YII_DEBUG ? explode(PHP_EOL, (string) $e) : explode(PHP_EOL, str_replace(APP_PATH . DIRECTORY_SEPARATOR, '', (string) $e));
        return $this->error($errCode ?: 60750001, $errMsg ?: $e->getMessage(), $data);
    }
}
