<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\customs\zapi\common\helpers\RegexHelper;
use app\customs\zapi\models\search\RegexpSearch;
use app\customs\zapi\services\assist\RegexpAssist;
use Yii;
use yii\base\Exception;

class RegexpService extends BaseService
{
    /**
     * 正则表达式列表
     * @param array $params
     * @return Result
     * @throws \yii\db\Exception
     */
    public function getList(array $params = []): Result
    {
        $search = new RegexpSearch();
        $search->selectExpressions = ['expression', 'expression_type'];
        $provider = $search->search($params);
        return $this->success([
            'total' => $provider->getTotalCount(),
            'list' => $provider->getModels(),
        ]);
    }

    /**
     * @param $regexpId
     * @return Result
     * @throws \yii\db\Exception
     */
    public function getInfo($regexpId): Result
    {
        $search = new RegexpSearch();
        $search->selectExpressions = ['expressionid', 'regexpid', 'expression', 'expression_type', 'exp_delimiter', 'case_sensitive'];
        $provider = $search->search(['regexpids' => $regexpId]);
        $models = $provider->getModels();
        if (empty($models)) {
            return $this->error(60750004);
        }
        return $this->success(current($models));
    }

    /**
     * @param array $params
     * @return Result
     */
    public function createRegexp(array $params = []): Result
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = RegexpAssist::instance()->create($params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Create Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750801, $e->getMessage());
        }
    }

    /**
     * @param $regexpid
     * @param array $params
     * @return Result
     */
    public function updateRegexp($regexpid, array $params = []): Result
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $params['regexpid'] = $regexpid;
            $result = RegexpAssist::instance()->update($params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Update Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750802, $e->getMessage());
        }
    }

    /**
     * @param $regexpIds
     * @return Result
     */
    public function deleteRegexp($regexpIds): Result
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = RegexpAssist::instance()->delete(filter_integer((array)$regexpIds));
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Delete Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750803, $e->getMessage());
        }
    }

    /**
     * @param $testString
     * @param array $expressions
     * @return Result
     */
    public function testRegex($testString, array $expressions): Result
    {
        $result = RegexpAssist::instance()->testRegex($testString, $expressions);
        return $this->success($result);
    }

    /**
     * @return Result
     */
    public function getOptions(): Result
    {
        $types =  RegexHelper::expression_type2str();
        $regTypes = [];
        foreach ($types as $type => $name) {
            $regTypes[] = [
                'value' => $type,
                'label' => $name,
            ];
        }
        return $this->success([
            'types' => $regTypes,
        ]);
    }
}