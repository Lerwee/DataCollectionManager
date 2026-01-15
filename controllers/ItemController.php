<?php
namespace app\customs\zapi\controllers;

use app\customs\zapi\services\ItemService;
use Yii;
use app\common\base\BaseController;
use yii\db\Exception;
use yii\web\Response;

/**
 * Class ItemController
 * @package app\customs\zapi\controllers
 */
class ItemController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'batch-update',
        'delete',
        'create-prototype',
        'update-prototype',
        'batch-update-prototype',
        'delete-prototype',
        'set-status',
        'set-prototype-status',
        'set-discover-status',
        'test',
        'test-get-value',
        'test-form',
        'check-now'
    ];

    /**
     * @return Response
     * @throws Exception
     */
    public function actionOptions(): Response
    {
        $result = ItemService::instance()->getOptions();
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     * @throws Exception
     */
    public function actionList(): Response
    {
        $params = Yii::$app->request->get();
        $result = ItemService::instance()->getList($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionInfo(): Response
    {
        $params = Yii::$app->request->get();
        if (empty($params['itemid'])) {
            return $this->error(error_code(10000021, ['param' => 'itemid']));
        }
        $result = ItemService::instance()->getInfo($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionCreateForm(): Response
    {
        $params = Yii::$app->request->get();
        $result = ItemService::instance()->getCreateForm($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionCreate(): Response
    {
        $params = Yii::$app->request->post();
        $internal = $params['internal'] ?? 0;
        $result = ItemService::instance()->createItem($params, (bool)$internal);
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
        $result = ItemService::instance()->updateItem((int)$params['itemid'], $params, (bool)$internal);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionBatchUpdate(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['ids'])) {
            return $this->error(error_code(10000021, ['param' => 'ids']));
        }
        $result = ItemService::instance()->batchUpdateItems($params['ids'], $params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
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
        $result = ItemService::instance()->setItemStatus($params['itemid'], (int)$params['status']);
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
        $result = ItemService::instance()->deleteItems($params['itemid']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     * @throws Exception
     */
    public function actionPrototypeList(): Response
    {
        $params = Yii::$app->request->get();
        $result = ItemService::instance()->getPrototypeList($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionPrototypeInfo(): Response
    {
        $params = Yii::$app->request->get();
        if (empty($params['itemid'])) {
            return $this->error(error_code(10000021, ['param' => 'itemid']));
        }
        $result = ItemService::instance()->getPrototypeInfo($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionCreatePrototype(): Response
    {
        $params = Yii::$app->request->post();
        $internal = $params['internal'] ?? 0;
        $result = ItemService::instance()->createItemPrototype($params, (bool)$internal);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionUpdatePrototype(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['itemid'])) {
            return $this->error(error_code(10000021, ['param' => 'itemid']));
        }
        $internal = $params['internal'] ?? 0;
        $result = ItemService::instance()->updateItemPrototype((int)$params['itemid'], $params, (bool)$internal);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionBatchUpdatePrototype(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['ids'])) {
            return $this->error(error_code(10000021, ['param' => 'ids']));
        }
        $result = ItemService::instance()->batchUpdateItemPrototypes($params['ids'], $params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionSetPrototypeStatus(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['itemid'])) {
            return $this->error(error_code(10000021, ['param' => 'itemid']));
        }
        if (!isset($params['status'])) {
            return $this->error(error_code(10000021, ['param' => 'status']));
        }
        $result = ItemService::instance()->setItemPrototypeStatus($params['itemid'], (int)$params['status']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionSetDiscoverStatus(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['itemid'])) {
            return $this->error(error_code(10000021, ['param' => 'itemid']));
        }
        if (!isset($params['status'])) {
            return $this->error(error_code(10000021, ['param' => 'status']));
        }
        $result = ItemService::instance()->setItemDiscoverStatus($params['itemid'], (int)$params['status']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionDeletePrototype(): Response
    {
        $params = Yii::$app->request->post();
        if (empty($params['itemid'])) {
            return $this->error(error_code(10000021, ['param' => 'itemid']));
        }
        $result = ItemService::instance()->deleteItemPrototypes($params['itemid']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionTestForm(): Response
    {
        $params = Yii::$app->request->post();
        $result = ItemService::instance()->testItemForm($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionTestGetValue(): Response
    {
        $params = Yii::$app->request->post();
        $result = ItemService::instance()->testGetValue($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionTest(): Response
    {
        $params = Yii::$app->request->post();
        $result = ItemService::instance()->testItem($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionCheckNow(): Response
    {
        $params = Yii::$app->request->post();
        $result = ItemService::instance()->checkNow($params);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionHelpItems(): Response
    {
        $params = Yii::$app->request->get();
        if (!isset($params['itemtype'])) {
            return $this->error(error_code(10000021, ['param' => 'itemtype']));
        }
        $result = ItemService::instance()->getHelpItems((int)$params['itemtype']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionTemplateValuemaps(): Response
    {
        $params = Yii::$app->request->get();
        if (empty($params['hostids'])) {
            return $this->error(error_code(10000021, ['param' => 'hostids']));
        }
        if (empty($params['context'])) {
            return $this->error(error_code(10000021, ['param' => 'context']));
        }
        $result = ItemService::instance()->getTemplateValueMap($params['hostids'], $params['context']);
        return $this->autoReturn($result);
    }

    /**
     * @return Response
     */
    public function actionTags(): Response
    {
        $params = Yii::$app->request->get();
        if (empty($params['hostid'])) {
            return $this->error(error_code(10000021, ['param' => 'hostid']));
        }
        $result = ItemService::instance()->getHostTags($params['hostid']);
        return $this->autoReturn($result);
    }
}