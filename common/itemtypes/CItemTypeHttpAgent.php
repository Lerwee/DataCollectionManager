<?php declare(strict_types=1);

namespace app\customs\zapi\common\itemtypes;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\validators\Int32RangesValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\JsonValidator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\ObjectValidator;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\XmlValidator;

class CItemTypeHttpAgent extends CItemType
{

    /**
     * @inheritDoc
     */
    const TYPE = ITEM_TYPE_HTTPAGENT;

    /**
     * @inheritDoc
     */
    const FIELD_NAMES = ['url', 'query_fields', 'request_method', 'post_type', 'posts', 'headers', 'status_codes',
        'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy', 'interfaceid', 'authtype', 'username',
        'password', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'timeout',
        'delay', 'allow_traps', 'trapper_hosts'
    ];

    /**
     * @inheritDoc
     */
    public static function getCreateValidationRules(array $item): array
    {
        $is_item_prototype = $item['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE;

        return [
            'url' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'url')],
            'query_fields' => [ObjectsValidator::class, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => []],
            'request_method' => [Int32Validator::class, 'in' => implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]), 'default' => DB::getDefault('items', 'request_method')],
            'post_type' => [Int32Validator::class, 'in' => implode(',', [PRS_POSTTYPE_RAW, PRS_POSTTYPE_JSON, PRS_POSTTYPE_XML]), 'default' => DB::getDefault('items', 'post_type')],
            'posts' => [MultipleValidator::class, 'rules' => [
                [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'posts'), 'when' => function ($model) {
                    return $model->post_type == PRS_POSTTYPE_RAW;
                }],
                [JsonValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'macros_n' => ['{HOST.IP}', '{HOST.CONN}', '{HOST.DNS}', '{HOST.HOST}', '{HOST.NAME}', '{ITEM.ID}', '{ITEM.KEY}', '{ITEM.KEY.ORIG}'], 'length' => DB::getFieldLength('items', 'posts'), 'when' => function ($model) {
                    return $model->post_type == PRS_POSTTYPE_JSON;
                }],
                [XmlValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'posts'), 'when' => function ($model) {
                    return $model->post_type == PRS_POSTTYPE_XML;
                }]
            ]],
            'headers' => [ObjectValidator::class, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => []],
            'status_codes' => [Int32RangesValidator::class, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'status_codes')],
            'follow_redirects' => [Int32Validator::class, 'in' => implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON])],
            'retrieve_mode' => [MultipleValidator::class,
                'rules' => [Int32Validator::class, 'in' => HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, 'when' => function ($model) {
                    return $model->request_method == HTTPCHECK_REQUEST_HEAD;
                }],
                'else' => [Int32Validator::class, 'in' => implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH])]
            ],
            'output_format' => [Int32Validator::class, 'in' => implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON])],
            'http_proxy' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'http_proxy')],
            'interfaceid' => self::getCreateFieldRule('interfaceid', $item),
            'authtype' => self::getCreateFieldRule('authtype', $item),
            'username' => self::getCreateFieldRule('username', $item),
            'password' => self::getCreateFieldRule('password', $item),
            'verify_peer' => [Int32Validator::class, 'in' => implode(',', [PRS_HTTP_VERIFY_PEER_OFF, PRS_HTTP_VERIFY_PEER_ON])],
            'verify_host' => [Int32Validator::class, 'in' => implode(',', [PRS_HTTP_VERIFY_HOST_OFF, PRS_HTTP_VERIFY_HOST_ON])],
            'ssl_cert_file' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'ssl_cert_file')],
            'ssl_key_file' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'ssl_key_file')],
            'ssl_key_password' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'ssl_key_password')],
            'timeout' => self::getCreateFieldRule('timeout', $item),
            'delay' => self::getCreateFieldRule('delay', $item),
            'allow_traps' => [Int32Validator::class, 'in' => implode(',', [HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON]), 'default' => DB::getDefault('items', 'allow_traps')],
            'trapper_hosts' => self::getCreateFieldRule('trapper_hosts', $item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRules(array $db_item): array
    {
        $is_item_prototype = $db_item['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE;

        return [
            'url' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'url')],
            'query_fields' => [ObjectsValidator::class, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => []],
            'request_method' => [Int32Validator::class, 'in' => implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD])],
            'post_type' => [Int32Validator::class, 'in' => implode(',', [PRS_POSTTYPE_RAW, PRS_POSTTYPE_JSON, PRS_POSTTYPE_XML])],
            'posts' => [MultipleValidator::class, 'rules' => [
                [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'posts'), 'when' => function ($model) {
                    return $model->post_type == PRS_POSTTYPE_RAW;
                }],
                [JsonValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'macros_n' => ['{HOST.IP}', '{HOST.CONN}', '{HOST.DNS}', '{HOST.HOST}', '{HOST.NAME}', '{ITEM.ID}', '{ITEM.KEY}', '{ITEM.KEY.ORIG}'], 'length' => DB::getFieldLength('items', 'posts'), 'when' => function ($model) {
                    return $model->post_type == PRS_POSTTYPE_JSON;
                }],
                [XmlValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'posts'), 'when' => function ($model) {
                    return $model->post_type == PRS_POSTTYPE_XML;
                }]
            ]],
            'headers' => [ObjectValidator::class, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => []],
            'status_codes' => [Int32RangesValidator::class, 'flags' => API_ALLOW_USER_MACRO | ($is_item_prototype ? API_ALLOW_LLD_MACRO : 0), 'length' => DB::getFieldLength('items', 'status_codes')],
            'follow_redirects' => [Int32Validator::class, 'in' => implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON])],
            'retrieve_mode' => [MultipleValidator::class,
                'rules' => [
                    Int32Validator::class, 'in' => HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, 'when' => function ($model) {
                        return $model->request_method == HTTPCHECK_REQUEST_HEAD;
                    }],
                'else' => [Int32Validator::class, 'in' => implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH])]
            ],
            'output_format' => [Int32Validator::class, 'in' => implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON])],
            'http_proxy' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'http_proxy')],
            'interfaceid' => self::getUpdateFieldRule('interfaceid', $db_item),
            'authtype' => self::getUpdateFieldRule('authtype', $db_item),
            'username' => self::getUpdateFieldRule('username', $db_item),
            'password' => self::getUpdateFieldRule('password', $db_item),
            'verify_peer' => [Int32Validator::class, 'in' => implode(',', [PRS_HTTP_VERIFY_PEER_OFF, PRS_HTTP_VERIFY_PEER_ON])],
            'verify_host' => [Int32Validator::class, 'in' => implode(',', [PRS_HTTP_VERIFY_HOST_OFF, PRS_HTTP_VERIFY_HOST_ON])],
            'ssl_cert_file' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'ssl_cert_file')],
            'ssl_key_file' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'ssl_key_file')],
            'ssl_key_password' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('items', 'ssl_key_password')],
            'timeout' => self::getUpdateFieldRule('timeout', $db_item),
            'delay' => self::getUpdateFieldRule('delay', $db_item),
            'allow_traps' => [Int32Validator::class, 'in' => implode(',', [HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON])],
            'trapper_hosts' => self::getUpdateFieldRule('trapper_hosts', $db_item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRulesInherited(array $db_item): array
    {
        return [
            'url' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'query_fields' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'request_method' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'post_type' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'posts' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'headers' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'status_codes' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'follow_redirects' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'retrieve_mode' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'output_format' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'http_proxy' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'interfaceid' => self::getUpdateFieldRuleInherited('interfaceid', $db_item),
            'authtype' => self::getUpdateFieldRuleInherited('authtype', $db_item),
            'username' => self::getUpdateFieldRuleInherited('username', $db_item),
            'password' => self::getUpdateFieldRuleInherited('password', $db_item),
            'verify_peer' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'verify_host' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'ssl_cert_file' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'ssl_key_file' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'ssl_key_password' => [UnexpectedValidator::class, 'error_type' => API_ERR_INHERITED],
            'timeout' => self::getUpdateFieldRuleInherited('timeout', $db_item),
            'delay' => self::getUpdateFieldRuleInherited('delay', $db_item),
            'allow_traps' => [Int32Validator::class, 'in' => implode(',', [HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON])],
            'trapper_hosts' => self::getUpdateFieldRuleInherited('trapper_hosts', $db_item)
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getUpdateValidationRulesDiscovered(): array
    {
        return [
            'url' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'query_fields' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'request_method' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'post_type' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'posts' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'headers' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'status_codes' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'follow_redirects' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'retrieve_mode' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'output_format' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'http_proxy' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'interfaceid' => self::getUpdateFieldRuleDiscovered('interfaceid'),
            'authtype' => self::getUpdateFieldRuleDiscovered('authtype'),
            'username' => self::getUpdateFieldRuleDiscovered('username'),
            'password' => self::getUpdateFieldRuleDiscovered('password'),
            'verify_peer' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'verify_host' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'ssl_cert_file' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'ssl_key_file' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'ssl_key_password' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'timeout' => self::getUpdateFieldRuleDiscovered('timeout'),
            'delay' => self::getUpdateFieldRuleDiscovered('delay'),
            'allow_traps' => [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
            'trapper_hosts' => self::getUpdateFieldRuleDiscovered('trapper_hosts')
        ];
    }
}
