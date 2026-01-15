<?php
namespace app\customs\zapi\common\server;


use app\customs\zapi\common\helpers\CSettingsHelper;
use app\customs\zapi\common\helpers\ValidateHelper;

/**
 * A class for interacting with the server.
 *
 */
class MonitorServer {

    /**
     * Return item queue overview.
     */
    const QUEUE_OVERVIEW = 'overview';

    /**
     * Return item queue overview by proxy.
     */
    const QUEUE_OVERVIEW_BY_PROXY = 'overview by proxy';

    /**
     * Return a detailed item queue.
     */
    const QUEUE_DETAILS = 'details';

    /**
     * Response value if the request has been executed successfully.
     */
    const RESPONSE_SUCCESS = 'success';

    /**
     * Response value if an error occurred.
     */
    const RESPONSE_FAILED = 'failed';

    /**
     * Auxiliary constants for request() method.
     */
    const PRS_TCP_EXPECT_HEADER = 1;
    const PRS_TCP_EXPECT_DATA = 2;

    /**
     * Max number of bytes to read from the response for each iteration.
     */
    const READ_BYTES_LIMIT = 8192;

    /**
     *  server host name.
     *
     * @var string|null
     */
    protected $host;

    /**
     *  server port number.
     *
     * @var int|null
     */
    protected $port;

    /**
     * Request connect timeout.
     *
     * @var int
     */
    protected $connect_timeout;

    /**
     * Request timeout.
     *
     * @var int
     */
    protected $timeout;

    /**
     * Maximum response size. If the size of the response exceeds this value, an error will be triggered.
     *
     * @var int
     */
    protected $totalBytesLimit;

    /**
     *  server socket resource.
     *
     * @var resource
     */
    protected $socket;

    /**
     * Error message.
     *
     * @var string
     */
    protected $error;

    /**
     * Total result count (if any).
     *
     * @var int
     */
    protected $total;

    /**
     * @var array $debug  Section 'debug' data from server response.
     */
    protected $debug = [];

    /**
     * Class constructor.
     *
     * @param string|null $host
     * @param int|null    $port
     * @param int         $connect_timeout
     * @param int         $timeout
     * @param int         $totalBytesLimit
     */
    public function __construct($host, $port, $connect_timeout, $timeout, $totalBytesLimit) {
        $this->host = $host;
        $this->port = $port;
        $this->connect_timeout = $connect_timeout;
        $this->timeout = $timeout;
        $this->totalBytesLimit = $totalBytesLimit;
    }

    /**
     * Executes a script on the given host or event and returns the result.
     *
     * @param string      $scriptid
     * @param string      $sid
     * @param null|string $hostid
     * @param null|string $eventid
     *
     * @return bool|array
     */
    public function executeScript(string $scriptid, string $sid, ?string $hostid = null, ?string $eventid = null) {
        $params = [
            'request' => 'command',
            'scriptid' => $scriptid,
            'sid' => $sid,
            'clientip' => \Yii::$app->request->getUserIP()
        ];

        if ($hostid !== null) {
            $params['hostid'] = $hostid;
        }

        if ($eventid !== null) {
            $params['eventid'] = $eventid;
        }

        return $this->request($params);
    }

    /**
     * Request server to test item preprocessing steps.
     *
     * @param array  $data                                     Array of preprocessing steps test.
     * @param string $data['value']                            Value to use for preprocessing step testing.
     * @param int    $data['value_type']                       Item value type.
     * @param array  $data['history']                          Previous value object.
     * @param string $data['history']['value']                 Previous value.
     * @param string $data['history']['timestamp']             Previous value time.
     * @param array  $data['steps']                            Preprocessing step object.
     * @param int    $data['steps'][]['type']                  Type of preprocessing step.
     * @param string $data['steps'][]['params']                Parameters of preprocessing step.
     * @param int    $data['steps'][]['error_handler']         Error handler selected as "custom on fail".
     * @param string $data['steps'][]['error_handler_params']  Parameters configured for selected error handler.
     * @param string $sid                                      User session ID.
     *
     * @return array
     */
    public function testPreprocessingSteps(array $data, $sid) {
        return $this->request([
            'request' => 'preprocessing.test',
            'data' => $data,
            'sid' => $sid
        ]);
    }

    /**
     * Request server to test item.
     *
     * @param array  $data  Array of item properties to test.
     * @param string $sid   User session ID.
     *
     * @return array|bool
     */
    public function testItem(array $data, string $sid) {
        return $this->request([
            'request' => 'item.test',
            'data' => $data,
            'sid' => $sid
        ]);
    }

    /**
     * Retrieve item queue information.
     *
     * Possible $type values:
     * - self::QUEUE_OVERVIEW
     * - self::QUEUE_OVERVIEW_BY_PROXY
     * - self::QUEUE_DETAILS
     *
     * @param string $type
     * @param string $sid   user session ID
     * @param int    $limit item count for details type
     *
     * @return bool|array
     */
    public function getQueue($type, $sid, $limit = 0) {
        $request = [
            'request' => 'queue.get',
            'sid' => $sid,
            'type' => $type
        ];

        if ($type == self::QUEUE_DETAILS) {
            $request['limit'] = $limit;
        }

        return $this->request($request);
    }

    /**
     * Request server to test media type.
     *
     * @param array  $data                 Array of media type test data to send.
     * @param string $data['mediatypeid']  Media type ID.
     * @param string $data['sendto']       Message destination.
     * @param string $data['subject']      Message subject.
     * @param string $data['message']      Message body.
     * @param string $data['params']       Custom parameters for media type webhook.
     * @param string $sid                  User session ID.
     *
     * @return bool|array
     */
    public function testMediaType(array $data, $sid) {
        return $this->request([
            'request' => 'alert.send',
            'sid' => $sid,
            'data' => $data
        ]);
    }

    /**
     * Request server to test report.
     *
     * @param array  $data                       Array of report test data to send.
     * @param string $data['name']               Report name (used to make attachment file name).
     * @param string $data['dashboardid']        Dashboard ID.
     * @param string $data['userid']             User ID used to access the dashboard.
     * @param string $data['period']             Report period. Possible values:
     *                                            0 - PRS_REPORT_PERIOD_DAY;
     *                                            1 - PRS_REPORT_PERIOD_WEEK;
     *                                            2 - PRS_REPORT_PERIOD_MONTH;
     *                                            3 - PRS_REPORT_PERIOD_YEAR.
     * @param string $data['now']                Report generation time (seconds since Epoch).
     * @param array  $data['params']             Report parameters.
     * @param string $data['params']['subject']  Report message subject.
     * @param string $data['params']['body']     Report message text.
     * @param string $sid                        User session ID.
     *
     * @return bool|array
     */
    public function testReport(array $data, string $sid) {
        return $this->request([
            'request' => 'report.test',
            'sid' => $sid,
            'data' => $data
        ]);
    }

    /**
     * Retrieve System information.
     *
     * @param $sid
     *
     * @return bool|array
     */
    public function getStatus($sid) {
        $response = $this->request([
            'request' => 'status.get',
            'type' => 'full',
            'sid' => $sid
        ]);

        if ($response === false) {
            return false;
        }

        $api_input_rules = ['type' => API_OBJECT, 'fields' => [
            'template stats' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
                'count' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.PRS_MAX_INT32]
            ]],
            'host stats' =>				['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
                'attributes' =>				['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
                    'proxyid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
                    'status' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])]
                ]],
                'count' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.PRS_MAX_INT32]
            ]],
            'item stats' =>				['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
                'attributes' =>				['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
                    'proxyid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
                    'status' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
                    'state' =>					['type' => API_INT32, 'in' => implode(',', [ITEM_STATE_NORMAL, ITEM_STATE_NOTSUPPORTED])]
                ]],
                'count' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.PRS_MAX_INT32]
            ]],
            'trigger stats' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
                'attributes' =>				['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
                    'status' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [TRIGGER_STATUS_ENABLED, TRIGGER_STATUS_DISABLED])],
                    'value' =>					['type' => API_INT32, 'in' => implode(',', [TRIGGER_VALUE_FALSE, TRIGGER_VALUE_TRUE])]
                ]],
                'count' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.PRS_MAX_INT32]
            ]],
            'user stats' =>				['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
                'attributes' =>				['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
                    'status' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PRS_SESSION_ACTIVE, PRS_SESSION_PASSIVE])]
                ]],
                'count' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.PRS_MAX_INT32]
            ]],
            // only for super-admins 'required performance' is available
            'required performance' =>	['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'fields' => [
                'attributes' =>				['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
                    'proxyid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
                ]],
                'count' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]	// API_FLOAT 0-n
            ]]
        ]];

        if (!ValidateHelper::validate($api_input_rules, $response, '/', $this->error)) {
            return false;
        }

        return $response;
    }

    /**
     * Returns true if the  server is running and false otherwise.
     *
     * @param $sid
     *
     * @return bool
     */
    public function isRunning($sid) {
        $active_node = API::getApiService('hanode')->get([
            'output' => ['address', 'port', 'lastaccess'],
            'filter' => ['status' => PRS_NODE_STATUS_ACTIVE],
            'sortfield' => 'lastaccess',
            'sortorder' => 'DESC',
            'limit' => 1
        ], false);

        if ($active_node && $active_node[0]['address'] === $this->host && $active_node[0]['port'] == $this->port) {
            if ((time() - $active_node[0]['lastaccess']) <
                timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HA_FAILOVER_DELAY))) {
                return true;
            }
        }

        $response = $this->request([
            'request' => 'status.get',
            'type' => 'ping',
            'sid' => $sid
        ]);

        if ($response === false) {
            return false;
        }

        $api_input_rules = ['type' => API_OBJECT, 'fields' => []];

        return ValidateHelper::validate($api_input_rules, $response, '/', $this->error);
    }

    /**
     * Evaluate trigger expressions.
     *
     * @param array  $data
     * @param string $sid
     *
     * @return bool|array
     */
    public function expressionsEvaluate(array $data, string $sid) {
        $response = $this->request([
            'request' => 'expressions.evaluate',
            'sid' => $sid,
            'data' => $data
        ]);

        if ($response === false) {
            return false;
        }

        $api_input_rules = ['type' => API_OBJECTS, 'fields' => [
            'expression' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
            'value' =>		['type' => API_INT32, 'in' => '0,1'],
            'error' =>		['type' => API_STRING_UTF8]
        ]];

        if (!ValidateHelper::validate($api_input_rules, $response, '/', $this->error)) {
            return false;
        }

        return $response;
    }

    /**
     * Returns the error message.
     *
     * @return string
     */
    public function getError() {
        return $this->error;
    }

    /**
     * Returns the total result count.
     *
     * @return int|null
     */
    public function getTotalCount() {
        return $this->total;
    }

    /**
     * Returns debug section from server response.
     *
     * @return array
     */
    public function getDebug() {
        return $this->debug;
    }

    /**
     * Executes a given JSON request and returns the result. Returns false if an error has occurred.
     *
     * @param array $params
     *
     * @return mixed    the output of the script if it has been executed successfully or false otherwise
     */
    protected function request(array $params) {
        // Reset object state.
        $this->error = null;
        $this->total = null;
        $this->debug = [];

        // Connect to the server.
        if (!$this->connect()) {
            return false;
        }

        // Set timeout.
        stream_set_timeout($this->socket, $this->timeout);

        // Send the command.
        $json = json_encode($params);
        if (fwrite($this->socket, PRS_TCP_HEADER.pack('V', strlen($json))."\x00\x00\x00\x00".$json) === false) {
            $this->error = t('zapi', 'Cannot send command, check connection with Collect server "{name}".', ['name' => $this->host]);
            return false;
        }

        $expect = self::PRS_TCP_EXPECT_HEADER;
        $response = '';
        $response_len = 0;
        $expected_len = null;
        $now = time();

        while (true) {
            if ((time() - $now) >= $this->timeout) {
                $this->error = t('zapi',
                    'Connection timeout of {num} seconds exceeded when connecting to Collect server "{name}".',
                    ['num' => $this->timeout, 'host' => $this->host]
                );
                return false;
            }

            if (!feof($this->socket) && ($buffer = fread($this->socket, self::READ_BYTES_LIMIT)) !== false) {
                $response_len += strlen($buffer);
                $response .= $buffer;

                if ($expect == self::PRS_TCP_EXPECT_HEADER) {
                    if (strncmp($response, PRS_TCP_HEADER, min($response_len, PRS_TCP_HEADER_LEN)) != 0) {
                        $this->error = t('zapi', 'Incorrect response received from Collect server "{name}".', ['name' => $this->host]);
                        return false;
                    }

                    if ($response_len < PRS_TCP_HEADER_LEN) {
                        continue;
                    }

                    $expect = self::PRS_TCP_EXPECT_DATA;
                }

                if ($response_len < PRS_TCP_HEADER_LEN + PRS_TCP_DATALEN_LEN) {
                    continue;
                }

                if ($expected_len === null) {
                    $expected_len = unpack('Vlen', substr($response, PRS_TCP_HEADER_LEN, 4))['len'];
                    $expected_len += PRS_TCP_HEADER_LEN + PRS_TCP_DATALEN_LEN;

                    if ($this->totalBytesLimit != 0 && $expected_len >= $this->totalBytesLimit) {
                        $this->error = t('zapi',
                            'Size of the response received from Collect server "{name}" exceeds the allowed size of {num} bytes. This value can be increased in the PRS_SOCKET_BYTES_LIMIT constant in include/defines.inc.php.',
                            ['name' => $this->host, 'num' => $this->totalBytesLimit]
                        );
                        return false;
                    }
                }

                if ($response_len >= $expected_len) {
                    break;
                }
            }
            else {
                $this->error =
                    t('zapi', 'Cannot read the response, check connection with the Collect server "{name}".', ['name' => $this->host]);
                return false;
            }
        }

        fclose($this->socket);

        if ($expected_len > $response_len || $response_len > $expected_len) {
            $this->error = t('zapi', 'Incorrect response received from Collect server "{name}".', ['name' => $this->host]);
            return false;
        }

        $response = json_decode(substr($response, PRS_TCP_HEADER_LEN + PRS_TCP_DATALEN_LEN), true);

        if (!$response || !$this->normalizeResponse($response)) {
            $this->error = t('zapi', 'Incorrect response received from Collect server "{name}".', ['name' => $this->host]);

            return false;
        }

        if (array_key_exists('debug', $response)) {
            $this->debug = $response['debug'];
        }

        // Request executed successfully.
        if ($response['response'] == self::RESPONSE_SUCCESS) {
            // saves total count
            $this->total = array_key_exists('total', $response) ? $response['total'] : null;

            $data =  array_key_exists('data', $response) ? $response['data'] : true;
            if (array_key_exists('data', $response)) {
                $data = $response['data'];
                if (is_array($data)) {
                    $data = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                    $data = preg_replace('/zabbix/i', 'Perseus', $data);
                    return json_decode($data, true);
                } else {
                    return preg_replace('/zabbix/i', 'Perseus', $data);
                }
            }
        }

        // An error on the server side occurred.
        $this->error = preg_replace('/zabbix/i', 'Perseus', rtrim($response['info']));
        return false;
    }

    /**
     * Opens a socket to the Perseus server. Returns the socket resource if the connection has been established or
     * false otherwise.
     *
     * @return bool|resource
     */
    protected function connect() {
        if (!$this->socket) {
            if ($this->host === null || $this->port === null) {
                $this->error = t('zapi', 'Connection to Collect server failed. Incorrect configuration.');
                return false;
            }

            if (!$socket = @fsockopen($this->host, $this->port, $errorCode, $errorMsg, $this->connect_timeout)) {
                $host_port = $this->host . ':' . $this->port;
                switch ($errorMsg) {
                    case 'Connection refused':
                        $dErrorMsg = t('zapi', 'Connection to Collect server "{port}" refused. Possible reasons:\n1. Incorrect "NodeAddress" or "ListenPort" in the "*_server.conf" or server IP/DNS override in the "*.conf.php";\n2. Security environment (for example, SELinux) is blocking the connection;\n3. Collect server daemon not running;\n4. Firewall is blocking TCP connection.\n', ['port' => $host_port]);
                        break;

                    case 'No route to host':
                        $dErrorMsg = t('zapi', 'Collect server "{port}" cannot be reached. Possible reasons:\n1. Incorrect "NodeAddress" or "ListenPort" in the "*_server.conf" or server IP/DNS override in the "*.conf.php";\n2. Incorrect network configuration.\n', ['port' => $host_port]);
                        break;

                    case 'Connection timed out':
                        $dErrorMsg = t('zapi', 'Connection to Collect server "{port}" timed out. Possible reasons:\n1. Incorrect "NodeAddress" or "ListenPort" in the "*_server.conf" or server IP/DNS override in the "*.conf.php";\n2. Firewall is blocking TCP connection.\n', ['port' => $host_port]);
                        break;

                    default:
                        $dErrorMsg = t('zapi', 'Connection to Collect server "{port}" failed. Possible reasons:\n1. Incorrect "NodeAddress" or "ListenPort" in the "*_server.conf" or server IP/DNS override in the "*.conf.php";\n2. Incorrect DNS server configuration.\n ', ['port' => $host_port]);
                }

                $this->error = rtrim($dErrorMsg.$errorMsg);
            }

            $this->socket = $socket;
        }

        return $this->socket;
    }

    /**
     * Returns true if the response received from the server is valid.
     *
     * @param array $response
     *
     * @return bool
     */
    protected function normalizeResponse(array &$response) {
        return (array_key_exists('response', $response) && ($response['response'] == self::RESPONSE_SUCCESS
                || $response['response'] == self::RESPONSE_FAILED && array_key_exists('info', $response))
        );
    }
}

