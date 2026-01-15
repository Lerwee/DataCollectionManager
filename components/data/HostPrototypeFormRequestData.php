<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\MacroHelper;
use app\customs\zapi\common\helpers\ProxyHelper;
use app\customs\zapi\common\helpers\SettingsHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\services\assist\ItemAssist;

/**
 * Class HostPrototypeFormRequestData
 * @package app\customs\zapi\components\data
 */
class HostPrototypeFormRequestData extends HostPrototypeRequestData
{
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

        $data = [
            'form_refresh' => $this->getRequest('form_refresh', 0),
            'discovery_rule' => $discoveryRule,
            'host_prototype' => [
                'hostid' => $hostid,
                'templateid' => ($hostid == 0) ? 0 : $hostPrototype['templateid'],
                'host' => $this->getRequest('host'),
                'name' => $this->getRequest('name'),
                'status' => $this->getRequest('status', HOST_STATUS_NOT_MONITORED),
                'discover' => $this->getRequest('discover', DB::getDefault('hosts', 'discover')),
                'templates' => [],
                'add_templates' => [],
                'inventory_mode' => $this->getRequest('inventory_mode',
                    SettingsHelper::get(SettingsHelper::DEFAULT_INVENTORY_MODE)
                ),
                'groupPrototypes' => $this->getRequest('group_prototypes', []),
                'macros' => $macros,
                'custom_interfaces' => $this->getRequest('custom_interfaces', DB::getDefault('hosts', 'custom_interfaces')),
                'interfaces' => $this->getRequest('interfaces', []),
                'main_interfaces' => $this->getRequest('mainInterfaces', [])
            ],
            'show_inherited_macros' => $this->getRequest('show_inherited_macros', 0),
            'readonly' => ($hostid != 0 && $hostPrototype['templateid']),
            'groups' => [],
            'tags' => $this->getRequest('tags', []),
            'context' => $this->getRequest('context'),
            // Parent discovery rules.
            'templates' => [],
        ];

        // Add already linked and new templates.
        $templates = [];
        $request_templates = $this->getRequest('templates', []);
        $request_add_templates = $this->getRequest('add_templates', []);

        if ($request_templates || $request_add_templates) {
            $templates = TemplateHelper::getTemplates([
                'output' => ['templateid', 'name'],
                'templateids' => array_merge($request_templates, $request_add_templates),
                'preservekeys' => true,
            ]);

            $data['host_prototype']['templates'] = array_intersect_key($templates, array_flip($request_templates));
            CArrayHelper::sort($data['host_prototype']['templates'], ['name']);

            $data['host_prototype']['add_templates'] = array_intersect_key($templates, array_flip($request_add_templates));

            foreach ($data['host_prototype']['add_templates'] as &$template) {
                $template = CArrayHelper::renameKeys($template, ['templateid' => 'id']);
            }
            unset($template);
        }

        // add parent host
        $parentHost = HostHelper::getHosts([
            'output' => ['hostid', 'proxy_hostid', 'status', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
                'ipmi_password', 'tls_accept', 'tls_connect', 'tls_issuer', 'tls_subject',
            ],
            'selectInterfaces' => API_OUTPUT_EXTEND,
            'hostids' => $discoveryRule['hostid'],
            'templated_hosts' => true,
        ]);

        $parentHost = reset($parentHost);
        $data['parent_host'] = $parentHost;
        $data['parent_hostid'] = $parentHost['hostid'];

        if (getRequest('group_links')) {
            $data['groups'] = GroupHelper::getHostGroups([
                'output' => ['groupid', 'name'],
                'groupids' => getRequest('group_links'),
                'editable' => true,
                'preservekeys' => true,
            ]);
        }

        if ($parentHost['proxy_hostid']) {
            $proxy = ProxyHelper::getProxies([
                'output' => ['host', 'proxyid'],
                'proxyids' => $parentHost['proxy_hostid'],
                'limit' => 1,
            ]);
            $data['proxy'] = reset($proxy);
        }

        if (!array_key_exists('form_refresh', $this->data)) {
            if ($data['host_prototype']['hostid'] != 0) {
                // When opening existing host prototype, display all values from database.
                $data['host_prototype'] = array_merge($data['host_prototype'], $hostPrototype);

                foreach ($data['host_prototype']['macros'] as &$macro) {
                    if ($macro['type'] == PRS_MACRO_TYPE_SECRET
                        && !array_key_exists('deny_revert', $macro) && !array_key_exists('value', $macro)) {
                        $macro['allow_revert'] = true;
                    }
                }
                unset($macro);

                $groupids = array_column($data['host_prototype']['groupLinks'], 'groupid');
                $data['groups'] = GroupHelper::getHostGroups([
                    'output' => ['groupid', 'name'],
                    'groupids' => $groupids,
                    'preservekeys' => true,
                ]);

                $n = 0;
                foreach ($groupids as $groupid) {
                    if (!array_key_exists($groupid, $data['groups'])) {
                        $postfix = (++$n > 1) ? ' (' . $n . ')' : '';
                        $data['groups'][$groupid] = [
                            'groupid' => $groupid,
                            'name' => t('zapi', 'Inaccessible group') . $postfix,
                            'inaccessible' => true,
                        ];
                    }
                }

                $data['tags'] = $data['host_prototype']['tags'];
            } else {
                // Set default values for new host prototype.
                $data['host_prototype']['status'] = HOST_STATUS_MONITORED;
            }
        } else {
            foreach (ItemAssist::INTERFACE_TYPES_BY_PRIORITY as $type) {
                if (array_key_exists($type, $data['host_prototype']['main_interfaces'])) {
                    $interfaceid = $data['host_prototype']['main_interfaces'][$type];
                    $data['host_prototype']['interfaces'][$interfaceid]['main'] = INTERFACE_PRIMARY;
                }
            }
            $data['host_prototype']['interfaces'] = array_values($data['host_prototype']['interfaces']);
        }

        $data['allowed_ui_conf_templates'] = true;
        // $data['templates'] = makeHostPrototypeTemplatesHtml($data['host_prototype']['hostid'],
        //     HostHelper::getHostPrototypeParentTemplates([$data['host_prototype']]), $data['allowed_ui_conf_templates']
        // );

        // Select writable templates
        $templateids = prs_objectValues($data['host_prototype']['templates'], 'templateid');
        $data['host_prototype']['writable_templates'] = [];

        if ($templateids) {
            $data['host_prototype']['writable_templates'] = TemplateHelper::getTemplates([
                'output' => ['templateid'],
                'templateids' => $templateids,
                'editable' => true,
                'preservekeys' => true,
            ]);
        }

        // tags
        if (!$data['tags']) {
            $data['tags'][] = ['tag' => '', 'value' => ''];
        } else {
            CArrayHelper::sort($data['tags'], ['tag', 'value']);
        }

        $macros = $data['host_prototype']['macros'];

        if ($data['show_inherited_macros']) {
            $macros = MacroHelper::mergeInheritedMacros($macros, MacroHelper::getInheritedMacros(array_keys($templates), $data['parent_hostid']));
        }

        // Sort only after inherited macros are added. Otherwise the list will look chaotic.
        $data['macros'] = array_values(order_macros($macros, 'macro'));

        if (!$data['macros'] && !$data['readonly']) {
            $macro = ['macro' => '', 'value' => '', 'description' => '', 'type' => PRS_MACRO_TYPE_TEXT];

            if ($data['show_inherited_macros']) {
                $macro['inherited_type'] = PRS_PROPERTY_OWN;
            }

            $data['macros'][] = $macro;
        }

        foreach ($data['macros'] as &$macro) {
            $macro['discovery_state'] = MacroHelper::DISCOVERY_STATE_MANUAL;
        }
        unset($macro);

        // This data is used in common.template.edit.js.php.
        $data['macros_tab'] = [
            'linked_templates' => array_map('strval', $templateids),
            'add_templates' => array_map('strval', array_keys($data['host_prototype']['add_templates'])),
        ];

        // Editable host groups.
        $groups_rw = ($data['groups'] && 3 != USER_TYPE_SUPER_ADMIN)
        ? GroupHelper::getHostGroups([
            'output' => [],
            'groupids' => array_keys($data['groups']),
            'editable' => true,
            'preservekeys' => true,
        ])
        : [];

        $data['groups_ms'] = [];

        foreach ($data['groups'] as $group) {
            $data['groups_ms'][] = [
                'id' => $group['groupid'],
                'name' => $group['name'],
                'inaccessible' => array_key_exists('inaccessible', $group) && $group['inaccessible'],
                'disabled' => 3 != USER_TYPE_SUPER_ADMIN
                && !array_key_exists($group['groupid'], $groups_rw),
            ];
        }

        if (array_key_exists('groupPrototypes', $data['host_prototype'])) {
            $data['host_prototype']['group_prototypes'] = $data['host_prototype']['groupPrototypes'];
            unset($data['host_prototype']['groupPrototypes']);
        }

        if (array_key_exists('groupLinks', $data['host_prototype'])) {
            $data['host_prototype']['group_links'] = array_column($data['host_prototype']['groupLinks'], 'groupid');
        }

        return $this->success($data);
    }
}
