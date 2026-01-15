<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\ItemHelper;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Items;

/**
 * Class ItemFormRequestData
 * @package app\customs\zapi\components\data
 */
class ItemFormRequestData extends RequestData
{
    public function validate(): Result
    {
        $data = [
            'is_discovery_rule' => $this->getRequest('is_discovery_rule', false),
            'parent_discoveryid' => $this->getRequest('parent_discoveryid', 0),
            'itemid' => $this->getRequest('itemid'),
            'limited' => false,
            'interfaceid' => $this->getRequest('interfaceid', 0),
            'name' => $this->getRequest('name', ''),
            'description' => $this->getRequest('description', ''),
            'key' => $this->getRequest('key', ''),
            'master_itemid' => $this->getRequest('master_itemid', 0),
            'hostname' => $this->getRequest('hostname'),
            'delay' => $this->getRequest('delay', PRS_ITEM_DELAY_DEFAULT),
            'history' => $this->getRequest('history', DB::getDefault('items', 'history')),
            'status' => $this->getRequest('status', isset($_REQUEST['form_refresh']) ? 1 : 0),
            'type' => $this->getRequest('type', 0),
            'snmp_oid' => $this->getRequest('snmp_oid', ''),
            'value_type' => $this->getRequest('value_type', ITEM_VALUE_TYPE_UINT64),
            'trapper_hosts' => $this->getRequest('trapper_hosts', ''),
            'units' => $this->getRequest('units', ''),
            'valuemapid' => $this->getRequest('valuemapid', 0),
            'params' => $this->getRequest('params', ''),
            'trends' => $this->getRequest('trends', DB::getDefault('items', 'trends')),
            'delay_flex' => array_values($this->getRequest('delay_flex', [])),
            'ipmi_sensor' => $this->getRequest('ipmi_sensor', ''),
            'authtype' => $this->getRequest('authtype', 0),
            'username' => $this->getRequest('username', ''),
            'password' => $this->getRequest('password', ''),
            'publickey' => $this->getRequest('publickey', ''),
            'privatekey' => $this->getRequest('privatekey', ''),
            'logtimefmt' => $this->getRequest('logtimefmt', ''),
            'possibleHostInventories' => null,
            'alreadyPopulated' => null,
            'initial_item_type' => null,
            'templates' => [],
            'jmx_endpoint' => $this->getRequest('jmx_endpoint', PRS_DEFAULT_JMX_ENDPOINT),
            'timeout' => $this->getRequest('timeout', DB::getDefault('items', 'timeout')),
            'url' => $this->getRequest('url'),
            'query_fields' => $this->getRequest('query_fields', []),
            'parameters' => $this->getRequest('parameters', []),
            'posts' => $this->getRequest('posts'),
            'status_codes' => $this->getRequest('status_codes', DB::getDefault('items', 'status_codes')),
            'follow_redirects' => hasRequest('form_refresh')
                ? (int) $this->getRequest('follow_redirects')
                : $this->getRequest('follow_redirects', DB::getDefault('items', 'follow_redirects')),
            'post_type' => $this->getRequest('post_type', DB::getDefault('items', 'post_type')),
            'http_proxy' => $this->getRequest('http_proxy'),
            'headers' => $this->getRequest('headers', []),
            'retrieve_mode' => $this->getRequest('retrieve_mode', DB::getDefault('items', 'retrieve_mode')),
            'request_method' => $this->getRequest('request_method', DB::getDefault('items', 'request_method')),
            'output_format' => $this->getRequest('output_format', DB::getDefault('items', 'output_format')),
            'allow_traps' => $this->getRequest('allow_traps', DB::getDefault('items', 'allow_traps')),
            'ssl_cert_file' => $this->getRequest('ssl_cert_file'),
            'ssl_key_file' => $this->getRequest('ssl_key_file'),
            'ssl_key_password' => $this->getRequest('ssl_key_password'),
            'verify_peer' => $this->getRequest('verify_peer', DB::getDefault('items', 'verify_peer')),
            'verify_host' => $this->getRequest('verify_host', DB::getDefault('items', 'verify_host')),
            'http_authtype' => $this->getRequest('http_authtype', PRS_HTTP_AUTH_NONE),
            'http_username' => $this->getRequest('http_username', ''),
            'http_password' => $this->getRequest('http_password', ''),
            'preprocessing' => $this->getRequest('preprocessing', []),
            'preprocessing_script_maxlength' => DB::getFieldLength('item_preproc', 'params'),
            'context' => $this->getRequest('context'),
            'show_inherited_tags' => $this->getRequest('show_inherited_tags', 0),
            'tags' => $this->getRequest('tags', []),
        ];
        return $this->success($data);
    }
}