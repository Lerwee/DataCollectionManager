<?php
namespace app\customs\zapi\services;

use app\common\base\BaseService;
use app\common\components\Result;
use app\customs\zapi\common\helpers\TriggerHelper;
use app\customs\zapi\components\data\TriggerFormRequestData;
use app\customs\zapi\components\data\TriggerPrototypeFormRequestData;
use app\customs\zapi\components\data\TriggerPrototypeRequestData;
use app\customs\zapi\components\data\TriggerPrototypeSearchRequestData;
use app\customs\zapi\components\data\TriggerRequestData;
use app\customs\zapi\components\data\TriggerSearchRequestData;
use app\customs\zapi\components\PopupTriggerExpr;
use app\customs\zapi\models\search\trigger\TriggerPrototypeSearch;
use app\customs\zapi\models\search\trigger\TriggerSearch;
use app\customs\zapi\services\assist\TriggerAssist;
use app\customs\zapi\services\assist\TriggerPrototypeAssist;
use app\modules\libzbx\models\Triggers;
use Yii;
use yii\base\Exception;

/**
 * Class TriggerService
 * @package app\customs\zapi\services
 */
class TriggerService extends BaseService
{
    /**
     * 监控项列表
     * @param array $params
     * @return Result
     * @throws \yii\db\Exception
     */
    public function getList(array $params = []): Result
    {
        $search = new TriggerSearch();
        $requestData = new TriggerSearchRequestData(['data' => $params]);
        if (!$requestData->isSuccess()) {
            return $requestData->getResult();
        }
        $provider = $search->search($requestData->getData(), ['visible' => 1]);
        return $this->success([
            'total' => $provider->getTotalCount(),
            'list' => $provider->getModels(),
        ]);
    }

    /**
     * @param array $params
     * @return Result
     * @throws \yii\db\Exception
     */
    public function getTriggerInfo(array $params = []): Result
    {
        $requestData = new TriggerFormRequestData(['data' => $params]);
        if (!$requestData->isSuccess()) {
            return $requestData->getResult();
        }
        $trigger = $requestData->getData();
        $result = TriggerHelper::getTriggerFormData($trigger);
        if (empty($result)) {
            return $this->error(60750004);
        }
        return $this->success($result);
    }

    /**
     * @param array $params item or [item1, item2]
     * @param bool $internal 是否内部调用（即原生API），当为false时，需要格式化
     * @return Result
     */
    public function createTrigger(array $params = [], bool $internal = true): Result
    {
        $trigger = [];
        if (!$internal) {
            $requestData = new TriggerRequestData(['data' => $params]);
            if (!$requestData->isSuccess()) {
                return $requestData->getResult();
            }
            $trigger = $requestData->getData();
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = TriggerAssist::instance()->create($trigger ?: $params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Create Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750301, $e->getMessage());
        }
    }

    /**
     * @param int $triggerId
     * @param array $params
     * @param bool $internal
     * @return Result
     */
    public function updateTrigger(int $triggerId, array $params = [], bool $internal = true): Result
    {
        $trigger = [];
        $params['triggerid'] = $triggerId;
        if (!$internal) {
            $requestData = new TriggerRequestData(['data' => $params, 'action' => 'update']);
            if (!$requestData->isSuccess()) {
                return $requestData->getResult();
            }
            $trigger = $requestData->getData();
            if (empty($trigger)) {
                return $this->success([], t('act', 'Update Success'));
            }
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = TriggerAssist::instance()->update($trigger ?: $params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Update Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750302, $e->getMessage());
        }
    }

    /**
     * @param int|array $triggerId
     * @return Result
     * @throws \yii\db\Exception
     */
    public function enableTriggers($triggerId): Result
    {
        return $this->setTriggerStatus($triggerId, 0);
    }

    /**
     * @param int|array $triggerId
     * @return Result
     * @throws \yii\db\Exception
     */
    public function disableTriggers($triggerId): Result
    {
        return $this->setTriggerStatus($triggerId, 1);
    }

    /**
     * @param $triggerId
     * @param int $status
     * @return Result
     * @throws \yii\db\Exception
     */
    public function setTriggerStatus($triggerId, int $status): Result
    {
        $triggerIds = filter_integer((array)$triggerId);
        $params = array_map(function ($triggerId) use ($status) {
            return [
                'triggerid' => $triggerId,
                'status' => $status
            ];
        }, $triggerIds);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = TriggerAssist::instance()->update($params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('app', $status ? 'Disable Success' : 'Enable Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750302, $e->getMessage());
        }
    }

    /**
     * @param int|array $triggerId
     * @return Result
     */
    public function deleteTriggers($triggerId): Result
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = TriggerAssist::instance()->delete(filter_integer((array)$triggerId));
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Delete Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750303, $e->getMessage());
        }
    }

    /**
     * 监控项原型列表
     * @param array $params
     * @return Result
     * @throws \yii\db\Exception
     */
    public function getPrototypeList(array $params = []): Result
    {
        $search = new TriggerPrototypeSearch();
        $requestData = new TriggerPrototypeSearchRequestData(['data' => $params]);
        if (!$requestData->isSuccess()) {
            return $requestData->getResult();
        }
        $provider = $search->search($requestData->getData(), ['visible' => 1]);
        return $this->success([
            'total' => $provider->getTotalCount(),
            'list' => $provider->getModels(),
        ]);
    }

    /**
     * @param array $params
     * @return Result
     * @throws \yii\db\Exception
     */
    public function getTriggerPrototypeInfo(array $params = []): Result
    {
        $requestData = new TriggerPrototypeFormRequestData(['data' => $params]);
        if (!$requestData->isSuccess()) {
            return $requestData->getResult();
        }
        $trigger = $requestData->getData();
        $result = TriggerHelper::getTriggerFormData($trigger);
        if (empty($result)) {
            return $this->error(60750004);
        }
        return $this->success($result);
    }

    /**
     * @param array $params item or [item1, item2]
     * @param bool $internal 是否内部调用（即原生API），当为false时，需要格式化
     * @return Result
     */
    public function createTriggerPrototype(array $params = [], bool $internal = true): Result
    {
        $trigger = [];
        if (!$internal) {
            $requestData = new TriggerPrototypeRequestData(['data' => $params]);
            if (!$requestData->isSuccess()) {
                return $requestData->getResult();
            }
            $trigger = $requestData->getData();
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = TriggerPrototypeAssist::instance()->create($trigger ?: $params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Create Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750301, $e->getMessage());
        }
    }

    /**
     * @param int $triggerId
     * @param array $params
     * @param bool $internal
     * @return Result
     */
    public function updateTriggerPrototype(int $triggerId, array $params = [], bool $internal = true): Result
    {
        $trigger = [];
        $params['itemid'] = $triggerId;
        if (!$internal) {
            $requestData = new TriggerPrototypeRequestData(['data' => $params, 'action' => 'update']);
            if (!$requestData->isSuccess()) {
                return $requestData->getResult();
            }
            $trigger = $requestData->getData();
            if (empty($trigger)) {
                return $this->success([], t('act', 'Update Success'));
            }
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = TriggerPrototypeAssist::instance()->update($trigger ?: $params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Update Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750302, $e->getMessage());
        }
    }

    /**
     * @param $triggerId
     * @param int $status
     * @return Result
     * @throws \yii\db\Exception
     */
    public function setTriggerPrototypeStatus($triggerId, int $status): Result
    {
        $triggerIds = filter_integer((array)$triggerId);
        $params = array_map(function ($triggerId) use ($status) {
            return [
                'triggerid' => $triggerId,
                'status' => $status
            ];
        }, $triggerIds);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = TriggerPrototypeAssist::instance()->update($params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('app', $status ? 'Disable Success' : 'Enable Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750302, $e->getMessage());
        }
    }

    /**
     * @param $triggerId
     * @param int $status
     * @return Result
     */
    public function setTriggerDiscoverStatus($triggerId, int $status): Result
    {
        $triggerId = filter_integer((array)$triggerId);
        $triggers = Triggers::findOne(['triggerid' => $triggerId, 'flags' => [PRS_FLAG_DISCOVERY_PROTOTYPE]]);
        if (empty($triggers)) {
            return $this->error(60750004);
        }
        $status = $status ? 1 : 0;
        Triggers::updateAll(['discover' => $status], ['triggerid' => $triggerId]);
        return $this->success([], t('app', $status ? 'Disable Success' : 'Enable Success'));
    }

    /**
     * @param int|array $triggerId
     * @return Result
     */
    public function deleteTriggerPrototypes($triggerId): Result
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = TriggerPrototypeAssist::instance()->delete(filter_integer((array)$triggerId));
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Delete Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750303, $e->getMessage());
        }
    }

    /**
     * @param array $params
     * @return Result
     */
    public function popupTriggerExpr(array $params): Result
    {
        $component = new PopupTriggerExpr(['input' => $params]);
        return $component->doAction();
    }

    /**
     * @param  array   $params
     * @param  boolean $internal
     * @return Result
     */
    public function update(array $params, bool $internal = true): Result
    {
        $triggerId = $params['triggerid'] ?? 0;
        return $this->updateTrigger((int) $triggerId,$params, $internal);
    }

    /**
     * @param  array  $triggerIds
     * @return Result
     */
    public function delete(array $triggerIds): Result
    {
        return $this->deleteTriggers($triggerIds);
    }
}