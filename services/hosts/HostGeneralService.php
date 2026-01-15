<?php

namespace app\customs\zapi\services\hosts;

use app\common\components\Result;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\AuditHelper;
use app\customs\zapi\common\helpers\MacroHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\common\managers\DiscoveryRuleManager;
use app\customs\zapi\common\managers\GraphManager;
use app\customs\zapi\common\managers\GraphPrototypeManager;
use app\customs\zapi\common\managers\HttpTestManager;
use app\customs\zapi\common\managers\TriggerManager;
use app\customs\zapi\common\managers\TriggerPrototypeManager;
use app\customs\zapi\services\assist\BaseTriggerAssist;
use app\customs\zapi\services\assist\DiscoverRuleAssist;
use app\customs\zapi\services\assist\ItemAssist;
use app\customs\zapi\services\assist\ItemPrototypeAssist;
use app\customs\zapi\services\assist\TriggerAssist;
use app\customs\zapi\services\assist\TriggerPrototypeAssist;
use app\customs\zapi\services\HostGroupService;
use app\customs\zapi\services\HostMacroService;
use app\customs\zapi\services\HostPrototypeService;
use app\customs\zapi\services\HttpTestService;
use app\customs\zapi\services\TemplateService;
use app\modules\libzbx\models\zbx\Actions;
use app\modules\libzbx\models\zbx\Conditions;
use app\modules\libzbx\models\zbx\Functions;
use app\modules\libzbx\models\zbx\Graphs;
use app\modules\libzbx\models\zbx\GraphsItems;
use app\modules\libzbx\models\zbx\Hostmacro;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\HostsGroups;
use app\modules\libzbx\models\zbx\HostsTemplates;
use app\modules\libzbx\models\zbx\Httptest;
use app\modules\libzbx\models\zbx\Items;
use app\modules\libzbx\models\zbx\OpcommandHst;
use app\modules\libzbx\models\zbx\Operations;
use app\modules\libzbx\models\zbx\Optemplate;
use app\modules\libzbx\models\zbx\SysmapsElements;
use app\modules\libzbx\models\zbx\Triggers;
use app\modules\libzbx\models\zbx\Valuemap;
use app\modules\libzbx\models\zbx\ValuemapMapping;
use yii\db\Expression;
use yii\db\Query;

abstract class HostGeneralService  extends BaseHostService
{
    /**
     * Update table "hosts_groups" and populate hosts.groups by "hostgroupid" property.
     *
     * @param array      $hosts
     * @param array|null $dbHosts
     */
    protected function updateGroups(array &$hosts, array $dbHosts = null): void
    {
        $idFieldName = $this instanceof TemplateService ? 'templateid' : 'hostid';

        $inserts = [];
        $deletes = [];

        foreach ($hosts as &$host) {
            if (!array_key_exists('groups', $host)) {
                continue;
            }

            $hostId = $host[$idFieldName];
            $news = [];

            $dbGroups = ($dbHosts !== null)
                ? array_column($dbHosts[$hostId]['groups'], null, 'groupid')
                : [];

            foreach ($host['groups'] as &$group) {
                if (array_key_exists($group['groupid'], $dbGroups)) {
                    $group['hostgroupid'] = $dbGroups[$group['groupid']]['hostgroupid'];
                    unset($dbGroups[$group['groupid']]);
                } else {
                    $inserts[] = [
                        'hostid' => $hostId,
                        'groupid' => $group['groupid']
                    ];
                    $news[] = $group['groupid'];
                }
            }
            unset($group);

            
            $exists = array_column($dbGroups, 'hostgroupid');
            $deletes = array_merge($deletes, $exists);
            if ($news || $exists) {
                AuditHelper::collectDetail($hostId, 'groups', $news, $exists);
            }
        }
        unset($host);

        if ($deletes) {
            HostsGroups::deleteAll(['hostgroupid' => $deletes]);
        }

        if ($inserts) {
            $hostgroupids = DB::insertBatch(HostsGroups::tableName(), $inserts);
        }

        foreach ($hosts as &$host) {
            if (!array_key_exists('groups', $host)) {
                continue;
            }

            foreach ($host['groups'] as &$group) {
                if (!array_key_exists('hostgroupid', $group)) {
                    $group['hostgroupid'] = array_shift($hostgroupids);
                }
            }
            unset($group);
        }
        unset($host);
    }



    /**
     * Update table "hosts_templates" and change objects of linked or unliked templates on target hosts or templates.
     *
     * @param array      $hosts
     * @param array|null $dbHosts
     * @param array|null $updateHostIds
     */
    protected function updateTemplates(array &$hosts, array &$dbHosts = null, array &$updateHostIds = null): void
    {
        $idFieldName = $this instanceof TemplateService ? 'templateid' : 'hostid';

        parent::updateTemplates($hosts, $dbHosts, $updateHostIds);

        $ins_links = [];
        $del_links = [];
        $del_links_clear = [];

        foreach ($hosts as $host) {
            if (!array_key_exists('templates', $host) && !array_key_exists('templates_clear', $host)) {
                continue;
            }

            $dbTemplates = ($dbHosts !== null)
                ? array_column($dbHosts[$host[$idFieldName]]['templates'], null, 'templateid')
                : [];

            if (array_key_exists('templates', $host)) {
                foreach ($host['templates'] as $template) {
                    if (array_key_exists($template['templateid'], $dbTemplates)) {
                        unset($dbTemplates[$template['templateid']]);
                    } else {
                        $ins_links[$template['templateid']][] = $host[$idFieldName];
                    }
                }

                $templates_clear = array_key_exists('templates_clear', $host)
                    ? array_column($host['templates_clear'], null, 'templateid')
                    : [];

                foreach ($dbTemplates as $del_template) {
                    if (array_key_exists($del_template['templateid'], $templates_clear)) {
                        $del_links_clear[$del_template['templateid']][] = $host[$idFieldName];
                    } else {
                        $del_links[$del_template['templateid']][] = $host[$idFieldName];
                    }
                }
            } elseif (array_key_exists('templates_clear', $host)) {
                foreach ($host['templates_clear'] as $template) {
                    $del_links_clear[$template['templateid']][] = $host[$idFieldName];
                }
            }
        }

        while ($del_links_clear) {
            $templateid = key($del_links_clear);
            $hostids = reset($del_links_clear);
            $templateids = [$templateid];
            unset($del_links_clear[$templateid]);

            foreach ($del_links_clear as $templateid => $_hostids) {
                if ($_hostids === $hostids) {
                    $templateids[] = $templateid;
                    unset($del_links_clear[$templateid]);
                }
            }

            self::unlinkTemplatesObjects($templateids, $hostids, true);
        }

        while ($del_links) {
            $templateid = key($del_links);
            $hostids = reset($del_links);
            $templateids = [$templateid];
            unset($del_links[$templateid]);

            foreach ($del_links as $templateid => $_hostids) {
                if ($_hostids === $hostids) {
                    $templateids[] = $templateid;
                    unset($del_links[$templateid]);
                }
            }

            self::unlinkTemplatesObjects($templateids, $hostids);
        }

        while ($ins_links) {
            $templateid = key($ins_links);
            $hostids = reset($ins_links);
            $templateids = [$templateid];
            unset($ins_links[$templateid]);

            foreach ($ins_links as $templateid => $_hostids) {
                if ($_hostids === $hostids) {
                    $templateids[] = $templateid;
                    unset($ins_links[$templateid]);
                }
            }

            self::linkTemplatesObjects($templateids, $hostids);
        }
    }

    /**
     * Unlink or clear objects of given templates from given hosts or templates.
     *
     * @param array      $templateIds
     * @param array|null $hostIds
     * @param bool       $clear
     */
    protected static function unlinkTemplatesObjects(array $templateIds, array $hostIds = null, bool $clear = false): void
    {
        // triggers
        $query = new Query();
        $query->select(new Expression('DISTINCT f.triggerid'))
            ->from([
                'f' => Functions::tableName(),
                'i' => Items::tableName()
            ]);
        $query->where('f.itemid=i.itemid')
            ->andWhere(SqlHelper::whereIn('{{i}}.hostid', $templateIds));
        $tplTriggerIds = $query->column();


        self::clearTemplatesTriggers($templateIds, $hostIds, $clear, $tplTriggerIds);
    }

    /**
     * Add objects of given templates to given hosts or templates.
     *
     * @param array $templateids
     * @param array $hostids
     */
    private static function linkTemplatesObjects(array $templateids, array $hostids): void
    {
        // TODO: Modify parameters of syncTemplates methods when complete audit log will be implementing for hosts.
        $link_request = [
            'templateids' => $templateids,
            'hostids' => $hostids
        ];

        foreach ($templateids as $templateid) {
            // Fist link web items, so that later regular items can use web item as their master item.
            HttpTestManager::instance()->link($templateid, $hostids);
        }

        ItemAssist::linkTemplateObjects($templateids, $hostids);
        $ruleIds = DiscoverRuleAssist::instance()->syncTemplates($templateids, $hostids);

        if ($ruleIds) {
            ItemPrototypeAssist::linkTemplateObjects($templateids, $hostids);
            HostPrototypeService::instance()->linkTemplateObjects($ruleIds, $hostids);
        }

        TriggerAssist::instance()->syncTemplates($link_request);

        if ($ruleIds) {
            TriggerPrototypeAssist::instance()->syncTemplates($link_request);
            GraphPrototypeManager::instance()->syncTemplates($link_request);
        }

        GraphManager::instance()->syncTemplates($link_request);

        BaseTriggerAssist::syncTemplateDependencies($link_request['templateids'], $link_request['hostids']);
    }

    /**
     * Add the existing host or template groups, templates, tags, macros.
     *
     * @param array $hosts
     * @param array $dbHosts
     */
    protected function addAffectedObjects(array $hosts, array &$dbHosts): void
    {
        $this->addAffectedGroups($hosts, $dbHosts);
        parent::addAffectedObjects($hosts, $dbHosts);
    }

    /**
     * @param array $hosts
     * @param array $db_hosts
     */
    protected function addAffectedGroups(array $hosts, array &$dbHosts): void
    {
        $primaryKey = $this instanceof TemplateService ? 'templateid' : 'hostid';

        $hostIds = [];

        foreach ($hosts as $host) {
            if (array_key_exists('groups', $host)) {
                $hostIds[] = $host[$primaryKey];
                $dbHosts[$host[$primaryKey]]['groups'] = [];
            }
        }

        if (!$hostIds) {
            return;
        }

        $groups = HostGroupService::instance()->getGroups([
            'isPage' => 0,
            'preserveKey' => true,
            'type' => $this instanceof TemplateService ? HOST_GROUP_TYPE_TEMPLATE_GROUP : HOST_GROUP_TYPE_HOST_GROUP,
            'hostids' => $hostIds,
        ]);


        $query = HostsGroups::find()
            ->select(['hostgroupid', 'hostid', 'groupid'])
            ->asArray()
            ->where(SqlHelper::whereIn('hostid', $hostIds))
            ->andWhere(SqlHelper::whereIn('groupid', array_keys($groups)));
        foreach ($query->each() as $group) {
            $dbHosts[$group['hostid']]['groups'][$group['hostgroupid']] =
                array_diff_key($group, ['hostid' => 1]);
        }
    }

    /**
     * Add the existing groups, macros or templates whether these are affected by the mass methods.
     *
     * @param string     $objects
     * @param array      $objectIds
     * @param array      $dbHosts
     */
    protected function massAddAffectedObjects(string $objects, array $objectIds, array &$dbHosts): void
    {
        $primaryKey = $this instanceof TemplateService ? 'templateid' : 'hostid';

        foreach ($dbHosts as &$dbHost) {
            $dbHost[$objects] = [];
        }
        unset($dbHost);
        $hostIds = array_keys($dbHosts);

        if ($objects === 'groups') {
            if ($objectIds) {
                $groupIds = $objectIds;
            } else {
                $dbGroups = HostGroupService::instance()->getGroups([
                    'isPage' => 0,
                    'preserveKey' => true,
                    'type' => $this instanceof TemplateService ? HOST_GROUP_TYPE_TEMPLATE_GROUP : HOST_GROUP_TYPE_HOST_GROUP,
                    'hostids' => $hostIds,
                ]);
                $groupIds = array_keys($dbGroups);
            }

            $query = HostsGroups::find()
                ->select(['hostgroupid', 'hostid', 'groupid'])
                ->asArray()
                ->where(SqlHelper::whereIn('hostid', $hostIds))
                ->andWhere(SqlHelper::whereIn('groupid', $groupIds));
            foreach ($query->each() as $link) {
                $dbHosts[$link['hostid']]['groups'][$link['hostgroupid']] = array_diff_key($link, ['hostid' => 1]);
            }
        }

        if ($objects === 'macros') {
            $patternMacros = [];
            $trimmedMacros = [];
            if ($objectIds) {
                foreach ($objectIds as $macro) {
                    $trimmedMacro = MacroHelper::trimMacro($macro);
                    $pos = strpos($trimmedMacro, ':');
                    $patternMacros[] = [DB_LIKE, 'macro', (($pos === false) ? '{$' . $trimmedMacro : '{$' . substr($trimmedMacro, 0, $pos)) . '%', false];
                    $trimmedMacros[] = $trimmedMacro;
                }
            }

            $query = Hostmacro::find()
                ->select(['hostmacroid', 'hostid', 'macro', 'value', 'description', 'type'])
                ->asArray()
                ->where(SqlHelper::whereIn('hostid', $hostIds));
            if (count($patternMacros) == 1) {
                $query->andWhere(current($patternMacros));
            } elseif (1 < count($patternMacros)) {
                array_unshift($patternMacros, 'OR');
                $query->andWhere($patternMacros);
            }

            foreach ($query->each() as $hostMacro) {
                if (!$objectIds || in_array(MacroHelper::trimMacro($macro), $trimmedMacros)) {
                    $dbHosts[$hostMacro['hostid']]['macros'][$hostMacro['hostmacroid']] = array_diff_key($hostMacro, ['hostid' => 1]);
                }
            }
        }

        if ($objects === 'templates') {
            $permittedTemplates = $objectIds ? [] : TemplateHelper::getTemplates([
                'output' => [],
                'hostids' => $hostIds,
                'preserveKey' => true,
            ]);

            $query = HostsTemplates::find()
                ->select(['hosttemplateid', 'hostid', 'templateid'])
                ->where(SqlHelper::whereIn('hostid', $hostIds));
            $query->asArray();

            foreach ($query->each() as $link) {
                if ($objectIds) {
                    if (in_array($link['templateid'], $objectIds)) {
                        $dbHosts[$link['hostid']]['templates'][$link['hosttemplateid']] = array_diff_key($link, ['hostid' => 1]);
                    } else {
                        $dbHosts[$link['hostid']]['nopermissions_templates'][$link['hosttemplateid']] = array_diff_key($link, ['hostid' => 1]);
                    }
                } else {
                    if (array_key_exists($link['templateid'], $permittedTemplates)) {
                        $dbHosts[$link['hostid']]['templates'][$link['hosttemplateid']] = array_diff_key($link, ['hostid' => 1]);
                    } else {
                        $dbHosts[$link['hostid']]['nopermissions_templates'][$link['hosttemplateid']] = array_diff_key($link, ['hostid' => 1]);
                    }
                }
            }
        }
    }


    /**
     * Get templates or hosts input array based on requested data and database data.
     *
     * @param array $data
     * @param array $db_objects
     *
     * @return array
     */
    protected function getObjectsByData(array $data, array $db_objects): array
    {
        $primaryKey = $this instanceof TemplateService ? 'templateid' : 'hostid';

        $objects = [];

        foreach ($db_objects as $db_object) {
            $object = [$primaryKey => $db_object[$primaryKey]];

            if (array_key_exists('groups', $db_object)) {
                $object['groups'] = [];

                if (array_key_exists('groups', $data)) {
                    foreach ($data['groups'] as $group) {
                        $object['groups'][] = ['groupid' => $group['groupid']];
                    }
                }
            }

            if (array_key_exists('macros', $db_object)) {
                $object['macros'] = [];

                if (array_key_exists('macros', $data) && is_array(reset($data['macros']))) {
                    $db_macros = [];

                    foreach ($db_object['macros'] as $db_macro) {
                        $db_macros[MacroHelper::trimMacro($db_macro['macro'])] = $db_macro;
                    }

                    foreach ($data['macros'] as $macro) {
                        $trimmed_macro = MacroHelper::trimMacro($macro['macro']);

                        if (array_key_exists($trimmed_macro, $db_macros)) {
                            $object['macros'][] = ['hostmacroid' => $db_macros[$trimmed_macro]['hostmacroid']] + $macro
                                + ['description' => DB::getDefault('hostmacro', 'description')];
                        } else {
                            $object['macros'][] = $macro;
                        }
                    }
                }
            }

            if (array_key_exists('templates', $db_object)) {
                $templates = $this instanceof TemplateService ? 'templates_link' : 'templates';
                $templateids = $this instanceof TemplateService ? 'templateids_link' : 'templateids';

                if (array_key_exists($templates, $data) || array_key_exists($templateids, $data)) {
                    $object['templates'] = [];

                    if (array_key_exists($templates, $data)) {
                        foreach ($data[$templates] as $template) {
                            $object['templates'][] = ['templateid' => $template['templateid']];
                        }
                    }
                }

                if (array_key_exists('templates_clear', $data) || array_key_exists('templateids_clear', $data)) {
                    $object['templates_clear'] = [];
                    $db_templateids = array_column($db_object['templates'], 'templateid');

                    if (array_key_exists('templates_clear', $data)) {
                        foreach ($data['templates_clear'] as $template) {
                            if (in_array($template['templateid'], $db_templateids)) {
                                $object['templates_clear'][] = ['templateid' => $template['templateid']];
                            }
                        }
                    } else {
                        foreach ($data['templateids_clear'] as $templateid) {
                            if (in_array($templateid, $db_templateids)) {
                                $object['templates_clear'][] = ['templateid' => $templateid];
                            }
                        }
                    }
                }
            }

            $objects[] = $object;
        }

        return $objects;
    }

    /**
     * 删除指定主机/模板的发现规则
     *
     * @param string $hostIdWhereIn
     * @return void
     */
    protected static function deleteDiscoveryRules($hostIdWhereIn)
    {
        $query = Items::find()->select(['itemid'])
            ->where(['flags' => PRS_FLAG_DISCOVERY_RULE])
            ->andWhere($hostIdWhereIn)
            ->asArray();
        if ($ruleIds = $query->column()) {
            DiscoveryRuleManager::delete($ruleIds);
        }
    }

    /**
     * 删除指定主机/模板的常规指标
     *
     * @param string $hostIdWhereIn
     * @return void
     */
    protected static function deletePlainItems($hostIdWhereIn)
    {
        // 删除常规指标
        $query = Items::find()
            ->where($hostIdWhereIn)
            ->andWhere(['flags' => PRS_FLAG_DISCOVERY_NORMAL])
            ->andWhere(['type' => ItemAssist::SUPPORTED_ITEM_TYPES]);
        $query->select(['itemid', 'name'])->indexBy('itemid')->asArray();
        if ($dbItems = $query->all()) {
            ItemAssist::deleteItems($dbItems);
        }
    }

    /**
     * 删除指定主机/模板关联的拨测
     *
     * @param string $hostIdWhereIn
     * @return void
     */
    protected static function deleteHttpTest($hostIdWhereIn)
    {
        $query = Httptest::find()
            ->where($hostIdWhereIn);
        $query->select(['name'])->indexBy('httptestid');
        if ($id2nameItem = $query->column()) {
            HttpTestService::deleteForce($id2nameItem);
        }
    }

    /**
     * delete host from maps
     *
     * @param array $hostIds
     * @return int
     */
    protected static function deleteMapsByHostIds(array $hostIds)
    {
        return SysmapsElements::deleteAll([
            'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
            'elementid' => $hostIds
        ]);
    }

    /**
     * 禁用指定主机/模板的的动作
     *
     * @param int[] $hostIds
     * @param string $hostIdWhereIn
     * @return void
     */
    protected function disableActionsWithClearConditions(array $hostIds, $hostIdWhereIn)
    {
        $isTemplate = $this instanceof TemplateService;
        $conditionType = $isTemplate ? PRS_CONDITION_TYPE_TEMPLATE : PRS_CONDITION_TYPE_HOST;
        $hostIdWhereIn = $hostIdWhereIn ?: SqlHelper::whereIn('hostid', $hostIds);

        $query = Conditions::find();
        $query->select(new Expression('DISTINCT actionid'));
        $query->where(['conditiontype' => $conditionType])
            ->andWhere(SqlHelper::stringWhereIn('value', array_map(function ($id) {return (string) $id;}, $hostIds)));
        $query->indexBy('actionid')
            ->asArray();
        // actions from conditions
        $actionIds = $query->column();

        // action from operations
        $query = new Query();
        $query->from([
            'o' => Operations::tableName(),
            'oh' => $isTemplate ? Optemplate::tableName() : OpcommandHst::tableName()
        ]);
        $query->where('o.operationid=oh.operationid');
        if ($isTemplate) {
            $query->andWhere(str_replace('hostid', '{{oh}}.templateid', $hostIdWhereIn));
        } else {
            $query->andWhere(str_replace('hostid', '{{oh}}.hostid', $hostIdWhereIn));
        }

        $query->select(new Expression('DISTINCT o.actionid'))
            ->indexBy('actionid');
        $actionIds += $query->column();

        if (!empty($actionIds)) {
            Actions::updateAll(['status' => ACTION_STATUS_DISABLED], ['actionid' => $actionIds]);
        }

        // 删除动作条件
        Conditions::deleteAll([
            'conditiontype' => $conditionType,
            'value' => $hostIds
        ]);

        if ($isTemplate) {
            $query = Optemplate::find()
                ->select(new Expression('DISTINCT operationid'));
            $query->where(str_replace('hostid', 'templateid', $hostIdWhereIn));
            $query->indexBy('operationid')->asArray();
            $operationIds = $query->column();

            Optemplate::deleteAll(str_replace('hostid', 'templateid', $hostIdWhereIn));

            $subQuery =  Optemplate::find()
                ->alias('ot')
                ->select(new Expression('NULL'))
                ->where('ot.operationid=o.operationid');
        } else {
            $query = OpcommandHst::find()
                ->select(new Expression('DISTINCT operationid'));
            $query->where($hostIdWhereIn);
            $query->indexBy('operationid')->asArray();
            $operationIds = $query->column();

            // 删除动作操作命令
            OpcommandHst::deleteAll($hostIdWhereIn);

            $subQuery = OpcommandHst::find()
                ->alias('oh')
                ->select(new Expression('NULL'))
                ->where('oh.operationid=o.operationid');
        }

        $query = Operations::find()
            ->alias('o');
        $query->where(SqlHelper::whereIn('o.operationid', $operationIds));
        $query->andWhere(['NOT EXISTS', $subQuery]);
        $query->select(new Expression('DISTINCT o.operationid'));
        $operationIds = $query->indexBy('operationid')->column();
        Operations::deleteAll(['operationid' => $operationIds]);
    }

    /**
     * @param string $hostIdWhereIn
     * @return array
     * 返回示例e.g.
     * ```php
     * return [
     *     'hostid1' => [
     *         [
     *             'uuid' => 'uuid 1',
     *             'name' => 'name 1',
     *             'mappings' => [
     *                 [
     *                     'value' => 1,
     *                     'newvalue' => 'on'
     *                 ]
     *             ]
     *         ],
     *         [
     *             'uuid' => 'uuid 2',
     *             'name' => 'name 2',
     *             'mappings' => [
     *                 [
     *                     'value' => 1,
     *                     'newvalue' => 'up'
     *                 ]
     *             ]
     *         ]
     *     ],sss
     *     'hostid2' => []
     * ];
     * 
     * ```
     */
    protected static function gatherValueMappingGroupByHostId($hostIdWhereIn, $withPrimary = false)
    {
        $query = new Query();
        $query->from([
            'v' => Valuemap::tableName(),
            'vm' => ValuemapMapping::tableName()
        ]);

        $query->where('v.valuemapid=vm.valuemapid')
            ->andWhere(str_replace('hostid', '{{v}}.hostid', $hostIdWhereIn));

        $query->select(['v.valuemapid', 'v.hostid', 'v.name', 'v.uuid', 'vm.value', 'vm.newvalue', 'vm.type']);

        $rows = $query->all();

        $data = [];
        foreach ($rows as $row) {
            if (!array_key_exists($row['hostid'], $data)) {
                $data[$row['hostid']] = [];
            }

            if (!array_key_exists($row['valuemapid'], $data[$row['hostid']])) {
                $data[$row['hostid']][$row['valuemapid']] = [
                    'uuid' => $row['uuid'],
                    'name' => $row['name'],
                    'mappings' => []
                ];
                $withPrimary && $data[$row['hostid']][$row['valuemapid']]['valuemapid'] = $row['valuemapid'];
            }

            $data[$row['hostid']][$row['valuemapid']]['mappings'][] = [
                'value' => $row['value'],
                'newvalue' => $row['newvalue'],
                'type' => $row['type'],
            ];
        }
        return $data;
    }

    /**
     * @param string $hostIdWhereIn
     * @return array
     */
    protected static function gatherHostMacrosGroupByHostId($hostIdWhereIn)
    {
        $rows = Hostmacro::find()
            ->select(['macro', 'value', 'description', 'hostid'])
            ->where($hostIdWhereIn)->asArray()->all();

        $data = [];
        foreach ($rows as $row) {
            $data[$row['hostid']][] = array_filter(array_diff_key($row, ['hostid' => 1]));
        }
        return $data;
    }

    /**
     * Allows to:
     * - add hosts to groups;
     * - link templates to hosts;
     * - add new macros to hosts.
     *
     * Supported $data parameters are:
     * - hosts          - an array of hosts to be updated
     * - templates      - an array of templates to be updated
     * - groups         - an array of host groups to add the host to
     * - templates_link - an array of templates to link to the hosts
     * - macros         - an array of macros to create on the host
     *
     * @param array $data
     *
     * @return Result
     */
    public function massAdd(array $data): Result
    {
        $hostIds = array_column($data['hosts'], 'hostid');
        $templateIds = array_column($data['templates'], 'templateid');

        $allHostIds = array_merge($hostIds, $templateIds);

        // add groups
        if (array_key_exists('groups', $data) && $data['groups']) {
            $options = ['groups' => $data['groups']];

            if ($data['hosts']) {
                $options += [
                    'hosts' => array_map(
                        static function ($host) {
                            return array_intersect_key($host, array_flip(['hostid']));
                        },
                        $data['hosts']
                    )
                ];
            }

            if ($data['templates']) {
                $options += [
                    'templates' => array_map(
                        static function ($template) {
                            return array_intersect_key($template, array_flip(['templateid']));
                        },
                        $data['templates']
                    )
                ];
            }

            HostGroupService::instance()->massAdd($options);
        }

        // link templates
        if (!empty($data['templates_link'])) {
            $this->link(array_column(prs_toArray($data['templates_link']), 'templateid'), $allHostIds);
        }

        // create macros
        if (!empty($data['macros'])) {
            $data['macros'] = prs_toArray($data['macros']);

            $hostMacrosToAdd = [];
            foreach ($data['macros'] as $hostMacro) {
                foreach ($allHostIds as $hostid) {
                    $hostMacro['hostid'] = $hostid;
                    $hostMacrosToAdd[] = $hostMacro;
                }
            }

            HostMacroService::instance()->create($hostMacrosToAdd);
        }

        $data = $this instanceof TemplateService ? ['templateids' => $templateIds] : ['hostids' => $hostIds];

        return $this->success([$data]);
    }

    /**
     * Allows to:
     * - remove hosts from groups;
     * - unlink and clear templates from hosts;
     * - remove macros from hosts.
     *
     * Supported $data parameters are:
     * - hostids            - an array of host IDs to be updated
     * - templateids        - an array of template IDs to be updated
     * - groupids           - an array of host group IDs the hosts should be removed from
     * - templateids_link   - an array of template IDs to unlink from the hosts
     * - templateids_clear  - an array of template IDs to unlink and clear from the hosts
     * - macros             - an array of macros to delete from the hosts
     *
     * @param array $data
     *
     * @return Result
     */
    public function massRemove(array $data): Result
    {
        $allHostIds = array_merge($data['hostids'], $data['templateids']);

        if (!empty($data['templateids_link'])) {
            $this->unlink(prs_toArray($data['templateids_link']), $allHostIds);
        }

        if (isset($data['templateids_clear'])) {
            $this->unlink(prs_toArray($data['templateids_clear']), $allHostIds, true);
        }

        if (array_key_exists('macros', $data)) {
            if (!$data['macros']) {
                return $this->error(60750101, t('zapi', 'Empty input parameter.'));
            }

            $query = Hostmacro::find()
                ->select(['hostmacroid'])
                ->where(SqlHelper::whereIn('hostid', $allHostIds))
                ->andWhere(['macro' => $data['macros']])
                ->asArray();
            $hostMacroIds = $query->column();

            if ($hostMacroIds) {
                HostMacroService::instance()->delete($hostMacroIds);
            }
        }

        if (isset($data['groupids'])) {
            $options = ['groupids' => $data['groupids']];

            if ($data['hostids']) {
                $options['hostids'] = $data['hostids'];
            }

            if ($data['templateids']) {
                $options['templateids'] = $data['templateids'];
            }

            HostGroupService::instance()->massRemove($options);
        }

        $r = $this instanceof TemplateService ? ['templateids' => $data['templateids']] : ['hostids' => $data['hostids']];

        return $this->success($r);
    }

    protected function link(array $templateIds, array $targetIds)
    {
        $hosts_linkage_inserts = parent::link($templateIds, $targetIds);
        $templates_hostids = [];
        $link_requests = [];

        foreach ($hosts_linkage_inserts as $host_tpl_ids) {
            $templates_hostids[$host_tpl_ids['templateid']][] = $host_tpl_ids['hostid'];
        }

        foreach ($templates_hostids as $templateid => $hostids) {
            // Fist link web items, so that later regular items can use web item as their master item.
            HttpTestManager::instance()->link($templateid, $hostids);
        }

        while ($templates_hostids) {
            $templateid = key($templates_hostids);
            $link_request = [
                'hostids' => reset($templates_hostids),
                'templateids' => [$templateid]
            ];
            unset($templates_hostids[$templateid]);

            foreach ($templates_hostids as $templateid => $hostids) {
                if ($link_request['hostids'] === $hostids) {
                    $link_request['templateids'][] = $templateid;
                    unset($templates_hostids[$templateid]);
                }
            }

            $link_requests[] = $link_request;
        }

        foreach ($link_requests as $link_request) {
            ItemAssist::linkTemplateObjects($link_request['templateids'], $link_request['hostids']);
            $ruleIds = DiscoverRuleAssist::instance()->syncTemplates($link_request['templateids'], $link_request['hostids']);
            if ($ruleIds) {
                ItemPrototypeAssist::linkTemplateObjects($link_request['templateids'], $link_request['hostids']);
                HostPrototypeService::instance()->linkTemplateObjects($ruleIds, $link_request['hostids']);
            }
        }

        // we do linkage in two separate loops because for triggers you need all items already created on host
        foreach ($link_requests as $link_request) {
            TriggerAssist::instance()->syncTemplates($link_request);
            TriggerPrototypeAssist::instance()->syncTemplates($link_request);
        //    GraphPrototypeManager::instance()->syncTemplates($link_request);
        //    GraphManager::instance()->syncTemplates($link_request);
        }

        foreach ($link_requests as $link_request) {
            BaseTriggerAssist::syncTemplateDependencies($link_request['templateids'], $link_request['hostids']);
        }

        return $hosts_linkage_inserts;
    }

    /**
     * Unlinks the templates from the given hosts. If $targetids is set to null, the templates will be unlinked from
     * all hosts.
     *
     * @param array      $templateids
     * @param null|array $targetids		the IDs of the hosts to unlink the templates from
     * @param bool       $clear			delete all of the inherited objects from the hosts
     */
    protected function unlink($templateids, $targetids = null, $clear = false)
    {
        $templateIdWhereIn = SqlHelper::whereIn('{{i}}.hostid', $templateids);

        // check that all triggers on templates that we unlink, don't have items from another templates
        $query = new Query();
        $query->from([
            't' => Triggers::tableName(),
            'f' => Functions::tableName(),
            'i' => Items::tableName()
        ]);
        $query->where('t.triggerid=f.triggerid')
            ->andWhere('f.itemid=i.itemid')
            ->andWhere($templateIdWhereIn)
            ->andWhere(['t.flags' => PRS_FLAG_DISCOVERY_NORMAL]);

        $query->andWhere([
            'EXISTS',
            (new Query())->from([
                'ff' => Functions::tableName(),
                'ii' => Items::tableName()
            ])->where('ff.itemid=ii.itemid')
                ->andWhere('ff.triggerid=t.triggerid')
                ->andWhere(SqlHelper::whereIn('{{ii}}.hostid', $templateids, true))
        ]);

        $query->select([new Expression('DISTINCT t.description')]);

        if ($desc = $query->scalar()) {
            $msg = t('zapi', 'Cannot unlink trigger "{name}", it has items from template that is left linked to host.', ['name' => $desc]);
            self::exception(60750101, $msg);
        }


        $query = (new Query())
            ->from([
                'f' => Functions::tableName(),
                'i' => Items::tableName()
            ]);
        $query->where('f.itemid=i.itemid')
            ->andWhere($templateIdWhereIn);

        $query->select([new Expression('DISTINCT f.triggerid')]);

        $tplTriggerIds = $query->column();


        self::clearTemplatesTriggers($templateids, $targetids, $clear, $tplTriggerIds);

        parent::unlink($templateids, $targetids);
    }

    private static function clearTemplatesTriggers(array $templateIds, array $hostIds = null, bool $clear = false, array $tplTriggerIds = [])
    {
        $flags = ($clear)
            ? [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_RULE]
            : [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_RULE, PRS_FLAG_DISCOVERY_PROTOTYPE];

        $updateTriggers = [
            PRS_FLAG_DISCOVERY_NORMAL => [],
            PRS_FLAG_DISCOVERY_PROTOTYPE => []
        ];

        if ($tplTriggerIds) {
            $query = new Query();
            if ($hostIds) {
                $query->select([new Expression('DISTINCT t.triggerid'), 't.flags']);
                $query->from([
                    't' => Triggers::tableName(),
                    'f' => Functions::tableName(),
                    'i' => Items::tableName()
                ]);
                $query->where('t.triggerid=f.triggerid')
                    ->andWhere('f.itemid=i.itemid')
                    ->andWhere(SqlHelper::whereIn('{{i}}.hostid', $hostIds));
            } else {
                $query->select(['t.triggerid', 't.flags']);
                $query->from(['t' => Triggers::tableName()]);
            }

            $query->andWhere(SqlHelper::whereIn('{{t}}.templateid', $tplTriggerIds))
                ->andWhere(['t.flags' => $flags]);

            if ($triggers = $query->all()) {
                foreach ($triggers as $trigger) {
                    if ($clear) {
                        $updateTriggers[$trigger['flags']][$trigger['triggerid']] = true;
                    } else {
                        $updateTriggers[$trigger['flags']][$trigger['triggerid']] = [
                            'values' => ['templateid' => 0],
                            'where' => ['triggerid' => $trigger['triggerid']]
                        ];
                    }
                }
            }

            if (!$clear && ($updateTriggers[PRS_FLAG_DISCOVERY_NORMAL] || $updateTriggers[PRS_FLAG_DISCOVERY_PROTOTYPE])) {
                $query = new Query();
                $query->from([
                    't' => Triggers::tableName(),
                    'f' => Functions::tableName(),
                    'i' => Items::tableName(),
                    'h' => Hosts::tableName()
                ]);
                $query->where('t.triggerid=f.triggerid')
                    ->andWhere('f.itemid=i.itemid')
                    ->andWhere('i.hostid=h.hostid')
                    ->andWhere(['h.status' => HOST_STATUS_TEMPLATE])
                    ->andWhere(SqlHelper::whereIn('{{t}}.triggerid', array_keys($updateTriggers[PRS_FLAG_DISCOVERY_NORMAL] + $updateTriggers[PRS_FLAG_DISCOVERY_PROTOTYPE])));
                $query->select([new Expression('DISTINCT t.triggerid'), 't.flags']);
                if ($triggers = $query->all()) {
                    foreach ($triggers as $trigger) {
                        $updateTriggers[$trigger['flags']][$trigger['triggerid']]['values']['uuid'] = generateUuidV4();
                    }
                }
            }
        }

        if ($updateTriggers[PRS_FLAG_DISCOVERY_NORMAL]) {
            if ($clear) {
                TriggerManager::delete(array_keys($updateTriggers[PRS_FLAG_DISCOVERY_NORMAL]));
            } else {
                DB::update(Triggers::tableName(), $updateTriggers[PRS_FLAG_DISCOVERY_NORMAL]);
            }
        }

        if ($updateTriggers[PRS_FLAG_DISCOVERY_PROTOTYPE]) {
            if ($clear) {
                TriggerPrototypeManager::delete(array_keys($updateTriggers[PRS_FLAG_DISCOVERY_PROTOTYPE]));
            } else {
                DB::update(Triggers::tableName(), $updateTriggers[PRS_FLAG_DISCOVERY_PROTOTYPE]);
            }
        }

        // graphs
        $query = new Query();
        $query->from([
            'g' => Graphs::tableName(),
            'gi' => GraphsItems::tableName(),
            'i' => Items::tableName(),
        ]);
        $query->where('g.graphid=gi.graphid')
            ->andWhere('gi.itemid=i.itemid')
            ->andWhere(SqlHelper::whereIn('{{i}}.hostid', $templateIds))
            ->andWhere(['g.flags' => $flags]);
        $query->select([new Expression('DISTINCT g.graphid')]);
        if ($tplGraphIds = $query->column()) {
            $updateGraphs = [
                PRS_FLAG_DISCOVERY_NORMAL => [],
                PRS_FLAG_DISCOVERY_PROTOTYPE => []
            ];

            $query = new Query();
            if ($hostIds) {
                $query->select([new Expression('DISTINCT g.graphid'), 'g.flags']);
                $query->from([
                    'g' => Graphs::tableName(),
                    'gi' => GraphsItems::tableName(),
                    'i' => Items::tableName(),
                ]);
                $query->where('g.graphid=gi.graphid')
                    ->andWhere('gi.itemid=i.itemid')
                    ->andWhere(SqlHelper::whereIn('{{i}}.hostid', $hostIds));
            } else {
                $query->select(['g.graphid', 'g.flags']);
                $query->from(['g' => Graphs::tableName()]);
            }

            $query->andWhere(SqlHelper::whereIn('{{g}}.templateid', $tplGraphIds));

            $graphs = $query->all();
            foreach ($graphs as $graph) {
                if ($clear) {
                    $updateGraphs[$graph['flags']][$graph['graphid']] = true;
                } else {
                    $updateGraphs[$graph['flags']][$graph['graphid']] = [
                        'values' => ['templateid' => 0],
                        'where' => ['graphid' => $graph['graphid']]
                    ];
                }
            }

            if (!$clear && ($updateGraphs[PRS_FLAG_DISCOVERY_NORMAL] || $updateGraphs[PRS_FLAG_DISCOVERY_PROTOTYPE])) {
                $query = new Query();
                $query->from([
                    'g' => Graphs::tableName(),
                    'gi' => GraphsItems::tableName(),
                    'i' => Items::tableName(),
                    'h' => Hosts::tableName()
                ]);
                $query->where('g.graphid=gi.graphid')
                    ->andWhere('gi.itemid=i.itemid')
                    ->andWhere('i.hostid=h.hostid')
                    ->andWhere(['h.status' => HOST_STATUS_TEMPLATE])
                    ->andWhere(SqlHelper::whereIn('{{g}}.graphid', array_keys($updateGraphs[PRS_FLAG_DISCOVERY_NORMAL] + $updateGraphs[PRS_FLAG_DISCOVERY_PROTOTYPE])));
                $query->select([new Expression('DISTINCT g.graphid'), 'g.flags']);
                if ($triggers = $query->all()) {
                    foreach ($graphs as $graph) {
                        $updateGraphs[$graph['flags']][$graph['graphid']]['values']['uuid'] = generateUuidV4();
                    }
                }
            }

            if ($updateGraphs[PRS_FLAG_DISCOVERY_PROTOTYPE]) {
                if ($clear) {
                    GraphPrototypeManager::delete(array_keys($updateGraphs[PRS_FLAG_DISCOVERY_PROTOTYPE]));
                } else {
                    DB::update(Graphs::tableName(), $updateGraphs[PRS_FLAG_DISCOVERY_PROTOTYPE]);
                }
            }

            if ($updateGraphs[PRS_FLAG_DISCOVERY_NORMAL]) {
                if ($clear) {
                    GraphManager::delete(array_keys($updateGraphs[PRS_FLAG_DISCOVERY_NORMAL]));
                } else {
                    DB::update(Graphs::tableName(), $updateGraphs[PRS_FLAG_DISCOVERY_NORMAL]);
                }
            }
        }
        if ($clear) {
            DiscoverRuleAssist::clearTemplateObjects($templateIds, $hostIds);
            ItemAssist::clearTemplateObjects($templateIds, $hostIds);
            HttpTestService::clearTemplateObjects($templateIds, $hostIds);
        } else {
            DiscoverRuleAssist::unlinkTemplateObjects($templateIds, $hostIds);
            ItemAssist::unlinkTemplateObjects($templateIds, $hostIds);
            HttpTestService::unlinkTemplateObjects($templateIds, $hostIds);
        }
    }

    /**
     * Check templates links for given data of mass API methods.
     *
     * @param string $method
     * @param array  $templateids
     * @param array  $db_hosts
     */
    protected function massCheckTemplatesLinks(string $method, array $templateids, array $db_hosts,  array $templateids_clear = []): void
    {
        $ins_templates = [];
        $del_links = [];
        $check_double_linkage = false;
        $del_templates = [];
        $del_links_clear  = [];

        foreach ($db_hosts as $hostid => $db_host) {
            $db_templateids = array_column($db_host['templates'], 'templateid');

            if ($method === 'massadd') {
                $_templateids = array_diff($templateids, $db_templateids);
            } elseif ($method === 'massremove') {
                $_templateids = array_diff($db_templateids, $templateids);
            } else {
                $_templateids = $templateids;
            }

            $permitted_templateids = $_templateids;
            $templates_count = count($permitted_templateids);
            $upd_templateids = [];

            if (array_key_exists('nopermissions_templates', $db_host)) {
                foreach ($db_host['nopermissions_templates'] as $db_template) {
                    $_templateids[] = $db_template['templateid'];
                    $templates_count++;
                    $upd_templateids[] = $db_template['templateid'];
                }
            }

            foreach ($permitted_templateids as $templateid) {
                $index = array_search($templateid, $db_templateids);

                if ($index !== false) {
                    $upd_templateids[] = $templateid;
                    unset($db_templateids[$index]);
                } else {
                    $ins_templates[$templateid][$hostid] = $_templateids;

                    if ($this instanceof TemplateService || $templates_count > 1) {
                        $check_double_linkage = true;
                    }
                }
            }

            foreach ($db_templateids as $db_templateid) {
                $del_links[$db_templateid][$hostid] = true;

                if ($upd_templateids) {
                    $del_templates[$db_templateid][$hostid] = $upd_templateids;
                }

                if (array_key_exists($db_templateid, $templateids_clear)) {
                    $del_links_clear[$db_templateid][$hostid] = true;
                }
            }
        }

        if ($del_templates) {
            $this->checkTriggerExpressionsOfDelTemplates($del_templates);
        }

        if ($del_links_clear) {
            $this->checkTriggerDependenciesOfHostTriggers($del_links_clear);
        }

        if ($ins_templates) {
            if ($this instanceof TemplateService) {
                self::checkCircularLinkageNew($ins_templates, $del_links);
            }

            if ($check_double_linkage) {
                $this->checkDoubleLinkageNew($ins_templates, $del_links, null);
            }

            $this->checkTriggerDependenciesOfInsTemplates($ins_templates);
            $this->checkTriggerExpressionsOfInsTemplates($ins_templates);
        }
    }
}
