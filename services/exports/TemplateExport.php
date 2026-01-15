<?php

namespace app\customs\zapi\services\exports;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\export\writers\CExportWriterFactory;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\TriggerHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\import\validators\CImportValidatorFactory;
use app\customs\zapi\common\macros\CMacrosResolverHelper;
use app\customs\zapi\forms\ExportForm;
use app\customs\zapi\services\HttpTestService;
use app\modules\libzbx\models\zbx\Hostmacro;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\HostsGroups;
use app\modules\libzbx\models\zbx\HostsTemplates;
use app\modules\libzbx\models\zbx\HostTag;
use app\modules\libzbx\models\zbx\Hstgrp;
use app\modules\libzbx\models\zbx\Valuemap;
use app\modules\libzbx\models\zbx\ValuemapMapping;
use yii\db\Query;

class TemplateExport extends BaseExport
{
    const ZIP_FILE_EXT = '.perseusz';

    const ZIP_FILE_NAME_TPL = 'lw_template.json';

    const ZIP_FILE_NAME_TPL_EXTRA = 'lw_template_extra.json';
    
    /**
     * @var boolean 关联扩展数据
     */
    public $withExtraData = false;

    /**
     * @var array Array with templates to be unlinked entity data.
     */
    protected $unlinkTemplatesData;

    /**
     * @var array Array with data that maybe be exported.
     */
    protected $extraData;

    /**
     * {@inheritDoc}
     */
    public function load(array $options)
    {
        parent::load($options);

        $this->data = [
            'templates' => [],
            'template_groups' => [],
            'host_groups' => [],
            'hosts' => [],
            'triggers' => [],
            'triggerPrototypes' => [],
            'graphs' => [],
            'graphPrototypes' => [],
            'images' => [],
            'maps' => [],
            'mediaTypes' => []
        ];

        $this->dataFields = [
            'item' => ['hostid', 'type', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends', 'status', 'value_type', 'trapper_hosts', 'units', 'valuemapid', 'params', 'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'interfaceid', 'description', 'inventory_link', 'flags', 'logtimefmt', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'parameters', 'posts', 'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode', 'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer', 'verify_host', 'allow_traps', 'uuid'],
            'drule' => ['itemid', 'hostid', 'type', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends', 'status', 'value_type', 'trapper_hosts', 'units', 'formula', 'valuemapid', 'params', 'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'interfaceid', 'description', 'inventory_link', 'flags', 'filter', 'lifetime', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'posts', 'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode', 'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer', 'verify_host', 'allow_traps', 'parameters', 'uuid'],
            'item_prototype' => ['hostid', 'type', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends', 'status', 'value_type', 'trapper_hosts', 'units', 'valuemapid', 'params', 'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'interfaceid', 'description', 'inventory_link', 'flags', 'logtimefmt', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'parameters', 'posts', 'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode', 'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer', 'verify_host', 'allow_traps', 'discover', 'uuid'],
            'trigger_prototype' => ['expression', 'description', 'url', 'url_name', 'status', 'priority', 'comments', 'type', 'flags', 'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag', 'manual_close', 'opdata', 'discover', 'event_name', 'uuid'],
            'httptests' => ['name', 'hostid', 'delay', 'retries', 'agent', 'http_proxy', 'variables', 'headers', 'status', 'authentication', 'http_user', 'http_password', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'uuid'],
            'trigger' => ['expression', 'description', 'url', 'url_name', 'status', 'priority', 'comments', 'type', 'flags', 'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag', 'manual_close', 'opdata', 'event_name', 'uuid']
        ];

        if ($this->withExtraData) {
            $this->extraData = [
                'migrates' => [], // 模板迁移数据
                'helpers' => [],  // 帮助说明数据
                'nestles' => [],  // 详情卡片数据
                'images' => [],
            ];
        }

        return $this;
    }

    /**
     * Excludes objects that cannot be exported.
     *
     * @param array $options
     *
     * @return array
     */
    protected function filterOptions(array $options)
    {
        if ($options['hosts']) {
            // exclude discovered hosts
            $query = Hosts::find()
                ->select(['hostid'])
                ->where(SqlHelper::whereIn('hostid', filter_integer($options['hosts'])))
                ->andWhere(['status' => [0, 1]])
                ->andWhere(['flags' => PRS_FLAG_DISCOVERY_NORMAL]);

            $options['hosts'] = $query->asArray()->column();
        }

        if ($this->isAny && empty($options['templates'])) {
            $query = Hosts::find()
                ->select(['hostid'])
                ->andWhere(['status' => HOST_STATUS_TEMPLATE]);
            $options['templates'] = $query->asArray()->column();
        }

        return $options;
    }

    /**
	 * Gathers data required for export from database depends on $options passed to constructor.
	 */
	public function gatherData() {
		$options = $this->filterOptions($this->options);

		if ($options['host_groups']) {
			$this->gatherHostGroups($options['host_groups']);
		}
        
		if ($options['template_groups']) {
			$this->gatherTemplateGroups($options['template_groups']);
		}

		if ($options['templates']) {
			$this->gatherTemplates($options['templates']);
		}

		if ($options['hosts']) {
			$this->gatherHosts($options['hosts']);
		}

		if ($options['templates'] || $options['hosts']) {
			$this->gatherGraphs($options['hosts'], $options['templates']);
			$this->gatherTriggers($options['hosts'], $options['templates']);
		}

		// if ($options['maps']) {
		// 	$options['images'] = array_merge($options['images'], $this->gatherMaps($options['maps']));
		// 	$options['images'] = array_keys(array_flip($options['images']));
		// }

		// if ($options['images']) {
		// 	$this->gatherImages($options['images']);
		// }

		// if ($options['mediaTypes']) {
		// 	$this->gatherMediaTypes($options['mediaTypes']);
		// }
	}

    /**
	 * Get host groups for export from database.
	 *
	 * @param array $groupIds
	 */
	protected function gatherHostGroups(array $groupIds) {
        $this->data['host_groups'] += GroupHelper::getHostGroups([
            'output' => ['name', 'uuid'],
            'groupids' => $groupIds
        ]);
	}

	/**
	 * Get template groups for export from database.
	 *
	 * @param array $groupIds
	 */
	protected function gatherTemplateGroups(array $groupIds) {
        $this->data['template_groups'] += GroupHelper::getTemplateGroups([
            'output' => ['name', 'uuid'],
            'groupids' => $groupIds
        ]);
	}


    /**
     * @param  int[] $templateIds
     */
    public function gatherTemplates(array $templateIds)
    {
        $idWhereIn = SqlHelper::whereIn('hostid', $templateIds);
        $templates = Hosts::find()
            ->select(['hostid', 'host', 'name', 'description', 'uuid', 'vendor_name', 'vendor_version'])
            ->indexBy('hostid')
            ->where($idWhereIn)
            ->andWhere(['status' => HOST_STATUS_TEMPLATE])
            ->asArray()
            ->all();

        if ($templates) {
            $groups = $this->getHostGroups($idWhereIn);

            $macros = $this->getHostMacros($idWhereIn);

            $parentTemplates = $this->getParentTemplates($idWhereIn);

            $valuemaps = $this->getValueMaps($idWhereIn);

            $tags = $this->getTags($idWhereIn);

            foreach ($templates as &$template) {
                $template['templategroups'] = $groups[$template['hostid']] ?? [];
                // merge host groups with all groups
                $this->data['template_groups'] += array_column($template['templategroups'], null, 'groupid');

                $template['macros'] = $macros[$template['hostid']] ?? [];
                $template['parentTemplates'] = $parentTemplates[$template['hostid']] ?? [];
                $template['valuemaps'] = $valuemaps[$template['hostid']] ?? [];
                $template['tags'] = $tags[$template['hostid']] ?? [];

                $template['dashboards'] = [];
                $template['discoveryRules'] = [];
                $template['items'] = [];
                $template['httptests'] = [];
            }
            unset($template);

            $templates = $this->gatherDashboards($templates);
            $templates = $this->gatherItems($templates);
            $templates = $this->gatherDiscoveryRules($templates);
            $templates = $this->gatherHttpTests($templates);
            $templates = $this->removeHttpTestItems($templates);

            $this->gatherGraphs([], $templateIds);
            $this->gatherTriggers([], $templateIds);

            $this->data['templates'] = $templates;
        }
    }

    public function getHostGroups(string $idWhereIn, int $type = HOST_GROUP_TYPE_TEMPLATE_GROUP)
    {
        $query = (new Query())
            ->from([
                'hg' => HostsGroups::tableName(),
                'g' => Hstgrp::tableName()
            ])
            ->where('hg.groupid=g.groupid')
            ->andWhere(['g.type' => $type])
            ->andWhere(str_replace('hostid', '{{hg}}.hostid', $idWhereIn))
            ->select(['g.groupid', 'g.name', 'g.uuid', 'hg.hostid']);
        // 模板分组
        $groups = $query->all();

        $data = [];
        foreach ($groups as $group) {
            $data[$group['hostid']][] = array_diff_key($group, ['hostid' => 1]);
        }
        return $data;
    }

    public function getHostMacros(string $idWhereIn)
    {
        $query = Hostmacro::find()
            ->where($idWhereIn)
            ->asArray();

        $rows = $query->all();

        $data  = [];
        foreach ($rows as $row) {
            $data[$row['hostid']][] = $row;
        }
        return $data;
    }

    public function getParentTemplates(string $idWhereIn)
    {
        $query = new Query();
        $query->from([
            'h' => Hosts::tableName(),
            'ht' => HostsTemplates::tableName(),
        ]);
        $query->select([
            'templateid' => 'h.hostid',
            'h.proxy_hostid',
            'h.host',
            'h.name',
            'h.status',
            'h.flags',
            'h.description',
            'h.ipmi_authtype',
            'h.ipmi_privilege',
            'h.ipmi_username',
            'h.ipmi_password',
            'h.maintenanceid',
            'h.maintenance_status',
            'h.maintenance_type',
            'h.maintenance_from',
            'h.tls_connect',
            'h.tls_accept',
            'h.tls_issuer',
            'h.tls_subject',
            'h.tls_psk_identity',
            'h.tls_psk',
            'h.proxy_address',
            'h.auto_compress',
            'h.custom_interfaces',
            'h.uuid',
            'h.vendor_name',
            'h.vendor_version',
            'ht.link_type',
            'ht.hostid',
        ]);

        $query->where('h.hostid=ht.templateid')
            ->andWhere(str_replace('hostid', 'ht.hostid', $idWhereIn));

        $rows = $query->all();

        $data = [];
        foreach ($rows as $row) {
            $data[$row['hostid']][] = array_diff_key($row, ['hostid' => 1]);
        }

        return $data;
    }

    public function getValueMaps(string $idWhereIn)
    {
        $query = new Query();
        $query->from([
            'v' => Valuemap::tableName(),
            'vm' => ValuemapMapping::tableName(),
        ]);

        $query->where('v.valuemapid=vm.valuemapid')
            ->andWhere(str_replace('hostid', 'v.hostid', $idWhereIn));

        $query->select(['v.valuemapid', 'v.name', 'v.hostid', 'v.uuid', 'vm.value', 'vm.newvalue', 'vm.type']);

        $rows = $query->all();

        $data = [];
        foreach ($rows as $row) {
            if (!array_key_exists($row['hostid'], $data)) {
                $data[$row['hostid']] = [];
            }

            if (!array_key_exists($row['valuemapid'], $data[$row['hostid']])) {
                $data[$row['hostid']][$row['valuemapid']] = [
                    'valuemapid' => $row['valuemapid'],
                    'name' => $row['name'],
                    'uuid' => $row['uuid'],
                    'mappings' => []
                ];
            }
            $data[$row['hostid']][$row['valuemapid']]['mappings'][] = [
                'type' => $row['type'],
                'value' => $row['value'],
                'newvalue' => $row['newvalue'],
            ];
        }

        return array_map(function ($v) {
            return array_values($v);
        }, $data);
    }

    public function getTags(string $idWhereIn)
    {
        $query = new Query();
        $query->from(HostTag::tableName());

        $query->where($idWhereIn);

        $query->select(['hostid', 'tag', 'value']);

        $rows = $query->all();

        $data = [];
        foreach ($rows as $row) {
            $data[$row['hostid']][] = array_diff_key($row, ['hostid' => 1]);
        }
        return $data;
    }


    /**
     * @param  array       $templates
     * @param  string|null $idWhereIn
     * @return array
     */
    protected function gatherDashboards($templates, ?string $idWhereIn = null)
    {
        return $templates;
    }

    /**
     * @param  array $hosts
     * @return array
     */
    protected function gatherItems($hosts)
    {
        $options = [
            'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
            'selectTags' => ['tag', 'value'],
            'webitems' => true,
            'filter' => ['flags' => PRS_FLAG_DISCOVERY_NORMAL],
            'preservekeys' => true,
            'is_all' => true,
        ];

        // Find inherited template items.
        $inherited_items = [];

        if ($this->unlinkTemplatesData) {
            $templateids = [];

            foreach ($this->unlinkTemplatesData as $unlinkTemplatesData) {
                if ($unlinkTemplatesData['unlink_itemids']) {
                    $templateids[] = $unlinkTemplatesData['templateid'];
                }
            }

            $inherit_options = [
                'output' => array_merge($this->dataFields['item'], ['templateid']),
                'templateids' => $templateids,
                'inherited' => true
            ];

            $inherited_items = ItemHelper::getItems($options + $inherit_options);

            foreach ($inherited_items as $itemid => $item) {
                foreach ($this->unlinkTemplatesData as $templates_data) {
                    if (
                        $item['hostid'] == $templates_data['templateid']
                        && !in_array($item['templateid'], $templates_data['unlink_itemids'])
                    ) {
                        unset($inherited_items[$itemid]);
                    }
                }
            }
        }

        $options += [
            'output' => $this->dataFields['item'],
            'hostids' => array_keys($hosts),
            'inherited' => false
        ];


        $items = ItemHelper::getItems($options);

        $items = $items + $inherited_items;

        foreach ($items as $itemid => &$item) {
            if ($item['type'] == ITEM_TYPE_DEPENDENT) {
                if (array_key_exists($item['master_itemid'], $items)) {
                    $item['master_item'] = ['key_' => $items[$item['master_itemid']]['key_']];
                } else {
                    // Do not export dependent items with master item from template.
                    unset($items[$itemid]);
                }
            }
        }
        unset($item);

        // Web items will be removed from result after all items and discovery rules are gathered and processed.

        $items = $this->prepareItems($items);

        foreach ($items as $item) {
            $item['host'] = $hosts[$item['hostid']]['host'];
            $hosts[$item['hostid']]['items'][] = $item;
        }

        return $hosts;
    }

    /**
     * @param  array       $hosts
     * @param  string|null $idWhereIn
     * @return array
     */
    protected function gatherDiscoveryRules($hosts)
    {
        $options = [
            'selectFilter' => ['evaltype', 'formula', 'conditions'],
            'selectLLDMacroPaths' => ['lld_macro', 'path'],
            'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
            'selectOverrides' => ['name', 'step', 'stop', 'filter', 'operations'],
            'preservekeys' => true,
            'is_all' => true,
        ];
        // Find inherited discovery rules.
        $inherited_discovery_rules = [];

        if ($this->unlinkTemplatesData) {
            $templateids = [];

            foreach ($this->unlinkTemplatesData as $unlinkTemplatesData) {
                if ($unlinkTemplatesData['unlink_discoveries']) {
                    $templateids[] = $unlinkTemplatesData['templateid'];
                }
            }

            $inherit_options = [
                'output' => array_merge($this->dataFields['drule'], ['templateid']),
                'templateids' => $templateids,
                'inherited' => true
            ];

            $inherited_discovery_rules = DiscoverRuleHelper::getDiscoverRules($options + $inherit_options);

            foreach ($inherited_discovery_rules as $id => $discovery_rule) {
                foreach ($this->unlinkTemplatesData as $unlinkTemplatesData) {
                    if (
                        $discovery_rule['hostid'] == $unlinkTemplatesData['templateid']
                        && !in_array($discovery_rule['templateid'], $unlinkTemplatesData['unlink_discoveries'])
                    ) {
                        unset($discovery_rule[$id]);
                    }
                }
            }
        }

        $options += [
            'output' => $this->dataFields['drule'],
            'hostids' => array_keys($hosts),
            'inherited' => false
        ];

        // $searcher = new DiscoverRuleSearch();
        // $provider = $searcher->search($options);
        // $discovery_rules = $provider->getModels();

        $discovery_rules = DiscoverRuleHelper::getDiscoverRules($options);

        $discovery_rules = $discovery_rules + $inherited_discovery_rules;

        $itemids = [];
        foreach ($hosts as $host_data) {
            foreach ($host_data['items'] as $item) {
                $itemids[$item['itemid']] = $item['key_'];
            }
        }

        $discovery_rules = $this->prepareDiscoveryRules($discovery_rules);

        // Discovery rules may use web items as master items.
        foreach ($discovery_rules as $discovery_rule) {
            if ($discovery_rule['type'] == ITEM_TYPE_DEPENDENT) {
                $master_itemid = $discovery_rule['master_itemid'];

                if (array_key_exists($master_itemid, $itemids)) {
                    $discovery_rule['master_item'] = ['key_' => $itemids[$master_itemid]];
                }
            }

            foreach ($discovery_rule['itemPrototypes'] as $itemid => $item_prototype) {
                $discovery_rule['itemPrototypes'][$itemid]['host'] = $hosts[$discovery_rule['hostid']]['host'];
            }

            $hosts[$discovery_rule['hostid']]['discoveryRules'][] = $discovery_rule;
        }

        return $hosts;
    }

    /**
     * @param  array       $hosts
     * @return array
     */
    protected function gatherHttpTests($hosts)
    {
        $options = [
            'selectSteps' => [
                'no',
                'name',
                'url',
                'query_fields',
                'posts',
                'variables',
                'headers',
                'follow_redirects',
                'retrieve_mode',
                'timeout',
                'required',
                'status_codes'
            ],
            'selectTags' => ['tag', 'value'],
            'preservekeys' => true
        ];

        // Get inherited templates http tests.
        $inherited_httptests = [];

        if ($this->unlinkTemplatesData) {
            $templateids = [];

            foreach ($this->unlinkTemplatesData as $unlinkTemplatesData) {
                if ($unlinkTemplatesData['unlink_httptests']) {
                    $templateids[] = $unlinkTemplatesData['templateid'];
                }
            }

            $inherit_options = [
                'output' => array_merge($this->dataFields['httptests'], ['templateid']),
                'templateids' => $templateids,
                'inherited' => true
            ];

            $inherited_httptests = HttpTestService::instance()->getHttpTestsByIds([], $templateids);

            foreach ($inherited_httptests as $id => $httptest) {
                foreach ($this->unlinkTemplatesData as $unlinkTemplatesData) {
                    if (
                        $httptest['hostid'] == $unlinkTemplatesData['templateid']
                        && !in_array($httptest['templateid'], $unlinkTemplatesData['unlink_httptests'])
                    ) {
                        unset($inherited_httptests[$id]);
                    }
                }
            }
        }

        $options += [
            'output' => $this->dataFields['httptests'],
            'hostids' => array_keys($hosts),
            'inherited' => false
        ];
        $httptests = HttpTestService::instance()->getHttpTestsByIds([], array_keys($hosts));

        $httptests += $inherited_httptests;

        foreach ($httptests as $httptest) {
            $hosts[$httptest['hostid']]['httptests'][] = $httptest;
        }

        return $hosts;
    }

    /**
     * Remove web items.
     *
     * @param array $hosts  Array of hosts or templates to remove the web items from.
     *
     * @return array
     */
    protected function removeHttpTestItems(array $hosts): array
    {
        foreach ($hosts as &$host) {
            if ($host['items']) {
                foreach ($host['items'] as $idx => $item) {
                    if ($item['type'] == ITEM_TYPE_HTTPTEST) {
                        unset($host['items'][$idx]);
                    }
                }
            }
        }
        unset($host);

        return $hosts;
    }

    /**
	 * Get Hosts for export from database.
	 *
	 * @param array $hostIds
	 */
	protected function gatherHosts(array $hostIds) 
    {
        return [];
    }

    /**
     * Get graphs for export from database.
     *
     * @param array $hostIds
     * @param array $templateIds
     */
    protected function gatherGraphs(array $hostIds, array $templateIds)
    {
        return [];
    }

    /**
     * Get triggers for export from database.
     *
     * @param array $hostIds
     * @param array $templateIds
     */
    protected function gatherTriggers(array $hostIds, array $templateIds)
    {
        $options = [
            'selectDependencies' => ['expression', 'description', 'recovery_expression'],
            'selectItems' => ['itemid', 'flags', 'type', 'templateid'],
            'selectTags' => ['tag', 'value'],
            'selectHosts' => ['status'],
            'filter' => ['flags' => PRS_FLAG_DISCOVERY_NORMAL],
            'preservekeys' => true
        ];

        // Get templates inherited triggers.
        $inherited_triggers = [];

        if ($this->unlinkTemplatesData) {
            $templateids = [];

            foreach ($this->unlinkTemplatesData as $unlinkTemplatesData) {
                if ($unlinkTemplatesData['unlink_triggers']) {
                    $templateids[] = $unlinkTemplatesData['templateid'];
                }
            }

            $inherit_options = [
                'output' => array_merge($this->dataFields['trigger'], ['templateid']),
                'templateids' => $templateids,
                'inherited' => true
            ];

            $inherited_triggers = TriggerHelper::getTriggers($options + $inherit_options);

            foreach ($inherited_triggers as $id => $trigger) {
                foreach ($this->unlinkTemplatesData as $unlinkTemplatesData) {
                    if (
                        in_array($unlinkTemplatesData['templateid'], $trigger['hosts'])
                        && !in_array($trigger['templateid'], $unlinkTemplatesData['unlink_triggers'])
                    ) {
                        unset($inherited_triggers[$id]);
                    }
                }
            }
        }

        $options += [
            'output' => $this->dataFields['trigger'],
            'hostids' => array_merge($hostIds, $templateIds),
            'inherited' => false
        ];

        $triggers = TriggerHelper::getTriggers($options);

        $triggers += $inherited_triggers;

        $this->data['triggers'] = $this->prepareTriggers($triggers);
    }

    /**
     * Get items related objects data from database. and set 'valueMaps' data.
     *
     * @param array $items
     *
     * @return array
     */
    protected function prepareItems(array $items)
    {
        $valueMapIds = array_filter(array_column($items, 'valuemapid'));

        $id2name = [];
        if ($valueMapIds) {
            $id2name = Valuemap::find()->where(['valuemapid' => $valueMapIds])->select(['name'])->indexBy('valuemapid')->column();
        }

        foreach ($items as $idx => &$item) {
            $item['valuemap'] = [];

            if ($item['valuemapid'] != 0) {
                $item['valuemap'] = ['name' => $id2name[$item['valuemapid']]];
            }
        }
        unset($item);

        return $items;
    }

    /**
     * Get discovery rules related objects from database.
     *
     * @param array $items
     *
     * @return array
     */
    protected function prepareDiscoveryRules(array $items)
    {
        $templateids = [];

        foreach ($items as &$item) {
            $item['itemPrototypes'] = [];
            $item['graphPrototypes'] = [];
            $item['triggerPrototypes'] = [];
            $item['hostPrototypes'] = [];

            // Unset unnecessary condition fields.
            foreach ($item['filter']['conditions'] as &$condition) {
                unset($condition['item_conditionid'], $condition['itemid']);
            }
            unset($condition);

            // Unset unnecessary filter field and prepare the operations.
            if ($item['overrides']) {
                foreach ($item['overrides'] as &$override) {
                    foreach ($override['operations'] as &$operation) {
                        if (array_key_exists('opstatus', $operation)) {
                            $operation['status'] = (string) $operation['opstatus']['status'];
                            unset($operation['opstatus']);
                        }
                        if (array_key_exists('opdiscover', $operation)) {
                            $operation['discover'] =  (string) $operation['opdiscover']['discover'];
                            unset($operation['opdiscover']);
                        }
                        if (array_key_exists('opperiod', $operation)) {
                            $operation['delay'] = $operation['opperiod']['delay'];
                            unset($operation['opperiod']);
                        }
                        if (array_key_exists('ophistory', $operation)) {
                            $operation['history'] = $operation['ophistory']['history'];
                            unset($operation['ophistory']);
                        }
                        if (array_key_exists('optrends', $operation)) {
                            $operation['trends'] = $operation['optrends']['trends'];
                            unset($operation['optrends']);
                        }
                        if (array_key_exists('opseverity', $operation)) {
                            $operation['severity'] = $operation['opseverity']['severity'];
                            unset($operation['opseverity']);
                        }
                        if (array_key_exists('optag', $operation)) {
                            $operation['tags'] = [];
                            foreach ($operation['optag'] as $tag) {
                                $operation['tags'][] = $tag;
                            }
                            unset($operation['optag']);
                        }
                        if (array_key_exists('optemplate', $operation)) {
                            foreach ($operation['optemplate'] as $template) {
                                $templateids[$template['templateid']] = true;
                            }
                        }
                        if (array_key_exists('opinventory', $operation)) {
                            $operation['inventory_mode'] = $operation['opinventory']['inventory_mode'];
                            unset($operation['opinventory']);
                        }
                    }
                    unset($operation);
                }
                unset($override);
            }
        }
        unset($item);

        if ($templateids) {

            $query = Hosts::find()
                ->where([
                    'hostid' => array_keys($templateids),
                    'status' => HOST_STATUS_TEMPLATE
                ])
                ->select(['name'])
                ->indexBy('hostid');
            $templates = $query->asArray()->column();

            foreach ($items as &$item) {
                if ($item['overrides']) {
                    foreach ($item['overrides'] as &$override) {
                        foreach ($override['operations'] as &$operation) {
                            if (array_key_exists('optemplate', $operation)) {
                                $operation['templates'] = [];
                                foreach ($operation['optemplate'] as $template) {
                                    $operation['templates'][] = ['name' => $templates[$template['templateid']]];
                                }
                                unset($operation['optemplate']);
                            }
                        }
                        unset($operation);
                    }
                    unset($override);
                }
            }
            unset($item);
        }

        // gather item prototypes
        $options = [
            'output' => $this->dataFields['item_prototype'],
            'selectDiscoveryRule' => ['itemid'],
            'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
            'selectTags' => ['tag', 'value'],
            'discoveryids' => array_column($items, 'itemid'),
            'preservekeys' => true,
            'is_all' => true,
        ];

        $options +=  $this->unlinkTemplatesData ? ['templated' => true] : ['inherited' => false];

        $item_prototypes = ItemHelper::getItemPrototypes($options);

        $unresolved_master_itemids = [];

        // Gather all master item IDs and check if master item IDs already belong to item prototypes.
        foreach ($item_prototypes as $item_prototype) {
            if (
                $item_prototype['type'] == ITEM_TYPE_DEPENDENT
                && !array_key_exists($item_prototype['master_itemid'], $item_prototypes)
            ) {
                $unresolved_master_itemids[$item_prototype['master_itemid']] = true;
            }
        }

        // Some leftover regular, non-lld and web items.
        if ($unresolved_master_itemids) {
            $master_items = ItemHelper::getItems([
                'output' => ['itemid', 'key_'],
                'itemids' => array_keys($unresolved_master_itemids),
                'filter' => ['flags' => PRS_FLAG_DISCOVERY_NORMAL],
                'webitems' => true,
                'preservekeys' => true,
                'is_all' => true,
            ]);

        }

        $valuemapids = [];
     
        foreach ($item_prototypes as &$item_prototype) {
            $item_prototype['valuemapid'] && $valuemapids[$item_prototype['valuemapid']] = $item_prototype['valuemapid'];

            if ($item_prototype['type'] == ITEM_TYPE_DEPENDENT) {
                $master_itemid = $item_prototype['master_itemid'];
                if (array_key_exists($master_itemid, $item_prototypes)) {
                    $item_prototype['master_item'] = ['key_' => $item_prototypes[$master_itemid]['key_']];
                } else {
                    $item_prototype['master_item'] = ['key_' => $master_items[$master_itemid]['key_']];
                }
            }
        }
        unset($item_prototype);

        $id2name = [];
        if ($valuemapids) {
            $id2name = Valuemap::find()
                ->where(['valuemapid' => array_keys($valuemapids)])
                ->select(['name'])
                ->indexBy('valuemapid')
                ->column();
        }

        foreach ($item_prototypes as $item_prototype) {
            $item_prototype['valuemap'] = [];

            if ($item_prototype['valuemapid'] != 0) {
                $item_prototype['valuemap']['name'] = $id2name[$item_prototype['valuemapid']];
            }

            $items[$item_prototype['discoveryRule']['itemid']]['itemPrototypes'][] = $item_prototype;
        }

        // gather graph prototypes
        $options = [
            'output' => API_OUTPUT_EXTEND,
            'discoveryids' => array_column($items, 'itemid'),
            'selectDiscoveryRule' => API_OUTPUT_EXTEND,
            'selectGraphItems' => API_OUTPUT_EXTEND,
            'preservekeys' => true,
            'is_all' => true,
        ];

        $options +=  $this->unlinkTemplatesData ? ['templated' => true] : ['inherited' => false];

        // TODO: GraphPrototype
        $graphs = [];

        $graphs = $this->prepareGraphs($graphs);

        foreach ($graphs as $graph) {
            $items[$graph['discoveryRule']['itemid']]['graphPrototypes'][] = $graph;
        }

        // gather trigger prototypes
        $options = [
            'output' => $this->dataFields['trigger_prototype'],
            'selectDiscoveryRule' => $this->dataFields['item'],
            'selectDependencies' => ['expression', 'description', 'recovery_expression'],
            'selectHosts' => ['status'],
            'selectItems' => ['itemid', 'flags', 'type'],
            'selectTags' => ['tag', 'value'],
            'discoveryids' => array_column($items, 'itemid'),
            'preservekeys' => true
        ];

        $options +=  $this->unlinkTemplatesData ? ['templated' => true] : ['inherited' => false];

        $triggers = TriggerHelper::getTriggerPrototypes($options);

        $triggers = $this->prepareTriggers($triggers);

        foreach ($triggers as $trigger) {
            $items[$trigger['discoveryRule']['itemid']]['triggerPrototypes'][] = $trigger;
        }

        // gather host prototypes
        $options = [
            'discoveryids' => prs_objectValues($items, 'itemid'),
            'output' => API_OUTPUT_EXTEND,
            'selectGroupLinks' => ['groupid'],
            'selectGroupPrototypes' => ['name'],
            'selectDiscoveryRule' => API_OUTPUT_EXTEND,
            'selectTemplates' => API_OUTPUT_EXTEND,
            'selectMacros' => API_OUTPUT_EXTEND,
            'selectTags' => ['tag', 'value'],
            'selectInterfaces' => ['main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
            'preservekeys' => true,
            'is_all' => true,
        ];

        if (!$this->unlinkTemplatesData) {
            $options += ['inherited' => false];
        }

        $host_prototypes = HostHelper::getHostPrototypes($options);

        // Replace group prototype group IDs with references.
        $groupids = [];

        foreach ($host_prototypes as $host_prototype) {
            $groupids += array_flip(array_column($host_prototype['groupLinks'], 'groupid'));
        }

        $groups = $this->getGroupsReferences(array_keys($groupids));

        // Export the groups used in group prototypes.
        $this->data['host_groups'] += $groups;

        foreach ($host_prototypes as $host_prototype) {
            foreach ($host_prototype['groupLinks'] as &$group_link) {
                $group_link = $groups[$group_link['groupid']];
            }
            unset($group_link);

            $items[$host_prototype['discoveryRule']['itemid']]['hostPrototypes'][] = $host_prototype;
        }

        return $items;
    }

    /**
     * Unset graphs that have LLD created items and replace graph itemids with array of host and key.
     *
     * @param array $graphs
     *
     * @return array
     */
    protected function prepareGraphs(array $graphs)
    {
        $graphItemIds = [];

        foreach ($graphs as $graph) {
            foreach ($graph['gitems'] as $gItem) {
                $graphItemIds[$gItem['itemid']] = $gItem['itemid'];
            }

            if ($graph['ymin_itemid']) {
                $graphItemIds[$graph['ymin_itemid']] = $graph['ymin_itemid'];
            }
            if ($graph['ymax_itemid']) {
                $graphItemIds[$graph['ymax_itemid']] = $graph['ymax_itemid'];
            }
        }

        $graph_items = ItemHelper::getItems([
            'output' => ['key_'],
            'selectHosts' => ['host', 'status'],
            'itemids' => $graphItemIds,
            'webitems' => true,
            'filter' => [
                'flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_PROTOTYPE]
            ],
            'preservekeys' => true,
            'is_all' => true,
        ]);

        foreach ($graphs as $gnum => $graph) {
            if ($graph['ymin_itemid']) {
                if (array_key_exists($graph['ymin_itemid'], $graph_items)) {
                    $item = $graph_items[$graph['ymin_itemid']];
                    $graphs[$gnum]['ymin_itemid'] = [
                        'host' => $item['hosts'][0]['host'],
                        'key' => $item['key_']
                    ];
                } else {
                    unset($graphs[$gnum]);
                    continue;
                }
            }

            if ($graph['ymax_itemid']) {
                if (array_key_exists($graph['ymax_itemid'], $graph_items)) {
                    $item = $graph_items[$graph['ymax_itemid']];
                    $graphs[$gnum]['ymax_itemid'] = [
                        'host' => $item['hosts'][0]['host'],
                        'key' => $item['key_']
                    ];
                } else {
                    unset($graphs[$gnum]);
                    continue;
                }
            }

            foreach ($graph['gitems'] as $ginum => $gItem) {
                if (array_key_exists($gItem['itemid'], $graph_items)) {
                    $item = $graph_items[$gItem['itemid']];

                    if ($item['hosts'][0]['status'] != HOST_STATUS_TEMPLATE) {
                        unset($graph['uuid']);
                    }
                    unset($item['hosts'][0]['status']);

                    $graphs[$gnum]['gitems'][$ginum]['itemid'] = [
                        'host' => $item['hosts'][0]['host'],
                        'key' => $item['key_']
                    ];
                } else {
                    unset($graphs[$gnum]);
                    continue 2;
                }
            }
        }

        return $graphs;
    }

    /**
     * Prepare trigger expressions and unset triggers containing discovered items.
     *
     * @param array $triggers
     *
     * @return array
     */
    protected function prepareTriggers(array $triggers)
    {
        // Unset triggers containing discovered items.
        foreach ($triggers as $idx => &$trigger) {
            if ($trigger['hosts'][0]['status'] != HOST_STATUS_TEMPLATE) {
                unset($trigger['uuid']);
            }
            unset($trigger['hosts']);

            foreach ($trigger['items'] as $item) {
                if ($item['flags'] == PRS_FLAG_DISCOVERY_CREATED) {
                    unset($triggers[$idx]);
                    continue 2;
                }
            }

            $trigger['dependencies'] = CMacrosResolverHelper::resolveTriggerExpressions(
                $trigger['dependencies'],
                ['sources' => ['expression', 'recovery_expression']]
            );
        }
        unset($trigger);

        $triggers = CMacrosResolverHelper::resolveTriggerExpressions(
            $triggers,
            ['sources' => ['expression', 'recovery_expression']]
        );

        return $triggers;
    }

    /**
     * Get groups references by group IDs.
     *
     * @param array $groupIds
     *
     * @return array
     */
    protected function getGroupsReferences(array $groupIds)
    {
        $groups = GroupHelper::getHostGroups([
            'groupids' => $groupIds,
			'output' => ['uuid', 'name'],
			'preservekeys' => true
        ]);

        // Access denied for some objects?
        if (count($groups) != count($groupIds)) {
        }

        foreach ($groups as &$group) {
            $group = [
                'uuid' => $group['uuid'],
                'name' => $group['name']
            ];
        }
        unset($group);

        return $groups;
    }

    public function output()
    {
        $schema = (new CImportValidatorFactory(CExportWriterFactory::YAML))
            ->getObject($this->version)
            ->getSchema();

        $this->setBuilder(ExportBuilder::instance());

        $simple_triggers = [];
        if ($this->data['triggers']) {
            $unlink_itemids = [];

            if ($this->unlinkTemplatesData) {
                foreach ($this->unlinkTemplatesData as $template_data) {
                    $unlink_itemids = array_merge($unlink_itemids, $template_data['unlink_itemids']);
                }
            }

            $simple_triggers = $this->builder->extractSimpleTriggers($this->data['triggers'], $unlink_itemids);
        }

        if ($this->data['template_groups']) {
            $this->builder->buildTemplateGroups(
                $schema['rules']['template_groups'],
                $this->data['template_groups']
            );
        }

        if ($this->data['host_groups']) {
            $this->builder->buildHostGroups($schema['rules']['host_groups'], $this->data['host_groups']);
        }

        if ($this->data['templates']) {
            $this->builder->buildTemplates(
                $schema['rules']['templates'],
                $this->data['templates'],
                $simple_triggers
            );
        }

        if ($this->data['hosts']) {
            $this->builder->buildHosts($schema['rules']['hosts'], $this->data['hosts'], $simple_triggers);
        }

        if ($this->data['triggers']) {
            $this->builder->buildTriggers($schema['rules']['triggers'], $this->data['triggers']);
        }

        if ($this->data['graphs']) {
            $this->builder->buildGraphs($schema['rules']['graphs'], $this->data['graphs']);
        }

        if ($this->data['images']) {
            $this->builder->buildImages($this->data['images']);
        }

        if ($this->data['maps']) {
            $this->builder->buildMaps($schema['rules']['maps'], $this->data['maps']);
        }

        if ($this->data['mediaTypes']) {
            $this->builder->buildMediaTypes($schema['rules']['media_types'], $this->data['mediaTypes']);
        }

        $writer = CExportWriterFactory::getWriter($this->format);
        $this->setWriter($writer);
        return $writer->write($this->builder->getExport());
    }

    /**
     * @param  array $params
     * @return array
     */
    public function compare(array $params)
    {
        $this->load($params);
        $this->validateExport($params, true);
        $this->gatherData();
        $this->output();
        return $this->builder->getExport();
    }

    /**
     * Validate input parameters for export() and exportCompare() methods.
     *
     * @param array $params
     * @param bool  $with_unlinked_parent_templates
     *
     * @throws ValidateException if the input is invalid.
     */
    private function validateExport(array &$params, bool $with_unlinked_parent_templates = false): void
    {
        $error = '';
        $rules = ExportForm::getValidationRules($with_unlinked_parent_templates);
        $bool = ValidateHelper::validateObject($params, $rules, [], $error);
        if (!$bool) {
            throw new ValidateException(60750001, $error);
        }
    }

    public function gatherExtraData()
    {
        if(empty($this->data['templates'])) {
            return ;
        }
        $templateIds = array_keys($this->data['templates']);

        $templates = \app\modules\magpie\models\TemplateConfig::findByTemplateIds($templateIds);

        if (empty($templates)) {
            return;
        }

        $extras = \app\modules\libzbx\models\TemplateExtra::find()
            ->select(['template_id', 'example', 'help_text', 'status'])
            ->indexBy('template_id')
            ->asArray()
            ->all();

        foreach($templates as $template) {
            if (!$template->getIsMaster()) {
                continue;
            }

            if ($template->triggerid) {
                $triggers = \app\modules\libzbx\models\Triggers::find()->select('description')
                    ->where(['triggerid' => array_filter(explode(',', $template->triggerid))])->column();
            } else {
                $triggers = [];
            }


            // 模板迁移数据
            $this->extraData['migrates'][] = $template->getAttributes(null, ['template_config_id', 'exclude_macros', 'expect_macros', 'triggerid', 'hostid']) + [
                'host' => $template->host,
                'name' => $template->name,
                'triggers' => $triggers,
            ];

            // 帮助说明数据
            if (array_key_exists($template->hostid, $extras)) {
                $helper = $extras[$template->hostid];
                $this->extraData['helpers'][] = array_merge([
                        'template_host' => $template->host,
                        'template_name' => $template->name,
                    ], $helper);
                if ($helper['help_text']) {
                    $this->extraData['images'] = array_merge($this->extraData['images'], $this->extractLocalImageSrc($helper));
                }
            }
            
            // 详情卡片
            $data = \app\modules\magpie\services\NestleService::instance()->exportDataByTemplate($template);
            if ($data) {
                $this->extraData['nestles'][$template->host] = $data;
            }
        }
    }

    protected function extractLocalImageSrc($html)
    {
         $dom = new \DOMDocument();
        // 忽略 HTML 警告（如未闭合标签）
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $images = $dom->getElementsByTagName('img');
        $localImages = [];

        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            if ($src && !preg_match('#^https?://#i', $src) && !preg_match('#^data:#i', $src)) {
                // 排除 http/https 和 data:image
                $localImages[] = $src;
            }
        }

        return $localImages;
    }

    public function outputExtra()
    {
        return $this->extraData;
    }
}
