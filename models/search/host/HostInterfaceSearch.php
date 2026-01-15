<?php

namespace app\customs\zapi\models\search\host;

use app\common\helpers\SqlHelper;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\models\search\BaseSearch;
use yii\db\Query;

class HostInterfaceSearch extends BaseSearch
{
    public $groupids = null;
    public $hostids = null;
    public $interfaceids = null;
    public $itemids = null;
    public $triggerids = null;
    public $selectHosts = null;
    public $selectItems = null;

    protected $tableName = 'interface';
    protected $tableAlias = 'hi';
    protected $sortColumns = ['interfaceid', 'dns', 'ip'];

    public $is_all = false;

    /**
     * Searches
     *
     * @param  array $params
     * @return ActiveDataProvider
     */
    public function search(array $params = [])
    {
        $this->setAttributes($params);

        $sqlParts = [
            'select'    => ['interface' => $this->tableAlias . '.interfaceid'],
            'from'        => [$this->tableAlias => $this->tableName],
            'where'        => [],
            'group'        => [],
            'order'        => [],
        ];

        $query = new Query();
        $query->from($sqlParts['from'])
            ->select($sqlParts['select']);

        // interfaceids
        if (null !== $this->interfaceids) {
            $query->andWhere(SqlHelper::whereIn('hi.interfaceid', filter_integer((array) $this->interfaceids)));
        }

        // hostids
        if (null !== $this->hostids) {
            $query->andWhere(SqlHelper::whereIn('hi.hostid', filter_integer((array) $this->hostids)));
            if ($this->groupCount) {
                $sqlParts['group']['hostid'] = 'hi.hostid';
            }
        }

        // itemids
        if (null !== $this->itemids) {
            $sqlParts['from']['i'] = 'items';
            $sqlParts['where']['hi'] = 'hi.interfaceid=i.interfaceid';
            $query->andWhere(SqlHelper::whereIn('i.itemid', filter_integer((array) $this->itemids)));
        }

        // triggerids
        if (null !== $this->triggerids) {
            $sqlParts['from']['f'] = 'functions';
            $sqlParts['from']['i'] = 'items';
            $sqlParts['where']['hi'] = 'hi.hostid=i.hostid';
            $sqlParts['where']['fi'] = 'f.itemid=i.itemid';
            $query->andWhere(SqlHelper::whereIn('f.triggerid', filter_integer((array) $this->triggerids)));
        }

        $options = get_object_vars($this);

        // search
        if (is_array($this->search)) {
            ZSqlHelper::zbxDbSearch('interface hi', $options, $query);
        }

        // filter
        if ($this->filter !== null) {
            if ($condition = ZSqlHelper::dbFilter('interface', $this->filter, 'hi', $this->searchByAny)) {
                $query->andWhere($condition);
            }
        }

        if (!$this->countOutput && $this->outputIsRequested('details', $this->output)) {
            $query->leftJoin(['his' => 'interface_snmp'], 'his.interfaceid=hi.interfaceid');
        }

        if ($this->limit !== null && $this->limit > 0) {
            $query->limit($this->limit);
        }

        foreach ($sqlParts['where'] as $condition) {
            $query->andWhere($condition);
        }
        $query->from($sqlParts['from']);
        $query->groupBy($sqlParts['group']);

        $this->applyQueryOutputOptions($query, $this->tableName, $this->tableAlias, $this->countOutput ? 'count' : $this->output, $options);
        $this->applyQuerySortOptions($query, $this->tableName, $this->tableAlias, $this->sortfield, $this->sortorder);


        $provider = new ActiveDataProvider([
            'query' => $query
        ]);
        if ($this->is_all) {
            $provider->setPagination(false);
        }

        if ($this->countOutput) {
            return $provider;
        }

        if ($this->preservekeys) {
            $query->indexBy('interfaceid');
        }
        $models = $this->format($provider->getModels());
        $provider->setModels($models);
        return $provider;
    }

    protected function format(array $models)
    {
        if ($models) {

            $models = $this->addRelatedObjects($models);
            $models = $this->unsetExtraFields($models, ['hostid'], $this->output);

            // Moving additional fields to separate object.
            if ($this->outputIsRequested('details', $this->output)) {
                foreach ($models as &$value) {
                    $snmp_fields = [
                        'version',
                        'bulk',
                        'community',
                        'securityname',
                        'securitylevel',
                        'authpassphrase',
                        'privpassphrase',
                        'authprotocol',
                        'privprotocol',
                        'contextname',
                        'max_repetitions'
                    ];

                    $interface_type = $value['type'];

                    if (!$this->outputIsRequested('type', $this->output)) {
                        unset($value['type']);
                    }

                    $details = [];

                    // Handle SNMP related fields.
                    if ($interface_type == INTERFACE_TYPE_SNMP) {
                        foreach ($snmp_fields as $field_name) {
                            $details[$field_name] = $value[$field_name];
                            unset($value[$field_name]);
                        }

                        if ($details['version'] == SNMP_V1) {
                            unset($details['max_repetitions']);
                        }

                        if ($details['version'] == SNMP_V1 || $details['version'] == SNMP_V2C) {
                            foreach (
                                [
                                    'securityname',
                                    'securitylevel',
                                    'authpassphrase',
                                    'privpassphrase',
                                    'authprotocol',
                                    'privprotocol',
                                    'contextname'
                                ] as $snmp_field_name
                            ) {
                                unset($details[$snmp_field_name]);
                            }
                        } else {
                            unset($details['community']);
                        }
                    } else {
                        foreach ($snmp_fields as $field_name) {
                            unset($value[$field_name]);
                        }
                    }

                    $value['details'] = $details;
                }
                unset($value);
            }
        }
        return $models;
    }

    /**
     * {@inheritDoc}
     */
    protected function applyQueryOutputOptions(Query &$query, string $tableName, string $tableAlias, $outPut = 'extent', array $options = []) {
		parent::applyQueryOutputOptions($query, $tableName, $tableAlias, $outPut, $options);

		if (!$options['countOutput'] && $this->outputIsRequested('details', $options['output'])) {
			// Select interface type to check show details array or not.

            $query->addSelect('hi.type');
            $query->addSelect([ZSqlHelper::dbConditionCoalesce('his.version', SNMP_V2C, 'version')]);
            $query->addSelect([ZSqlHelper::dbConditionCoalesce('his.bulk', SNMP_BULK_ENABLED, 'bulk')]);
            $query->addSelect([ZSqlHelper::dbConditionCoalesce('his.community', '', 'community')]);
            $query->addSelect([ZSqlHelper::dbConditionCoalesce('his.max_repetitions', '10', 'max_repetitions')]);
            $query->addSelect([ZSqlHelper::dbConditionCoalesce('his.securityname', '', 'securityname')]);
            $query->addSelect([ZSqlHelper::dbConditionCoalesce('his.securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, 'securitylevel')]);
            $query->addSelect([ZSqlHelper::dbConditionCoalesce('his.authpassphrase', '', 'authpassphrase')]);
            $query->addSelect([ZSqlHelper::dbConditionCoalesce('his.privpassphrase', '', 'privpassphrase')]);
            $query->addSelect([ZSqlHelper::dbConditionCoalesce('his.authprotocol', ITEM_SNMPV3_AUTHPROTOCOL_MD5, 'authprotocol')]);
            $query->addSelect([ZSqlHelper::dbConditionCoalesce('his.privprotocol', ITEM_SNMPV3_PRIVPROTOCOL_DES, 'privprotocol')]);
            $query->addSelect([ZSqlHelper::dbConditionCoalesce('his.contextname', '', 'contextname')]);


		}

		if (!$options['countOutput'] && $options['selectHosts'] !== null) {
            $query->addSelect('hi.hostid');
		}
	}

    /**
     * {@inheritDoc}
     */
    protected function addRelatedObjects(array $result)
    {
		$result = parent::addRelatedObjects($result);

		$interfaceIds = array_keys($result);

        $options = $this->getAttributes();

		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'interfaceid', 'hostid');
            $hosts = HostHelper::getHosts([
				'output' => $options['selectHosts'],
				'hosts' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding items
		if ($options['selectItems'] !== null) {
			if ($options['selectItems'] != API_OUTPUT_COUNT) {
                $items = ItemHelper::getItems([
					'output' => $this->outputExtend($options['selectItems'], ['itemid', 'interfaceid']),
					'interfaceids' => $interfaceIds,
					'nopermissions' => true,
					'preservekeys' => true,
					'filter' => ['flags' => null]
				]);
	
				$relationMap = $this->createRelationMap($items, 'interfaceid', 'itemid');

				$items = $this->unsetExtraFields($items, ['interfaceid', 'itemid'], $options['selectItems']);
				$result = $relationMap->mapMany($result, $items, 'items', $options['limitSelects']);
			}
			else {
				$items = ItemHelper::getItems([
					'interfaceids' => $interfaceIds,
					'nopermissions' => true,
					'filter' => ['flags' => null],
					'countOutput' => true,
					'groupCount' => true
				]);
				$items = prs_toHash($items, 'interfaceid');
				foreach ($result as $interfaceid => $interface) {
					$result[$interfaceid]['items'] = array_key_exists($interfaceid, $items)
						? $items[$interfaceid]['rowscount']
						: '0';
				}
			}
		}

		return $result;
	}
}
