<?php

namespace app\customs\zapi\models\search\trigger;

use app\common\helpers\ArrayHelper;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\components\RelationMap;
use app\customs\zapi\models\search\BaseSearch;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\zbx\Hstgrp;
use yii\db\Query;

/**
 * Class BaseTriggerSearch
 * @package app\customs\zapi\models\search\trigger
 */
class BaseTriggerSearch extends BaseSearch
{
    public $groupids = null;
    public $templateids = null;
    public $hostids = null;
    public $triggerids = null;
    public $itemids = null;
    public $functions = null;
    public $inherited = null;
    public $dependent = null;
    public $templated = null;
    public $discoveryids = null;
    public $monitored = null;
    public $active = null;
    public $maintenance = null;
    public $withUnacknowledgedEvents = null;
    public $withAcknowledgedEvents = null;
    public $withLastEventUnacknowledged = null;
    public $skipDependent = null;
    public $nopermissions = null;
    public $editable = false;
    // timing
    public $lastChangeSince = null;
    public $lastChangeTill = null;
    // filter
    public $group = null;
    public $host = null;
    public $only_true = null;
    public $min_severity = null;
    public $evaltype = TAG_EVAL_TYPE_AND_OR;
    public $tags = null;
    public $filter = null;
    public $search = null;
    public $searchByAny = null;
    public $startSearch = false;
    public $excludeSearch = false;
    public $searchWildcardsEnabled = null;
    // output
    public $expandDescription = null;
    public $expandComment = null;
    public $expandExpression = null;
    public $output = API_OUTPUT_EXTEND;
    public $selectGroups = null;
    public $selectHostGroups = null;
    public $selectTemplateGroups = null;
    public $selectHosts = null;
    public $selectItems = null;
    public $selectFunctions = null;
    public $selectDependencies = null;
    public $selectDiscoveryRule = null;
    public $selectLastEvent = null;
    public $selectTags = null;
    public $selectTriggerDiscovery = null;
    public $countOutput = false;
    public $groupCount = false;
    public $preservekeys = false;
    public $sortfield = '';
    public $sortorder = '';
    public $limit = null;
    public $limitSelects = null;
    public $filter_value = -1;

    public $is_all = false;

    public function rules()
    {
        return [
            [[
                'groupids', 'templateids', 'hostids', 'triggerids', 'itemids', 'functions', 'inherited', 'dependent', 'templated',
                'monitored', 'active', 'maintenance', 'withUnacknowledgedEvents', 'withAcknowledgedEvents', 'withLastEventUnacknowledged',
                'skipDependent', 'nopermissions', 'editable', 'discoveryids',
                // timing
                'lastChangeSince', 'lastChangeTill',
                // filter
                'group', 'host', 'only_true', 'min_severity', 'evaltype', 'tags', 'filter', 'search', 'searchByAny', 'startSearch',
                'excludeSearch', 'searchWildcardsEnabled',
                // output
                'expandDescription', 'expandComment', 'expandExpression', 'output', 'selectGroups', 'selectHostGroups', 'selectTemplateGroups', 'selectHosts',
                'selectItems', 'selectFunctions', 'selectDependencies', 'selectDiscoveryRule', 'selectLastEvent', 'selectTags', 'selectTriggerDiscovery', 'countOutput', 'groupCount',
                'preservekeys', 'sortfield', 'sortorder', 'limit', 'limitSelects', 'filter_value', 'is_all'
            ], 'safe']
        ];
    }

    /**
     * @param array $result
     * @return array
     */
    protected function addRelatedObjects(array $result): array
    {
        $result = parent::addRelatedObjects($result);
        $result = ArrayHelper::index($result, 'triggerid');
        $triggerids = array_keys($result);;

        // adding groups
        $this->addRelatedGroups($result, 'selectGroups');
        $this->addRelatedGroups($result, 'selectHostGroups');
        $this->addRelatedGroups($result, 'selectTemplateGroups');

        // adding hosts
        if ($this->selectHosts !== null && $this->selectHosts != API_OUTPUT_COUNT) {
            $rows = (new Query())->select(['f.triggerid', 'i.hostid'])
                ->from(['f' => 'functions', 'i' => 'items'])
                ->where(['f.triggerid' => $triggerids])
                ->andWhere('f.itemid=i.itemid')
                ->all();
            $relationMap = new RelationMap();
            foreach ($rows as $relation) {
                $relationMap->addRelation($relation['triggerid'], $relation['hostid']);
            }

            $hosts = Hosts::find()->select($this->selectHosts)
                ->addSelect(['hostid'])
                ->where(['hostid' => $relationMap->getRelatedIds()])
                ->andWhere(['status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE]])
                ->indexBy('hostid')
                ->asArray()
                ->all();
            if (!is_null($this->limitSelects)) {
                order_result($hosts, 'host');
            }
            $result = $relationMap->mapMany($result, $hosts, 'hosts', $this->limitSelects);
        }

        // adding functions
        if ($this->selectFunctions !== null && $this->selectFunctions != API_OUTPUT_COUNT) {
            $functions = (new Query())
                ->select($this->outputExtend($this->selectFunctions, ['triggerid', 'functionid']))
                ->from(['functions'])
                ->where(['triggerid' => $triggerids])
                ->indexBy('functionid')
                ->all();

            // Rename column 'name' to 'function'.
            $function = reset($functions);
            if ($function && array_key_exists('name', $function)) {
                $functions = CArrayHelper::renameObjectsKeys($functions, ['name' => 'function']);
            }

            $relationMap = $this->createRelationMap($functions, 'triggerid', 'functionid');

            $functions = $this->unsetExtraFields($functions, ['triggerid', 'functionid'], $this->selectFunctions);
            $result = $relationMap->mapMany($result, $functions, 'functions');
        }

        // Adding trigger tags.
        if ($this->selectTags !== null && $this->selectTags != API_OUTPUT_COUNT) {
            $tags = (new Query())
                ->select($this->outputExtend($this->selectTags, ['triggertagid', 'triggerid']))
                ->from(['trigger_tag'])
                ->where(['triggerid' => $triggerids])
                ->indexBy('triggertagid')
                ->all();

            $relationMap = $this->createRelationMap($tags, 'triggerid', 'triggertagid');
            $tags = $this->unsetExtraFields($tags, ['triggertagid', 'triggerid'], []);
            $result = $relationMap->mapMany($result, $tags, 'tags');
        }

        return $result;
    }

    /**
     * Adds related host or template groups requested by "select*" options to the resulting object set.
     *
     * @param array $options [IN] Original input options.
     * @param array $result [IN/OUT] Result output.
     * @param string $option [IN] Possible values:
     *                               - "selectGroups" (deprecated);
     *                               - "selectHostGroups";
     *                               - "selectTemplateGroups".
     */
    private function addRelatedGroups(array &$result, string $option): void
    {
        if ($this->{$option} === null || $this->{$option} === API_OUTPUT_COUNT) {
            return;
        }

        $rows = (new Query())->select(['f.triggerid', 'hg.groupid'])
            ->from(['f' => 'functions', 'i' => 'items', 'hg' => 'hosts_groups'])
            ->where(['f.triggerid' => array_keys($result)])
            ->andWhere('f.itemid=i.itemid')
            ->andWhere('i.hostid=hg.hostid')
            ->all();
        $relationMap = new RelationMap();
        foreach ($rows as $relation) {
            $relationMap->addRelation($relation['triggerid'], $relation['groupid']);
        }

        $groups = [];
        switch ($option) {
            case 'selectGroups':
                $output_tag = 'groups';
                $groups = Hstgrp::find()->select($this->{$option})
                    ->where(['groupid' => $relationMap->getRelatedIds()])
                    ->indexBy('groupid')
                    ->asArray()->all();
                break;

            case 'selectHostGroups':
                $output_tag = 'hostgroups';
                $groups = Hstgrp::find()->select($this->{$option})
                    ->where(['groupid' => $relationMap->getRelatedIds()])
                    ->andWhere(['type' => HOST_GROUP_TYPE_HOST_GROUP])
                    ->indexBy('groupid')
                    ->asArray()->all();
                break;

            case 'selectTemplateGroups':
                $groups = Hstgrp::find()->select($this->{$option})
                    ->where(['groupid' => $relationMap->getRelatedIds()])
                    ->andWhere(['type' => HOST_GROUP_TYPE_TEMPLATE_GROUP])
                    ->indexBy('groupid')
                    ->asArray()->all();
                $output_tag = 'templategroups';
                break;
        }

        $result = $relationMap->mapMany($result, $groups, $output_tag);
    }
}