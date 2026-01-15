<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\MacroHelper;

/**
 * Class HostPrototypeRequestData
 * @package app\customs\zapi\components\data
 */
class HostPrototypeRequestData extends RequestData
{
    /**
     * @var string
     */
    public $action = 'create';

    /**
     * @return Result
     */
    public function beforeValidate(): Result
    {
        if (!$this->getRequest('parent_discoveryid')) {
            return $this->error(error_code(60750003, [
                'error' => 'parent_discoveryid',
            ]));
        }

        $hostid = $this->getRequest('hostid', 0);

        $discoveryRule = DiscoverRuleHelper::getDiscoverRules([
            'itemids' => $this->getRequest('parent_discoveryid'),
            'output' => API_OUTPUT_EXTEND,
            'selectHosts' => ['flags'],
            'editable' => true,
        ]);

        $discoveryRule = reset($discoveryRule);
        if (!$discoveryRule || $discoveryRule['hosts'][0]['flags'] == PRS_FLAG_DISCOVERY_CREATED) {
            return $this->error(10000404);
        }

        $hostPrototype = [];

        if ($hostid != 0) {
            $hostPrototype = HostHelper::getHostPrototypes([
                'output' => API_OUTPUT_EXTEND,
                'selectGroupLinks' => ['groupid'],
                'selectGroupPrototypes' => ['group_prototypeid', 'name'],
                'selectTemplates' => ['templateid', 'name'],
                'selectParentHost' => ['hostid'],
                'selectMacros' => ['hostmacroid', 'macro', 'value', 'type', 'description'],
                'selectTags' => ['tag', 'value'],
                'selectInterfaces' => ['type', 'main', 'useip', 'ip', 'dns', 'port', 'details'],
                'hostids' => $hostid,
                'editable' => true,
            ]);

            $hostPrototype = reset($hostPrototype);
            if (!$hostPrototype) {
                return $this->error(10000404);
            }
        }

        // Remove inherited macros data (actions: 'add', 'update' and 'form').
        $macros = MacroHelper::cleanInheritedMacros($this->getRequest('macros', []));

        // Remove empty new macro lines.
        $macros = array_filter($macros, function ($macro) {
            $keys = array_flip(['hostmacroid', 'macro', 'value', 'description']);

            return (bool) array_filter(array_intersect_key($macro, $keys));
        });

        return $this->success([
            'discoveryRule' => $discoveryRule,
            'hostPrototype' => $hostPrototype,
            'macros' => $macros,
            'hostid' => $hostid,
        ]);
    }

    /**
     * @return Result
     */
    public function validate(): Result
    {
        $request = $this->beforeValidate();
        if (!$request->isSuccess()) {
            return $request;
        }
        extract($request->getData());

        $input = [
            'host' => $this->getRequest('host', DB::getDefault('hosts', 'host')),
            'name' => $this->getRequest($this->getRequest('name', '') === '' ? 'host' : 'name', DB::getDefault('hosts', 'name')),
            'custom_interfaces' => $this->getRequest('custom_interfaces', DB::getDefault('hosts', 'custom_interfaces')),
            'status' => $this->getRequest('status', HOST_STATUS_NOT_MONITORED),
            'discover' => $this->getRequest('discover', DB::getDefault('hosts', 'discover')),
            'interfaces' => prepareHostPrototypeInterfaces(
                $this->getRequest('interfaces', []), $this->getRequest('mainInterfaces', [])
            ),
            'groupLinks' => prepareHostPrototypeGroupLinks($this->getRequest('group_links', [])),
            'groupPrototypes' => prepareHostPrototypeGroupPrototypes($this->getRequest('group_prototypes', [])),
            'templates' => prs_toObject(
                array_merge($this->getRequest('templates', []), $this->getRequest('add_templates', [])),
                'templateid'
            ),
            'tags' => prepareHostPrototypeTags($this->getRequest('tags', [])),
            'macros' => prepareHostPrototypeMacros($macros),
            'inventory_mode' => $this->getRequest('inventory_mode', HOST_INVENTORY_DISABLED)
        ];

        if ($this->action == 'create') {
            $host = ['ruleid' => $this->getRequest('parent_discoveryid')] + getSanitizedHostPrototypeFields(
                ['templateid' => 0] + $input
            );
        } elseif ($this->action == 'update') {
            $host = ['hostid' => $hostid] + getSanitizedHostPrototypeFields(
                ['templateid' => $hostPrototype['templateid']] + $input
            );
        } else {
            $host = $input;
        }

        return $this->success($host);
    }
}
