<?php

namespace app\customs\zapi\services\assist;

use app\common\components\Result;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\CSettingsHelper;
use app\customs\zapi\common\parsers\CExpressionParser;
use app\customs\zapi\common\parsers\CItemKey;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\server\MonitorServer;
use app\customs\zapi\common\validators\z\CExpressionValidator;
use app\modules\libzbx\components\ZabbixServer;
use yii\base\Exception;

class ItemTestGetValueAssist extends ItemTestAssist
{
    /**
     * Show final result in item test dialog.
     *
     * @var bool
     */
    protected $show_final_result;

    /**
     * Use previous value for preprocessing test.
     *
     * @var bool
     */
    protected $use_prev_value;

    /**
     * Retrieve value from host.
     *
     * @var bool
     */
    protected $get_value_from_host;

    /**
     * Time suffixes supported by Perseus server.
     *
     * @var array
     */
    protected static $supported_time_suffixes = ['w', 'd', 'h', 'm', 's'];

    /**
     * @param $params
     * @return Result
     */
    public function getItemValue($params): Result
    {
        try {
            $this->raw_input = $params;
            $this->checkInput();
            if ($this->checkPermissions() !== true) {
                return $this->error(60750203);
            }
            $data = $this->getValue();
            return $this->success($data);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750204, $e->getMessage());
        }
    }

    protected function checkInput()
    {
        $fields = [
            'authtype'				=> 'in '.implode(',', [PRS_HTTP_AUTH_NONE, PRS_HTTP_AUTH_BASIC, PRS_HTTP_AUTH_NTLM, PRS_HTTP_AUTH_KERBEROS, PRS_HTTP_AUTH_DIGEST, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
            'headers'				=> 'array',
            'hostid'				=> 'db hosts.hostid',
            'proxy_hostid'			=> 'id',
            'http_authtype'			=> 'in '.implode(',', [PRS_HTTP_AUTH_NONE, PRS_HTTP_AUTH_BASIC, PRS_HTTP_AUTH_NTLM, PRS_HTTP_AUTH_KERBEROS, PRS_HTTP_AUTH_DIGEST, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
            'http_password'			=> 'string',
            'http_proxy'			=> 'string',
            'http_username'			=> 'string',
            'flags'					=> 'in '. implode(',', [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_RULE, PRS_FLAG_DISCOVERY_PROTOTYPE, PRS_FLAG_DISCOVERY_CREATED]),
            'follow_redirects'		=> 'in 0,1',
            'key'					=> 'string',
            'interface'				=> 'array',
            'ipmi_sensor'			=> 'string',
            'item_type'				=> 'required|int32',
            'jmx_endpoint'			=> 'string',
            'macros'				=> 'array',
            'output_format'			=> 'in '.implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON]),
            'params_ap'				=> 'string',
            'params_es'				=> 'string',
            'params_f'				=> 'string',
            'script'				=> 'string',
            'password'				=> 'string',
            'post_type'				=> 'in '.implode(',', [PRS_POSTTYPE_RAW, PRS_POSTTYPE_JSON, PRS_POSTTYPE_XML]),
            'posts'					=> 'string',
            'privatekey'			=> 'string',
            'publickey'				=> 'string',
            'query_fields'			=> 'array',
            'parameters'			=> 'array',
            'request_method'		=> 'in '.implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]),
            'retrieve_mode'			=> 'in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]),
            'snmp_oid'				=> 'string',
            'ssl_cert_file'			=> 'string',
            'ssl_key_file'			=> 'string',
            'ssl_key_password'		=> 'string',
            'status_codes'			=> 'string',
            'test_type'				=> 'required|in '.implode(',', [self::PRS_TEST_TYPE_ITEM, self::PRS_TEST_TYPE_ITEM_PROTOTYPE, self::PRS_TEST_TYPE_LLD]),
            'time_change'			=> 'int32',
            'timeout'				=> 'string',
            'username'				=> 'string',
            'url'					=> 'string',
            'value'					=> 'string',
            'value_type'			=> 'in '.implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT]),
            'verify_host'			=> 'in 0,1',
            'verify_peer'			=> 'in 0,1'
        ];

        $ret = $this->validateInput($fields);

        if ($ret) {
            $testable_item_types = self::getTestableItemTypes($this->getInput('hostid', '0'));
            $this->item_type = $this->getInput('item_type');
            $this->test_type = $this->getInput('test_type');
            $this->is_item_testable = in_array($this->item_type, $testable_item_types);

            if (!$this->is_item_testable) {
                error(t('zapi', 'Test of "{type}" items is not supported.', ['type' => item_type2str($this->item_type)]));
                $ret = false;
            }

            // Check if key is valid for item types it's mandatory.
            if ($ret && in_array($this->item_type, $this->item_types_has_key_mandatory)) {
                $key = $this->getInput('key', '');

                /*
                 * VMware and icmpping simple checks are not supported.
                 * This normally cannot be achieved from UI so no need for error message.
                 */
                if ($this->item_type == ITEM_TYPE_SIMPLE
                    && (substr($key, 0, 7) === 'vmware.' || substr($key, 0, 8) === 'icmpping')) {
                    $ret = false;
                }
                else {
                    $item_key_parser = new CItemKey();

                    if ($item_key_parser->parse($key) != CParser::PARSE_SUCCESS) {
                        error(t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' =>  'key_',
                            'error' => $item_key_parser->getError()
                        ]));
                        $ret = false;
                    }
                }
            }

            // Test interface options.
            $interface = $this->getInput('interface', []);

            if (array_key_exists($this->item_type, $this->items_require_interface)) {
                if (!$this->validateInterface($interface)) {
                    $ret = false;
                }
            }

            if ($this->item_type == ITEM_TYPE_CALCULATED) {
                $expression_parser = new CExpressionParser([
                    'usermacros' => true,
                    'lldmacros' => ($this->getInput('test_type') == self::PRS_TEST_TYPE_ITEM_PROTOTYPE),
                    'calculated' => true,
                    'host_macro' => true,
                    'empty_host' => true
                ]);

                if ($expression_parser->parse($this->getInput('params_f')) != CParser::PARSE_SUCCESS) {
                    error(t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                        'attribute' => t('zapi', 'Formula'),
                        'error' => $expression_parser->getError()
                    ]));
                }
                else {
                    $expression_validator = new CExpressionValidator([
                        'usermacros' => true,
                        'lldmacros' => ($this->getInput('test_type') == self::PRS_TEST_TYPE_ITEM_PROTOTYPE),
                        'calculated' => true
                    ]);

                    if (!$expression_validator->validate($expression_parser->getResult()->getTokens())) {
                        error(t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' => t('zapi', 'Formula'),
                            'error' => $expression_validator->getError()
                        ]));
                    }
                }
            }
        }

        if ($messages = get_and_clear_messages()) {
            self::exception(60750001, implode("\r\n", array_column($messages, 'message')));
        }

        return $ret;
    }

    protected function getValue()
    {
        $PRS_SERVER = env('PERSEUS_SERVER');
        $PRS_SERVER_PORT = env('PERSEUS_SERVER_PORT');

        // Get post data for particular item type.
        $data = $this->getItemTestProperties($this->getInputAll(), true);

        // Apply effective macros values to properties.
        $data = $this->resolveItemPropertyMacros($data);

        if ($this->item_type != ITEM_TYPE_CALCULATED) {
            unset($data['value_type']);
        }
        else {
            $data['host']['hostid'] = $this->getInput('hostid');
        }

        // Rename fields according protocol.
        $data = CArrayHelper::renameKeys($data, [
            'params_ap' => 'params',
            'params_es' => 'params',
            'params_f' => 'params',
            'script' => 'params',
            'http_username' => 'username',
            'http_password' => 'password',
            'http_authtype' => 'authtype',
            'item_type' => 'type'
        ]);

        if (array_key_exists('headers', $data)) {
            $data['headers'] = $this->transformHeaderFields($data['headers']);
        }

        if (array_key_exists('query_fields', $data)) {
            $data['query_fields'] = $this->transformQueryFields($data['query_fields']);
        }

        if (array_key_exists('parameters', $data)) {
            $data['parameters'] = $this->transformParametersFields($data['parameters']);
        }

        // Only non-empty fields need to be sent to server.
        $data = $this->unsetEmptyValues($data);

        /*
         * Server will turn off status code check if field value is empty. If field is not present, then server will
         * default to check if status code is 200.
         */
        if ($this->item_type == ITEM_TYPE_HTTPAGENT && !array_key_exists('status_codes', $data)) {
            $data['status_codes'] = '';
        }

        $output = [
            'user' => [
                'debug_mode' => YII_DEBUG
            ]
        ];

        // Send test to be executed on Perseus server.
        $server = new MonitorServer($PRS_SERVER, $PRS_SERVER_PORT,
            timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
            timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::ITEM_TEST_TIMEOUT)), PRS_SOCKET_BYTES_LIMIT
        );
        $result = $server->testItem($data, (new ZabbixServer())->getToken());

        // Handle the response.
        if ($result === false) {
            error($server->getError());
        }
        elseif (is_array($result)) {
            if (array_key_exists('result', $result)) {
                $output['prev_value'] = $this->getInput('value', '');
                $output['prev_time'] = $this->getPrevTime();
                $output['value'] = $result['result'];
                $output['eol'] = (strstr($result['result'], "\r\n") === false) ? PRS_EOL_LF : PRS_EOL_CRLF;
            }

            if (array_key_exists('error', $result) && $result['error'] !== '') {
                error($result['error']);
            }
        }

        if ($messages = get_and_clear_messages()) {
            self::exception(60750001, implode("\r\n", array_column($messages, 'message')));
        }
        return $output;
    }
}