<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\ItemHelper;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Items;

/**
 * Class ItemPrototypeRequestData
 * @package app\customs\zapi\components\data
 */
class ItemPrototypeRequestData extends ItemRequestData
{
    /**
     * @var string
     */
    public $action = 'create';

    /**
     * @return Result
     * @throws \yii\db\Exception
     */
    public function validate(): Result
    {
        $paramsFieldName = ItemHelper::getParamFieldNameByType($this->getRequest('type', 0));
        $this->data['params'] = $this->getRequest($paramsFieldName, '');

        $type = (int)$this->getRequest('type', DB::getDefault('items', 'type'));
        $key = $this->getRequest('key', DB::getDefault('items', 'key_'));

        if (ItemHelper::isItemExampleKey($type, $key)) {
            return $this->error(60750202);
        }

        $delay_flex = $this->getRequest('delay_flex', []);

        if (!ItemHelper::isValidCustomIntervals($delay_flex, false, $error)) {
            return $this->error(error_code(60750201, ['error' => $error]));
        }

        $value_type = (int)$this->getRequest('value_type', DB::getDefault('items', 'value_type'));
        $trends_default = in_array($value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
            ? DB::getDefault('items', 'trends')
            : 0;

        $request_method = $this->getRequest('request_method', DB::getDefault('items', 'request_method'));
        $retrieve_mode_default = $request_method == HTTPCHECK_REQUEST_HEAD
            ? HTTPTEST_STEP_RETRIEVE_MODE_HEADERS
            : DB::getDefault('items', 'retrieve_mode');

        $input = [
            'name' => $this->getRequest('name', DB::getDefault('items', 'name')),
            'type' => $type,
            'key_' => $key,
            'value_type' => $value_type,
            'units' => $this->getRequest('units', DB::getDefault('items', 'units')),
            'history' => $this->getRequest('history_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF
                ? ITEM_NO_STORAGE_VALUE
                : $this->getRequest('history', DB::getDefault('items', 'history')),
            'trends' => $this->getRequest('trends_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF
                ? ITEM_NO_STORAGE_VALUE
                : $this->getRequest('trends', $trends_default),
            'valuemapid' => $this->getRequest('valuemapid', 0),
            'inventory_link' => $this->getRequest('inventory_link', DB::getDefault('items', 'inventory_link')),
            'logtimefmt' => $this->getRequest('logtimefmt', DB::getDefault('items', 'logtimefmt')),
            'description' => $this->getRequest('description', DB::getDefault('items', 'description')),
            'status' => $this->getRequest('status', ITEM_STATUS_DISABLED),
            'discover' => $this->getRequest('discover', DB::getDefault('items', 'discover')),
            'tags' => ItemHelper::prepareItemTags($this->getRequest('tags', [])),
            'preprocessing' => ItemHelper::normalizeItemPreprocessingSteps($this->getRequest('preprocessing', [])),

            // Type fields.
            // The fields used for multiple item types.
            'interfaceid' => $this->getRequest('interfaceid', 0),
            'authtype' => $type == ITEM_TYPE_HTTPAGENT
                ? $this->getRequest('http_authtype', DB::getDefault('items', 'authtype'))
                : $this->getRequest('authtype', DB::getDefault('items', 'authtype')),
            'username' => $type == ITEM_TYPE_HTTPAGENT
                ? $this->getRequest('http_username', DB::getDefault('items', 'username'))
                : $this->getRequest('username', DB::getDefault('items', 'username')),
            'password' => $type == ITEM_TYPE_HTTPAGENT
                ? $this->getRequest('http_password', DB::getDefault('items', 'password'))
                : $this->getRequest('password', DB::getDefault('items', 'password')),
            'params' => $this->getRequest('params', DB::getDefault('items', 'params')),
            'timeout' => $this->getRequest('timeout', DB::getDefault('items', 'timeout')),
            'delay' => ItemHelper::getDelayWithCustomIntervals($this->getRequest('delay', DB::getDefault('items', 'delay')), $delay_flex),
            'trapper_hosts' => $this->getRequest('trapper_hosts', DB::getDefault('items', 'trapper_hosts')),

            // Dependent item type specific fields.
            'master_itemid' => $this->getRequest('master_itemid', 0),

            // HTTP Agent item type specific fields.
            'url' => $this->getRequest('url', DB::getDefault('items', 'url')),
            'query_fields' => ItemHelper::prepareItemQueryFields($this->getRequest('query_fields', [])),
            'request_method' => $request_method,
            'post_type' => $this->getRequest('post_type', DB::getDefault('items', 'post_type')),
            'posts' => $this->getRequest('posts', DB::getDefault('items', 'posts')),
            'headers' => ItemHelper::prepareItemHeaders($this->getRequest('headers', [])),
            'status_codes' => $this->getRequest('status_codes', DB::getDefault('items', 'status_codes')),
            'follow_redirects' => $this->getRequest('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF),
            'retrieve_mode' => $this->getRequest('retrieve_mode', $retrieve_mode_default),
            'output_format' => $this->getRequest('output_format', DB::getDefault('items', 'output_format')),
            'http_proxy' => $this->getRequest('http_proxy', DB::getDefault('items', 'http_proxy')),
            'verify_peer' => $this->getRequest('verify_peer', DB::getDefault('items', 'verify_peer')),
            'verify_host' => $this->getRequest('verify_host', DB::getDefault('items', 'verify_host')),
            'ssl_cert_file' => $this->getRequest('ssl_cert_file', DB::getDefault('items', 'ssl_cert_file')),
            'ssl_key_file' => $this->getRequest('ssl_key_file', DB::getDefault('items', 'ssl_key_file')),
            'ssl_key_password' => $this->getRequest('ssl_key_password', DB::getDefault('items', 'ssl_key_password')),
            'allow_traps' => $this->getRequest('allow_traps', DB::getDefault('items', 'allow_traps')),

            // IPMI item type specific fields.
            'ipmi_sensor' => $this->getRequest('ipmi_sensor', DB::getDefault('items', 'ipmi_sensor')),

            // JMX item type specific fields.
            'jmx_endpoint' => $this->getRequest('jmx_endpoint', DB::getDefault('items', 'jmx_endpoint')),

            // Script item type specific fields.
            'parameters' => ItemHelper::prepareItemParameters($this->getRequest('parameters', [])),

            // SNMP item type specific fields.
            'snmp_oid' => $this->getRequest('snmp_oid', DB::getDefault('items', 'snmp_oid')),

            // SSH item type specific fields.
            'publickey' => $this->getRequest('publickey', DB::getDefault('items', 'publickey')),
            'privatekey' => $this->getRequest('privatekey', DB::getDefault('items', 'privatekey'))
        ];

        $lldRule = Items::find()->alias('i')->select(['itemid', 'hostid'])
            ->where(['itemid' => $this->getRequest('parent_discoveryid')])
            ->limit(1)->asArray()->one();
        if (!$lldRule) {
            return $this->error(60750203);
        }
        $host = Hosts::find()->select(['hostid', 'status'])->where(['hostid' => $lldRule['hostid']])->limit(1)->asArray()->one();
        if ($this->action == 'create') {
            $item = [
                'hostid' => $lldRule['hostid'],
                'ruleid' => $lldRule['itemid']
            ];

            $item += $this->getSanitizedItemFields($input + [
                    'templateid' => '0',
                    'flags' => PRS_FLAG_DISCOVERY_PROTOTYPE,
                    'hosts' => [$host]
                ]);
        } else {
            $itemId = $this->getRequest('itemid');
            $currentItem = Items::find()->select(['templateid', 'flags', 'type', 'key_', 'value_type', 'authtype', 'allow_traps'])
                ->where(['itemid' => $itemId])
                ->asArray()->one();
            $item = $this->getSanitizedItemFields($input + $currentItem + [
                    'flags' => PRS_FLAG_DISCOVERY_PROTOTYPE,
                    'hosts' => [$host]
                ]);
            $item['itemid'] = $itemId;
        }
        return $this->success($item);
    }
}