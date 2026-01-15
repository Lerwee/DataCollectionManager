<?php
namespace app\customs\zapi\controllers;

use app\common\base\BaseController;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\MacroHelper;
use app\customs\zapi\common\helpers\ProxyHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\services\HostService;
use Yii;
use yii\web\Response;

class HostController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'delete',
        'monitor',
    ];

    /**
     * 主机列表
     *
     * @return Response
     */
    public function actionList(): Response
    {
        $params = Yii::$app->request->get();
        return $this->autoReturn(HostService::instance()->getList($params));
    }

    /**
     * 新增主机
     *
     * @return Response
     */
    public function actionCreate(): Response
    {
        $params = Yii::$app->request->post();

        $host = [
            'status' => $params['status'] ?? HOST_STATUS_NOT_MONITORED,
            'proxy_hostid' => $params['proxy_hostid'] ?? 0,
            'groups' => prs_toObject($params['groups'] ?? [], 'groupid'),
            'interfaces' => $params['interfaces'] ?? [],
            # 'tags' => $params['tags'] ?? [],
            'templates' => $this->processTemplates([
                $params['add_templates'] ?? [], $params['templates'] ?? [],
            ]),
            'macros' => $this->processUserMacros($params['macros'] ?? []),
            'inventory_mode' => HOST_INVENTORY_DISABLED,
            'tls_connect' => $params['tls_connect'] ?? HOST_ENCRYPTION_NONE,
            'tls_accept' => $params['tls_accept'] ?? HOST_ENCRYPTION_NONE,
        ];

        $fields = [
            'host', 'name', 'visiblename', 'description', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
            'ipmi_password', 'tls_subject', 'tls_issuer', 'tls_psk_identity', 'tls_psk',
            'valuemaps',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $params)) {
                $host[$field] = $params[$field];
            }
        }

        if ($host['tls_connect'] != HOST_ENCRYPTION_PSK && !($host['tls_accept'] & HOST_ENCRYPTION_PSK)) {
            unset($host['tls_psk'], $host['tls_psk_identity']);
        }

        if ($host['tls_connect'] != HOST_ENCRYPTION_CERTIFICATE
            && !($host['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE)) {
            unset($host['tls_issuer'], $host['tls_subject']);
        }

        $host = CArrayHelper::renameKeys($host, ['visiblename' => 'name']);

        return $this->autoReturn(HostService::instance()->create($host));
    }

    /**
     * 编辑主机
     *
     * @return Response
     */
    public function actionUpdate(): Response
    {
        $params = Yii::$app->request->post();
        $hostid = empty($params['hostid']) ? (int) Yii::$app->request->get('hostid') : (int) $params['hostid'];
        if (empty($hostid)) {
            return $this->error(error_code(10000021, ['param' => 'hostid']));
        }

        $hosts = HostHelper::getHosts([
            'output' => ['hostid', 'host', 'name', 'status', 'description', 'proxy_hostid', 'ipmi_authtype',
                'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'tls_connect', 'tls_accept', 'tls_issuer',
                'tls_subject', 'flags', #'inventory_mode'
            ],
            'selectMacros' => ['hostmacroid', 'macro', 'value', 'type', 'description', 'automatic'],
            'hostids' => $hostid,
            'limit' => 1,
            'preservekeys' => true,
        ]);

        if (empty($hosts)) {
            return $this->error(10000404);
        }
        $host = reset($hosts);
        $host['inventory_mode'] = HOST_INVENTORY_DISABLED;

        $clear_templates = array_diff(
            $params['clear_templates'] ?? [],
            $params['add_templates'] ?? [],
        );

        $data = [
            'hostid'          => $host['hostid'],
            'host'            => $params['host'] ?? $host['host'],
            'name'            => $params['name'] ?? $host['name'],
            'status'          => $params['status'] ?? $host['status'],
            'proxy_hostid'    => $params['proxy_hostid'] ?? $host['proxy_hostid'],
            'groups'          => prs_toObject($params['groups'] ?? [], 'groupid'),
            'interfaces'      => $params['interfaces'] ?? [] ,//$this->processHostInterfaces($params['interfaces'] ?? []),
            # 'tags' => $this->processTags($this->getInput('tags', [])),
            'templates'       => $this->processTemplates([
                $params['add_templates'] ?? [], $params['templates'] ?? [],
            ]),
            'templates_clear' => prs_toObject($clear_templates, 'templateid'),
            'macros'          => $this->processUserMacros($params['macros'] ?? [], $host['macros']),
            'tls_connect'     => $params['tls_connect'] ?? $host['tls_connect'],
            'tls_accept'      => $params['tls_accept'] ?? $host['tls_accept'],
        ];

        $host_properties = [
            'description', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'tls_subject',
            'tls_issuer', 'inventory_mode',
        ];

        foreach ($host_properties as $prop) {
            $val = $params[$prop] ?? $host[$prop];
            if (!array_key_exists($prop, $host) || $val !== $host[$prop]) {
                $data[$prop] = $val;
            }
        }

        isset($params['tls_psk_identity']) && $data['tls_psk_identity'] = $params['tls_psk_identity'];
        isset($params['tls_psk']) && $data['tls_psk'] = $params['tls_psk'];

        if ($data['tls_connect'] != HOST_ENCRYPTION_PSK && !($data['tls_accept'] & HOST_ENCRYPTION_PSK)) {
            unset($data['tls_psk'], $data['tls_psk_identity']);
        }

        if ($data['tls_connect'] != HOST_ENCRYPTION_CERTIFICATE
            && !($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE)) {
            unset($data['tls_issuer'], $data['tls_subject']);
        }

        if ($host['flags'] == PRS_FLAG_DISCOVERY_CREATED) {
            $data = array_intersect_key($data,
                array_flip(['hostid', 'status', 'description', 'tags', 'macros', 'inventory', 'templates',
                    'templates_clear',
                ])
            );
        } else {
            if (array_key_exists('valuemaps', $params)) {
                $data['valuemaps'] = $params['valuemaps'];
            }
        }

        return $this->autoReturn(HostService::instance()->update($data));
    }

    /**
     * 删除主机
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $hostIds = (array) Yii::$app->request->post('hostids', []);
        if (empty($hostIds)) {
            return $this->error(error_code(10000021, ['param' => 'hostids']));
        }
        return $this->autoReturn(HostService::instance()->delete($hostIds));
    }

    public function actionMonitor(): Response
    {
        $hostId = Yii::$app->request->post('hostid');
        if (empty($hostId)) {
            return $this->error(error_code(10000021, ['param' => 'hostid']));
        }

        $status = (int) Yii::$app->request->post('status');

        $params = array_map(function ($id) use ($status) {
            return [
                'status' => $status,
                'hostid' => (int) $id,
            ];
        }, is_array($hostId) ? $hostId : [$hostId]);

        $result = HostService::instance()->update($params);

        $msg = $status ? Yii::t('monitor', 'Disable Success') : Yii::t('monitor', 'Enable Success');
        $result->setErrmsg($msg);
        return $this->autoReturn($result);
    }

    public function actionProfile()
    {
        return $this->success([
            'templates' => TemplateHelper::getTemplates(['output' => ['templateid', 'name']]),
            'groups' => array_values(GroupHelper::getHostGroups(['output' => ['groupid', 'name']])),
            'host_groups' => array_values(GroupHelper::getHostGroups(['output' => ['groupid', 'name'], 'with_hosts' => 1])),
            'proxies' => ProxyHelper::getProxies(['output' => ['host']]),
        ]);
    }

    /**
     * Prepare host interfaces.
     *
     * @param array $interfaces Submitted interfaces.
     *
     * @return array Interfaces for assigning to host.
     */
    protected function processHostInterfaces(array $interfaces): array
    {
        foreach ($interfaces as $key => $interface) {
            if ($interface['type'] == INTERFACE_TYPE_SNMP) {
                if (!array_key_exists('details', $interface)) {
                    $interface['details'] = [];
                }

                $interfaces[$key]['details']['bulk'] = array_key_exists('bulk', $interface['details'])
                ? SNMP_BULK_ENABLED
                : SNMP_BULK_DISABLED;
            }

            if (isset($interface['interfaceid']) && empty($interface['interfaceid'])) {
                unset($interfaces[$key]['interfaceid']);
            }

            $interfaces[$key]['main'] = INTERFACE_SECONDARY;
        }

        $main_interfaces = Yii::$app->request->post('mainInterfaces', []);

        foreach ([1, 2, 3, 4] as $type) {
            if (array_key_exists($type, $main_interfaces) && array_key_exists($main_interfaces[$type], $interfaces)) {
                $interfaces[$main_interfaces[$type]]['main'] = INTERFACE_PRIMARY;
            }
        }
        return $interfaces;
    }

    /**
     * Merge and prepare templates.
     *
     * @param array $templates Array of one or more submitted template sets as arrays (added, existing) to be combined.
     *
     * @return array Templates for assigning to host.
     */
    protected function processTemplates(array $templates): array
    {
        $all_templates = [];

        foreach ($templates as $template_set) {
            $all_templates = array_merge($all_templates, $template_set);
        }

        return prs_toObject($all_templates, 'templateid');
    }

    /**
     * Prepare host level user macros.
     *
     * @param array $macros Submitted macros.
     *
     * @return array Macros for assigning to host.
     */
    protected function processUserMacros(array $macros, array $db_macros = []): array
    {
        $db_macros = array_column($db_macros, null, 'hostmacroid');
        $macro_fields = array_flip(['macro', 'value', 'type', 'description']);
        $macros = cleanInheritedMacros($macros);

        foreach ($macros as &$macro) {
            if (array_key_exists('hostmacroid', $macro) && array_key_exists($macro['hostmacroid'], $db_macros)) {
                $db_macro = $db_macros[$macro['hostmacroid']];
                $macro_diff = array_diff_assoc(array_intersect_key($macro, $macro_fields), $db_macro);
                $mandatory_fields = ['hostmacroid' => $macro['hostmacroid']];

                if (array_key_exists('discovery_state', $macro)
                    && $macro['discovery_state'] == 0x2) {
                    $macro_diff['automatic'] = PRS_USERMACRO_MANUAL;
                }

                if ($macro['type'] == PRS_MACRO_TYPE_VAULT
                    && (!array_key_exists('discovery_state', $macro)
                        || $macro['discovery_state'] != 0x1)) {
                    /**
                     * Macro value must be passed to be sure its syntax is still valid.
                     * Syntax may be changed, e.g., if the Vault provider has been changed.
                     */
                    $mandatory_fields['value'] = $macro['value'];
                }

                $macro = $mandatory_fields + $macro_diff;
            } else {
                unset($macro['discovery_state'], $macro['original_value'], $macro['original_description'],
                    $macro['original_macro_type'], $macro['allow_revert']
                );
            }
        }
        unset($macro);

        return array_filter($macros,
            function (array $macro): bool {
                return (bool) array_filter(
                    array_intersect_key($macro, array_flip(['hostmacroid', 'macro', 'value', 'description']))
                );
            }
        );
    }

    /**
     * 主机表单
     *
     * @return Response
     */
    public function actionForm(): Response
    {
        $hostId = (int) Yii::$app->request->get('hostid');
        if (empty($hostId)) {
            return $this->error(error_code(10000021, ['param' => 'hostid']));
        }
        $hosts = HostHelper::getHosts([
            'output'                => ['hostid', 'host', 'name', 'status', 'description', 'proxy_hostid', 'ipmi_authtype',
                'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'tls_connect', 'tls_accept', 'tls_issuer',
                'tls_subject', 'flags', #'inventory_mode'
            ],
            'selectDiscoveryRule'   => ['itemid', 'name', 'parent_hostid'],
            'selectHostGroups'      => ['groupid'],
            'selectHostDiscovery'   => ['parent_hostid'],
            'selectInterfaces'      => ['interfaceid', 'type', 'main', 'available', 'error', 'details', 'ip', 'dns',
                'port', 'useip',
            ],
            'selectMacros'          => ['hostmacroid', 'macro', 'value', 'description', 'type', 'automatic'],
            'selectParentTemplates' => ['templateid', 'name', 'link_type'],
            'selectValueMaps'       => ['valuemapid', 'name', 'mappings'],
            'hostids'               => $hostId,
            'preservekeys'          => true,
            'limit'                 => 1,
        ]);

        if (empty($hosts)) {
            return $this->error(10000404);
        }

        $host = current($hosts);

        # $interfaces = $host['interfaces'];
        if ($host['interfaces']) {
            $interfaceIds = array_column($host['interfaces'], 'interfaceid');
            $interfaceItems = HostHelper::getInterfaces([
                'output' => [],
                'selectItems' => API_OUTPUT_COUNT,
                'interfaceids' => $interfaceIds,
                'preservekeys' => true,
            ]);
            foreach ($host['interfaces'] as $i => $interface) {
                if (array_key_exists($interface['interfaceid'], $interfaceItems)) {
                    $host['interfaces'][$i]['items'] = (int) $interfaceItems[$interface['interfaceid']]['items'];
                } else {
                    $host['interfaces'][$i]['items'] = 0;
                }
            }
        }

        $host['groups'] = array_map(function ($g) {
            return $g['groupid'];
        }, $host['hostgroups']);

        $host['templates'] = array_map(function ($t) {
            return $t['templateid'];
        }, $host['parentTemplates']);

        $macros = [];
        if ($host['macros']) {
            $macros = MacroHelper::cleanInheritedMacros($host['macros']);

            // Remove empty new macro lines.
            $macros = array_filter($macros, function ($macro) {
                $keys = array_flip(['hostmacroid', 'macro', 'value', 'description']);

                return (bool) array_filter(array_intersect_key($macro, $keys));
            });
        } else {
            $macros[] = [
                'type' => PRS_MACRO_TYPE_TEXT,
                'macro' => '',
                'value' => '',
                'description' => '',
                'automatic' => PRS_USERMACRO_MANUAL
            ];
        }

        foreach ($macros as &$macro) {
            if (array_key_exists('automatic', $macro) && $macro['automatic'] == PRS_USERMACRO_AUTOMATIC) {
                $macro['discovery_state'] = MacroHelper::DISCOVERY_STATE_AUTOMATIC;

                $macro['original'] = [
                    'value' => getMacroConfigValue($macro),
                    'description' => $macro['description'],
                    'type' => $macro['type']
                ];
            } else {
                $macro['discovery_state'] = MacroHelper::DISCOVERY_STATE_MANUAL;
            }

            unset($macro['automatic']);
        }
        unset($macro);

        $macros = array_values(order_macros($macros, 'macro'));
        $readonly = 0;
        $show_inherited_macros = 1;
        if (!$macros && !$readonly) {
            $macro = ['macro' => '', 'value' => '', 'description' => '', 'type' => PRS_MACRO_TYPE_TEXT];
            if ($show_inherited_macros) {
                $macro['inherited_type'] = PRS_PROPERTY_OWN;
            }
            $macros[] = $macro;
        }

        foreach ($macros as &$macro) {
            if (!array_key_exists('discovery_state', $macro)) {
                $macro['discovery_state'] = 0x3;
            }

            self::addMacroOriginalValues($macro);
        }
        unset($macro);

        $host['macros'] = $macros;

        return $this->success([
            'form' => $host,
            'options' => [
                'snmp' => (new \app\modules\libzbx\models\zbx\Interfaces())->getDetailFormAttributes(),
            ],
        ]);
    }

    /**
     * Create array of original macro values from input fields.
     *
     * @param array  $macro
     * @param string $macro['original_value']
     * @param string $macro['original_description']
     * @param string $macro['original_macro_type']
     */
    protected static function addMacroOriginalValues(array &$macro)
    {
        if ($macro['discovery_state'] == 0x3) {
            return;
        }

        $field_keys_map = [
            'original_value' => 'value',
            'original_description' => 'description',
            'original_macro_type' => 'type',
        ];

        $macro['original'] = array_intersect_key($macro, $field_keys_map);
        $macro['original'] = CArrayHelper::renameKeys($macro['original'], $field_keys_map);

        foreach (array_keys($field_keys_map) as $key) {
            unset($macro[$key]);
        }
    }
}
