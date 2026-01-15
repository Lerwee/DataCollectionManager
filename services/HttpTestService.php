<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\managers\base\ManagerTrait;
use app\customs\zapi\common\managers\HttpTestManager;
use app\customs\zapi\common\managers\ItemManager;
use app\customs\zapi\common\parsers\CRangesParser;
use app\customs\zapi\components\data\HttpTestRequestData;
use app\customs\zapi\forms\HttpTestForm;
use app\customs\zapi\services\assist\BaseAssist;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\Httpstep;
use app\modules\libzbx\models\zbx\HttpstepField;
use app\modules\libzbx\models\zbx\Httpstepitem;
use app\modules\libzbx\models\zbx\Httptest;
use app\modules\libzbx\models\zbx\HttptestField;
use app\modules\libzbx\models\zbx\Httptestitem;
use app\modules\libzbx\models\zbx\HttptestTag;
use app\modules\libzbx\models\zbx\Items;
use Yii;
use yii\base\Exception;
use yii\db\Query;

class HttpTestService extends BaseAssist
{
    use ManagerTrait;

    private $websites;

    /**
     * create
     *
     * @param array $params
     * @return Result
     */
    public function create(array $params, bool $internal = true): Result
    {
        if (!$internal) {
            $request = new HttpTestRequestData(['data' => $params]);
            if (!$request->isSuccess()) {
                return $request->getResult();
            }
            $params = $request->getData();
        }
        $params['enableTransaction'] = true;
        $result = $this->createByInternal($params);
        return $result;
    }

    /**
     * Create new HTTP tests.
     *
     * @param array $httpTests
     *
     * @return Result
     */
    public function createByInternal(array $httpTests): Result
    {
        $enableTransaction = false;
        if (isset($params['enableTransaction'])) {
            $enableTransaction = (bool) $params['enableTransaction'];
            unset($params['enableTransaction']);
        }
        $enableTransaction && $transaction = Httptest::getDb()->beginTransaction();
        try {
            $this->validateCreate($httpTests);
            $httpTests = HttpTestManager::instance()->persist($httpTests);
            $enableTransaction && $transaction->commit();
            // TODO: zbx audit

            return $this->success(['httptestids' => array_column($httpTests, 'httptestid')]);
        } catch (Exception $e) {
            $enableTransaction && $transaction->rollBack();
            return $this->errorException($e);
        }
    }

    /**
     * @param array $httpTests
     *
     * @throws ValidateException if the input is invalid.
     */
    protected function validateCreate(array &$httpTests)
    {
        $rules = HttpTestForm::getValidationRules();
        $bool = ValidateHelper::validateObjects($httpTests, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostid', 'name']]], $error);
        if (!$bool) {
            self::exception(60750001, $error);
        }

        $id2names = [];

        foreach ($httpTests as $httpTest) {
            $id2names[$httpTest['hostid']][] = $httpTest['name'];
        }

        self::checkHostsAndTemplates($httpTests, $db_hosts, $db_templates);
        self::addHostStatus($httpTests, $db_hosts, $db_templates);

        self::validateUuid($httpTests, $db_hosts + $db_templates);

        self::addUuid($httpTests, $db_templates);

        self::checkUuidDuplicates($httpTests);
        $this->checkDuplicates($id2names);
        $this->validateAuthParameters($httpTests, __FUNCTION__);
        $this->validateSslParameters($httpTests, __FUNCTION__);
        $this->validateSteps($httpTests, __FUNCTION__);
    }

    /**
     * Undocumented function
     *
     * @param  array   $params
     * @param  boolean $internal
     * @return Result
     */
    public function update(array $params, bool $internal = true): Result
    {
        if (!$internal) {
            $request = new HttpTestRequestData(['data' => $params]);
            if (!$request->isSuccess()) {
                return $request->getResult();
            }
            $params = $request->getData();
        }
        $params['enableTransaction'] = true;
        $result = $this->updateByInternal($params);
        return $result;
    }

    /**
     * Update new HTTP tests.
     *
     * @param array $httpTests
     *
     * @return Result
     */
    public function updateByInternal(array $httpTests): Result
    {
        $enableTransaction = false;
        if (isset($params['enableTransaction'])) {
            $enableTransaction = (bool) $params['enableTransaction'];
            unset($params['enableTransaction']);
        }
        $enableTransaction && $transaction = Httptest::getDb()->beginTransaction();
        try {
            $this->validateUpdate($httpTests, $dbHttpTests);
            $httpTests = HttpTestManager::instance()->persist($httpTests);
            $enableTransaction && $transaction->commit();
            // foreach ($dbHttpTests as &$dbHttpTest) {
            //     unset($dbHttpTest['headers'], $dbHttpTest['variables'], $dbHttpTest['steps']);
            // }
            // unset($dbHttpTest);
            // TODO: zbx audit

            return $this->success(['httptestids' => array_column($httpTests, 'httptestid')]);
        } catch (Exception $e) {
            $enableTransaction && $transaction->rollBack();
            return $this->errorException($e);
        }
    }

    /**
     * @param array $httpTests
     * @param array $dbHttpTests
     *
     * @throws ValidateException if the input is invalid.
     */
    protected function validateUpdate(array &$httpTests, array &$dbHttpTests = null)
    {
        $rules = HttpTestForm::getValidationRules('update');

        $bool = ValidateHelper::validateObjects($httpTests, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['httptestid']]], $error);
        if (!$bool) {
            self::exception(60750001, $error);
        }

        $dbHttpTests = $this->getHttpTestsByIds(array_column($httpTests, 'httptestid'));


        $db_hosts = [];

        foreach ($dbHttpTests as &$dbHttpTest) {
            $dbHttpTest['headers'] = [];
            $dbHttpTest['variables'] = [];
            $dbHttpTest['steps'] = prs_toHash($dbHttpTest['steps'], 'httpstepid');

            $db_hosts[$dbHttpTest['hostid']] = $dbHttpTest['hosts'][0]['status'] == HOST_STATUS_TEMPLATE
                ? []
                : $dbHttpTest['hosts'][0];
        }
        unset($dbHttpTest);

        $id2names = [];

        foreach ($httpTests as $httpTest) {
            if (!array_key_exists($httpTest['httptestid'], $dbHttpTests)) {
                self::exception(60750001, t('zapi', 'No permissions to referred object or it does not exist!'));
            }

            $dbHttpTest = $dbHttpTests[$httpTest['httptestid']];

            if (array_key_exists('name', $httpTest)) {
                if ($dbHttpTest['templateid'] != 0) {
                    self::exception(PRS_API_ERROR_PARAMETERS, _s(
                        'Cannot update a templated web scenario "%1$s": %2$s.',
                        $httpTest['name'],
                        _s('unexpected parameter "%1$s"', 'name')
                    ));
                    self::exception(60750001, t('zapi', 'Cannot update a templated web scenario "{name}": {error}.', [
                        'name' => $httpTest['name'],
                        'error' => t('zapi', 'unexpected parameter "{parameter}"', ['parameter' => 'name']),
                    ]));
                }

                if ($httpTest['name'] !== $dbHttpTest['name']) {
                    $id2names[$dbHttpTest['hostid']][] = $httpTest['name'];
                }
            }
        }

        $httpTests = $this->extendObjectsByKey($httpTests, $dbHttpTests, 'httptestid', ['hostid', 'name']);

        // uniqueness
        foreach ($httpTests as &$httpTest) {
            $dbHttpTest = $dbHttpTests[$httpTest['httptestid']];

            if (array_key_exists('steps', $httpTest)) {
                // unexpected parameters for templated web scenario steps
                if ($dbHttpTest['templateid'] != 0) {
                    foreach ($httpTest['steps'] as $httpstep) {
                        foreach (['name', 'no'] as $field_name) {
                            if (array_key_exists($field_name, $httpstep)) {
                                self::exception(PRS_API_ERROR_PARAMETERS, _s(
                                    'Cannot update step for a templated web scenario "%1$s": %2$s.',
                                    $httpTest['name'],
                                    _s('unexpected parameter "%1$s"', $field_name)
                                ));
                            }
                        }
                    }
                }
                $httpTest['steps'] = $this->extendObjectsByKey($httpTest['steps'], $dbHttpTest['steps'], 'httpstepid', ['name']);
            }
        }
        unset($httpTest);

        HttpTestForm::validateUniqueness($httpTests);

        self::validateUuid($httpTests, $db_hosts);

        self::checkUuidDuplicates($httpTests, $dbHttpTests);

        // validation
        if ($id2names) {
            $this->checkDuplicates($id2names);
        }
        $this->validateAuthParameters($httpTests, __FUNCTION__, $dbHttpTests);
        $this->validateSslParameters($httpTests, __FUNCTION__, $dbHttpTests);
        $this->validateSteps($httpTests, __FUNCTION__, $dbHttpTests);

        return $httpTests;
    }


    public function getHttpTestsByIds(array $ids, array $hostIds = [])
    {
        if ($this->websites !== null) {
            return $this->websites;
        }
        if ($hostIds) {
            $idWhereIn = SqlHelper::whereIn('hostid', $hostIds);
        } else {
            $idWhereIn = SqlHelper::whereIn('httptestid', $ids);
        }

        $this->websites = Httptest::find()
            ->select([
                'uuid', 'httptestid', 'hostid', 'name', 'delay', 'retries', 'agent', 'http_proxy',
                'status', 'authentication', 'http_user', 'http_password', 'verify_peer', 'verify_host',
                'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'templateid'
            ])
            ->where($idWhereIn)
            ->indexBy('httptestid')
            ->asArray()
            ->all();
        
        if (stripos($idWhereIn, 'hostid') !== false) {
            $idWhereIn = SqlHelper::whereIn('httptestid', array_column($this->websites, 'httptestid'));
        }

        $dbHttpFields = HttptestField::find()
            ->select(['httptestid', 'type', 'name', 'value'])
            ->where($idWhereIn)
            ->asArray()
            ->all();

        $params = [];
        foreach ($dbHttpFields as $dbHttpField) {
            $params[$dbHttpField['httptestid']][$dbHttpField['type']][] = [
                'name' => $dbHttpField['name'],
                'value' => $dbHttpField['value'],
            ];
        }

        foreach ($this->websites as &$dbHttpTest) {
            $dbHttpTest['headers'] = [];
            $dbHttpTest['variables'] = [];
            if (array_key_exists($dbHttpTest['httptestid'], $params)) {
                $fields = $params[$dbHttpTest['httptestid']];
                $dbHttpTest['headers'] = $fields[PRS_HTTPFIELD_HEADER]  ?? [];
                $dbHttpTest['variables'] = $fields[PRS_HTTPFIELD_VARIABLE]  ?? [];
            }
        }
        unset($dbHttpTest);

        $dbHttpSteps = Httpstep::find()
            ->select([
                'httptestid', 'httpstepid', 'name', 'no', 'url', 'timeout', 'posts', 'required',
                'status_codes', 'follow_redirects', 'retrieve_mode', 'post_type'
            ])
            ->where($idWhereIn)
            ->asArray()
            ->all();
        $fields = HttpstepField::find()
            ->select(['httpstepid', 'type', 'name', 'value'])
            ->where(SqlHelper::whereIn('httpstepid', array_column($dbHttpSteps, 'httpstepid')))
            ->asArray()
            ->all();
        $params = [];
        foreach ($fields as $field) {
            $params[$field['httpstepid']][$field['type']][] = [
                'name' => $field['name'],
                'value' => $field['value'],
            ];
        }
        unset($fields);

        foreach ($dbHttpSteps as $dbHttpStep) {
            $dbHttpStep['posts'] = [];
            $dbHttpStep['headers'] = [];
            $dbHttpStep['variables'] = [];
            $dbHttpStep['query_fields'] = [];
            if (array_key_exists($dbHttpStep['httpstepid'], $params)) {
                $fields = $params[$dbHttpStep['httpstepid']];
                $dbHttpStep['headers'] = $fields[PRS_HTTPFIELD_HEADER]  ?? [];
                $dbHttpStep['variables'] = $fields[PRS_HTTPFIELD_VARIABLE]  ?? [];
                $dbHttpStep['posts'] = $fields[PRS_HTTPFIELD_POST_FIELD]  ?? [];
                $dbHttpStep['query_fields'] = $fields[PRS_HTTPFIELD_QUERY_FIELD]  ?? [];
            }
            $this->websites[$dbHttpStep['httptestid']]['steps'][] = $dbHttpStep;
        }
        unset($dbHttpStep, $dbHttpSteps);


        $hosts = Hosts::find()
            ->select(['hostid', 'status'])
            ->where(SqlHelper::whereIn('hostid', array_column($this->websites, 'hostid')))
            ->indexBy('hostid')
            ->asArray()
            ->all();

        foreach ($this->websites as &$dbHttpTest) {
            $dbHttpTest['hosts'] = array_key_exists($dbHttpTest['hostid'], $hosts) ? [$hosts[$dbHttpTest['hostid']]] : [];
        }
        unset($dbHttpTest);

        return $this->websites;
    }



    public function delete(array $ids, bool $internal = true): Result
    {
        try {
            self::validateDelete($ids, $dbHttpTests);
            self::deleteForce(array_column($dbHttpTests, 'name', 'httptestid'));
            return $this->success(['httptestids' => $ids]);
        } catch (Exception $e) {
            return $this->errorException($e);
        }
    }

    /**
     * @param array      $httpTestIds
     * @param array|null $dbHttpTests
     */
    private function validateDelete(array $httpTestIds, ?array &$dbHttpTests): void
    {
        $dbHttpTests = Httptest::find()
            ->select(['httptestid', 'name', 'templateid'])
            ->where(SqlHelper::whereIn('httptestid', filter_integer($httpTestIds)))
            ->asArray()
            ->indexBy('httptestid')
            ->all();

        if (count($dbHttpTests) != count($httpTestIds)) {
            self::exception(60750001, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        foreach ($httpTestIds as $httpTestId) {
            if ($dbHttpTests[$httpTestId]['templateid'] != 0) {
                self::exception(60750001, t('zapi', 'Cannot delete templated web scenario "{name}".', [
                    'name' => $dbHttpTests[$httpTestId]['name']
                ]));
            }
        }
    }

    /**
     * Check for duplicated web scenarios.
     *
     * @param array $id2names
     *
     * @throws ValidateException  if web scenario already exists.
     */
    private function checkDuplicates(array $id2names)
    {
        $conditions = [];
        foreach ($id2names as $hostid => $names) {
            $conditions[] = ['hostid' => $hostid, 'name' => $names];
        }

        if (count($conditions) > 1) {
            array_unshift($conditions, 'OR');
        } else {
            $conditions = current($conditions);
        }

        $name = Httptest::find()->select('name')->where($conditions)->asArray()->limit(1)->scalar();
        if ($name) {
            self::exception(60750001, t('zapi', 'Web scenario "{name}" already exists.', [
                'name' => $name
            ]));
        }
    }

    /**
     * @param array  $httpTests
     * @param string $method
     * @param array  $db_httptests
     *
     * @throws ValidateException  if auth parameters are invalid.
     */
    private function validateAuthParameters(array &$httpTests, $method, array $db_httptests = null)
    {
        foreach ($httpTests as &$httpTest) {
            if (
                array_key_exists('authentication', $httpTest) || array_key_exists('http_user', $httpTest)
                || array_key_exists('http_password', $httpTest)
            ) {
                $httpTest += [
                    'authentication' => ($method === 'validateUpdate')
                        ? $db_httptests[$httpTest['httptestid']]['authentication']
                        : PRS_HTTP_AUTH_NONE
                ];

                if ($httpTest['authentication'] == PRS_HTTP_AUTH_NONE) {
                    foreach (['http_user', 'http_password'] as $field_name) {
                        $httpTest += [$field_name => ''];

                        if ($httpTest[$field_name] !== '') {
                            self::exception(60750001, t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                                'attribute' => $field_name,
                                'error' => t('zapi', 'should be empty')
                            ]));
                        }
                    }
                }
            }
        }
        unset($httpTest);
    }

    /**
     * @param array  $httpTests
     * @param string $method
     * @param array  $db_httptests
     *
     * @throws ValidateException if SSL cert is present but SSL key is not.
     */
    private function validateSslParameters(array &$httpTests, $method, array $db_httptests = null)
    {
        foreach ($httpTests as &$httptest) {
            if (
                array_key_exists('ssl_key_password', $httptest)
                || array_key_exists('ssl_key_file', $httptest)
                || array_key_exists('ssl_cert_file', $httptest)
            ) {
                if ($method === 'validateCreate') {
                    $httptest += [
                        'ssl_key_password' => '',
                        'ssl_key_file' => '',
                        'ssl_cert_file' => ''
                    ];
                } else {
                    $db_httptest = $db_httptests[$httptest['httptestid']];
                    $httptest += [
                        'ssl_key_password' => $db_httptest['ssl_key_password'],
                        'ssl_key_file' => $db_httptest['ssl_key_file'],
                        'ssl_cert_file' => $db_httptest['ssl_cert_file']
                    ];
                }

                if ($httptest['ssl_key_password'] != '' && $httptest['ssl_key_file'] == '') {
                    self::exception(60750001, t('zapi', 'Empty SSL key file for web scenario "{name}".', ['name' => $httptest['name']]));
                }

                if ($httptest['ssl_key_file'] != '' && $httptest['ssl_cert_file'] == '') {
                    self::exception(60750001, t('zapi', 'Empty SSL certificate file for web scenario "{name}".', ['name' => $httptest['name']]));
                }
            }
        }
        unset($httptest);
    }


    /**
     * @param array  $httptests
     * @param string $method
     * @param array  $db_httptests
     *
     * @throws ValidateException
     */
    protected function validateSteps(array &$httptests, $method, array $db_httptests = null)
    {
        if ($method === 'validateUpdate') {
            foreach ($httptests as $httptest) {
                if (!array_key_exists('steps', $httptest)) {
                    continue;
                }

                $db_httptest = $db_httptests[$httptest['httptestid']];

                if ($db_httptest['templateid'] != 0 && count($httptest['steps']) != count($db_httptest['steps'])) {
                    self::exception(60750001, t('zapi', 'Incorrect templated web scenario step count.'));
                }

                foreach ($httptest['steps'] as $step) {
                    if (!array_key_exists('httpstepid', $step)) {
                        if ($db_httptest['templateid'] == 0) {
                            continue;
                        } else {
                            self::exception(60750001, t('zapi', 'Cannot update step for a templated web scenario "{name}": {error}.', [
                                'name' => $httptest['name'],
                                'error' => t('zapi', 'the parameter "{parameter}" is missing', ['parameter' => 'httpstepid'])
                            ]));
                        }
                    }

                    if (!array_key_exists($step['httpstepid'], $db_httptest['steps'])) {
                        self::exception(60750001, t('zapi', 'No permissions to referred object or it does not exist!'));
                    }
                }
            }
        }

        $this->checkStatusCodes($httptests);
        $this->validateRetrieveMode($httptests, $method, $db_httptests);
    }

    /**
     * Validate http response code range.
     * Range can be empty string or list of comma separated numeric strings or user macros.
     *
     * Examples: '100-199, 301, 404, 500-550, {$MACRO}-200, {$MACRO}-{$MACRO}'
     *
     * @param array $httptests
     *
     * @throws ValidateException if the status code range is invalid.
     */
    private function checkStatusCodes(array $httptests)
    {
        $ranges_parser = new CRangesParser(['usermacros' => true]);

        foreach ($httptests as $httptest) {
            if (!array_key_exists('steps', $httptest)) {
                continue;
            }

            foreach ($httptest['steps'] as $httpstep) {
                if (!array_key_exists('status_codes', $httpstep) || $httpstep['status_codes'] === '') {
                    continue;
                }

                if ($ranges_parser->parse($httpstep['status_codes']) != CRangesParser::PARSE_SUCCESS) {
                    self::exception(60750001, t('zapi', 'Invalid response code "{code}".', [
                        'code' => $httpstep['status_codes']
                    ]));
                }
            }
        }
    }

    /**
     * @param array  $httptests
     * @param string $method
     * @param array  $db_httptests
     *
     * @throws ValidateException if parameters is invalid.
     */
    private function validateRetrieveMode(array &$httptests, $method, array $db_httptests = null)
    {
        foreach ($httptests as &$httptest) {
            if (!array_key_exists('steps', $httptest)) {
                continue;
            }

            foreach ($httptest['steps'] as &$httpstep) {
                if (
                    array_key_exists('retrieve_mode', $httpstep)
                    || array_key_exists('posts', $httpstep)
                    || array_key_exists('required', $httpstep)
                ) {

                    if ($method === 'validateCreate' || !array_key_exists('httpstepid', $httpstep)) {
                        $httpstep += [
                            'retrieve_mode' => HTTPTEST_STEP_RETRIEVE_MODE_CONTENT,
                            'posts' => '',
                            'required' => ''
                        ];
                    } else {
                        $db_httptest = $db_httptests[$httptest['httptestid']];
                        $db_httpstep = $db_httptest['steps'][$httpstep['httpstepid']];
                        $httpstep += [
                            'retrieve_mode' => $db_httpstep['retrieve_mode'],
                            'required' => $db_httpstep['required'],
                            'posts' => ($db_httpstep['retrieve_mode'] != HTTPTEST_STEP_RETRIEVE_MODE_HEADERS)
                                ? $db_httpstep['posts']
                                : ''
                        ];
                    }

                    if ($httpstep['retrieve_mode'] == HTTPTEST_STEP_RETRIEVE_MODE_HEADERS) {
                        if ($httpstep['posts'] !== '' && $httpstep['posts'] !== []) {
                            $field_name = $httpstep['required'] !== '' ? 'required' : 'posts';
                            self::exception(60750001, t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                                'attribute' => $field_name,
                                'error' => t('zapi', 'should be empty')
                            ]));
                        }
                    }
                }
            }
            unset($httpstep);
        }
        unset($httptest);
    }

    /**
     * Check that host IDs of given web scenarios are valid.
     * If host IDs are valid, $db_hosts and $db_templates parameters will be filled with found hosts and templates.
     *
     * @param array      $httptests
     * @param array|null $db_hosts
     * @param array|null $db_templates
     *
     * @throws ValidateException
     */
    protected static function checkHostsAndTemplates(array $httptests, array &$dbHosts = null,  array &$dbTemplates = null): void
    {
        $hostIds = array_unique(array_column($httptests, 'hostid'));

        $hosts = Hosts::find()
            ->select(['hostid', 'name', 'host', 'status', 'uuid'])
            ->where(SqlHelper::whereIn('hostid', $hostIds))
            ->andWhere(['status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE]])
            ->asArray()
            ->all();


        foreach ($hosts as $host) {
            $hostId = $host['hostid'];
            if ($host['status'] == HOST_STATUS_TEMPLATE) {
                $host['templateid'] = $host['hostid'];
                unset($host['hostid']);
                $dbTemplates[$hostId] = $host;
            } else {
                $dbHosts[$hostId] = $host;
            }
        }
        if (count($hosts) != count($hostIds)) {
            self::exception(60750001, _('No permissions to referred object or it does not exist!'));
        }
        $dbHosts === null && $dbHosts = [];
        $dbTemplates === null && $dbTemplates = [];
    }


    /**
     * Add host_status property to given web scenarios in accordance of given hosts and templates statuses.
     *
     * @param array $httpTests
     * @param array $db_hosts
     * @param array $db_templates
     */
    protected static function addHostStatus(array &$httpTests, array $db_hosts, array $db_templates): void
    {
        foreach ($httpTests as &$httpTest) {
            if (array_key_exists($httpTest['hostid'], $db_templates)) {
                $httpTest['host_status'] = HOST_STATUS_TEMPLATE;
            } else {
                $httpTest['host_status'] = $db_hosts[$httpTest['hostid']]['status'];
            }
        }
        unset($httpTest);
    }

    /**
     * @param array $httpTests
     * @param array $db_hosts
     * @param string $method
     *
     * @throws ValidateException
     */
    private static function validateUuid(array $httpTests, array $db_hosts, string $method = 'create'): void
    {
        foreach ($httpTests as &$httpTest) {
            $httptest['host_status'] = array_key_exists('status', $db_hosts[$httpTest['hostid']])
                ? $db_hosts[$httpTest['hostid']]['status']
                : HOST_STATUS_TEMPLATE;
        }
        unset($httpTest);

        $rules = HttpTestForm::getUuidValidationRules($method, false);

        $bool = ValidateHelper::validateObjects($httpTests, $rules, ['flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid']]], $error);
        if (!$bool) {
            self::exception(60750001, $error);
        }
    }


    /**
     * Add the UUID to those of the given web scenarios that belong to a template and don't have the 'uuid' parameter
     * set.
     *
     * @param array $httpTests
     * @param array $db_templates
     */
    private static function addUuid(array &$httpTests, array $db_templates): void
    {
        foreach ($httpTests as &$httpTest) {
            if (array_key_exists($httpTest['hostid'], $db_templates) && !array_key_exists('uuid', $httpTest)) {
                $httpTest['uuid'] = generateUuidV4();
            }
        }
        unset($httpTest);
    }

    /**
     * Verify web scenario UUIDs are not repeated.
     *
     * @param array      $httpTests
     * @param array|null $db_httptests
     *
     * @throws APIException
     */
    private static function checkUuidDuplicates(array $httpTests, array $db_httptests = null): void
    {
        $httptest_indexes = [];

        foreach ($httpTests as $i => $httpTest) {
            if (!array_key_exists('uuid', $httpTest) || $httpTest['uuid'] === '') {
                continue;
            }

            if ($db_httptests === null || $httpTest['uuid'] !== $db_httptests[$httpTest['httptestid']]['uuid']) {
                $httptest_indexes[$httpTest['uuid']] = $i;
            }
        }

        if (!$httptest_indexes) {
            return;
        }

        $uuid = Httptest::find()->select(['uuid'])->where(['uuid' =>  array_keys($httptest_indexes)])
            ->limit(1)
            ->asArray()
            ->scalar();

        if ($uuid) {
            self::exception(60750501, t('zapi', 'Invalid parameter {attribute}, {error}', [
                'attribute' => '/' . ($httptest_indexes[$uuid] + 1),
                'error' => t('zapi', 'web scenario with the same UUID already exists')
            ]));
        }
    }

    /**
     * @param array $id2name [httptestid => name]
     */
    public static function deleteForce(array $id2name): void
    {
        $id2name = self::getInheritedOrDependentData($id2name, Httptest::tableName(), 'templateid', 'httptestid');

        $httpTestIds = array_keys($id2name);
        $idWhereIn = SqlHelper::whereIn('httptestid', $httpTestIds);

        self::deleteAffectedItems($idWhereIn);
        self::deleteAffectedSteps($idWhereIn);

        HttptestField::deleteAll($idWhereIn);
        HttptestTag::deleteAll($idWhereIn);
        Httptest::updateAll(['templateid' => null], $idWhereIn);
        Httptest::deleteAll($idWhereIn);
        // TODO:zbx audit
    }

    /**
     * Delete items, which would remain without web scenarios after the given web scenarios deletion.
     *
     * @param string $httpTestIdWhere
     */
    private static function deleteAffectedItems($httpTestIdWhere): void
    {
        $query = new Query();
        $query->from([
            'hti' => Httptestitem::tableName(),
            'i' => Items::tableName(),
        ]);
        $query->where('hti.itemid=i.itemid')
            ->andWhere(str_replace('httptestid', '{{hti}}.httptestid', $httpTestIdWhere));
        $query->select(['i.name', 'hti.itemid'])
            ->indexBy('itemid');
        $id2name = $query->column();
        $id2name = self::getInheritedOrDependentData($id2name, Items::tableName(), 'templateid', 'itemid');

        Httptestitem::deleteAll(SqlHelper::whereIn('itemid', array_keys($id2name)));
        ItemManager::deleteForce($id2name);
    }

    /**
     * Delete steps of the given web scenarios.
     *
     * @param array $httpTestIdWhere
     */
    private static function deleteAffectedSteps($httpTestIdWhere): void
    {
        $query = Httpstep::find()
            ->select('httpstepid');
        $query->where($httpTestIdWhere);

        $stepIds = $query->column();

        $stepIdWhere = SqlHelper::whereIn('httpstepid', $stepIds);

        self::deleteAffectedStepItems($stepIdWhere);
        HttpstepField::deleteAll($stepIdWhere);
        Httpstep::deleteAll($stepIdWhere);
    }

    /**
     * Delete items of the given web scenario steps.
     *
     * @param string $stepIds
     */
    private static function deleteAffectedStepItems($stepIdWhere): void
    {
        $query = new Query();
        $query->from([
            'hsi' => Httpstepitem::tableName(),
            'i' => Items::tableName(),
        ]);
        $query->where('hsi.itemid=i.itemid')
            ->andWhere(str_replace('httpstepid', '{{hsi}}.httpstepid', $stepIdWhere));
        $query->select(['i.name', 'hsi.itemid'])
            ->indexBy('itemid');
        $id2name = $query->column();
        $id2name = self::getInheritedOrDependentData($id2name, Items::tableName(), 'templateid', 'itemid');

        Httpstepitem::deleteAll(SqlHelper::whereIn('itemid', array_keys($id2name)));
        ItemManager::deleteForce($id2name);
    }

    /**
     * @param array      $templateIds
     * @param array|null $hostIds
     */
    public static function unlinkTemplateObjects(array $templateIds, array $hostIds = null): void
    {
        $query = new Query();
        $query->from([
            'ht' => Httptest::tableName(),
            'hht' => Httptest::tableName(),
            'h' => Hosts::tableName(),
        ]);
        $query->where('ht.httptestid=hht.templateid')
            ->andWhere('hht.hostid=h.hostid')
            ->andWhere(SqlHelper::whereIn('ht.hostid', $templateIds));

        if ($hostIds) {
            $query->andWhere(SqlHelper::whereIn('hht.hostid', $hostIds));
        }

        $query->select(['hht.name', 'hht.httptestid', 'host_status' => 'h.status']);

        $httpTests = [];

        foreach ($query->each() as $row) {
            $httpTest = [
                'httptestid' => $row['httptestid'],
                'name' => $row['name'],
                'templateid' => 0
            ];

            if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
                $httpTest += ['uuid' => generateUuidV4()];
            }

            $httpTests[] = $httpTest;
        }

        if ($httpTests) {
            static::instance()->update($httpTests);
        }
    }

    /**
     * @param array      $templateIds
     * @param array|null $hostIds
     */
    public static function clearTemplateObjects(array $templateIds, array $hostIds = null): void
    {
        $query = new Query();
        $query->from([
            'ht' => Httptest::tableName(),
            'hht' => Httptest::tableName(),
        ]);
        $query->where('ht.httptestid=hht.templateid')
            ->andWhere(SqlHelper::whereIn('ht.hostid', $templateIds));

        if ($hostIds) {
            $query->andWhere(SqlHelper::whereIn('hht.hostid', $hostIds));
        }

        $query->select(['hht.name', 'hht.httptestid'])->indexBy('httptestid');
        $id2name = $query->column();

        if ($id2name) {
            self::deleteForce($id2name);
        }
    }
}
