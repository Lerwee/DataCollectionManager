<?php
namespace app\customs\zapi\models\search;

use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use yii\db\Query;

/**
 * 模板搜索模型
 *
 * @property string $keyword            关键词
 */
class ProxySearch extends BaseSearch
{
    public $is_all = false;

    public $proxyids = null;

    public $selectHosts     = null;
    public $selectInterface = null;

    protected $sortColumns = ['hostid', 'host', 'status'];

    /**
     * searches
     *
     * @param  array $params
     * @return ActiveDataProvider
     */
    public function search(array $params)
    {
        $this->setAttributes($params);

        $output_fields = ['proxyid', 'host', 'status', 'description', 'lastaccess', 'tls_connect', 'tls_accept',
            'tls_issuer', 'tls_subject', 'proxy_address', 'auto_compress', 'version', 'compatibility',
        ];

        /*
		 * For internal calls, it is possible to get the write-only fields if they were specified in output.
		 * Specify write-only fields in output only if they will not appear in debug mode.
		 */
        if (true) {
            $output_fields[] = 'tls_psk_identity';
            $output_fields[] = 'tls_psk';
        }

        $host_fields = ['hostid', 'proxy_hostid', 'host', 'status', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
            'ipmi_password', 'maintenanceid', 'maintenance_status', 'maintenance_type', 'maintenance_from', 'name',
            'flags', 'description', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject', 'inventory_mode',
            'active_available',
        ];
        $interface_fields = ['interfaceid', 'hostid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'available',
            'error', 'errors_from', 'disable_until',
        ];

        $sqlParts = [
            'select' => ['hostid' => 'h.hostid'],
            'from'   => ['h' => 'hosts'],
            'where'  => [['h.status' => [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE]]],
            'order'  => [],
        ];

        $query = new Query();

        $query->select($sqlParts['select']);

        if ($this->countOutput) {
            $this->output      = ['proxyid'];
            $this->countOutput = false;
        } elseif ($this->output === API_OUTPUT_EXTEND) {
            $this->output = $output_fields;
        }

        // proxyids
        if ($this->proxyids !== null) {
            $sqlParts['where'][] = ZSqlHelper::dbConditionInt('h.hostid', filter_integer((array) $this->proxyids));
        }

        // filter
        if ($this->filter !== null) {
            $this->filter = CArrayHelper::renameKeys($this->filter, ['proxyid' => 'hostid']);

            $filter = ZSqlHelper::dbFilter('hosts', $this->filter, 'h', (bool) $this->searchAny);
            if ($filter) {
                $sqlParts['where'][] = $filter;
            }

            $rt_filter = [];
            foreach (['lastaccess', 'version', 'compatibility'] as $field) {
                if (array_key_exists($field, $this->filter) && $this->filter[$field] !== null) {
                    $rt_filter[$field] = $this->filter[$field];
                }
            }

            if ($rt_filter) {
                $filter = ZSqlHelper::dbFilter('host_rtdata', $rt_filter + $this->filter, 'hr', (bool) $this->searchAny);
                if ($filter) {
                    $sqlParts['where'][] = $filter;
                }
            }
        }

        // search
        if ($this->search !== null) {
            ZSqlHelper::zbxDbSearch('hosts t', [
                'search'                 => $this->search,
                'startSearch'            => $this->startSearch,
                'excludeSearch'          => $this->excludeSearch,
                'searchWildcardsEnabled' => $this->searchWildcardsEnabled,
                'searchByAny'            => $this->searchByAny,
            ], $query);
        }

        $this->applyQueryOutputOptions($query, 'hosts', 'h', $this->countOutput ? 'count' : $this->output);
        $this->applyQuerySortOptions($query, 'hosts', 'h', $this->sortfield, $this->sortorder);

        foreach ($sqlParts['where'] as $where) {
            $query->andWhere($where);
        }

        $query->from($sqlParts['from']);

        $provider = new ActiveDataProvider([
            'query' => $query,
        ]);
        if ($this->is_all) {
            $provider->setPagination(false);
        }

        if ($this->countOutput) {
            return $provider;
        }

        if ($this->preservekeys) {
            $query->indexBy('hostid');
        }

        $models = $this->format($provider->getModels());
        $provider->setModels($models);
        return $provider;
    }

    protected function format(array $result)
    {
        if ($result) {
            $result = $this->addRelatedObjects($result);
            $result = $this->unsetExtraFields($result, ['proxyid', 'name_upper'], $this->output);
        }

        return $result;
    }

    protected function addRelatedObjects(array $result)
    {
        $result = parent::addRelatedObjects($result);

        $proxyIds = array_keys($result);

        // selectHosts
        if ($this->selectHosts !== null && $this->selectHosts != API_OUTPUT_COUNT) {
            $hosts = HostHelper::getHosts([
                'output'       => $this->outputExtend($this->selectHosts, ['hostid', 'proxy_hostid']),
                'proxyids'     => $proxyIds,
                'preservekeys' => true,
            ]);

            $relationMap = $this->createRelationMap($hosts, 'proxy_hostid', 'hostid');
            $hosts       = $this->unsetExtraFields($hosts, ['proxy_hostid', 'hostid'], $this->selectHosts);
            $result      = $relationMap->mapMany($result, $hosts, 'hosts');
        }

        // adding host interface
        if ($this->selectInterface !== null && $this->selectInterface != API_OUTPUT_COUNT) {
            $interfaces = HostHelper::getInterfaces([
                'output'        => $this->outputExtend($this->selectInterface, ['interfaceid', 'hostid']),
                'hostids'       => $proxyIds,
                'nopermissions' => true,
                'preservekeys'  => true,
            ]);

            $relationMap = $this->createRelationMap($interfaces, 'hostid', 'interfaceid');
            $interfaces  = $this->unsetExtraFields($interfaces, ['hostid', 'interfaceid'], $this->selectInterface);
            $result      = $relationMap->mapOne($result, $interfaces, 'interface');

            foreach ($result as $key => $proxy) {
                if (! empty($proxy['interface'])) {
                    $result[$key]['interface'] = $proxy['interface'];
                }
            }
        }

        return $result;
    }
}
