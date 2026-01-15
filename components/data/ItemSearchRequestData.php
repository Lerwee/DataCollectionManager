<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\modules\libzbx\models\Hosts;
use yii\db\Query;

/**
 * Class ItemSearchRequestData
 * @package app\customs\zapi\components\data
 */
class ItemSearchRequestData extends RequestData
{
    /**
     * @return Result
     */
    public function validate(): Result
    {
        $filter_groupids = $this->getSubGroups($this->getRequest('filter_groupids', []), $this->getRequest('context'));
        $filter_hostids = $this->getRequest('filter_hostids', []);
        $filter_itemids = $this->getRequest('filter_itemids', []);
        $filter_tag_values = $this->getRequest('filter_tag_values', []);

        $sortField = $this->getRequest('sort', 'name');
        $sortOrder = $this->getRequest('sortorder', PRS_SORT_UP);

        // Filter and subfilter tags.
        $filter_evaltype = $this->getRequest('filter_evaltype', TAG_EVAL_TYPE_AND_OR);
        $filter_tags = [];
        foreach ($this->getRequest('filter_tags', []) as $tag) {
            $tag = [
                'tag' => $tag['tag'] ?? '',
                'value' => $tag['value'] ?? '',
                'operator' => $tag['operator'] ?? 0,
            ];
            if ($tag['tag'] === '' && $tag['value'] === '') {
                continue;
            }
            $filter_tags[] = $tag;
        }
        if (count($filter_hostids) == 1) {
            $hostid = reset($filter_hostids);
        } else {
            $hostid = null;
        }

        $data = [
            'form' => $this->getRequest('form'),
            'sort' => $sortField,
            'sortorder' => $sortOrder,
            'hostid' => $hostid,
            'context' => $this->getRequest('context')
        ];

        // items
        $options = [
            'search' => [],
            'output' => [
                'itemid', 'type', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status', 'value_type', 'error',
                'templateid', 'flags', 'state', 'master_itemid'
            ],
            'templated' => ($data['context'] === 'template'),
            'editable' => true,
            'selectHosts' => API_OUTPUT_EXTEND,
            'selectTriggers' => ['triggerid'],
            'selectDiscoveryRule' => API_OUTPUT_EXTEND,
            'selectItemDiscovery' => ['ts_delete'],
            'selectTags' => ['tag', 'value'],
            'sortfield' => $sortField,
            'evaltype' => $filter_evaltype,
            'tags' => $filter_tags,
        ];

        if ($filter_hostids) {
            $options['hostids'] = filter_integer((array)$filter_hostids);
        }
        if ($filter_groupids) {
            $options['groupids'] = $filter_groupids;
        }
        if ($filter_itemids) {
            $options['itemids'] = filter_integer((array)$filter_itemids);
        }
        if ($filter_tag_values) {
            $options['tag_values'] = (array)$filter_tag_values;
        }
        $filter_name = $this->getRequest('filter_name', '');
        $filter_type = $this->getRequest('filter_type', -1);
        $filter_key = $this->getRequest('filter_key', '');
        $filter_snmp_oid = $this->getRequest('filter_snmp_oid', '');
        $filter_value_type = $this->getRequest('filter_value_type', -1);
        if (isset($filter_name) && !prs_empty($filter_name)) {
            $options['search']['name'] = $filter_name;
        }
        if (isset($filter_type) && !prs_empty($filter_type) && $filter_type != -1) {
            $options['filter']['type'] = $filter_type;
        }
        if (isset($filter_key) && !prs_empty($filter_key)) {
            $options['search']['key_'] = $filter_key;
        }
        if (isset($filter_snmp_oid) && !prs_empty($filter_snmp_oid)) {
            $options['filter']['snmp_oid'] = $filter_snmp_oid;
        }
        if (isset($filter_value_type) && !prs_empty($filter_value_type)
            && $filter_value_type != -1) {
            $options['filter']['value_type'] = $filter_value_type;
        }
        $filter_valuemapids = $this->getRequest('filter_valuemapids', []);
        if (array_key_exists('hostids', $options) && $filter_valuemapids) {
            $templates = Hosts::getTemplateIds($filter_hostids);
            $allTemplateIds = ArrayHelper::getColumn($templates, 'templateid');

            $valuemap_names = (new Query())->from(['valuemap'])->select(['name'])
                ->where(['valuemapid' => $filter_valuemapids])
                ->column();

            $options['filter']['valuemapid'] = (new Query())->from(['valuemap'])->select(['valuemapid'])
                ->where(['hostid' => $allTemplateIds])
                ->andWhere(['name' => $valuemap_names])
                ->column();
        }

        /*
         * Trapper and SNMP trap items contain zeros in "delay" field and, if no specific type is set, look in item types
         * other than trapper and SNMP trap that allow zeros. For example, when a flexible interval is used. Since trapper
         * and SNMP trap items contain zeros, but those zeros should not be displayed, they cannot be filtered by entering
         * either zero or any other number in filter field.
         */
        if (isset($this->data['filter_delay'])) {
            $filter_delay = $this->getRequest('filter_delay');
            $filter_type = $this->getRequest('filter_type');
            $filter_key = $this->getRequest('filter_key');
            if ($filter_delay !== '') {
                if ($filter_type == -1 && $filter_delay == 0) {
                    $options['filter']['type'] = [ITEM_TYPE_PERSEUS, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL,
                        ITEM_TYPE_PERSEUS_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI,
                        ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED, ITEM_TYPE_JMX
                    ];

                    $options['filter']['delay'] = $filter_delay;
                } elseif ($filter_type == ITEM_TYPE_TRAPPER || $filter_type == ITEM_TYPE_SNMPTRAP
                    || $filter_type == ITEM_TYPE_DEPENDENT
                    || ($filter_type == ITEM_TYPE_PERSEUS_ACTIVE && strncmp($filter_key, 'mqtt.get', 8) === 0)) {
                    $options['filter']['delay'] = -1;
                } else {
                    $options['filter']['delay'] = $filter_delay;
                }
            }
        }

        $filter_history = $this->getRequest('filter_history', '');
        if (isset($filter_history) && !prs_empty($filter_history)) {
            $options['filter']['history'] = $filter_history;
        }

        // If no specific value type is set, set a numeric value type when filtering by trends.
        if (isset($this->data['filter_trends'])) {
            $filter_trends = $this->getRequest('filter_trends');

            if ($filter_trends !== '') {
                $options['filter']['trends'] = $filter_trends;

                if ($filter_value_type == -1) {
                    $options['filter']['value_type'] = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];
                }
            }
        }

        $filter_status = $this->getRequest('filter_status', -1);
        $filter_state = $this->getRequest('filter_state', -1);
        $filter_with_triggers = $this->getRequest('filter_with_triggers', -1);
        if (isset($filter_status) && !prs_empty($filter_status) && $filter_status != -1) {
            $options['filter']['status'] = $filter_status;
        }
        if (isset($filter_state) && !prs_empty($filter_state) && $filter_state != -1) {
            $options['filter']['status'] = ITEM_STATUS_ACTIVE;
            $options['filter']['state'] = $filter_state;
        }

        if ($this->getRequest('filter_inherited', -1) != -1) {
            $options['inherited'] = $this->getRequest('filter_inherited');
        }
        if ($this->getRequest('filter_discovered', -1) != -1) {
            $options['filter']['flags'] = $this->getRequest('filter_discovered');
        }
        if (isset($filter_with_triggers) && !prs_empty($filter_with_triggers)
            && $filter_with_triggers != -1) {
            $options['with_triggers'] = $filter_with_triggers;
        }

        return $this->success($options);
    }

    protected function getSubGroups($groupIds, string $context = 'host'): array
    {
        $groups = (new Query())->select(['name'])
            ->from(['hstgrp'])
            ->where(['groupid' => $groupIds])
            ->andWhere(['type' => $context == 'host' ? HOST_GROUP_TYPE_HOST_GROUP : HOST_GROUP_TYPE_TEMPLATE_GROUP])
            ->all();
        $where = array_map(function ($name) {
            return ['like', 'name', "$name/%", false];
        }, $groups);
        if ($where) {
            array_unshift($where, 'or');
            return (new Query())->select(['groupid'])
                ->from(['hstgrp'])
                ->where($where)
                ->andWhere(['type' => $context == 'host' ? HOST_GROUP_TYPE_HOST_GROUP : HOST_GROUP_TYPE_TEMPLATE_GROUP])
                ->column();
        }
        return [];
    }
}