<?php

namespace app\customs\zapi\services;

use app\common\base\BaseService;
use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\customs\zapi\models\search\item\ItemPrototypeSearch;
use app\customs\zapi\models\search\item\ItemSearch;
use app\customs\zapi\models\search\TemplateSearch;
use app\customs\zapi\models\search\trigger\TriggerPrototypeSearch;
use app\customs\zapi\models\search\trigger\TriggerSearch;
use yii\db\Exception;

/**
 * Class MonitorService
 * @package app\customs\zapi\services
 */
class MonitorService extends BaseService
{
    /**
     * @param int $templateId
     * @return Result
     * @throws Exception
     */
    public function getTemplateItems(int $templateId): Result
    {
        $search = new ItemSearch();
        $search->templateids = $templateId;
        $search->sortorder = 'name';
        $search->is_all = true;
        $provider = $search->search([
            'output' => ['itemid', 'name', 'key_', 'flags', 'type', 'value_type', 'status'],
            'selectHosts' => ['name'],
            'webitems' => true
        ]);
        return $this->success($provider->getModels());
    }

    /**
     * @param int $discoveryId
     * @param array $params
     * @return Result
     * @throws Exception
     */
    public function getItemPrototypes(int $discoveryId, array $params = []): Result
    {
        $search = new ItemPrototypeSearch();
        $search->discoveryids = $discoveryId;
        $search->sortorder = 'name';
        $search->is_all = true;
        $provider = $search->search([
            'output' => ['hostid', 'itemid', 'name', 'key_', 'flags', 'type', 'value_type', 'status'],
            'selectHosts' => ['name'],
            'templated' => isset($params['templated_hosts']) ? true : null
        ]);
        return $this->success($provider->getModels());
    }

    /**
     * @param int $templateId
     * @param array $params
     * @return Result
     * @throws Exception
     */
    public function getTemplateTriggers(int $templateId, array $params = []): Result
    {
        $search = new TriggerSearch();
        $search->templateids = $templateId;
        $search->is_all = true;
        $provider = $search->search([
            'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'state'],
            'selectHosts' => ['name'],
            'selectDependencies' => ['triggerid', 'expression', 'description'],
            'expandDescription' => true,
            'expandExpression' => true
        ]);
        $models = $provider->getModels();
        ArrayHelper::multisort($models, 'description');
        return $this->success($models);
    }

    /**
     * @param int $discoveryId
     * @param array $params
     * @return Result
     * @throws Exception
     */
    public function getTriggerPrototypes(int $discoveryId, array $params = []): Result
    {
        $search = new TriggerPrototypeSearch();
        $search->discoveryids = $discoveryId;
        $search->is_all = true;
        $provider = $search->search([
            'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'state'],
            'selectHosts' => ['name'],
            'selectDependencies' => ['triggerid', 'expression', 'description'],
            'expandDescription' => true,
            'expandExpression' => true
        ]);
        $models = $provider->getModels();
        ArrayHelper::multisort($models, 'description');
        return $this->success($models);
    }

    /**
     * @param int $hostId
     * @return Result
     * @throws Exception
     */
    public function getHostItems(int $hostId): Result
    {
        $search = new ItemSearch();
        $search->hostids = $hostId;
        $search->sortorder = 'name';
        $search->is_all = true;
        $provider = $search->search([
            'output' => ['itemid', 'name', 'key_', 'flags', 'type', 'value_type', 'status'],
            'selectHosts' => ['name'],
            'webitems' => true
        ]);
        return $this->success($provider->getModels());
    }

    /**
     * @param int $hostId
     * @param array $params
     * @return Result
     * @throws Exception
     */
    public function getHostTriggers(int $hostId, array $params = []): Result
    {
        $search = new TriggerSearch();
        $search->hostids = $hostId;
        $search->is_all = true;
        $options = [
            'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'state'],
            'selectHosts' => ['name'],
            'selectDependencies' => ['triggerid', 'expression', 'description'],
            'expandDescription' => true,
            'expandExpression' => true
        ];
        if (isset($params['with_monitored_triggers'])) {
            $options['monitored'] = true;
        }

        if (isset($params['normal_only'])) {
            $options['filter']['flags'] = PRS_FLAG_DISCOVERY_NORMAL;
        }
        $provider = $search->search($options);
        $models = $provider->getModels();
        ArrayHelper::multisort($models, 'description');
        return $this->success($models);
    }
}