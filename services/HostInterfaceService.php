<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\parsers\CIPParser;
use app\customs\zapi\common\parsers\CUserMacroParser;
use app\customs\zapi\models\search\item\ItemSearch;
use app\customs\zapi\services\assist\BaseAssist;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\Interfaces;
use app\modules\libzbx\models\zbx\InterfaceSnmp;
use app\modules\libzbx\models\zbx\Items;
use yii\db\Expression;
use yii\db\Query;

class HostInterfaceService extends BaseAssist
{
    /**
     * Check interfaces input.
     *
     * @param array  $interfaces
     * @param string $method
     */
    public function checkInput(array &$interfaces, $method)
    {
        $update = ($method == 'update');

        // permissions
        if ($update) {
            $interfaceDBfields = ['interfaceid' => null];

            $dbInterfaces = Interfaces::find()
                ->where(SqlHelper::whereIn('interfaceid', array_column($interfaces, 'interfaceid')))
                ->indexBy('interfaceid')
                ->asArray()
                ->all();
        } else {
            $interfaceDBfields = [
                'hostid' => null,
                'ip' => null,
                'dns' => null,
                'useip' => null,
                'port' => null,
                'main' => null
            ];
        }


        $dbHosts = Hosts::find()
            ->select(['hostid', 'host', 'flags'])
            ->where(SqlHelper::whereIn('hostid', array_column($interfaces, 'hostid')))
            ->andWhere(['status' => [0, 1]])
            ->indexBy('hostid')
            ->asArray()
            ->all();
        $dbProxies = Hosts::find()
            ->select(['host'])
            ->where(SqlHelper::whereIn('hostid', array_column($interfaces, 'hostid')))
            ->andWhere(['status' => [5, 6]])
            ->indexBy('hostid')
            ->asArray()
            ->column();

        $check_have_items = [];
        foreach ($interfaces as &$interface) {
            if (!check_db_fields($interfaceDBfields, $interface)) {
                throw new ValidateException(60750101, t('zapi', 'Incorrect arguments passed to function.'));
            }

            if ($update) {
                if (!isset($dbInterfaces[$interface['interfaceid']])) {
                    throw new ValidateException(60750101, t('zapi', 'No permissions to referred object or it does not exist!'));
                }

                $dbInterface = $dbInterfaces[$interface['interfaceid']];
                if (isset($interface['hostid']) && bccomp($dbInterface['hostid'], $interface['hostid']) != 0) {
                    throw new ValidateException(60750101, t('zapi', 'Cannot switch host for interface.'));
                }

                if (array_key_exists('type', $interface) && $interface['type'] != $dbInterface['type']) {
                    $check_have_items[] = $interface['interfaceid'];
                }

                $interface['hostid'] = $dbInterface['hostid'];

                // we check all fields on "updated" interface
                $updInterface = $interface;
                $interface = prs_array_merge($dbInterface, $interface);
            } else {
                if (!isset($dbHosts[$interface['hostid']]) && !isset($dbProxies[$interface['hostid']])) {
                    throw new ValidateException(60750101, t('zapi', 'No permissions to referred object or it does not exist!'));
                }

                if (isset($dbProxies[$interface['hostid']])) {
                    $interface['type'] = INTERFACE_TYPE_UNKNOWN;
                } elseif (!isset($interface['type'])) {
                    throw new ValidateException(60750101, t('zapi', 'Incorrect arguments passed to function.'));
                }
            }

            if ($interface['ip'] === '' && $interface['dns'] === '') {
                throw new ValidateException(60750101, t('zapi', 'IP and DNS cannot be empty for host interface.'));
            }

            if ($interface['useip'] == INTERFACE_USE_IP && $interface['ip'] === '') {
                throw new ValidateException(60750101, t('zapi', 'Interface with {target} "{name}" cannot have empty IP address.', [
                    'target' => 'DNS',
                    'name' =>  $interface['dns']
                ]));
            }

            if ($interface['useip'] == INTERFACE_USE_DNS && $interface['dns'] === '') {
                if ($dbHosts && !empty($dbHosts[$interface['hostid']]['host'])) {

                    throw new ValidateException(60750101, t('zapi', 'Interface with IP "{ip}" cannot have empty DNS name while having "Use DNS" property on "{name}".', [
                        'ip' =>  $interface['ip'],
                        'name' =>  $dbHosts[$interface['hostid']]['host']
                    ]));
                } elseif ($dbProxies && !empty($dbProxies[$interface['hostid']])) {
                    throw new ValidateException(60750101, t('zapi', 'Interface with IP "{ip}" cannot have empty DNS name while having "Use DNS" property on "{name}".', [
                        'ip' =>  $interface['ip'],
                        'name' =>  $dbProxies[$interface['hostid']]
                    ]));
                } else {
                    throw new ValidateException(60750101, t('zapi', 'Interface with {target} "{name}" cannot have empty IP address.', [
                        'target' => 'IP',
                        'name' =>  $interface['ip']
                    ]));
                }
            }

            if (isset($interface['dns'])) {
                $this->checkDns($interface);
            }
            if (isset($interface['ip'])) {
                $this->checkIp($interface);
            }
            if (isset($interface['port']) || $method == 'create') {
                $this->checkPort($interface);
            }

            if ($update) {
                $interface = $updInterface;
            }
        }
        unset($interface);

        // check if any of the affected hosts are discovered
        if ($update) {
            foreach ($interfaces as &$interface) {
                if (array_key_exists($interface['interfaceid'], $dbInterfaces)) {
                    $interface['hostid'] = $dbInterfaces[$interface['interfaceid']]['hostid'];
                }
            }

            if ($check_have_items && $this->checkIfInterfaceHasItems($check_have_items, $error)) {
                throw new ValidateException(60750101, $error);
            }
        }

        foreach ($dbHosts as $dbHost) {
            if ($dbHost['flags'] == PRS_FLAG_DISCOVERY_CREATED) {
                throw new ValidateException(60750101, t('zapi', 'Cannot update "{field}" for a discovered host "{host}".', [
                    'field' => 'interface',
                    'host' => $dbHost['host']
                ]));
            }
        }
    }

    /**
     * Validates the "dns" field.
     *
     * @throws ValidateException if the field is invalid.
     *
     * @param array $interface
     * @param string $interface['dns']
     */
    protected function checkDns(array $interface)
    {
        if ($interface['dns'] === '') {
            return;
        }

        $user_macro_parser = new CUserMacroParser();

        if (
            !preg_match('/^' . PRS_PREG_DNS_FORMAT . '$/', $interface['dns'])
            && $user_macro_parser->parse($interface['dns']) != CUserMacroParser::PARSE_SUCCESS
        ) {
            self::exception(
                60750101,
                t('zapi', 'Incorrect interface {name} parameter "{value}" provided.', [
                    'name' => 'DNS',
                    'value' => $interface['dns']
                ])
            );
        }
    }

    /**
     * Validates the "ip" field.
     *
     * @throws ValidateException if the field is invalid.
     *
     * @param array $interface
     * @param string $interface['ip']
     */
    protected function checkIp(array $interface)
    {
        if ($interface['ip'] === '') {
            return;
        }

        $user_macro_parser = new CUserMacroParser();

        if (
            preg_match('/^' . PRS_PREG_MACRO_NAME_FORMAT . '$/', $interface['ip'])
            || $user_macro_parser->parse($interface['ip']) == CUserMacroParser::PARSE_SUCCESS
        ) {
            return;
        }

        $ip_parser = new CIPParser(['v6' => PRS_HAVE_IPV6]);

        if ($ip_parser->parse($interface['ip']) != CUserMacroParser::PARSE_SUCCESS) {
            self::exception(60750101, t('zapi', 'Invalid IP address "{value}".', ['value' => $interface['ip']]));
        }
    }

    /**
     * Validates the "port" field.
     *
     * @throws ValidateException if the field is empty or invalid.
     *
     * @param array $interface
     */
    protected function checkPort(array $interface)
    {
        if (!isset($interface['port']) || $interface['port'] === '') {
            self::exception(60750101, t('zapi', 'Port cannot be empty for host interface.'));
        } elseif (!validatePortNumberOrMacro($interface['port'])) {
            self::exception(
                60750101,
                t('zapi', 'Incorrect interface {name} parameter "{value}" provided.', [
                    'name' => 'port',
                    'value' => $interface['port']
                ])
            );
        }
    }

    /**
     * Check SNMP related inputs.
     *
     * @param array $interfaces
     */
    protected function checkSnmpInput(array $interfaces)
    {
        foreach ($interfaces as $interface) {
            if (!array_key_exists('type', $interface) || $interface['type'] != INTERFACE_TYPE_SNMP) {
                continue;
            }

            if (!array_key_exists('details', $interface)) {
                throw new ValidateException(60750101, t('zapi', 'Incorrect arguments passed to function.'));
            }

            $this->checkSnmpVersion($interface);

            $this->checkSnmpCommunity($interface);

            $this->checkSnmpMaxRepetitions($interface);

            $this->checkSnmpBulk($interface);

            $this->checkSnmpSecurityLevel($interface);

            $this->checkSnmpAuthProtocol($interface);

            $this->checkSnmpPrivProtocol($interface);
        }
    }

    /**
     * Replace existing interfaces with input interfaces.
     *
     * @param array $host
     * @return Result
     * @throws ValidateException
     */
    public function replaceHostInterfaces($host): Result
    {
        if (isset($host['interfaces']) && !is_null($host['interfaces'])) {
            $host['interfaces'] = prs_toArray($host['interfaces']);

            $this->checkHostInterfaces($host['interfaces'], $host['hostid']);

            $interfaces_delete = Interfaces::find()
                ->where(['hostid' => $host['hostid']])
                ->indexBy('interfaceid')
                ->asArray()
                ->all();


            $interfaces_add = [];
            $interfaces_update = [];

            foreach ($host['interfaces'] as $interface) {
                $interface['hostid'] = $host['hostid'];

                if (!array_key_exists('interfaceid', $interface)) {
                    $interfaces_add[] = $interface;
                } elseif (array_key_exists($interface['interfaceid'], $interfaces_delete)) {
                    $interfaces_update[] = $interface;
                    unset($interfaces_delete[$interface['interfaceid']]);
                }
            }

            if ($interfaces_update) {
                $this->checkInput($interfaces_update, 'update');

                $this->updateInterfaces($interfaces_update);

                $this->updateInterfaceDetails($interfaces_update);
            }

            if ($interfaces_add) {
                $this->checkInput($interfaces_add, 'create');
                $interfaceids = DB::insert('interface', $interfaces_add);

                $this->checkSnmpInput($interfaces_add);

                $snmp_interfaces = [];
                foreach ($interfaceids as $key => $id) {
                    if ($interfaces_add[$key]['type'] == INTERFACE_TYPE_SNMP) {
                        $snmp_interfaces[] = ['interfaceid' => $id] + $interfaces_add[$key]['details'];
                    }
                }

                $this->createSnmpInterfaceDetails($snmp_interfaces);

                foreach ($host['interfaces'] as &$interface) {
                    if (!array_key_exists('interfaceid', $interface)) {
                        $interface['interfaceid'] = array_shift($interfaceids);
                    }
                }
                unset($interface);
            }

            if ($interfaces_delete) {
                $this->delete(array_keys($interfaces_delete));
            }

            return $this->success(['interfaceids' => array_column($host['interfaces'], 'interfaceid')]);
        }

        return $this->success(['interfaceids' => []]);
    }

    private function checkHostInterfaces(array $interfaces, $hostid)
    {
        $interfaces_with_missing_data = [];

        foreach ($interfaces as $interface) {
            if (array_key_exists('interfaceid', $interface)) {
                if (!array_key_exists('type', $interface) || !array_key_exists('main', $interface)) {
                    $interfaces_with_missing_data[$interface['interfaceid']] = true;
                }
            } elseif (!array_key_exists('type', $interface) || !array_key_exists('main', $interface)) {
                throw new ValidateException(60750101, t('zapi', 'Incorrect arguments passed to function.'));
            }
        }

        if ($interfaces_with_missing_data) {
            $dbInterfaces = Interfaces::find()
                ->where(SqlHelper::whereIn('interfaceid', $interfaces_with_missing_data))
                ->select(['main', 'type', 'interfaceid'])
                ->indexBy('interfaceid')
                ->asArray()
                ->all();
            if (count($interfaces_with_missing_data) != count($dbInterfaces)) {
                throw new ValidateException(60750101, t('zapi', 'No permissions to referred object or it does not exist!'));
            }
        }

        foreach ($interfaces as $id => $interface) {
            if (isset($interface['interfaceid']) && isset($dbInterfaces[$interface['interfaceid']])) {
                $interfaces[$id] = array_merge($interface, $dbInterfaces[$interface['interfaceid']]);
            }
            $interfaces[$id]['hostid'] = $hostid;
        }

        if (!$this->checkMainInterfaces($interfaces, $error)) {
            throw new ValidateException(60750101, $error);
        }
    }

    /**
     * Create SNMP interfaces.
     *
     * @param array $interfaces
     */
    protected function createSnmpInterfaceDetails(array $interfaces)
    {
        if (count($interfaces)) {
            if (count(array_column($interfaces, 'interfaceid')) != count($interfaces)) {
                self::exception(60750101);
            }

            $interfaces = $this->sanitizeSnmpFields($interfaces);

            foreach ($interfaces as $interface) {
                DB::insert(InterfaceSnmp::tableName(), [$interface], false);
            }
        }
    }

    /**
	 * Sanitize SNMP fields by version.
	 *
	 * @param array $interfaces
	 *
	 * @return array
	 */
	protected function sanitizeSnmpFields(array $interfaces): array {
		$default_fields = [
			'community' => '',
			'max_repetitions' =>  DB::getDefault('interface_snmp', 'max_repetitions'),
			'securityname' => '',
			'securitylevel' => DB::getDefault('interface_snmp', 'securitylevel'),
			'authpassphrase' => '',
			'privpassphrase' => '',
			'authprotocol' => DB::getDefault('interface_snmp', 'authprotocol'),
			'privprotocol' => DB::getDefault('interface_snmp', 'privprotocol'),
			'contextname' => ''
		];

		foreach ($interfaces as &$interface) {
			if ($interface['version'] == SNMP_V1) {
				unset($interface['max_repetitions']);
			}

			if ($interface['version'] == SNMP_V1 || $interface['version'] == SNMP_V2C) {
				unset($interface['securityname'], $interface['securitylevel'], $interface['authpassphrase'],
					$interface['privpassphrase'], $interface['authprotocol'], $interface['privprotocol'],
					$interface['contextname']
				);
			}
			else {
				unset($interface['community']);
			}

			$interface = $interface + $default_fields;
		}

		return $interfaces;
	}

    public function massAdd(array $data): Result
    {
        $interfaces = prs_toArray($data['interfaces']);
        $hosts = prs_toArray($data['hosts']);

        $insertData = [];
        foreach ($interfaces as $interface) {
            foreach ($hosts as $host) {
                $newInterface = $interface;
                $newInterface['hostid'] = $host['hostid'];

                $insertData[] = $newInterface;
            }
        }

        return $this->create($insertData);
    }


    protected function validateMassRemove(array $data, ?string &$error = null): bool
    {
        if (!$data['hostids'] || !$data['interfaces']) {
            $error =  t('zapi', 'Empty input parameters.');
            return false;
        }

        // Check permissions.

        // Check interfaces.
        $ckQuery = Hosts::find()
            ->where(SqlHelper::whereIn('hostid', $data['hostids']))
            ->andWhere(['flags' => PRS_FLAG_DISCOVERY_CREATED])
            ->limit(1)
            ->select(['host']);
        if ($name = $ckQuery->scalar()) {
            $error = t('zapi', 'Cannot delete interface for discovered host "{name}".', ['name' => $name]);
            return false;
        }

        // check interfaces
        foreach ($data['interfaces'] as $interface) {
            if (!isset($interface['dns']) || !isset($interface['ip']) || !isset($interface['port'])) {
                $error =  t('zapi', 'Incorrect arguments passed to function.');
                return false;
            }

            $filter = [
                'hostid' => $data['hostids'],
                'ip' => $interface['ip'],
                'dns' => $interface['dns'],
                'port' => $interface['port']
            ];

            $interfaceIds = Interfaces::find()
                ->where($filter)
                ->asArray()
                ->select(['interfaceid'])
                ->column();

            // check main interfaces
            if ($interfaceIds && !$this->checkMainInterfacesOnDelete($interfaceIds, $error)) {
                return false;
            }
        }

        return true;
    }

    private function checkMainInterfacesOnCreate(array $interfaces)
    {
        $hostIds = [];
        foreach ($interfaces as $interface) {
            $hostIds[$interface['hostid']] = $interface['hostid'];
        }

        $dbInterfaces = Interfaces::find()
            ->where(SqlHelper::whereIn('hostid', $hostIds))
            ->select(['hostid', 'main', 'type'])
            ->indexBy('interfaceid')
            ->asArray()
            ->all();


        $interfaces = array_merge($dbInterfaces, $interfaces);

        $this->checkMainInterfaces($interfaces);
    }

    private function checkMainInterfacesOnDelete(array $interfaceIds, ?string &$error = null): bool
    {
        if ($this->checkIfInterfaceHasItems($interfaceIds, $error)) {
            return false;
        }

        $hostIds = Interfaces::find()
            ->where(SqlHelper::whereIn('interfaceid', $interfaceIds))
            ->asArray()
            ->select(['hostid' => new Expression('DISTINCT hostid')])
            ->indexBy('hostid')
            ->column();


        $dbInterfaces = Interfaces::find()
            ->where(SqlHelper::whereIn('hostid', $hostIds))
            ->select(['hostid', 'main', 'type', 'interfaceid'])
            ->indexBy('interfaceid')
            ->asArray()
            ->all();

        foreach ($interfaceIds as $interfaceId) {
            unset($dbInterfaces[$interfaceId]);
        }

        return $this->checkMainInterfaces($dbInterfaces, $error);
    }

    /**
     * Check if main interfaces are correctly set for every interface type.
     * Each host must either have only one main interface for each interface type, or have no interface of that type at all.
     *
     * @param array $interfaces
     */
    private function checkMainInterfaces(array $interfaces, ?string &$error = null): bool
    {
        $interfaceTypes = [];
        foreach ($interfaces as $interface) {
            if (!isset($interfaceTypes[$interface['hostid']])) {
                $interfaceTypes[$interface['hostid']] = [];
            }

            if (!isset($interfaceTypes[$interface['hostid']][$interface['type']])) {
                $interfaceTypes[$interface['hostid']][$interface['type']] = ['main' => 0, 'all' => 0];
            }

            if ($interface['main'] == INTERFACE_PRIMARY) {
                $interfaceTypes[$interface['hostid']][$interface['type']]['main']++;
            } else {
                $interfaceTypes[$interface['hostid']][$interface['type']]['all']++;
            }
        }

        foreach ($interfaceTypes as $interfaceHostId => $interfaceType) {
            foreach ($interfaceType as $type => $counters) {
                if ($counters['all'] && !$counters['main']) {
                    $host = Hosts::find()
                        ->select(['name'])
                        ->where(['hostid' => $interfaceHostId])
                        ->andWhere(['status' => [0, 1]])
                        ->asArray()
                        ->scalar();
                    $error = t('zapi', 'No default interface for "{src_name}" type on "{dst_name}".', [
                        'src_name' => HostHelper::hostInterfaceTypeNumToName($type),
                        'dst_name' => $host,
                    ]);
                    return false;
                }

                if ($counters['main'] > 1) {
                    $error = t('zapi', 'Host cannot have more than one default interface of the same type.');
                    return false;
                }
            }
        }

        return true;
    }

    private function checkIfInterfaceHasItems(array $interfaceIds, ?string &$error = null): bool
    {
        $query = new Query();
        $query->from([
            'i' => Items::tableName(),
            'h' => Hosts::tableName()
        ])
            ->where('i.hostid=h.hostid')
            ->andWhere(SqlHelper::whereIn('i.interfaceid', $interfaceIds))
            ->limit(1)
            ->select(['item_name' => 'i.name', 'host_name' => 'h.name']);

        if ($one = $query->one()) {
            $error = t('zapi', 'Interface is linked to item "{src_name}" on "{dst_name}".', [
                'src_name' => $one['item_name'], 'dst_name' => $one['host_name']
            ]);
            return true;
        }
        return false;
    }

    /**
     * Check if SNMP version is valid. Valid versions: SNMP_V1, SNMP_V2C, SNMP_V3.
     *
     * @param array $interface
     *
     * @throws APIException if "version" value is incorrect.
     */
    protected function checkSnmpVersion(array $interface)
    {
        if (
            !array_key_exists('version', $interface['details'])
            || !in_array($interface['details']['version'], [SNMP_V1, SNMP_V2C, SNMP_V3])
        ) {
            self::exception(60750101);
        }
    }

    /**
     * Check SNMP community. For SNMPv1 and SNMPv2c it required.
     *
     * @param array $interface
     *
     * @throws APIException if "community" value is incorrect.
     */
    protected function checkSnmpCommunity(array $interface)
    {
        if (($interface['details']['version'] == SNMP_V1 || $interface['details']['version'] == SNMP_V2C)
            && (!array_key_exists('community', $interface['details'])
                || $interface['details']['community'] === '')
        ) {
            self::exception(60750101);
        }
    }

    /**
     * Check SNMP max repetition count.
     *
     * @param array $interface
     *
     * @throws APIException if "max_repetitions" value is incorrect.
     */
    protected function checkSnmpMaxRepetitions(array $interface)
    {
        if (($interface['details']['version'] == SNMP_V2C || $interface['details']['version'] == SNMP_V3)
            && (array_key_exists('max_repetitions', $interface['details'])
                && (!is_numeric($interface['details']['max_repetitions'])
                    || $interface['details']['max_repetitions'] < 1
                    || $interface['details']['max_repetitions'] > PRS_MAX_INT32))
        ) {
            self::exception(60750101);
        }
    }

    /**
     * Validates SNMP interface "bulk" field.
     *
     * @param array $interface
     *
     * @throws APIException if "bulk" value is incorrect.
     */
    protected function checkSnmpBulk(array $interface)
    {
        if ($interface['type'] !== null && (($interface['type'] != INTERFACE_TYPE_SNMP
            && isset($interface['details']['bulk']) && $interface['details']['bulk'] != SNMP_BULK_ENABLED)
            || ($interface['type'] == INTERFACE_TYPE_SNMP && isset($interface['details']['bulk'])
                && (prs_empty($interface['details']['bulk'])
                    || ($interface['details']['bulk'] != SNMP_BULK_DISABLED
                        && $interface['details']['bulk'] != SNMP_BULK_ENABLED))))) {
            self::exception(60750101, t('zapi', 'Incorrect bulk value for interface.'));
        }
    }

    /**
     * Check SNMP Security level field.
     *
     * @param array $interface
     * @param array $interface['details']
     * @param array $interface['details']['version']        SNMP version
     * @param array $interface['details']['securitylevel']  SNMP security level
     *
     * @throws APIException if "securitylevel" value is incorrect.
     */
    protected function checkSnmpSecurityLevel(array $interface)
    {
        if ($interface['details']['version'] == SNMP_V3 && (array_key_exists('securitylevel', $interface['details'])
            && !in_array($interface['details']['securitylevel'], [
                ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,
                ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV
            ]))) {
            self::exception(60750101);
        }
    }

    /**
     * Check SNMP authentication  protocol.
     *
     * @param array $interface
     * @param array $interface['details']
     * @param array $interface['details']['version']       SNMP version
     * @param array $interface['details']['authprotocol']  SNMP authentication protocol
     *
     * @throws APIException if "authprotocol" value is incorrect.
     */
    protected function checkSnmpAuthProtocol(array $interface)
    {
        if ($interface['details']['version'] == SNMP_V3 && (array_key_exists('authprotocol', $interface['details'])
            && !array_key_exists($interface['details']['authprotocol'], ItemHelper::getSnmpV3AuthProtocols()))) {
            self::exception(60750101);
        }
    }

    /**
     * Check SNMP Privacy protocol.
     *
     * @param array $interface
     * @param array $interface['details']
     * @param array $interface['details']['version']       SNMP version
     * @param array $interface['details']['privprotocol']  SNMP privacy protocol
     *
     * @throws APIException if "privprotocol" value is incorrect.
     */
    protected function checkSnmpPrivProtocol(array $interface)
    {
        if ($interface['details']['version'] == SNMP_V3 && (array_key_exists('privprotocol', $interface['details'])
            && !array_key_exists($interface['details']['privprotocol'], ItemHelper::getSnmpV3PrivProtocols()))) {
            self::exception(60750101);
        }
    }

    protected function updateInterfaces(array $interfaces): bool
    {
        $data = [];

        foreach ($interfaces as $interface) {
            $data[] = [
                'values' => $interface,
                'where' => ['interfaceid' => $interface['interfaceid']]
            ];
        }

        DB::update(Interfaces::tableName(), $data);

        return true;
    }

    protected function updateInterfaceDetails(array $interfaces): bool
    {
        $db_interfaces = Interfaces::find()
            ->select(['interfaceid', 'type'])
            ->with('interfaceSnmp')
            ->where(SqlHelper::whereIn('interfaceid', array_column($interfaces, 'interfaceid')))
            ->indexBy('interfaceid')
            ->asArray()
            ->all();

        DB::delete(InterfaceSnmp::tableName(), ['interfaceid' => array_column($interfaces, 'interfaceid')]);

        $snmp_interfaces = [];
        foreach ($interfaces as $interface) {
            $interfaceid = $interface['interfaceid'];

            // Check new interface type or, if interface type not present, check type from db.
            if ((!array_key_exists('type', $interface) && $db_interfaces[$interfaceid]['type'] != INTERFACE_TYPE_SNMP)
                || (array_key_exists('type', $interface) && $interface['type'] != INTERFACE_TYPE_SNMP)
            ) {
                continue;
            } else {
                // Type is required for SNMP validation.
                $interface['type'] = INTERFACE_TYPE_SNMP;
            }

            // Merge details with db values or set only values from db.
            $interface['details'] = array_key_exists('details', $interface)
                ? $interface['details'] + $db_interfaces[$interfaceid]['interfaceSnmp']
                : $db_interfaces[$interfaceid]['interfaceSnmp'];

            $this->checkSnmpInput([$interface]);

            $snmp_interfaces[] = ['interfaceid' => $interfaceid] + $interface['details'];
        }

        $this->createSnmpInterfaceDetails($snmp_interfaces);

        return true;
    }

    public function create(array $interfaces): Result
    {
        $interfaces = to_array($interfaces);
        $this->checkInput($interfaces, __FUNCTION__);
        $this->checkSnmpInput($interfaces);
        $this->checkMainInterfacesOnCreate($interfaces);

        $interfaceids = DB::insert('interface', $interfaces);

        $snmp_interfaces = [];
        foreach ($interfaceids as $key => $id) {
            if ($interfaces[$key]['type'] == INTERFACE_TYPE_SNMP) {
                $snmp_interfaces[] = ['interfaceid' => $id] + $interfaces[$key]['details'];
            }
        }

        $this->createSnmpInterfaceDetails($snmp_interfaces);

        return $this->success(['interfaceids' => $interfaceids]);
    }

    /**
     * Delete interfaces.
     * Interface cannot be deleted if it's main interface and exists other interface of same type on same host.
     * Interface cannot be deleted if it is used in items.
     *
     * @param array $interfaceids
     *
     * @return Result
     */
    public function delete(array $interfaceids): Result
    {
        if (empty($interfaceids)) {
            self::exception(60750101, t('zapi', 'Empty input parameter.'));
        }

        $dbInterfaces = Interfaces::find()
            ->where(SqlHelper::whereIn('interfaceid', $interfaceids))
            ->indexBy('interfaceid')
            ->asArray()
            ->all();

        foreach ($interfaceids as $interfaceId) {
            if (!isset($dbInterfaces[$interfaceId])) {
                self::exception(60750101, t('zapi', 'No permissions to referred object or it does not exist!'));
            }
        }

        if (!$this->checkMainInterfacesOnDelete($interfaceids, $error)) {
            self::exception(60750101, $error);
        }

        DB::delete(Interfaces::tableName(), ['interfaceid' => $interfaceids]);
        DB::delete(InterfaceSnmp::tableName(), ['interfaceid' => $interfaceids]);

        return $this->success(['interfaceids' => $interfaceids]);
    }


    /**
     * Remove hosts from interfaces.
     *
     * @param array $data
     * @param array $data['interfaceids']
     * @param array $data['hostids']
     * @param array $data['templateids']
     *
     * @return array
     */
    public function massRemove(array $data): Result
    {
        if (!array_key_exists('hostids', $data) || !array_key_exists('interfaces', $data)) {
            return $this->error(60750101, t('zapi', 'Incorrect input parameters.'));
        }

        $data['interfaces'] = prs_toArray($data['interfaces']);
        $data['hostids'] = prs_toArray($data['hostids']);

        if (!$this->validateMassRemove($data, $error)) {
            return $this->error(60750101, $error);
        }

        $interfaceIds = [];
        foreach ($data['interfaces'] as $interface) {
            $interfaceIds += Interfaces::find()
                ->where([
                    'hostid' => $data['hostids'],
                    'ip' => $interface['ip'],
                    'dns' => $interface['dns'],
                    'port' => $interface['port']
                ])
                ->asArray()
                ->select(['interfaceid'])
                ->indexBy(['interfaceid'])
                ->column();
        }

        if ($interfaceIds) {
            DB::delete(Interfaces::tableName(), ['interfaceid' => $interfaceIds]);
        }

        return $this->success(['interfaceids' => $interfaceIds]);
    }
}
