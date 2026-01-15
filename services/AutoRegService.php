<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\customs\zapi\services\assist\AutoRegAssist;
use Yii;
use yii\base\Exception;

/**
 * 自动注册
 * Class AutoRegService
 * @package app\customs\zapi\services
 */
class AutoRegService extends BaseService
{
    /**
     * @return Result
     */
    public function getInfo(): Result
    {
        $data = AutoRegAssist::instance()->get();
        return $this->success($data);
    }

    /**
     * @param array $params
     * @return Result
     */
    public function saveConfig(array $params): Result
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = AutoRegAssist::instance()->update($params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('zapi', 'General settings "{label}" updated successfully', ['label' => t('zapi', 'Auto Register')]));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(10009999, $e->getMessage());
        }
    }
}