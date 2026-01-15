<?php

namespace app\customs\zapi\services;

use app\common\base\BaseService;
use app\common\components\Result;
use app\customs\zapi\common\data\CItemData;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\CSettingsHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\components\data\ItemBatchFormRequestData;
use app\customs\zapi\components\data\ItemFormRequestData;
use app\customs\zapi\components\data\ItemPrototypeRequestData;
use app\customs\zapi\components\data\ItemPrototypeSearchRequestData;
use app\customs\zapi\components\data\ItemRequestData;
use app\customs\zapi\components\data\ItemSearchRequestData;
use app\customs\zapi\components\data\TriggerFormRequestData;
use app\customs\zapi\models\search\item\ItemPrototypeSearch;
use app\customs\zapi\models\search\item\ItemSearch;
use app\customs\zapi\services\assist\ItemAssist;
use app\customs\zapi\services\assist\ItemMassCheckNowAssist;
use app\customs\zapi\services\assist\ItemPrototypeAssist;
use app\customs\zapi\services\assist\ItemTestEditAssist;
use app\customs\zapi\services\assist\ItemTestGetValueAssist;
use app\customs\zapi\services\assist\ItemTestSendAssist;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Items;
use app\modules\libzbx\models\zbx\ItemTag;
use app\modules\libzbx\models\zbx\Valuemap;
use app\modules\libzbx\models\zbx\ValuemapMapping;
use Yii;
use yii\base\Exception;
use yii\db\Query;

/**
 * Class ItemService
 * @package app\customs\zapi\services
 */
class ItemService extends BaseService
{
    /**
     * 监控项列表
     * @param array $params
     * @return Result
     * @throws \yii\db\Exception
     */
    public function getList(array $params = []): Result
    {
        $search = new ItemSearch();
        $search->visible = true;
        $requestData = new ItemSearchRequestData(['data' => $params]);
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
        $requestData = new ItemFormRequestData(['data' => $params]);
        if (!$requestData->isSuccess()) {
            return $requestData->getResult();
        }
        $item = $requestData->getData();
        $result = ItemHelper::getItemFormData($item);
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
    public function createItem(array $params = [], bool $internal = true): Result
    {
        $item = [];
        if (!$internal) {
            $requestData = new ItemRequestData(['data' => $params]);
            if (!$requestData->isSuccess()) {
                return $requestData->getResult();
            }
            $item = $requestData->getData();
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = ItemAssist::instance()->create($item ?: $params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Create Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750204, $e->getMessage());
        }
    }

    /**
     * @param int $itemId
     * @param array $params
     * @param bool $internal
     * @return Result
     */
    public function updateItem(int $itemId, array $params = [], bool $internal = true): Result
    {
        $item = [];
        $params['itemid'] = $itemId;
        if (!$internal) {
            $requestData = new ItemRequestData(['data' => $params, 'action' => 'update']);
            if (!$requestData->isSuccess()) {
                return $requestData->getResult();
            }
            $item = $requestData->getData();
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = ItemAssist::instance()->update($item ?: $params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Update Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750205, $e->getMessage());
        }
    }

    /**
     * 批量更新
     * @param $itemIds
     * @param $params
     * @return Result
     */
    public function batchUpdateItems($itemIds, $params): Result
    {
        $params['ids'] = $itemIds;
        $params['prototype'] = false;
        $requestData = new ItemBatchFormRequestData(['data' => $params]);
        if (!$requestData->isSuccess()) {
            return $requestData->getResult();
        }
        $items = $requestData->getData();
        if (empty($items)) {
            return $this->success([], t('act', 'Update Success'));
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = ItemAssist::instance()->update($items);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Update Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750205, $e->getMessage());
        }
    }

    /**
     * @param $itemId
     * @param int $status
     * @return Result
     */
    public function setItemStatus($itemId, int $status): Result
    {
        $itemIds = filter_integer((array)$itemId);
        $params = array_map(function ($itemId) use ($status) {
            return [
                'itemid' => $itemId,
                'status' => $status
            ];
        }, $itemIds);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = ItemAssist::instance()->update($params);
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
     * @param int|array $itemId
     * @return Result
     */
    public function deleteItems($itemId): Result
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = ItemAssist::instance()->delete(filter_integer((array)$itemId));
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Delete Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750206, $e->getMessage());
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
        $search = new ItemPrototypeSearch();
        $search->visible = true;
        $requestData = new ItemPrototypeSearchRequestData(['data' => $params]);
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
    public function getPrototypeInfo(array $params): Result
    {
        $requestData = new ItemFormRequestData(['data' => $params]);
        if (!$requestData->isSuccess()) {
            return $requestData->getResult();
        }
        $item = $requestData->getData();
        $result = ItemHelper::getItemPrototypeFormData($item);
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
    public function createItemPrototype(array $params = [], bool $internal = true): Result
    {
        $item = [];
        if (!$internal) {
            $requestData = new ItemPrototypeRequestData(['data' => $params]);
            if (!$requestData->isSuccess()) {
                return $requestData->getResult();
            }
            $item = $requestData->getData();
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = ItemPrototypeAssist::instance()->create($item ?: $params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Create Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750204, $e->getMessage());
        }
    }

    /**
     * @param int $itemId
     * @param array $params
     * @param bool $internal
     * @return Result
     */
    public function updateItemPrototype(int $itemId, array $params = [], bool $internal = true): Result
    {
        $item = [];
        $params['itemid'] = $itemId;
        if (!$internal) {
            $requestData = new ItemPrototypeRequestData(['data' => $params, 'action' => 'update']);
            if (!$requestData->isSuccess()) {
                return $requestData->getResult();
            }
            $item = $requestData->getData();
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = ItemPrototypeAssist::instance()->update($item ?: $params);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Update Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750205, $e->getMessage());
        }
    }

    /**
     * 批量更新
     * @param $itemIds
     * @param $params
     * @return Result
     */
    public function batchUpdateItemPrototypes($itemIds, $params): Result
    {
        $params['ids'] = $itemIds;
        $params['prototype'] = true;
        $requestData = new ItemBatchFormRequestData(['data' => $params]);
        if (!$requestData->isSuccess()) {
            return $requestData->getResult();
        }
        $items = $requestData->getData();
        if (empty($items)) {
            return $this->success([], t('act', 'Update Success'));
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = ItemPrototypeAssist::instance()->update($items);
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Update Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750205, $e->getMessage());
        }
    }

    /**
     * @param $itemId
     * @param int $status
     * @return Result
     */
    public function setItemPrototypeStatus($itemId, int $status): Result
    {
        $itemIds = filter_integer((array)$itemId);
        $params = array_map(function ($itemId) use ($status) {
            return [
                'itemid' => $itemId,
                'status' => $status
            ];
        }, $itemIds);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = ItemPrototypeAssist::instance()->update($params);
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
     * @param $itemId
     * @param int $status
     * @return Result
     */
    public function setItemDiscoverStatus($itemId, int $status): Result
    {
        $itemIds = filter_integer((array)$itemId);
        $params = array_map(function ($itemId) use ($status) {
            return [
                'itemid' => $itemId,
                'discover' => $status
            ];
        }, $itemIds);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = ItemPrototypeAssist::instance()->update($params);
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
     * @param int|array $itemId
     * @return Result
     */
    public function deleteItemPrototypes($itemId): Result
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = ItemPrototypeAssist::instance()->delete(filter_integer((array)$itemId));
            if (!$result->isSuccess()) {
                $transaction->rollBack();
                return $result;
            }
            $transaction->commit();
            return $this->success($result->getData(), t('act', 'Delete Success'));
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->error(60750206, $e->getMessage());
        }
    }

    /**
     * @param $params
     * @return Result
     */
    public function testItemForm($params): Result
    {
        return ItemTestEditAssist::instance()->getTestView($params);
    }


    /**
     * @param $params
     * @return Result
     */
    public function testItem($params): Result
    {
        return ItemTestSendAssist::instance()->testSend($params);
    }

    /**
     * @param $params
     * @return Result
     */
    public function testGetValue($params): Result
    {
        return ItemTestGetValueAssist::instance()->getItemValue($params);
    }

    /**
     * @param $params
     * @return Result
     */
    public function checkNow($params): Result
    {
        return ItemMassCheckNowAssist::instance()->checkNow($params);
    }

    /**
     * @param int $itemType
     * @return Result
     */
    public function getHelpItems(int $itemType): Result
    {
        return $this->success(['list' => CItemData::getByType($itemType)]);
    }

    /**
     * @return Result
     */
    public function getOptions(): Result
    {
        $hasHelpItem = array_keys(CItemData::KEYS_BY_TYPE);
        $types = ItemHelper::itemType2str();
        $itemTypes = [];
        foreach ($types as $type => $name) {
            $itemTypes[] = [
                'type' => $type,
                'name' => $name,
                'interface_type' => (int)ItemHelper::itemTypeInterface($type),
                'visible' => (int)(ITEM_TYPE_HTTPTEST != $type),
                'has_help_items' => (int)in_array($type, $hasHelpItem),
                'discover_rule' => (int)!in_array($type, [ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMPTRAP])
            ];
        }
        $valueTypes = [
            ['value' => ITEM_VALUE_TYPE_UINT64, 'label' => t('zapi', 'Numeric (unsigned)')],
            ['value' => ITEM_VALUE_TYPE_FLOAT, 'label' => t('zapi', 'Numeric (float)')],
            ['value' => ITEM_VALUE_TYPE_STR, 'label' => t('zapi', 'Character')],
            ['value' => ITEM_VALUE_TYPE_LOG, 'label' => t('zapi', 'Log')],
            ['value' => ITEM_VALUE_TYPE_TEXT, 'label' => t('zapi', 'Text')]
        ];

        $postType = [
            ['value' => PRS_POSTTYPE_RAW, 'label' => t('zapi', 'Raw data')],
            ['value' => PRS_POSTTYPE_JSON, 'label' => t('zapi', 'JSON data')],
            ['value' => PRS_POSTTYPE_XML, 'label' => t('zapi', 'XML data')],
        ];

        $requestMethod = [
            ['value' => HTTPCHECK_REQUEST_GET, 'label' => t('zapi', 'GET')],
            ['value' => HTTPCHECK_REQUEST_POST, 'label' => t('zapi', 'POST')],
            ['value' => HTTPCHECK_REQUEST_PUT, 'label' => t('zapi', 'PUT')],
            ['value' => HTTPCHECK_REQUEST_HEAD, 'label' => t('zapi', 'HEAD')],
        ];

        $authType = [
            ['value' => ITEM_AUTHTYPE_PASSWORD, 'label' => t('zapi', 'Password')],
            ['value' => ITEM_AUTHTYPE_PUBLICKEY, 'label' => t('zapi', 'Public key')],
        ];

        return $this->success([
            'types' => $itemTypes,
            'value_type' => $valueTypes,
            'post_type' => $postType,
            'request_method' => $requestMethod,
            'auth_type' => $authType,
            'check_now_allow_types' => ItemHelper::checkNowAllowedTypes(),
            'preprocessing_types' => ItemHelper::preprocessingTypes()
        ]);
    }

    /**
     * @param $hostids
     * @param string $context
     * @return Result
     */
    public function getTemplateValueMap($hostids, $context = 'template'): Result
    {
        $records = [];
        $hosts = Hosts::find()->select(['hostid', 'name'])
            ->where(['hostid' => $hostids])
            ->andFilterWhere($context === 'host' ? [] : ['status' => 3])
            ->indexBy('hostid')
            ->all();

        $db_valuemaps = Valuemap::find()->select(['valuemapid', 'name', 'hostid'])
            ->where(['hostid' => $hostids])
            ->indexBy('valuemapid')
            ->asArray()->all();

        $db_mappings = ValuemapMapping::find()->select(['type', 'value', 'newvalue', 'valuemapid', 'valuemap_mappingid', 'sortorder'])
            ->where(['valuemapid' => array_keys($db_valuemaps)])
            ->asArray()
            ->all();

        CArrayHelper::sort($db_mappings, [['field' => 'sortorder', 'order' => PRS_SORT_UP]]);

        foreach ($db_mappings as $db_mapping) {
            $valuemapid = $db_mapping['valuemapid'];
            unset($db_mapping['valuemap_mappingid'], $db_mapping['valuemapid'], $db_mapping['sortorder']);

            $db_valuemaps[$valuemapid]['mappings'][] = $db_mapping;
        }

        foreach ($db_valuemaps as $db_valuemap) {
            $valuemap = [
                'id' => $db_valuemap['valuemapid'],
                'hostname' => $hosts[$db_valuemap['hostid']]['name'],
                'name' => $db_valuemap['name'],
                'mappings' => array_values($db_valuemap['mappings']),
            ];

            $records[$db_valuemap['valuemapid']] = $valuemap;
        }

        $records = array_column($records, null, 'id');
        CArrayHelper::sort($records, ['name', 'hostname']);
        return $this->success(['list' => array_values($records)]);
    }

    /**
     * @param $hostId
     * @return Result
     */
    public function getHostTags($hostId): Result
    {
        $apps = (new Query())->select(['label' => 'it.value', 'value' => 'min(value)', 'count' => 'count(1)'])
            ->from([
                'it' => ItemTag::tableName(),
                'i' => Items::tableName(),
            ])
            ->where('{{it}}.itemid={{i}}.itemid')
            ->andWhere([
                'i.hostid' => $hostId,
                'i.flags'  => [0, 4],
            ])
            ->andWhere(['!=', 'value', ''])
            ->groupBy('it.value')
            ->all();
        return $this->success($apps);
    }
}