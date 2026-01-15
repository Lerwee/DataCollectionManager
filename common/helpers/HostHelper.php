<?php

namespace app\customs\zapi\common\helpers;

use app\common\helpers\ArrayHelper;
use app\customs\zapi\models\search\host\HostInterfaceSearch;
use app\customs\zapi\models\search\host\HostPrototypeSearch;
use app\customs\zapi\models\search\host\HostSearch;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Interfaces;
use yii\base\Exception;
use yii\db\Query;

class HostHelper
{
    public static function hostInterfaceTypeNumToName($type)
    {
        switch ($type) {
            case Interfaces::TYPE_AGENT:
                $name = 'AGENT';
                break;
            case Interfaces::TYPE_SNMP:
                $name = 'SNMP';
                break;
            case Interfaces::TYPE_IPMI:
                $name = 'IPMI';
                break;
            case Interfaces::TYPE_JMX:
                $name = 'JMX';
                break;
            default:
                $name = '';
                break;
        }
        return $name;
    }

    /**
     * @param mixed $templateIds
     * @param mixed $output
     * @return array
     */
    public static function getTags($templateIds, $output = API_OUTPUT_EXTEND): array
    {
        $data = [];
        $templateIds = filter_integer((array) $templateIds);
        foreach ($templateIds as $templateId) {
            $data[$templateId] = [];
        }
        unset($row);

        if ($output === API_OUTPUT_EXTEND) {
            $output = ['hosttagid', 'hostid', 'tag', 'value'];
        } else {
            $output = array_unique(array_merge(['hosttagid', 'hostid'], $output));
        }

        $db_tags = (new Query())->select($output)
            ->from('host_tag')
            ->where(['hostid' => $templateIds])
            ->all();

        foreach ($db_tags as $db_tag) {
            $hostid = $db_tag['hostid'];
            unset($db_tag['hosttagid'], $db_tag['hostid']);
            $data[$hostid][] = $db_tag;
        }
        return $data;
    }

    /**
     * @param $hostIds
     * @param array $templateColumns
     * @return array
     */
    public static function getHostParentTemplates($hostIds, $templateColumns = []): array
    {
        $hosts_templates = (new Query())->select(['ht.hostid', 'ht.templateid'])
            ->from(['ht' => 'hosts_templates'])
            ->where(['ht.hostid' => $hostIds])
            ->all();
        $data = [];
        foreach ($hostIds as $hostId) {
            $data[$hostId] = [];
        }
        if ($hosts_templates) {
            $templates = Hosts::find()->select(['templateid' => 'hostid'])
                ->addSelect($templateColumns)
                ->where(['hostid' => ArrayHelper::getColumn($hosts_templates, 'templateid')])
                ->andWhere(['status' => 3])
                ->indexBy('templateid')
                ->asArray()->all();
            $relationMap = [];
            foreach ($hosts_templates as $item) {
                $relationMap[$item['hostid']][] = $item['templateid'];
            }
            foreach ($relationMap as $hostId => $templateIds) {
                $data[$hostId] = array_intersect_key($templates, array_flip($templateIds));
            }
        }
        return $data;
    }

    /**
     * Get all parent templates recursively for given hosts and/or templates.
     *
     * @param array $hostIds  Mandatory, not empty. Host or template id's for whom you are searching parents.
     *
     * @throws APIException if templates are looped.
     *
     * @return array          List with two arrays: host to parent templates template map, list of all parent templates.
     */
    public static function getParentTemplates(array $hostIds): array
    {
        $hostTemplates = [];
        $stepHostIds = array_flip($hostIds);
        do {
            $stepHostIds = array_keys($stepHostIds);
            foreach ($stepHostIds as $hostId) {
                $hostTemplates[$hostId]['parents'] = [];
            }

            $templateIds = [];
            $dbHostTemplates = (new Query())
                ->select(['hostid', 'templateid'])
                ->from('hosts_templates')
                ->where(['hostid' => $hostIds])
                ->all();
            foreach ($dbHostTemplates as $dbHostTemplate) {
                $hostTemplates[$dbHostTemplate['hostid']]['parents'][$dbHostTemplate['templateid']] = true;
                $templateIds[$dbHostTemplate['templateid']] = true;
            }

            // Only unprocessed templates will be populated.
            $stepHostIds = [];
            foreach (array_keys($templateIds) as $templateId) {
                if (!array_key_exists($templateId, $hostTemplates)) {
                    $stepHostIds[$templateId] = true;
                }
            }
        } while ($stepHostIds);

        $allTemplateIds = array_keys(array_diff_key($hostTemplates, array_flip($hostIds)));

        $parentMap = [];
        foreach ($hostIds as $hostId) {
            $parentMap[$hostId] = array_keys(static::recursiveGetAllParents($hostTemplates, $hostId));
        }

        return [$parentMap, $allTemplateIds];
    }

    /**
     * 获取主机数据
     *
     * @param  array $params
     * @return array
     */
    public static function getHosts(array $params): array
    {
        $search = new HostSearch();
        $search->is_all = true;
        $provider = $search->search($params);
        return $provider->getModels();
    }

    /**
     * 获取主机原型数据
     *
     * @param  array $params
     * @return array
     */
    public static function getHostPrototypes(array $params): array
    {
        $search = new HostPrototypeSearch();
        $search->is_all = true;
        $provider = $search->search($params);
        return $provider->getModels();
    }

    /**
     * 获取主机接口数据
     *
     * @param  array $params
     * @return array
     */
    public static function getInterfaces(array $params): array
    {
        $search = new HostInterfaceSearch();
        $search->is_all = true;
        $provider = $search->search($params);
        return $provider->getModels();
    }

    /**
     * 获取主机资产数据
     *
     * @param  array $params
     * @return array
     */
    public static function getInventories(array $params): array
    {
        return [];
    }

    /**
     * Get all parent templates recursively for given templateid.
     *
     * @param array  $template_tree  Array with all template parents.
     * @param string $templateid     ID of template whose parents we are looking for.
     *
     * @throws Exception if templates are looped.
     *
     * @return array                 All parent templates (as keys) for given templateid.
     */
    private static function recursiveGetAllParents(array &$template_tree, $templateid): array
    {
        if (array_key_exists('started', $template_tree[$templateid])) {
            // Loop in recursion detected.
            throw new Exception(t('zapi', 'Internal error.'));
        }

        $parents = $template_tree[$templateid]['parents'];

        // This template's parents are not yet retrieved. Collect them.
        if (!array_key_exists('final', $template_tree[$templateid])) {
            $template_tree[$templateid]['started'] = true;

            foreach (array_keys($template_tree[$templateid]['parents']) as $parentId) {
                $parents += static::recursiveGetAllParents($template_tree, $parentId);
            }

            $template_tree[$templateid]['parents'] = $parents;
            unset($template_tree[$templateid]['started']);
            $template_tree[$templateid]['final'] = true;
        }

        return $parents;
    }

    /**
     * Get parent templates for each given host prototype.
     *
     * @param array  $host_prototypes                  An array of host prototypes.
     * @param string $host_prototypes[]['hostid']      ID of host prototype.
     * @param string $host_prototypes[]['templateid']  ID of parent template host prototype.
     *
     * @return array
     */
    public static function getHostPrototypeParentTemplates(array $host_prototypes)
    {
        $parent_host_prototypeids = [];
        $data = [
            'links' => [],
            'templates' => [],
        ];

        foreach ($host_prototypes as $host_prototype) {
            if ($host_prototype['templateid'] != 0) {
                $parent_host_prototypeids[$host_prototype['templateid']] = true;
                $data['links'][$host_prototype['hostid']] = ['hostid' => $host_prototype['templateid']];
            }
        }

        if (!$parent_host_prototypeids) {
            return $data;
        }

        $all_parent_host_prototypeids = [];
        $hostids = [];
        $lld_ruleids = [];

        do {
            $db_host_prototypes = static::getHostPrototypes([
                'output' => ['hostid', 'templateid'],
                'selectDiscoveryRule' => ['itemid'],
                'selectParentHost' => ['hostid'],
                'hostids' => array_keys($parent_host_prototypeids),
            ]);

            $all_parent_host_prototypeids += $parent_host_prototypeids;
            $parent_host_prototypeids = [];

            foreach ($db_host_prototypes as $db_host_prototype) {
                $data['templates'][$db_host_prototype['parentHost']['hostid']] = [];
                $hostids[$db_host_prototype['hostid']] = $db_host_prototype['parentHost']['hostid'];
                $lld_ruleids[$db_host_prototype['hostid']] = $db_host_prototype['discoveryRule']['itemid'];

                if ($db_host_prototype['templateid'] != 0) {
                    if (!array_key_exists($db_host_prototype['templateid'], $all_parent_host_prototypeids)) {
                        $parent_host_prototypeids[$db_host_prototype['templateid']] = true;
                    }

                    $data['links'][$db_host_prototype['hostid']] = ['hostid' => $db_host_prototype['templateid']];
                }
            }
        } while ($parent_host_prototypeids);

        foreach ($data['links'] as &$parent_host_prototype) {
            $parent_host_prototype['parent_hostid'] = array_key_exists($parent_host_prototype['hostid'], $hostids)
            ? $hostids[$parent_host_prototype['hostid']]
            : 0;

            $parent_host_prototype['lld_ruleid'] = array_key_exists($parent_host_prototype['hostid'], $lld_ruleids)
            ? $lld_ruleids[$parent_host_prototype['hostid']]
            : 0;
        }
        unset($parent_host_prototype);

        $db_templates = $data['templates']
        ? TemplateHelper::getTemplates([
            'output' => ['name'],
            'templateids' => array_keys($data['templates']),
            'preservekeys' => true,
        ])
        : [];

        $rw_templates = $db_templates
        ? TemplateHelper::getTemplates([
            'output' => [],
            'templateids' => array_keys($db_templates),
            'editable' => true,
            'preservekeys' => true,
        ])
        : [];

        $data['templates'][0] = [];

        foreach ($data['templates'] as $hostid => &$template) {
            $template = array_key_exists($hostid, $db_templates)
            ? [
                'hostid' => $hostid,
                'name' => $db_templates[$hostid]['name'],
                'permission' => array_key_exists($hostid, $rw_templates) ? PERM_READ_WRITE : PERM_READ,
            ]
            : [
                'hostid' => $hostid,
                'name' => t('zapi', 'Inaccessible template'),
                'permission' => PERM_DENY,
            ];
        }
        unset($template);

        return $data;
    }
}
