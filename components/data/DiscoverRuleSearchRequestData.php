<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;

/**
 * Class DiscoverRuleSearchRequestData
 * @package app\customs\zapi\components\data
 */
class DiscoverRuleSearchRequestData extends RequestData
{
    /**
     * @return Result
     */
    public function validate(): Result
    {
        $filter = [
            'groups' => [],
            'hosts' => [],
            'name' => $this->getRequest('filter_name', ''),
            'key' => $this->getRequest('filter_key', ''),
            'type' => $this->getRequest('filter_type', -1),
            'delay' => $this->getRequest('filter_delay', ''),
            'lifetime' => $this->getRequest('filter_lifetime', ''),
            'snmp_oid' => $this->getRequest('filter_snmp_oid', ''),
            'state' => $this->getRequest('filter_state', -1),
            'status' => $this->getRequest('filter_status', -1)
        ];
        $filter_groupids = $this->getRequest('filter_groupids', []);
        $filter_hostids = $this->getRequest('filter_hostids', []);
        $filter_itemids = $this->getRequest('filter_itemids', []);
        $sort_field = $this->getRequest('sort',  'name');
        $sort_order = $this->getRequest('sortorder', PRS_SORT_UP);
        $data = [
            'filter' => $filter,
            'hostid' => (count($filter_hostids) == 1) ? reset($filter_hostids) : 0,
            'sort' => $sort_field,
            'sortorder' => $sort_order,
            'active_tab' => $this->getRequest('active', 1),
            'context' => $this->getRequest('context')
        ];

        // Select LLD rules.
        $options = [
            'output' => API_OUTPUT_EXTEND,
            'selectHosts' => ['hostid', 'name', 'status', 'flags'],
            'selectItems' => API_OUTPUT_COUNT,
            'selectGraphs' => API_OUTPUT_COUNT,
            'selectTriggers' => API_OUTPUT_COUNT,
            'selectHostPrototypes' => API_OUTPUT_COUNT,
            'editable' => true,
            'templated' => ($data['context'] === 'template'),
            'filter' => [],
            'search' => [],
            'sortfield' => $sort_field,
        ];

        if ($filter_groupids) {
            $options['groupids'] = $filter_groupids;
        }

        if ($filter_hostids) {
            $options['hostids'] = $filter_hostids;
        }
        if ($filter_itemids) {
            $options['itemids'] = filter_integer((array)$filter_itemids);
        }

        if ($filter['name'] !== '') {
            $options['search']['name'] = $filter['name'];
        }

        if ($filter['key'] !== '') {
            $options['search']['key_'] = $filter['key'];
        }

        if ($filter['type'] != -1) {
            $options['filter']['type'] = $filter['type'];
        }

        /*
         * Trapper and SNMP trap items contain zeros in "delay" field and, if no specific type is set, look in item types
         * other than trapper and SNMP trap that allow zeros. For example, when a flexible interval is used. Since trapper
         * and SNMP trap items contain zeros, but those zeros should not be displayed, they cannot be filtered by entering
         * either zero or any other number in filter field.
         */
        if ($filter['delay'] !== '') {
            if ($filter['type'] == -1 && $filter['delay'] == 0) {
                $options['filter']['type'] = [ITEM_TYPE_PERSEUS, ITEM_TYPE_SIMPLE,  ITEM_TYPE_INTERNAL,
                    ITEM_TYPE_PERSEUS_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI,
                    ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX
                ];
                $options['filter']['delay'] = $filter['delay'];
            }
            elseif ($filter['type'] == ITEM_TYPE_TRAPPER || $filter['type'] == ITEM_TYPE_DEPENDENT
                || ($filter['type'] == ITEM_TYPE_PERSEUS_ACTIVE && strncmp($filter['key'], 'mqtt.get', 8) === 0)) {
                $options['filter']['delay'] = -1;
            }
            else {
                $options['filter']['delay'] = $filter['delay'];
            }
        }

        if ($filter['lifetime'] !== '') {
            $options['filter']['lifetime'] = $filter['lifetime'];
        }

        if ($filter['snmp_oid'] !== '') {
            $options['filter']['snmp_oid'] = $filter['snmp_oid'];
        }

        if ($filter['status'] != -1) {
            $options['filter']['status'] = $filter['status'];
        }

        if ($filter['state'] != -1) {
            $options['filter']['status'] = ITEM_STATUS_ACTIVE;
            $options['filter']['state'] = $filter['state'];
        }
        return $this->success($options);
    }
}