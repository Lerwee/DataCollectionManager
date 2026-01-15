<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\components\data\DiscoverRuleRequestData;
use app\customs\zapi\components\data\DiscoverRuleSearchRequestData;
use app\customs\zapi\components\data\ItemFormRequestData;
use app\customs\zapi\models\search\DiscoverRuleSearch;
use app\customs\zapi\services\assist\DiscoverRuleAssist;
use app\modules\libzbx\models\Items;
use Yii;
use yii\base\Exception;

/**
 * Class DiscoverRuleService
 * @package app\customs\zapi\services
 */
class DiscoverRuleService extends BaseService
{
    /**
     * 监控项列表
     * @param array $params
     * @return Result
     * @throws \yii\db\Exception
     */
    public function getList(array $params = []): Result
    {
        $search = new DiscoverRuleSearch();
        $search->visible = true;
        $requestData = new DiscoverRuleSearchRequestData(['data' => $params]);
        if (!$requestData->isSuccess()) {
            return $requestData->getResult();
        }
        $provider = $search->search($requestData->getData());
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
    public function getInfo(array $params): Result
    {
        $params['is_discovery_rule'] = true;
        $requestData = new ItemFormRequestData(['data' => $params]);
        if (!$requestData->isSuccess()) {
            return $requestData->getResult();
        }
        $item = $requestData->getData();
        $result = DiscoverRuleHelper::getDiscoverRuleFromData($item);
        if (empty($result)) {
            return $this->error(60750004);
        }
        return $this->success($result);
    }

    /**
     * @param array $params
     * @return Result
     */
    public function getCreateForm(array $params = []): Result
    {
        $params['is_discovery_rule'] = true;
        $requestData = new ItemFormRequestData(['data' => $params]);
        if (!$requestData->isSuccess()) {
            return $requestData->getResult();
        }
        $data = $requestData->getData();
        $result = ItemHelper::formatFormData([], $data);
        return $this->success($result);
    }

    /**
     * @param array $params item or [item1, item2]
     * @param bool $internal 是否内部调用（即原生API），当为false时，需要格式化
     * @return Result
     */
    public function createDiscoverRule(array $params = [], bool $internal = true): Result
    {
        $discoverRule = [];
        if (!$internal) {
            $requestData = new DiscoverRuleRequestData(['data' => $params]);
            if (!$requestData->isSuccess()) {
                return $requestData->getResult();
            }
            $discoverRule = $requestData->getData();
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = DiscoverRuleAssist::instance()->create($discoverRule ?: $params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Create Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750601, $e->getMessage());
        }
    }

    /**
     * @param int $itemId
     * @param array $params
     * @param bool $internal
     * @return Result
     */
    public function updateDiscoverRule(int $itemId, array $params = [], bool $internal = true): Result
    {
        $discoverRule = [];
        $params['itemid'] = $itemId;
        if (!$internal) {
            $requestData = new DiscoverRuleRequestData(['data' => $params, 'action' => 'update']);
            if (!$requestData->isSuccess()) {
                return $requestData->getResult();
            }
            $discoverRule = $requestData->getData();
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = DiscoverRuleAssist::instance()->update($discoverRule ?: $params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Update Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750602, $e->getMessage());
        }
    }

    /**
     * @param $itemId
     * @param int $status
     * @return Result
     * @throws \yii\db\Exception
     */
    public function setStatus($itemId, int $status): Result
    {
        $itemId = filter_integer((array)$itemId);
        $items = DiscoverRuleHelper::getDiscoverRules([
            'output' => ['triggerid', 'status'],
            'itemids' => $itemId,
        ]);
        if (empty($items)) {
            return $this->error(60750004);
        }
        $status = $status ? 1 : 0;
        Items::updateAll(['status' => $status], ['itemid' => $itemId]);
        return $this->success([], t('app', $status ? 'Disable Success' : 'Enable Success'));
    }

    /**
     * @param int|array $itemId
     * @return Result
     */
    public function deleteDiscoverRules($itemId): Result
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = DiscoverRuleAssist::instance()->delete(filter_integer((array)$itemId));
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Delete Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750603, $e->getMessage());
        }
    }
}