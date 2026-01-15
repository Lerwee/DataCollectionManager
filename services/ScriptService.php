<?php

namespace app\customs\zapi\services;

use app\common\helpers\ArrayHelper;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\SettingHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\server\MonitorServer;
use app\customs\zapi\forms\scripts\ScriptGetForm;
use app\customs\zapi\models\search\ScriptSearch;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Scripts;
use app\modules\libzbx\models\zbx\Actions;
use app\modules\libzbx\models\zbx\Opcommand;
use app\modules\libzbx\models\zbx\Operations;
use app\modules\libzbx\models\zbx\ScriptParam;
use app\modules\libzbx\models\zbx\Users;
use yii\base\Exception;
use yii\db\Query;
use Yii;

class ScriptService extends BaseService
{
    public function getScript($params)
    {
        try {
            $options = [
                'output' => [
                    "scriptid",
                    "name",
                    "command",
                    "groupid",
                    "type",
                    "execute_on",
                    "scope",
                    "menu_path",
                ],
                'search' => [],
                'filter' => [],
                'selectActions' => [
                    'name', 'actionid'
                ],
                "sortfield" => ["scriptid"],
                "sortorder" => "DESC",
            ];

            if (isset($params['name']) && $params['name'] != '') {
                $options['search']['name'] = $params['name'];
            }
            if (isset($params['scope']) && $params['scope'] != '') {
                $options['filter']['scope'] = $params['scope'];
            }
            $result = $this->get($options);
            $data = $result->getData();
            foreach ($data['list'] as &$datum) {
                if ($datum['type'] == 5) {
                    $datum['command'] = '';
                }
            }
            return $this->success($data);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750204, $e->getMessage());
        }
    }

    public function infoScript($params)
    {
        try {
            $options = [
                'scriptids' => $params['scriptid'],
                'output' => [
                    "scriptid",
                    "name",
                    "command",
                    "groupid",
                    "description",
                    "confirmation",
                    "type",
                    "execute_on",
                    "timeout",
                    "scope",
                    "port",
                    "authtype",
                    "username",
                    "password",
                    "publickey",
                    "privatekey",
                    "menu_path",
                    "parameters",
                    "url",
                    "new_window",
                ],
                'selectActions' => [
                    'name', 'actionid'
                ],

            ];
            $form = new ScriptGetForm();
            $form->load($options, '');
            if (!$form->validate()) {
                self::exception(PRS_API_ERROR_PARAMETERS, current($form->getFirstErrors()));
            }
            $search = new ScriptSearch();
            $provider = $search->search($form->getAttributes());
            $data = $provider->getModels();
            $info = [];
            if ($data) {
                $info = $data[0];
            }
            return $this->success($info);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750204, $e->getMessage());
        }
    }

    /**
     *  // $options = '{"output":["scriptid","name","command","host_access","usrgrpid","groupid","type","execute_on","scope","menu_path"],"search":{},"filter":{"scope":"1"},"editable":true,"limit":1001,"preservekeys":true}';
     * // $options = json_decode($options, 1);
     * @param array $options
     * @return \app\common\components\Result
     * @throws \yii\db\Exception
     */
    public function get(array $options)
    {
        $form = new ScriptGetForm();
        $form->load($options, '');
        if (!$form->validate()) {
            self::exception(PRS_API_ERROR_PARAMETERS, current($form->getFirstErrors()));
        }
        $search = new ScriptSearch();
        $provider = $search->search($form->getAttributes());
        return $this->success([
            'total' => $provider->getTotalCount(),
            'list' => $provider->getModels(),
        ]);
    }

    /**
     * @param array $scripts
     *
     * @return array
     */
    public function create(array $scripts)
    {
        if (empty($scripts['scope']) && in_array($scripts['scope'], [2, 4])) {
            $scripts = array_merge($scripts, [
                'usrgrpid' => null,
                'host_access' => 2,
                'confirmation' => '',
            ]);
        }
        $this->validateCreate($scripts);

        $scriptids = DB::insert('scripts', $scripts);

        foreach ($scripts as $index => &$script) {
            $script['scriptid'] = $scriptids[$index];
        }
        unset($script);

        self::updateParams($scripts, __FUNCTION__);

        // 添加审计
        $msg = Yii::t('msg', '{name} created successfully', [
            'name' => Yii::t('app', 'Script')
        ]);
        $details = [
            audit_detail('name', '', implode('、', array_column($scripts, 'name')))
        ];
        $this->auditAdd(RESOURCE_ZAPI, implode('、', array_column($scripts, 'name')), $msg, $details);

        return ['scriptids' => $scriptids];
    }

    public function createScript($params)
    {
        try {
            $result = $this->create($params);
            return $this->success($result, t('act', 'Create Success'));
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750204, $e->getMessage());
        }
    }

    public function updateScript($params)
    {
        try {
            $result = $this->update($params);
            return $this->success($result, t('act', 'Update Success'));
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750204, $e->getMessage());
        }
    }

    /**
     * @param array $scripts
     *
     * @return array
     */
    public function update(array $scripts)
    {
        $this->validateUpdate($scripts, $db_scripts);

        $upd_scripts = [];
        foreach ($scripts as $script) {
            $upd_script = DB::getUpdatedValues('scripts', $script, $db_scripts[$script['scriptid']]);

            if ($upd_script) {
                $upd_scripts[] = [
                    'values' => $upd_script,
                    'where' => ['scriptid' => $script['scriptid']]
                ];
            }
        }

        if ($upd_scripts) {
            DB::update('scripts', $upd_scripts);
        }
        self::updateParams($scripts, __FUNCTION__, $db_scripts);

        // 添加审计
        $msg = Yii::t('msg', '{name} updated successfully', [
            'name' => Yii::t('app', 'Script')
        ]);
        $details = [
            audit_detail('name', '', $script['name'])
        ];
        $this->auditUpdate(RESOURCE_ZAPI, [$script['scriptid'] => $script['name']], $msg, $details);
        return ['scriptids' => array_column($scripts, 'scriptid')];
    }

    public function deleteScript($params)
    {
        try {
            $result = $this->delete($params['scriptid'] ?? []);
            return $this->success($result, t('act', 'Delete Success'));
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750204, $e->getMessage());
        }
    }

    /**
     * @param array $scriptids
     *
     * @return array
     */
    public function delete(array $scriptids)
    {
        self::validateDelete($scriptids, $db_scripts);

        DB::delete('scripts', ['scriptid' => $scriptids]);

        // 添加审计
        $msg = Yii::t('msg', '{name} deleted successfully', [
            'name' => Yii::t('app', 'Script')
        ]);
        $details = [
            audit_detail('ids', '', implode(',', $scriptids))
        ];
        $this->auditDelete(RESOURCE_ZAPI, 'ids:' . implode(',', $scriptids), $msg, $details);
        return ['scriptids' => $scriptids];
    }

    public function executeScript($params)
    {
        try {
            $result = $this->execute($params);
            return $this->success($result, t('zapi', 'execute Success'));
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750204, $e->getMessage());
        }
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function execute(array $data)
    {
        $api_input_rules = ['type' => API_OBJECT, 'fields' => [
            'scriptid' => ['type' => API_ID, 'flags' => API_REQUIRED],
            'hostid' => ['type' => API_ID],
            'eventid' => ['type' => API_ID]
        ]];

        if (!ValidateHelper::validate($api_input_rules, $data, '/', $error)) {
            self::exception(PRS_API_ERROR_PARAMETERS, $error);
        }

        if (!array_key_exists('hostid', $data) && !array_key_exists('eventid', $data)) {
            self::exception(
                PRS_API_ERROR_PARAMETERS,
                t('zapi', 'Invalid parameter "{param}": {error}.', ['param' => '/', 'error' => t('zapi', 'the parameter "{param}" is missing', ['param' => 'eventid'])])
            );
        }

        if (array_key_exists('hostid', $data) && array_key_exists('eventid', $data)) {
            self::exception(
                PRS_API_ERROR_PARAMETERS,
                t('zapi', 'Invalid parameter "{param}": {error}.', ['param' => '/', 'error' => t('zapi', 'the parameter "{param}" is missing', ['param' => 'eventid'])])
            );
        }

        if (array_key_exists('eventid', $data)) {
            $db_events = (new Query())->from('event')->andWhere(['eventid' => $data['eventid']])->all();
            if (!$db_events) {
                self::exception(
                    PRS_API_ERROR_PERMISSIONS,
                    t('zapi', 'No permissions to referred object or it does not exist!')
                );
            }

            $hostids = array_column($db_events[0]['hosts'], 'hostid');
            $is_event = true;
        } else {
            $hostids = $data['hostid'];
            $is_event = false;

            $db_hosts = Hosts::find()->andWhere(['hostid' => $hostids])->asArray()->all();
            if (!$db_hosts) {
                self::exception(
                    PRS_API_ERROR_PERMISSIONS,
                    t('zapi', 'No permissions to referred object or it does not exist!')
                );
            }
        }

        $db_scripts = $this->get([
            'output' => ['type'],
            'hostids' => $hostids,
            'is_all' => true,
            'scriptids' => $data['scriptid']
        ])->getData()['list'];

        if (!$db_scripts) {
            self::exception(PRS_API_ERROR_PERMISSIONS, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        if ($db_scripts[0]['type'] == PRS_SCRIPT_TYPE_URL) {
            self::exception(PRS_API_ERROR_PERMISSIONS, t('zapi', 'Cannot execute URL type script.'));
        }
        // execute script
        $perseus_server = new MonitorServer(
            env('ZABBIX_SERVER'),
            env('ZABBIX_SERVER_PORT'),
            timeUnitToSeconds(SettingHelper::get(SettingHelper::CONNECT_TIMEOUT)),
            timeUnitToSeconds(SettingHelper::get(SettingHelper::SCRIPT_TIMEOUT)),
            PRS_SOCKET_BYTES_LIMIT
        );

        $result = $perseus_server->executeScript(
            $data['scriptid'],
            self::getAdminTokenByDB(),
            $is_event ? null : $data['hostid'],
            $is_event ? $data['eventid'] : null
        );

        if ($result !== false) {
            // return the result in a backwards-compatible format
            return [
                'response' => 'success',
                'value' => $result,
                'debug' => $perseus_server->getDebug()
            ];
        } else {
            self::exception(PRS_API_ERROR_INTERNAL, $perseus_server->getError());
        }
    }


    /**
     * @param array $scripts
     * @param array $db_scripts
     *
     * @throws APIException if the input is invalid
     */
    protected function validateUpdate(array &$scripts, array &$db_scripts = null)
    {
        /*
         * Get general validation rules and firstly validate name uniqueness and all the possible fields, so that there
         * are no invalid fields for any of the script types. Unfortunately there is also a drawback, since field types
         * validated before we know what rules belong to each script type.
         */
        $api_input_rules = $this->getValidationRules('update', $common_fields);

        if (!ValidateHelper::validate($api_input_rules, $scripts, '/', $error)) {
            self::exception(PRS_API_ERROR_PARAMETERS, $error);
        }

        // Continue to validate script name.
        $db_scripts = Scripts::find()->andWhere(['scriptid' => array_column($scripts, 'scriptid')])->indexBy('scriptid')
            ->asArray()->all();

        if (count($db_scripts) != count($scripts)) {
            self::exception(PRS_API_ERROR_PERMISSIONS, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        $this->checkUniqueness($scripts, 'update');

        self::addAffectedObjects($scripts, $db_scripts);

        // Validate if scripts belong to actions and scope can be changed.
        $action_scriptids = [];

        foreach ($scripts as $script) {
            $db_script = $db_scripts[$script['scriptid']];

            if (
                array_key_exists('scope', $script) && $script['scope'] != PRS_SCRIPT_SCOPE_ACTION
                && $db_script['scope'] == PRS_SCRIPT_SCOPE_ACTION
            ) {
                $action_scriptids[$script['scriptid']] = true;
            }
        }

        if ($action_scriptids) {
            $actions = $this->getScriptUsedActions(array_keys($action_scriptids));
            if ($actions) {
                foreach ($scripts as $script) {
                    $db_script = $db_scripts[$script['scriptid']];

                    if (
                        array_key_exists('scope', $script) && $script['scope'] != PRS_SCRIPT_SCOPE_ACTION
                        && $db_script['scope'] == PRS_SCRIPT_SCOPE_ACTION
                    ) {

                        if (!empty($actions[$db_script['scriptid']])) {
                            $action = $actions[$db_script['scriptid']];
                            self::exception(
                                PRS_API_ERROR_PARAMETERS,
                                t('zapi',
                                    'Cannot update script scope. Script "{name}" is used in action "{action}".',
                                    [
                                        'name' => $db_script['name'],
                                        'action' => $action['name']
                                    ]
                                )
                            );
                        }
                    }
                }
            }
        }

        // Populate common and mandatory fields.
        $scripts = $this->extendObjectsByKey($scripts, $db_scripts, 'scriptid', ['name', 'type', 'scope']);

        foreach ($scripts as $index => &$script) {
            $db_script = $db_scripts[$script['scriptid']];
            $method = 'update';

            if (
                array_key_exists('type', $script) && $script['type'] != $db_script['type']
                || array_key_exists('scope', $script) && $script['scope'] == PRS_SCRIPT_SCOPE_ACTION
                && $db_script['scope'] != PRS_SCRIPT_SCOPE_ACTION
                && $db_script['type'] == PRS_SCRIPT_TYPE_URL
            ) {
                // This means that all other fields are now required just like create method.
                $method = 'create';

                // Populate username field, if no new name is given and types are similar to previous.
                if (
                    !array_key_exists('username', $script)
                    && (($db_script['type'] == PRS_SCRIPT_TYPE_TELNET && $script['type'] == PRS_SCRIPT_TYPE_SSH)
                        || ($db_script['type'] == PRS_SCRIPT_TYPE_SSH
                            && $script['type'] == PRS_SCRIPT_TYPE_TELNET))
                ) {
                    $script['username'] = $db_script['username'];
                }
            }

            $type_rules = $this->getTypeValidationRules($script['type'], $method, $type_fields);
            $scope_rules = $this->getScopeValidationRules($script['scope'], $scope_fields);

            // Temporary remove scope fields from script to validate type fields.
            $tmp = $script;
            $tmp_fields = array_intersect_key($scope_rules['fields'], $script);

            foreach ($tmp_fields as $field => $rules) {
                unset($tmp[$field]);
            }

            $type_rules['fields'] += $common_fields + $type_fields;

            if (!ValidateHelper::validate($type_rules, $tmp, '/' . ($index + 1), $error)) {
                self::exception(PRS_API_ERROR_PARAMETERS, $error);
            }

            // Validate all fields together.
            $scope_rules['fields'] += $type_rules['fields'] + $common_fields + $scope_fields;

            if (!ValidateHelper::validate($scope_rules, $script, '/' . ($index + 1), $error)) {
                self::exception(PRS_API_ERROR_PARAMETERS, $error);
            }

            if ($script['type'] == PRS_SCRIPT_TYPE_SSH) {
                $method = 'update';

                if (array_key_exists('authtype', $script) && $script['authtype'] != $db_script['authtype']) {
                    $method = 'create';
                }

                $script += ['authtype' => $db_script['authtype']];

                $ssh_rules = $this->getAuthTypeValidationRules($script['authtype'], $method);
                $ssh_rules['fields'] += $common_fields + $type_fields + $scope_fields;

                if (!ValidateHelper::validate($ssh_rules, $script, '/' . ($index + 1), $error)) {
                    self::exception(PRS_API_ERROR_PARAMETERS, $error);
                }
            }
        }
        unset($script);

        // Clear and reset all unnecessary fields.
        foreach ($scripts as &$script) {
            $db_script = $db_scripts[$script['scriptid']];

            if ($script['type'] != $db_script['type']) {
                switch ($script['type']) {
                    case PRS_SCRIPT_TYPE_IPMI:
                        $script['execute_on'] = DB::getDefault('scripts', 'execute_on');
                        $script['url'] = '';
                        $script['new_window'] = DB::getDefault('scripts', 'new_window');
                    // break; is not missing here

                    // no break
                    case PRS_SCRIPT_TYPE_CUSTOM_SCRIPT:
                        $script['port'] = '';
                        $script['authtype'] = DB::getDefault('scripts', 'authtype');
                        $script['username'] = '';
                        $script['password'] = '';
                        $script['publickey'] = '';
                        $script['privatekey'] = '';
                        $script['parameters'] = [];
                        $script['timeout'] = DB::getDefault('scripts', 'timeout');
                        $script['url'] = '';
                        $script['new_window'] = DB::getDefault('scripts', 'new_window');
                        break;

                    case PRS_SCRIPT_TYPE_SSH:
                        $script['execute_on'] = DB::getDefault('scripts', 'execute_on');
                        $script['parameters'] = [];
                        $script['timeout'] = DB::getDefault('scripts', 'timeout');
                        $script['url'] = '';
                        $script['new_window'] = DB::getDefault('scripts', 'new_window');
                        break;

                    case PRS_SCRIPT_TYPE_TELNET:
                        $script['authtype'] = DB::getDefault('scripts', 'authtype');
                        $script['publickey'] = '';
                        $script['privatekey'] = '';
                        $script['execute_on'] = DB::getDefault('scripts', 'execute_on');
                        $script['parameters'] = [];
                        $script['timeout'] = DB::getDefault('scripts', 'timeout');
                        $script['url'] = '';
                        $script['new_window'] = DB::getDefault('scripts', 'new_window');
                        break;

                    case PRS_SCRIPT_TYPE_WEBHOOK:
                        $script['port'] = '';
                        $script['authtype'] = DB::getDefault('scripts', 'authtype');
                        $script['username'] = '';
                        $script['password'] = '';
                        $script['publickey'] = '';
                        $script['privatekey'] = '';
                        $script['execute_on'] = DB::getDefault('scripts', 'execute_on');
                        $script['url'] = '';
                        $script['new_window'] = DB::getDefault('scripts', 'new_window');
                        break;

                    case PRS_SCRIPT_TYPE_URL:
                        $script['command'] = '';
                        $script['parameters'] = [];
                        $script['timeout'] = DB::getDefault('scripts', 'timeout');
                        $script['port'] = '';
                        $script['authtype'] = DB::getDefault('scripts', 'authtype');
                        $script['username'] = '';
                        $script['password'] = '';
                        $script['publickey'] = '';
                        $script['privatekey'] = '';
                        $script['execute_on'] = DB::getDefault('scripts', 'execute_on');
                        break;
                }
            } elseif (
                $script['type'] == PRS_SCRIPT_TYPE_SSH && $script['authtype'] != $db_script['authtype']
                && $script['authtype'] == ITEM_AUTHTYPE_PASSWORD
            ) {
                $script['publickey'] = '';
                $script['privatekey'] = '';
            }

            if ($script['scope'] != $db_script['scope'] && $script['scope'] == PRS_SCRIPT_SCOPE_ACTION) {
                $script['menu_path'] = '';
                $script['usrgrpid'] = 0;
                $script['host_access'] = DB::getDefault('scripts', 'host_access');
                $script['confirmation'] = '';
            }
        }
        unset($script);

        $this->checkDuplicates($scripts, $db_scripts);
    }

    /**
     * Check for unique script names within menu path in the input.
     *
     * @param array $scripts Array of scripts.
     * @param string $method API method "create" or "update". Default "create".
     *
     * $scripts = [[
     *     'name' =>      (string)  Script name (optional for update method).
     *     'menu_path' => (string)  Script menu path (optional).
     * ]]
     *
     * @throws APIException if script names within menu paths are not unique.
     */
    private function checkUniqueness(array $scripts, string $method = 'create'): void
    {
        if ($method === 'update') {
            $scripts = array_filter(
                $scripts,
                function($script) {
                    return array_key_exists('name', $script) || array_key_exists('menu_path', $script);
                }
            );

            if (!$scripts) {
                return;
            }
        }

        foreach ($scripts as &$script) {
            $menu_path = '';

            if (array_key_exists('menu_path', $script)) {
                $menu_path = trimPath($script['menu_path']);
            }

            // Trim preceeding and trailing slashes for comparison.
            $menu_path = trim($menu_path, '/');
            $script['menu_path'] = $menu_path;
        }
        unset($script);

        $api_input_rules = $this->getValidationRules($method);
        $api_input_rules['uniq'] = [['name', 'menu_path']];
        $api_input_rules['fields'] = array_intersect_key($api_input_rules['fields'], array_flip(['name', 'menu_path']));
        $api_input_rules['flags'] |= API_ALLOW_UNEXPECTED;
        if (!ValidateHelper::validate($api_input_rules, $scripts, '/', $error)) {
            self::exception(PRS_API_ERROR_PARAMETERS, $error);
        }
    }

    /**
     * Add the existing parameters to $db_scripts whether these are affected by the update.
     *
     * @param array $scripts
     * @param array $db_scripts
     */
    private static function addAffectedObjects(array $scripts, array &$db_scripts): void
    {
        $scriptids = [];

        foreach ($scripts as $script) {
            $scriptids[] = $script['scriptid'];
            $db_scripts[$script['scriptid']]['parameters'] = [];
        }

        if (!$scriptids) {
            return;
        }

        $db_parameters = ScriptParam::find()->select(['script_paramid', 'scriptid', 'name', 'value'])
            ->andWhere(['scriptid' => $scriptids])->asArray()->all() ?: [];
        foreach ($db_parameters as $db_parameter) {
            $db_scripts[$db_parameter['scriptid']]['parameters'][$db_parameter['script_paramid']] =
                array_diff_key($db_parameter, array_flip(['scriptid']));
        }
    }

    /**
     * Get general validation rules.
     *
     * @param string $method [IN]          API method "create" or "update".
     * @param array $common_fields [OUT]  Returns common fields for all script types.
     *
     * @return array
     */
    protected function getValidationRules(string $method, &$common_fields = []): array
    {
        $api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => []];

        $common_fields = [
            'name' => ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'name')],
            'type' => ['type' => API_INT32, 'in' => implode(',', [PRS_SCRIPT_TYPE_CUSTOM_SCRIPT, PRS_SCRIPT_TYPE_IPMI, PRS_SCRIPT_TYPE_SSH, PRS_SCRIPT_TYPE_TELNET, PRS_SCRIPT_TYPE_WEBHOOK, PRS_SCRIPT_TYPE_URL])],
            'scope' => ['type' => API_INT32],
            'groupid' => ['type' => API_ID],
            'description' => ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'description')]
        ];

        if ($method === 'create') {
            $common_fields['name']['flags'] |= API_REQUIRED;
            $common_fields['type']['flags'] = API_REQUIRED;
            $common_fields['scope']['flags'] = API_REQUIRED;
        } else {
            $api_input_rules['uniq'] = [['scriptid']];
            $common_fields += ['scriptid' => ['type' => API_ID, 'flags' => API_REQUIRED]];
        }

        /*
         * Merge together optional fields that depend on script type. Some of these fields are not required for some
         * script types. Set only type for now. Unique parameter names, lengths and other flags are set later.
         */
        $api_input_rules['fields'] += $common_fields + [
                'command' => ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'command')],
                'execute_on' => ['type' => API_INT32],
                'menu_path' => ['type' => API_STRING_UTF8],
                'usrgrpid' => ['type' => API_ID],
                'host_access' => ['type' => API_INT32],
                'confirmation' => ['type' => API_STRING_UTF8],
                'port' => ['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO],
                'authtype' => ['type' => API_INT32],
                'username' => ['type' => API_STRING_UTF8],
                'publickey' => ['type' => API_STRING_UTF8],
                'privatekey' => ['type' => API_STRING_UTF8],
                'password' => ['type' => API_STRING_UTF8],
                'timeout' => ['type' => API_TIME_UNIT],
                'parameters' => ['type' => API_OBJECTS, 'fields' => [
                    'name' => ['type' => API_STRING_UTF8],
                    'value' => ['type' => API_STRING_UTF8]
                ]],
                'url' => ['type' => API_URL],
                'new_window' => ['type' => API_INT32]
            ];

        return $api_input_rules;
    }

    /**
     * Get validation rules for each script type.
     *
     * @param int $type [IN]          Script type.
     * @param string $method [IN]          API method "create" or "update".
     * @param array $common_fields [OUT]  Returns common fields for specific script type.
     *
     * @return array
     */
    protected function getTypeValidationRules(int $type, string $method, &$common_fields = []): array
    {
        $api_input_rules = ['type' => API_OBJECT, 'fields' => []];
        $common_fields = [];

        switch ($type) {
            case PRS_SCRIPT_TYPE_CUSTOM_SCRIPT:
                $api_input_rules['fields'] += [
                    'scope' => ['type' => API_INT32, 'in' => implode(',', [PRS_SCRIPT_SCOPE_ACTION, PRS_SCRIPT_SCOPE_HOST, PRS_SCRIPT_SCOPE_EVENT])],
                    'command' => ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'command')],
                    'execute_on' => ['type' => API_INT32, 'in' => implode(',', [PRS_SCRIPT_EXECUTE_ON_AGENT, PRS_SCRIPT_EXECUTE_ON_SERVER, PRS_SCRIPT_EXECUTE_ON_PROXY])]
                ];

                if ($method === 'create') {
                    $api_input_rules['fields']['scope']['flags'] = API_REQUIRED;
                    $api_input_rules['fields']['command']['flags'] |= API_REQUIRED;
                }
                break;

            case PRS_SCRIPT_TYPE_IPMI:
                $api_input_rules['fields'] += [
                    'scope' => ['type' => API_INT32, 'in' => implode(',', [PRS_SCRIPT_SCOPE_ACTION, PRS_SCRIPT_SCOPE_HOST, PRS_SCRIPT_SCOPE_EVENT])],
                    'command' => ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'command')]
                ];

                if ($method === 'create') {
                    $api_input_rules['fields']['scope']['flags'] = API_REQUIRED;
                    $api_input_rules['fields']['command']['flags'] |= API_REQUIRED;
                }
                break;

            case PRS_SCRIPT_TYPE_SSH:
                $common_fields = [
                    'scope' => ['type' => API_INT32, 'in' => implode(',', [PRS_SCRIPT_SCOPE_ACTION, PRS_SCRIPT_SCOPE_HOST, PRS_SCRIPT_SCOPE_EVENT])],
                    'command' => ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'command')],
                    'port' => ['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO],
                    'authtype' => ['type' => API_INT32, 'in' => implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY])],
                    'username' => ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'username')],
                    'password' => ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'password')]
                ];

                if ($method === 'create') {
                    $common_fields['scope']['flags'] = API_REQUIRED;
                    $common_fields['command']['flags'] |= API_REQUIRED;
                    $common_fields['username']['flags'] |= API_REQUIRED;
                }

                $api_input_rules['fields'] += $common_fields + [
                        'publickey' => ['type' => API_STRING_UTF8],
                        'privatekey' => ['type' => API_STRING_UTF8]
                    ];
                break;

            case PRS_SCRIPT_TYPE_TELNET:
                $api_input_rules['fields'] += [
                    'scope' => ['type' => API_INT32, 'in' => implode(',', [PRS_SCRIPT_SCOPE_ACTION, PRS_SCRIPT_SCOPE_HOST, PRS_SCRIPT_SCOPE_EVENT])],
                    'command' => ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'command')],
                    'port' => ['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO],
                    'username' => ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'username')],
                    'password' => ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'password')]
                ];

                if ($method === 'create') {
                    $api_input_rules['fields']['scope']['flags'] = API_REQUIRED;
                    $api_input_rules['fields']['command']['flags'] |= API_REQUIRED;
                    $api_input_rules['fields']['username']['flags'] |= API_REQUIRED;
                }
                break;

            case PRS_SCRIPT_TYPE_WEBHOOK:
                $api_input_rules['fields'] += [
                    'scope' => ['type' => API_INT32, 'in' => implode(',', [PRS_SCRIPT_SCOPE_ACTION, PRS_SCRIPT_SCOPE_HOST, PRS_SCRIPT_SCOPE_EVENT])],
                    'command' => ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'command')],
                    'timeout' => ['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:' . SEC_PER_MIN],
                    'parameters' => ['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
                        'name' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('script_param', 'name')],
                        'value' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('script_param', 'value')]
                    ]]
                ];

                if ($method === 'create') {
                    $api_input_rules['fields']['scope']['flags'] = API_REQUIRED;
                    $api_input_rules['fields']['command']['flags'] |= API_REQUIRED;
                }
                break;

            case PRS_SCRIPT_TYPE_URL:
                $api_input_rules['fields'] += [
                    'scope' => ['type' => API_INT32, 'in' => implode(',', [PRS_SCRIPT_SCOPE_HOST, PRS_SCRIPT_SCOPE_EVENT])],
                    'url' => ['type' => API_URL, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('scripts', 'url')],
                    'new_window' => ['type' => API_INT32, 'in' => implode(',', [PRS_SCRIPT_URL_NEW_WINDOW_NO, PRS_SCRIPT_URL_NEW_WINDOW_YES]), 'default' => DB::getDefault('scripts', 'new_window')]
                ];

                if ($method === 'create') {
                    $api_input_rules['fields']['scope']['flags'] = API_REQUIRED;
                    $api_input_rules['fields']['url']['flags'] |= API_REQUIRED;
                }
                break;
        }

        return $api_input_rules;
    }

    /**
     * Get validation rules for script scope.
     *
     * @param int $scope [IN]          Script scope.
     * @param array $common_fields [OUT]  Returns common fields for specific script scope.
     *
     * @return array
     */
    protected function getScopeValidationRules(int $scope, &$common_fields = []): array
    {
        $api_input_rules = ['type' => API_OBJECT, 'fields' => []];
        $common_fields = [];

        if ($scope == PRS_SCRIPT_SCOPE_HOST || $scope == PRS_SCRIPT_SCOPE_EVENT) {
            $common_fields = [
                'menu_path' => ['type' => API_SCRIPT_MENU_PATH, 'length' => DB::getFieldLength('scripts', 'menu_path')],
                'usrgrpid' => ['type' => API_ID],
                'host_access' => ['type' => API_INT32, 'in' => implode(',', [PERM_READ, PERM_READ_WRITE])],
                'confirmation' => ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'confirmation')]
            ];

            $api_input_rules['fields'] += $common_fields;
        }

        return $api_input_rules;
    }

    /**
     * Check for duplicate script names within menu path.
     *
     * @param array $scripts Array of scripts.
     * @param array|null $db_scripts Array of scripts from database.
     *
     * $scripts = [[
     *     'scriptid' =>  (string)  Script ID.
     *     'name' =>      (string)  Script name.
     *     'menu_path' => (string)  Script menu path (exists if scope = 1 for update method).
     *     'scope' =>     (string)  Script scope.
     * ]]
     *
     * $db_scripts = [
     *     <scriptid> => [
     *         'name' =>      (string)  Script name.
     *         'menu_path' => (string)  Script menu path.
     *         'scope' =>     (string)  Script scope.
     *     ]
     * ]
     *
     * @throws APIException if script names within menu paths have duplicates in DB.
     */
    private function checkDuplicates(array $scripts, ?array $db_scripts = null): void
    {
        if ($db_scripts !== null) {
            $scripts = $this->extendFromObjects(prs_toHash($scripts, 'scriptid'), $db_scripts, ['menu_path']);

            /*
             * Remove unchanged scripts and continue validation only for scripts that have changed name, menu path or
             * scope. If scope is changed to action, menu_path will be reset to empty string and that is a change.
             */
            $scripts = array_filter(
                $scripts,
                function($script) use ($db_scripts) {
                    return $script['name'] !== $db_scripts[$script['scriptid']]['name']
                        || $script['menu_path'] !== $db_scripts[$script['scriptid']]['menu_path']
                        || ($script['scope'] !== $db_scripts[$script['scriptid']]['scope']
                            && $script['scope'] == PRS_SCRIPT_SCOPE_ACTION);
                }
            );

            if (!$scripts) {
                return;
            }
        }

        $scripts_ex = Scripts::find()->select(['scriptid', 'name', 'menu_path'])->andWhere(['name' => array_column($scripts, 'name')])->asArray()->all();
        if (!$scripts_ex) {
            return;
        }

        $db_scriptids = [];

        foreach ($scripts_ex as $script) {
            $name = self::getScriptNameAndPath($script);
            $db_scriptids[$name] = $script['scriptid'];
        }

        foreach ($scripts as $script) {
            $name = self::getScriptNameAndPath($script);

            if ($db_scripts === null && array_key_exists($name, $db_scriptids)) {
                self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Script "{name}" already exists.', ['name' => $script['name']]));
            } elseif (array_key_exists($name, $db_scriptids) && bccomp($script['scriptid'], $db_scriptids[$name]) != 0) {
                self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Script "{name}" already exists.', ['name' => $script['name']]));
            }
        }
    }

    /**
     * Helper function to combine trimmed menu path with name.
     *
     * @param array $script Script data.
     *
     * $script = [
     *     'name' =>      (string)  Script name.
     *     'menu_path' => (string)  Script menu path (optional).
     * ]
     *
     * Example:
     *   $script = [
     *       'name' =>      'ABC'
     *       'menu_path' => '/a/b'
     *   ]
     * Output: a/b/ABC
     *
     * @return string
     */
    private static function getScriptNameAndPath(array $script): string
    {
        $menu_path = '';

        if (array_key_exists('menu_path', $script)) {
            $menu_path = trimPath($script['menu_path']);
        }

        $menu_path = trim($menu_path, '/');

        return $menu_path === '' ? $script['name'] : $menu_path . '/' . $script['name'];
    }

    /**
     * Update "script_param" table and populate script.parameters by "script_paramid" property.
     *
     * @param array $scripts
     * @param string $method
     * @param array|null $db_scripts
     */
    private static function updateParams(array &$scripts, string $method, array $db_scripts = null): void
    {
        $ins_params = [];
        $upd_params = [];
        $del_paramids = [];

        foreach ($scripts as &$script) {
            if (!array_key_exists('parameters', $script)) {
                continue;
            }

            $db_params = ($method === 'update')
                ? array_column($db_scripts[$script['scriptid']]['parameters'], null, 'name')
                : [];

            foreach ($script['parameters'] as &$param) {
                if (array_key_exists($param['name'], $db_params)) {
                    $db_param = $db_params[$param['name']];
                    $param['script_paramid'] = $db_param['script_paramid'];
                    unset($db_params[$param['name']]);

                    $upd_param = DB::getUpdatedValues('script_param', $param, $db_param);

                    if ($upd_param) {
                        $upd_params[] = [
                            'values' => $upd_param,
                            'where' => ['script_paramid' => $db_param['script_paramid']]
                        ];
                    }
                } else {
                    $ins_params[] = ['scriptid' => $script['scriptid']] + $param;
                }
            }
            unset($param);

            $del_paramids = array_merge($del_paramids, array_column($db_params, 'script_paramid'));
        }
        unset($script);

        if ($ins_params) {
            $script_paramids = DB::insertBatch('script_param', $ins_params);
        }

        if ($upd_params) {
            DB::update('script_param', $upd_params);
        }

        if ($del_paramids) {
            DB::delete('script_param', ['script_paramid' => $del_paramids]);
        }

        foreach ($scripts as &$script) {
            if (!array_key_exists('parameters', $script)) {
                continue;
            }

            foreach ($script['parameters'] as &$param) {
                if (!array_key_exists('script_paramid', $param)) {
                    $param['script_paramid'] = array_shift($script_paramids);
                }
            }
            unset($param);
        }
        unset($script);
    }

    /**
     * @param array $scripts
     *
     * @throws APIException if the input is invalid
     */
    protected function validateCreate(array &$scripts)
    {
        /*
         * Get general validation rules and firstly validate name uniqueness and all the possible fields, so that there
         * are no invalid fields for any of the script types. Unfortunately there is also a drawback, since field types
         * validated before we know what rules belong to each script type.
         */
        $api_input_rules = $this->getValidationRules('create', $common_fields);

        if (!ValidateHelper::validate($api_input_rules, $scripts, '/', $error)) {
            self::exception(PRS_API_ERROR_PARAMETERS, $error);
        }

        /*
         * Then validate each script separately. Depending on script type, each script may have different set of allowed
         * fields. Then in case the type is SSH and authtype is set, validate parameters again.
         */
        foreach ($scripts as $index => $script) {
            $type_rules = $this->getTypeValidationRules($script['type'], 'create', $type_fields);
            $scope_rules = $this->getScopeValidationRules($script['scope'], $scope_fields);

            // Temporary remove scope fields from script to validate type fields.
            $tmp = $script;
            $tmp_fields = array_intersect_key($scope_rules['fields'], $script);

            foreach ($tmp_fields as $field => $rules) {
                unset($tmp[$field]);
            }

            $type_rules['fields'] += $common_fields + $type_fields;

            if (!ValidateHelper::validate($type_rules, $tmp, '/' . ($index + 1), $error)) {
                self::exception(PRS_API_ERROR_PARAMETERS, $error);
            }

            // Validate all fields together.
            $scope_rules['fields'] += $type_rules['fields'] + $common_fields + $scope_fields;

            if (!ValidateHelper::validate($scope_rules, $script, '/' . ($index + 1), $error)) {
                self::exception(PRS_API_ERROR_PARAMETERS, $error);
            }

            if (array_key_exists('authtype', $script)) {
                $ssh_rules = $this->getAuthTypeValidationRules($script['authtype'], 'create');
                $ssh_rules['fields'] += $common_fields + $type_fields + $scope_fields;

                if (!ValidateHelper::validate($ssh_rules, $script, '/' . ($index + 1), $error)) {
                    self::exception(PRS_API_ERROR_PARAMETERS, $error);
                }
            }
        }

        $this->checkUniqueness($scripts);
        $this->checkDuplicates($scripts);
    }


    /**
     * Validates parameters for script.delete method.
     *
     * @param array $scriptids
     * @param array|null $db_scripts
     *
     * @throws APIException if the input is invalid
     */
    private static function validateDelete(array &$scriptids, array &$db_scripts = null)
    {

        $db_scripts = Scripts::find()->select(['scriptid', 'name'])->andWhere(['scriptid' => $scriptids])->indexBy('scriptid')->asArray()->all();
        if (count($db_scripts) != count($scriptids)) {
            self::exception(PRS_API_ERROR_PERMISSIONS, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        // Check if deleted scripts used in actions.
        $db_actions = Yii::$app->db->createCommand(
            'SELECT a.name,oc.scriptid' .
            ' FROM opcommand oc,operations o,actions a' .
            ' WHERE oc.operationid=o.operationid' .
            ' AND o.actionid=a.actionid' .
            ' AND ' . SqlHelper::whereIn('oc.scriptid', $scriptids)
        )->queryAll();
        if ($db_actions) {
            foreach ($db_actions as $db_action) {
                self::exception(
                    PRS_API_ERROR_PARAMETERS,
                    t('zapi',
                        'Cannot delete scripts. Script "{name}" is used in action operation "{action}".',
                        [
                            'name' => $db_scripts[$db_action['scriptid']]['name'],
                            'action' => $db_action['name']
                        ]
                    )
                );
            }
        }
    }


    public function getAdminTokenByDB()
    {
        $user = Users::findOne(1);
        if (!$user) {
            return null;
        }
        //Perseus默认用户ID为1
        $query = (new \yii\db\Query)
            ->from('sessions')
            ->where([
                'userid' => $user->userid,
                'status' => 0,
            ]);

        $autologout = $this->toSeconds($user->autologout);

        if (0 != $autologout) {
            $query->andWhere(['>', '(lastaccess + ' . $autologout . ')', time()]);
        }

        $query->orderBy(['lastaccess' => SORT_DESC]);

        $r = $query->limit(1)->one();

        if ($r) {
            return $r['sessionid'];
        }

        $sessionid = md5(time() . 'jiuyileweidotcom' . 'admin' . rand(0, 10000000));

        $r = Yii::$app->db->createCommand()->insert('sessions', [
            'sessionid' => $sessionid,
            'userid' => 1,
            'lastaccess' => time(),
            'status' => 0,
        ])->execute();

        return $r ? $sessionid : null;
    }

    /**
     * 兼容3.2,3.4的时间表示
     *
     *
     * @return integer
     */
    protected function toSeconds($time)
    {
        if (is_numeric($time)) {
            return $time;
        }

        preg_match('/^((\d)+)([smhdw])?$/', $time, $matches);

        if (array_key_exists(3, $matches)) {
            $suffix = $matches[3];
            $time = $matches[1];

            switch ($suffix) {
                case 's':
                    $sec = $time;
                    break;
                case 'm':
                    $sec = bcmul($time, 60);
                    break;
                case 'h':
                    $sec = bcmul($time, 3600);
                    break;
                case 'd':
                    $sec = bcmul($time, 86400);
                    break;
                case 'w':
                    $sec = bcmul($time, 7 * 86400);
                    break;
            }
        }

        return $sec;
    }


    /**
     * Get validation rules for each script authtype.
     *
     * @param int $authtype Script authtype.
     * @param string $method API method "create" or "update".
     *
     * @return array
     */
    protected function getAuthTypeValidationRules(int $authtype, string $method): array
    {
        $api_input_rules = ['type' => API_OBJECT, 'fields' => []];

        if ($authtype == ITEM_AUTHTYPE_PUBLICKEY) {
            $api_input_rules['fields'] += [
                'publickey' => ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'publickey')],
                'privatekey' => ['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'privatekey')]
            ];

            if ($method === 'create') {
                $api_input_rules['fields']['publickey']['flags'] |= API_REQUIRED;
                $api_input_rules['fields']['privatekey']['flags'] |= API_REQUIRED;
            }
        }

        return $api_input_rules;
    }

    /**
     *
     *             //            $actions = API::Action()->get([
     * //                'output' => ['actionid', 'name'],
     * //                'selectOperations' => ['opcommand'],
     * //                'selectRecoveryOperations' => ['opcommand'],
     * //                'selectUpdateOperations' => ['opcommand'],
     * //                'scriptids' => array_keys($action_scriptids)
     * //            ]);
     * @param $scriptid
     */
    protected function getScriptUsedActions($scriptid)
    {
        $query = new Query();
        $query->select([
            'opd.scriptid',
            'opd.operationid',
            'a.actionid',
            'a.name',
        ]);
        $query->from([
            'opd' => Opcommand::tableName(),
            'ops' => Operations::tableName(),
            'a' => Actions::tableName(),
        ]);
        $query->andWhere('opd.operationid=ops.operationid');
        $query->andWhere('a.actionid=ops.actionid');
        $query->andWhere(['opd.scriptid' => $scriptid]);
        $data = $query->all() ?: [];
        $data = ArrayHelper::index($data, 'scriptid');
        return $data;
    }
}
