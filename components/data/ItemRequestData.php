<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\ItemHelper;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Items;

/**
 * Class ItemRequestData
 * @package app\customs\zapi\components\item
 */
class ItemRequestData extends RequestData
{
    /**
     * @var string
     */
    public $action = 'create';

    public function validate(): Result
    {
        $paramsFieldName = ItemHelper::getParamFieldNameByType($this->getRequest('type', 0));
        $this->data['params'] = $this->getRequest($paramsFieldName, '');
        if ($this->action == 'delete') {
            $itemIds = filter_integer($this->getRequest('itemids', []));
            $itemIds = array_unique($itemIds);
            $items = Items::find()->select(['itemid', 'name', 'templateid'])
                ->where(['itemid' => $itemIds])
                ->indexBy('itemid')->asArray()->all();
            if (count($itemIds) != count($items)) {
                return $this->error(60750203);
            }
            foreach ($items as $item) {
                if (!empty($item['templateid'])) {
                    return $this->error(error_code(60750201, ['error' => t('zapi', 'cannot delete inherited item')]));
                }
            }
            return $this->success($items);
        } else {
            return $this->validateEdit();
        }
    }

    /**
     * @return Result
     * @throws \yii\db\Exception
     */
    public function validateEdit(): Result
    {
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
            'query_fields' => ItemHelper::prepareItemQueryFields(array_filter((array)($this->getRequest('query_fields', [])))),
            'request_method' => $request_method,
            'post_type' => $this->getRequest('post_type', DB::getDefault('items', 'post_type')),
            'posts' => $this->getRequest('posts', DB::getDefault('items', 'posts')),
            'headers' => ItemHelper::prepareItemHeaders(array_filter((array)($this->getRequest('headers', [])))),
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
            'parameters' => ItemHelper::prepareItemParameters((array)$this->getRequest('parameters', [])),

            // SNMP item type specific fields.
            'snmp_oid' => $this->getRequest('snmp_oid', DB::getDefault('items', 'snmp_oid')),

            // SSH item type specific fields.
            'publickey' => $this->getRequest('publickey', DB::getDefault('items', 'publickey')),
            'privatekey' => $this->getRequest('privatekey', DB::getDefault('items', 'privatekey'))
        ];
        if (!$host = $this->getItemHost()) {
            return $this->error(60750203);
        }
        if ($this->action == 'create') {
            $item['hostid'] = $host['hostid'];
            $item += $this->getSanitizedItemFields($input + [
                    'templateid' => '0',
                    'flags' => PRS_FLAG_DISCOVERY_NORMAL,
                    'hosts' => [$host]
                ]
            );
        } else {
            $itemId = $this->getRequest('itemid');
            $currentItem = Items::find()->select(['templateid', 'flags', 'type', 'key_', 'value_type', 'authtype', 'allow_traps'])
                ->where(['itemid' => $itemId])
                ->asArray()->one();
            $item = $this->getSanitizedItemFields($input + $currentItem + ['hosts' => [$host]]);
            $item['itemid'] = $itemId;
        }
        return $this->success($item);
    }

    protected function getItemHost()
    {
        $itemId = $this->getRequest('itemid');
        if ($itemId) {
            $hostId = Items::find()->select(['hostid'])->where(['itemid' => (int)$itemId])->scalar();
        } else {
            $hostId = $this->getRequest('hostid');
        }
        $host = null;
        if ($hostId) {
            $host = Hosts::find()->select(['hostid', 'status'])->where(['hostid' => $hostId])->limit(1)->asArray()->one();
        }
        return $host;
    }

    /**
     * Get sanitized item fields of given input.
     * $input:
     * [
     *      'templateid' => string
     *      'flags' => int
     *      'type' => int
     *      'key_' => string
     *      'value_type' => int
     *      'authtype' => int
     *      'allow_traps' => int
     *      'hosts' => [['hostid' => int, 'status' => int]]
     * ]
     * @param array  $input
     * @return array
     */
    protected function getSanitizedItemFields(array $input): array
    {
        $field_names = $this->getMainItemFieldNames($input);

        if ($input['flags'] != PRS_FLAG_DISCOVERY_CREATED) {
            $field_names = array_merge($field_names, $this->getTypeItemFieldNames($input));
            $field_names = $this->getConditionalItemFieldNames($field_names, $input);
        }
        return array_intersect_key($input, array_flip($field_names));
    }

    /**
     * Get main item fields of given input.
     *
     * @param array $input ['templateid', 'flags']
     * @return array
     */
    public function getMainItemFieldNames(array $input): array
    {
        switch ($input['flags']) {
            case PRS_FLAG_DISCOVERY_NORMAL:
                if ($input['templateid'] == 0) {
                    return ['name', 'type', 'key_', 'value_type', 'units', 'history', 'trends', 'valuemapid',
                        'inventory_link', 'logtimefmt', 'description', 'status', 'tags', 'preprocessing'
                    ];
                } else {
                    return ['history', 'trends', 'inventory_link', 'description', 'status', 'tags'];
                }

            case PRS_FLAG_DISCOVERY_PROTOTYPE:
                if ($input['templateid'] == 0) {
                    return ['name', 'type', 'key_', 'value_type', 'units', 'history', 'trends', 'valuemapid', 'logtimefmt',
                        'description', 'status', 'discover', 'tags', 'preprocessing'
                    ];
                } else {
                    return ['history', 'trends', 'description', 'status', 'discover', 'tags'];
                }

            case PRS_FLAG_DISCOVERY_CREATED:
                return ['status'];
        }
        return ['status'];
    }

    /**
     * Get item field names of the given type and template ID.
     *
     * @param array $input ['templateid', 'type']
     */
    protected function getTypeItemFieldNames(array $input): array
    {
        switch ($input['type']) {
            case ITEM_TYPE_EXTERNAL:
            case ITEM_TYPE_PERSEUS:
                return ['interfaceid', 'delay'];

            case ITEM_TYPE_TRAPPER:
                return ['trapper_hosts'];

            case ITEM_TYPE_SIMPLE:
                return ['interfaceid', 'username', 'password', 'delay'];

            case ITEM_TYPE_PERSEUS_ACTIVE:
            case ITEM_TYPE_INTERNAL:
                return ['delay'];

            case ITEM_TYPE_DB_MONITOR:
                return ['username', 'password', 'params', 'delay'];

            case ITEM_TYPE_IPMI:
                if ($input['templateid'] == 0) {
                    return ['interfaceid', 'ipmi_sensor', 'delay'];
                } else {
                    return ['interfaceid', 'delay'];
                }

            case ITEM_TYPE_SSH:
                return ['interfaceid', 'authtype', 'username', 'publickey', 'privatekey', 'password', 'params', 'delay'];

            case ITEM_TYPE_TELNET:
                return ['interfaceid', 'username', 'password', 'params', 'delay'];

            case ITEM_TYPE_CALCULATED:
                return ['params', 'delay'];

            case ITEM_TYPE_JMX:
                if ($input['templateid'] == 0) {
                    return ['interfaceid', 'jmx_endpoint', 'username', 'password', 'delay'];
                } else {
                    return ['interfaceid', 'username', 'password', 'delay'];
                }

            case ITEM_TYPE_SNMPTRAP:
                return ['interfaceid'];

            case ITEM_TYPE_DEPENDENT:
                if ($input['templateid'] == 0) {
                    return ['master_itemid'];
                }

                return [];

            case ITEM_TYPE_HTTPAGENT:
                if ($input['templateid'] == 0) {
                    return ['url', 'query_fields', 'request_method', 'post_type', 'posts', 'headers', 'status_codes',
                        'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy', 'interfaceid', 'authtype',
                        'username', 'password', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file',
                        'ssl_key_password', 'timeout', 'delay', 'allow_traps', 'trapper_hosts'
                    ];
                } else {
                    return ['interfaceid', 'delay', 'allow_traps', 'trapper_hosts'];
                }

            case ITEM_TYPE_SNMP:
                if ($input['templateid'] == 0) {
                    return ['interfaceid', 'snmp_oid', 'delay'];
                } else {
                    return ['interfaceid', 'delay'];
                }

            case ITEM_TYPE_SCRIPT:
                if ($input['templateid'] == 0) {
                    return ['parameters', 'params', 'timeout', 'delay'];
                } else {
                    return ['delay'];
                }
        }
        return [];
    }

    /**
     * Get item field names excluding those that don't match a specific conditions.
     * $input:
     * [
     *      'type' => int
     *      'key_' => string
     *      'value_type' => int
     *      'authtype' => int
     *      'allow_traps' => int
     *      'hosts' => [['hostid' => int, 'status' => int]]
     * ]
     * @param array $field_names
     * @param array $input
     *
     * @return array
     */
    protected function getConditionalItemFieldNames(array $field_names, array $input): array
    {
        return array_filter($field_names, static function ($field_name) use ($input): bool {
            switch ($field_name) {
                case 'units':
                case 'trends':
                    return in_array($input['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);

                case 'valuemapid':
                    return in_array($input['value_type'],
                        [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64]
                    );

                case 'inventory_link':
                    return in_array($input['value_type'],
                        [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT]
                    );

                case 'logtimefmt':
                    return $input['value_type'] == ITEM_VALUE_TYPE_LOG;

                case 'interfaceid':
                    return in_array($input['hosts'][0]['status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);

                case 'username':
                case 'password':
                    return $input['type'] != ITEM_TYPE_HTTPAGENT || in_array($input['authtype'],
                            [PRS_HTTP_AUTH_BASIC, PRS_HTTP_AUTH_NTLM, PRS_HTTP_AUTH_KERBEROS, PRS_HTTP_AUTH_DIGEST]
                        );

                case 'delay':
                    return $input['type'] != ITEM_TYPE_PERSEUS_ACTIVE || strncmp($input['key_'], 'mqtt.get', 8) != 0;

                case 'trapper_hosts':
                    return $input['type'] != ITEM_TYPE_HTTPAGENT || $input['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_ON;

                case 'publickey':
                case 'privatekey':
                    return $input['authtype'] == ITEM_AUTHTYPE_PUBLICKEY;
            }

            return true;
        });
    }
}