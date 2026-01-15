<?php

namespace app\customs\zapi\services\hosts;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\AuditHelper;
use app\customs\zapi\common\helpers\SettingHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\common\validators\VaultSecretValidator;
use app\customs\zapi\services\assist\BaseAssist;
use app\customs\zapi\services\HostService;
use app\customs\zapi\services\TemplateService;
use app\modules\libzbx\models\zbx\Functions;
use app\modules\libzbx\models\zbx\Hostmacro;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\HostsTemplates;
use app\modules\libzbx\models\zbx\HostTag;
use app\modules\libzbx\models\zbx\Items;
use app\modules\libzbx\models\zbx\TriggerDepends;
use app\modules\libzbx\models\zbx\Triggers;
use Yii;
use yii\base\Exception;
use yii\db\Expression;
use yii\db\Query;

abstract class BaseHostService extends BaseAssist
{
    /**
     * Check whether all templates of triggers, from which depends the triggers of linking templates, are linked to
     * target hosts or templates.
     *
     * @param array  $ins_templates
     * @param array  $ins_templates[<templateid>][<hostid>]  Array of template IDs to replace on target object.
     *
     * @throws APIException if not linked template is found.
     */
    protected function checkTriggerDependenciesOfInsTemplates(array $ins_templates): void
    {
        $query = new Query();
        $query->from([
            'i' => Items::tableName(),
            'f' => Functions::tableName(),
            'td' => TriggerDepends::tableName(),
            'ff' => Functions::tableName(),
            'ii' => Items::tableName(),
            'h' => Hosts::tableName(),
        ]);
        $query->where('i.itemid=f.itemid')
            ->andWhere('f.triggerid=td.triggerid_down')
            ->andWhere('td.triggerid_up=ff.triggerid')
            ->andWhere('ff.itemid=ii.itemid')
            ->andWhere('ii.hostid=h.hostid');

        $query->andWhere(SqlHelper::whereIn('i.hostid', array_keys($ins_templates)))
            ->andWhere(['h.status' => HOST_STATUS_TEMPLATE]);

        $query->select(['ins_templateid' => new Expression('DISTINCT i.hostid'), 'td.triggerid_down', 'ii.hostid']);

        foreach ($query->each() as $row) {
            foreach ($ins_templates[$row['ins_templateid']] as $hostid => $templateids) {
                if (bccomp($row['hostid'], $hostid) == 0 && $this instanceof TemplateService) {

                    [$objects, $triggerName] = self::getObjectsAndTriggerDescription([$row['ins_templateid'], $hostid], $row['triggerid_down']);

                    self::exception(60750101, t('zapi', 'Cannot link template "{src_name}" to template "{dst_name}" due to dependency of trigger "{trigger_name}".', [
                        'src_name' => $objects[$row['ins_templateid']]['host'],
                        'dst_name' => $objects[$hostid]['host'],
                        'trigger_name' => $triggerName,
                    ]));
                }

                if (!in_array($row['hostid'], $templateids)) {

                    [$objects, $triggerName] = self::getObjectsAndTriggerDescription([$row['ins_templateid'], $row['hostid'], $hostid], $row['triggerid_down']);

                    if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
                        $error = 'Cannot link template "{src_name}" without template "{dst_name}" to template "{target_name}" due to dependency of trigger "{trigger_name}".';
                    } elseif ($objects[$hostid]['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                        $error = 'Cannot link template "{src_name}" without template "{dst_name}" to host prototype "{target_name}" due to dependency of trigger "{trigger_name}".';
                    } else {
                        $error = 'Cannot link template "{src_name}" without template "{dst_name}" to host "{target_name}" due to dependency of trigger "{trigger_name}".';
                    }

                    $error = Yii::t('zapi', $error, [
                        'src_name' => $objects[$row['ins_templateid']]['host'],
                        'dst_name' =>  $objects[$row['hostid']]['host'],
                        'target_name' =>  $objects[$hostid]['host'],
                        'trigger_name' => $triggerName
                    ]);
                    self::exception(60750101, $error);
                }
            }
        }

        if ($this instanceof TemplateService) {
            $hostids = [];

            foreach ($ins_templates as $hostids_templateids) {
                foreach ($hostids_templateids as $hostid => $templateids) {
                    $hostids[$hostid] = true;
                }
            }

            $query = new Query();
            $query->from([
                'i' => Items::tableName(),
                'f' => Functions::tableName(),
                'td' => TriggerDepends::tableName(),
                'ff' => Functions::tableName(),
                'ii' => Items::tableName(),
            ]);
            $query->where('i.itemid=f.itemid')
                ->andWhere('f.triggerid=td.triggerid_up')
                ->andWhere('td.triggerid_down=ff.triggerid')
                ->andWhere('ff.itemid=ii.itemid');

            $query->andWhere(SqlHelper::whereIn('i.hostid', array_keys($ins_templates)))
                ->andWhere(SqlHelper::whereIn('ii.hostid', array_keys($hostids)));

            $query->select(['ins_templateid' => new Expression('DISTINCT i.hostid'), 'td.triggerid_down', 'ii.hostid']);

            foreach ($query->each() as $row) {
                if (array_key_exists($row['hostid'], $ins_templates[$row['ins_templateid']])) {
                    [$objects, $triggerName] = self::getObjectsAndTriggerDescription([$row['ins_templateid'], $hostid], $row['triggerid_down']);

                    self::exception(60750101, t('zapi', 'Cannot link template "{src_name}" to template "{dst_name}" due to dependency of trigger "{trigger_name}".', [
                        'src_name' => $objects[$row['ins_templateid']]['host'],
                        'dst_name' => $objects[$row['hostid']]['host'],
                        'trigger_name' => $triggerName,
                    ]));
                }
            }
        }
    }

    /**
     * Check whether all templates of triggers of linking templates are linked to target hosts or templates.
     *
     * @param array  $ins_templates
     * @param array  $ins_templates[<templateid>][<hostid>]  Array of template IDs to replace on target object.
     *
     * @throws APIException if not linked template is found.
     */
    protected function checkTriggerExpressionsOfInsTemplates(array $ins_templates): void
    {
        $query = new Query();
        $query->from([
            'i' => Items::tableName(),
            'f' => Functions::tableName(),
            'ff' => Functions::tableName(),
            'ii' => Items::tableName(),
        ]);
        $query->where('i.itemid=f.itemid')
            ->andWhere('f.triggerid=ff.triggerid')
            ->andWhere('ff.itemid=ii.itemid');

        $query->andWhere(SqlHelper::whereIn('i.hostid', array_keys($ins_templates)));

        $query->select(['ins_templateid' => new Expression('DISTINCT i.hostid'), 'f.triggerid', 'ii.hostid']);



        foreach ($query->each() as $row) {
            foreach ($ins_templates[$row['ins_templateid']] as $hostid => $templateids) {
                if (bccomp($row['hostid'], $hostid) == 0 && $this instanceof TemplateService) {

                    [$objects, $triggerName] = self::getObjectsAndTriggerDescription([$row['ins_templateid'], $hostid], $row['triggerid']);

                    $error = Yii::t('zapi', 'Cannot link template "{src_name}" to template "{dst_name}" due to expression of trigger "{trigger_name}".', [
                        'src_name' => $objects[$row['ins_templateid']]['host'],
                        'dst_name' =>  $objects[$hostid]['host'],
                        'trigger_name' => $triggerName
                    ]);
                    self::exception(60750101, $error);
                }

                if (!in_array($row['hostid'], $templateids)) {

                    [$objects, $triggerName] = self::getObjectsAndTriggerDescription([$row['ins_templateid'], $row['hostid'], $hostid], $row['triggerid']);

                    if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
                        $error = 'Cannot link template "{src_name}" without template "{dst_name}" to template "{target_name}" due to expression of trigger "{trigger_name}".';
                    } elseif ($objects[$hostid]['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                        $error = 'Cannot link template "{src_name}" without template "{dst_name}" to host prototype "{target_name}" due to expression of trigger "{trigger_name}".';
                    } else {
                        $error = 'Cannot link template "{src_name}" without template "{dst_name}" to host "{target_name}" due to expression of trigger "{trigger_name}".';
                    }

                    self::exception(60750101, t('zapi', $error, [
                        'src_name' => $objects[$row['ins_templateid']]['host'],
                        'dst_name' => $objects[$row['hostid']]['host'],
                        'target_name' => $objects[$hostid]['host'],
                        'trigger_name' => $triggerName,
                    ]));
                }
            }
        }
    }
    /**
     * @param array      $hosts
     * @param array|null $dbHosts
     * @param array|null $updateHostIds
     */
    protected function updateTags(array &$hosts, array &$dbHosts = null, array &$updateHostIds = null): void
    {
        $idFieldName = $this instanceof TemplateService ? 'templateid' : 'hostid';

        $insertTags = [];
        $delHostTagIds = [];

        foreach ($hosts as $i => &$host) {
            if (!array_key_exists('tags', $host)) {
                continue;
            }

            $changed = false;
            $dbTags = ($dbHosts !== null) ? $dbHosts[$host[$idFieldName]]['tags'] : [];

            $val2id = [];

            foreach ($dbTags as $dbTag) {
                $val2id[$dbTag['tag']][$dbTag['value']] = $dbTag['hosttagid'];
            }

            foreach ($host['tags'] as &$tag) {
                if (
                    array_key_exists($tag['tag'], $val2id)
                    && array_key_exists($tag['value'], $val2id[$tag['tag']])
                ) {
                    $tag['hosttagid'] = $val2id[$tag['tag']][$tag['value']];
                    unset($dbTags[$tag['hosttagid']]);
                } else {
                    $insertTags[] = ['hostid' => $host[$idFieldName]] + $tag;
                    $changed  = true;
                }
            }
            unset($tag);

            $dbTags = array_filter($dbTags, static function (array $dbTag): bool {
                return $dbTag['automatic'] == PRS_TAG_MANUAL;
            });

            if ($dbTags) {
                $delHostTagIds = array_merge($delHostTagIds, array_keys($dbTags));
                $changed = true;
            }

            if ($dbHosts !== null) {
                if ($changed) {
                    $updateHostIds[$i] = $host[$idFieldName];
                } else {
                    unset($host['tags'], $dbHosts[$host[$idFieldName]]['tags']);
                }
            }
        }
        unset($host);

        if ($delHostTagIds) {
            DB::delete(HostTag::tableName(), ['hosttagid' => $delHostTagIds]);
        }

        if ($insertTags) {
            $hosttagids = DB::insert(HostTag::tableName(), $insertTags);
        }

        foreach ($hosts as &$host) {
            if (!array_key_exists('tags', $host)) {
                continue;
            }

            foreach ($host['tags'] as &$tag) {
                if (!array_key_exists('hosttagid', $tag)) {
                    $tag['hosttagid'] = array_shift($hosttagids);
                }
            }
            unset($tag);
        }
        unset($host);
    }

    /**
     * @param array      $hosts
     * @param array|null $dbHosts
     * @param array|null $updateHostIds
     */
    protected function updateMacros(array &$hosts, array &$dbHosts = null, array &$updateHostIds = null): void
    {
        $idFieldName = $this instanceof TemplateService ? 'templateid' : 'hostid';

        $insertHostMacros = [];
        $updateHostMacros = [];
        $delHostMacroIds = [];

        foreach ($hosts as $i => &$host) {
            if (!array_key_exists('macros', $host)) {
                continue;
            }

            $changed = false;
            $dbMacros = ($dbHosts !== null) ? $dbHosts[$host[$idFieldName]]['macros'] : [];

            foreach ($host['macros'] as &$macro) {
                if (array_key_exists('hostmacroid', $macro) && $dbMacros) {
                    $updates = DB::getUpdatedValues('hostmacro', $macro, $dbMacros[$macro['hostmacroid']]);
                    if ($updates) {
                        $updateHostMacros[] = [
                            'values' => $updates,
                            'where' => ['hostmacroid' => $macro['hostmacroid']]
                        ];
                        $changed = true;
                        if (isset($macro['value']) && $macro['value'] != $dbMacros[$macro['hostmacroid']]['value']) {
                            AuditHelper::collectDetail($host[$idFieldName],'macros: '. $macro['macro'], $macro['value'],  $dbMacros[$macro['hostmacroid']]['value']);
                        }
                    }

                    unset($dbMacros[$macro['hostmacroid']]);
                } else {
                    AuditHelper::collectDetail($host[$idFieldName],'macros: '. $macro['macro'], $macro['value']);
                    $insertHostMacros[] = ['hostid' => $host[$idFieldName]] + $macro;
                    $changed = true;
                }
            }
            unset($macro);

            if ($dbMacros) {
                $delHostMacroIds = array_merge($delHostMacroIds, array_keys($dbMacros));
                $changed = true;
            }

            if ($dbHosts !== null) {
                if ($changed) {
                    $updateHostIds[$i] = $host[$idFieldName];
                } else {
                    unset($host['macros'], $dbHosts[$host[$idFieldName]]['macros']);
                }
            }
        }
        unset($host);

        if ($delHostMacroIds) {
            DB::delete('hostmacro', ['hostmacroid' => $delHostMacroIds]);
        }

        if ($updateHostMacros) {
            DB::update('hostmacro', $updateHostMacros);
        }

        if ($insertHostMacros) {
            $hostMacroIds = DB::insert('hostmacro', $insertHostMacros);
        }

        foreach ($hosts as &$host) {
            if (!array_key_exists('macros', $host)) {
                continue;
            }

            foreach ($host['macros'] as &$macro) {
                if (!array_key_exists('hostmacroid', $macro)) {
                    $macro['hostmacroid'] = array_shift($hostMacroIds);
                }
            }
            unset($macro);
        }
        unset($host);
    }

    /**
     * Update table "hosts_templates".
     *
     * @param array      $hosts
     * @param array|null $dbHosts
     * @param array|null $updateHostIds
     */
    protected function updateTemplates(array &$hosts, array &$dbHosts = null, array &$updateHostIds = null): void
    {
        $idFieldName = $this instanceof TemplateService ? 'templateid' : 'hostid';

        $inserts = [];
        $deletes = [];

        foreach ($hosts as $i => &$host) {
            if (!array_key_exists('templates', $host) && !array_key_exists('templates_clear', $host)) {
                continue;
            }

            $dbTemplates = ($dbHosts !== null)
                ? array_column($dbHosts[$host[$idFieldName]]['templates'], null, 'templateid')
                : [];
            $changed = false;

            $auditAdd = [];
            $auditDel = [];
            if (array_key_exists('templates', $host)) {
                foreach ($host['templates'] as &$template) {
                    if (array_key_exists($template['templateid'], $dbTemplates)) {
                        $template['hosttemplateid'] = $dbTemplates[$template['templateid']]['hosttemplateid'];
                        unset($dbTemplates[$template['templateid']]);
                    } else {
                        $inserts[] = [
                            'hostid' => $host[$idFieldName],
                            'templateid' => $template['templateid']
                        ];
                        $auditAdd[] =$template['templateid'];
                        $changed = true;
                    }
                }
                unset($template);

                $templates_clear_indexes = [];

                if (array_key_exists('templates_clear', $host)) {
                    foreach ($host['templates_clear'] as $index => $template) {
                        $templates_clear_indexes[$template['templateid']] = $index;
                        $auditDel[] = $template['templateid'];
                    }
                }

                foreach ($dbTemplates as $del_template) {
                    $changed = true;
                    $deletes[] = $del_template['hosttemplateid'];
                    if (array_key_exists($del_template['templateid'], $templates_clear_indexes)) {
                        $index = $templates_clear_indexes[$del_template['templateid']];
                        $host['templates_clear'][$index]['hosttemplateid'] = $del_template['hosttemplateid'];
                        $auditDel[] = $del_template['templateid'];
                    }
                }
            } elseif (array_key_exists('templates_clear', $host)) {
                foreach ($host['templates_clear'] as &$template) {
                    $template['hosttemplateid'] = $dbTemplates[$template['templateid']]['hosttemplateid'];
                    $deletes[] = $dbTemplates[$template['templateid']]['hosttemplateid'];
                }
                unset($template);
            }

            if ($dbHosts !== null) {
                if ($changed) {
                    $updateHostIds[$i] = $host[$idFieldName];
                } else {
                    unset($host['templates'], $host['templates_clear'], $dbHosts[$host[$idFieldName]]['templates']);
                }
            }

            if ($auditAdd || $auditDel) {
                AuditHelper::collectDetail($host[$idFieldName], 'templates', $auditAdd, $auditDel);
            }

        }
        unset($host);

        if ($deletes) {
            HostsTemplates::deleteAll(SqlHelper::whereIn('hosttemplateid', $deletes));
        }

        if ($inserts) {
            $hostTemplateIds = DB::insertBatch(HostsTemplates::tableName(), $inserts);
        }

        foreach ($hosts as &$host) {
            if (!array_key_exists('templates', $host)) {
                continue;
            }

            foreach ($host['templates'] as &$template) {
                if (!array_key_exists('hosttemplateid', $template)) {
                    $template['hosttemplateid'] = array_shift($hostTemplateIds);
                }
            }
            unset($template);
        }
        unset($host);
    }

    /**
	 * Check for valid templates.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 *
	 * @throws APIException
	 */
	protected function checkTemplates(array $hosts, array $db_hosts = null)
    {
		$id_field_name = $this instanceof TemplateService ? 'templateid' : 'hostid';

		$edit_templates = [];

		foreach ($hosts as $i1 => $host) {
			if (array_key_exists('templates', $host) && array_key_exists('templates_clear', $host)) {
				$path_clear = '/'.($i1 + 1).'/templates_clear';
				$path = '/'.($i1 + 1).'/templates';

				foreach ($host['templates_clear'] as $i2_clear => $template_clear) {
					foreach ($host['templates'] as $i2 => $template) {
						if (bccomp($template['templateid'], $template_clear['templateid']) == 0) {
                            self::exception(60750101, t('zapi', 'Invalid parameter {parameter}, {error}', [
                                'parameter' => $path_clear.'/'.($i2_clear + 1).'/templateid',
                                'error' => t('zapi', 'cannot be specified the value of parameter "{parameter}"', [
                                    'parameter' => $path.'/'.($i2 + 1).'/templateid'
                                ])
                            ]));
						}
					}
				}
			}

			if (array_key_exists('templates', $host)) {
				$templates = array_column($host['templates'], null, 'templateid');

				if ($db_hosts === null) {
					$edit_templates += $templates;
				}
				else {
					$db_templates = array_column($db_hosts[$host[$id_field_name]]['templates'], null, 'templateid');

					$ins_templates = array_diff_key($templates, $db_templates);
					$del_templates = array_diff_key($db_templates, $templates);

					$edit_templates += $ins_templates + $del_templates;
				}
			}

			if (array_key_exists('templates_clear', $host)) {
				$edit_templates += array_column($host['templates_clear'], null, 'templateid');
			}
		}

		if (!$edit_templates) {
			return;
		}

        $count = Hosts::find()->where(SqlHelper::whereIn('hostid', array_keys($edit_templates)))->andWhere(['status' => HOST_STATUS_TEMPLATE])->count();

		if ($count != count($edit_templates)) {
            self::exception(60750101, t('zapi', 'No permissions to referred object or it does not exist!'));
		}

		foreach ($hosts as $i1 => $host) {
			if (!array_key_exists('templates_clear', $host)) {
				continue;
			}

			$db_templates = array_column($db_hosts[$host[$id_field_name]]['templates'], null, 'templateid');
			$path = '/'.($i1 + 1).'/templates_clear';

			foreach ($host['templates_clear'] as $i2 => $template) {
				if (!array_key_exists($template['templateid'], $db_templates)) {
                    self::exception(60750101, t('zapi', 'Invalid parameter {parameter}, {error}', [
                        'parameter' => $path.'/'.($i2 + 1).'/templateid',
                        'error' => t('zapi', 'cannot be unlinked')
                    ]));
				}
			}
		}
	}

    /**
     * Check templates links.
     *
     * @param array      $hosts
     * @param array|null $db_hosts
     */
    protected function checkTemplatesLinks(array $hosts, array $db_hosts = null): void
    {
        $id_field_name = $this instanceof TemplateService ? 'templateid' : 'hostid';

        $ins_templates = [];
        $del_links = [];
        $is_template_update = $this instanceof TemplateService && $db_hosts !== null;
        $double_linkage_scope = $is_template_update ? null : [];
        $del_templates = [];
        $del_links_clear = [];

        foreach ($hosts as $host) {
            if (array_key_exists('templates', $host)) {
                $db_templates = ($db_hosts !== null)
                    ? array_column($db_hosts[$host[$id_field_name]]['templates'], null, 'templateid')
                    : [];
                $templateids = array_column($host['templates'], 'templateid');
                $templates_count = count($host['templates']);
                $upd_templateids = [];

                if (
                    $db_hosts !== null
                    && array_key_exists('nopermissions_templates', $db_hosts[$host[$id_field_name]])
                ) {
                    foreach ($db_hosts[$host[$id_field_name]]['nopermissions_templates'] as $db_template) {
                        $templateids[] = $db_template['templateid'];
                        $templates_count++;
                        $upd_templateids[] = $db_template['templateid'];
                    }
                }

                foreach ($host['templates'] as $template) {
                    if (array_key_exists($template['templateid'], $db_templates)) {
                        $upd_templateids[] = $template['templateid'];
                        unset($db_templates[$template['templateid']]);
                    } else {
                        $ins_templates[$template['templateid']][$host[$id_field_name]] = $templateids;

                        if (!$is_template_update && $templates_count > 1) {
                            $double_linkage_scope[$template['templateid']][$host[$id_field_name]] = true;
                        }
                    }
                }

                foreach ($db_templates as $db_template) {
                    $del_links[$db_template['templateid']][$host[$id_field_name]] = true;

                    if (($this instanceof HostService || $this instanceof TemplateService) && $upd_templateids) {
                        $del_templates[$db_template['templateid']][$host[$id_field_name]] = $upd_templateids;
                    }
                }
            } elseif (array_key_exists('templates_clear', $host)) {
                $templateids = array_column($host['templates_clear'], 'templateid');
                $upd_templateids = [];

                foreach ($db_hosts[$host[$id_field_name]]['templates'] as $db_template) {
                    if (!in_array($db_template['templateid'], $templateids)) {
                        $upd_templateids[] = $db_template['templateid'];
                    }
                }

                foreach ($host['templates_clear'] as $template) {
                    $del_links[$template['templateid']][$host[$id_field_name]] = true;

                    if (($this instanceof HostService || $this instanceof TemplateService) && $upd_templateids) {
                        $del_templates[$template['templateid']][$host[$id_field_name]] = $upd_templateids;
                    }
                }
            }

            if (($this instanceof HostService || $this instanceof TemplateService) && array_key_exists('templates_clear', $host)) {
                foreach ($host['templates_clear'] as $template) {
                    $del_links_clear[$template['templateid']][$host[$id_field_name]] = true;
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
            if ($this instanceof TemplateService && $db_hosts !== null) {
                self::checkCircularLinkageNew($ins_templates, $del_links);
            }

            if ($is_template_update || $double_linkage_scope) {
                $this->checkDoubleLinkageNew($ins_templates, $del_links, $double_linkage_scope);
            }

            $this->checkTriggerDependenciesOfInsTemplates($ins_templates);
            $this->checkTriggerExpressionsOfInsTemplates($ins_templates);
        }
    }

    /**
     * Check whether all templates of triggers of unlinking templates are unlinked from target hosts or templates.
     *
     * @param array $delTemplates
     * @param array $delTemplates[<templateid>][<hostid>]  Array of IDs of existing templates.
     *
     * @throws Exception if not linked template is found.
     */
    protected function checkTriggerExpressionsOfDelTemplates(array $delTemplates): void
    {
        $query = new Query();
        $query->from([
            'i' => Items::tableName(),
            'f' => Functions::tableName(),
            'ff' => Functions::tableName(),
            'ii' => Items::tableName(),
        ]);
        $query->where('i.itemid=f.itemid')
            ->andWhere('f.triggerid=ff.triggerid')
            ->andWhere('ff.itemid=ii.itemid');

        $query->andWhere(SqlHelper::whereIn('i.hostid', array_keys($delTemplates)));

        $query->select(['del_templateid' => new Expression('DISTINCT i.hostid'), 'f.triggerid', 'ii.hostid']);

        foreach ($query->each() as $row) {
            foreach ($delTemplates[$row['del_templateid']] as $hostid => $updateTemplateIds) {
                if (in_array($row['hostid'], $updateTemplateIds)) {
                    [$objects, $triggerName] = self::getObjectsAndTriggerDescription([$row['del_templateid'], $row['hostid'], $hostid], $row['triggerid']);

                    $format = ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE)
                        ? 'Cannot unlink template "{src_name}" without template "{dst_name}" from template "{target_name}" due to expression of trigger "{trigger_name}".'
                        : 'Cannot unlink template "{src_name}" without template "{dst_name}" from host "{target_name}" due to expression of trigger "{trigger_name}".';
                    self::exception(60750101, Yii::t('zapi', $format, [
                        'src_name' => $objects[$row['del_templateid']]['host'],
                        'dst_name' => $objects[$row['hostid']]['host'],
                        'target_name' => $objects[$hostid]['host'],
                        'trigger_name' => $triggerName,
                    ]));
                }
            }
        }
    }

    /**
     * Check whether the triggers of the target hosts or templates don't have a dependencies on the triggers of the
     * unlinking (with cleaning) templates.
     *
     * @param array $del_links_clear[<templateid>][<hostid>]
     *
     * @throws Exception
     */
    protected function checkTriggerDependenciesOfHostTriggers(array $del_links_clear): void
    {
        $del_host_templates = [];

        foreach ($del_links_clear as $templateid => $hosts) {
            foreach ($hosts as $hostid => $foo) {
                $del_host_templates[$hostid][] = $templateid;
            }
        }

        $query = new Query();
        $query->from([
            'i' => Items::tableName(),
            'f' => Functions::tableName(),
            't' => Triggers::tableName(),
            'ff' => Functions::tableName(),
            'ii' => Items::tableName(),
        ]);
        $query->where('i.itemid=f.itemid')
            ->andWhere('f.triggerid=t.templateid')
            ->andWhere('t.triggerid=ff.triggerid')
            ->andWhere('ff.itemid=ii.itemid');

        $query->andWhere(SqlHelper::whereIn('i.hostid', array_keys($del_links_clear)))
            ->andWhere(SqlHelper::whereIn('ii.hostid', array_keys($del_host_templates)));

        $query->select(['templateid' => new Expression('DISTINCT i.hostid'), 't.triggerid', 'ii.hostid']);
        $trigger_links = [];
        foreach ($query->each() as $row) {
            if (in_array($row['templateid'], $del_host_templates[$row['hostid']])) {
                $trigger_links[$row['triggerid']][$row['hostid']] = $row['templateid'];
            }
        }

        if (!$trigger_links) {
            return;
        }

        $triggerIds = array_keys($trigger_links);
        $query = $this->getTriggerDependencyQuery($triggerIds, $triggerIds, array_keys($del_host_templates));

        foreach ($query->each() as $row) {
            foreach ($trigger_links[$row['triggerid_up']] as $hostid => $templateid) {
                if (bccomp($row['hostid'], $hostid) == 0) {
                    [$objects, $triggerName] = self::getObjectsAndTriggerDescription([$templateid, $hostid], $row['triggerid_down']);

                    $format = ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE)
                        ? 'Cannot unlink template "{template}" from template "{target_name}" due to dependency of trigger "{trigger_name}".'
                        : 'Cannot unlink template "{template}" from host "{target_name}" due to dependency of trigger "{trigger_name}".';

                    self::exception(60750101, Yii::t('zapi', $format, [
                        'template' => $objects[$templateid]['host'],
                        'target_name' => $objects[$hostid]['host'],
                        'trigger_name' => $triggerName,
                    ]));
                }
            }
        }

        if ($this instanceof TemplateService) {
            $trigger_hosts = [];

            foreach ($trigger_links as $triggerid => $hostids) {
                $trigger_hosts[$triggerid] = array_keys($hostids);
            }

            $trigger_map = [];

            while (true) {

                $query = new Query();
                $query->from([
                    't' => Triggers::tableName(),
                    'f' => Functions::tableName(),
                    'i' => Items::tableName(),
                ]);
                $query->where('t.triggerid=f.triggerid')
                    ->andWhere('f.itemid=i.itemid');

                $query->andWhere(SqlHelper::whereIn('t.templateid', array_keys($trigger_hosts)));

                $query->select([new Expression('DISTINCT t.templateid'), 't.triggerid', 'i.hostid']);

                $_trigger_hosts = [];
                $hostids = [];

                foreach ($query->each() as $row) {
                    foreach ($trigger_hosts[$row['templateid']] as $hostid) {
                        if (
                            array_key_exists($row['hostid'], $del_host_templates)
                            && in_array($hostid, $del_host_templates[$row['hostid']])
                        ) {
                            continue;
                        }

                        $trigger_map[$row['triggerid']] = $row['templateid'];
                        $_trigger_hosts[$row['triggerid']][] = $row['hostid'];
                        $hostids[$row['hostid']] = true;
                    }
                }

                if (!$_trigger_hosts) {
                    break;
                }

                $trigger_hosts = $_trigger_hosts;

                $triggerIds = array_keys($trigger_hosts);
                $query = $this->getTriggerDependencyQuery($triggerIds, $triggerIds, array_keys($hostids));

                foreach ($query->each() as $row) {
                    foreach ($trigger_hosts[$row['triggerid_up']] as $hostid) {
                        if (bccomp($row['hostid'], $hostid) == 0) {
                            $triggerid = $row['triggerid_up'];

                            do {
                                $triggerid = $trigger_map[$triggerid];
                            } while (array_key_exists($triggerid, $trigger_map));

                            $from_hostid = key($trigger_links[$triggerid]);
                            $templateid = $trigger_links[$triggerid][$from_hostid];

                            [$objects, $triggerName] = self::getObjectsAndTriggerDescription([$templateid, $from_hostid, $hostid], $row['triggerid_down']);

                            $format = ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE)
                                ? 'Cannot unlink template "{src_name}" from template "{dst_name}" due to dependency of trigger "{trigger_name}" on template "{target_name}".'
                                : 'Cannot unlink template "{src_name}" from template "{dst_name}" due to dependency of trigger "{trigger_name}" on host "{target_name}".';

                            self::exception(60750101, Yii::t('zapi', $format, [
                                'src_name' => $objects[$templateid]['host'],
                                'dst_name' => $objects[$from_hostid]['host'],
                                'trigger_name' => $triggerName,
                                'target_name' => $objects[$hostid]['host']
                            ]));
                        }
                    }
                }
            }
        }
    }

    /**
     * Check whether circular linkage occurs as a result of the given changes in templates links.
     *
     * @param array $ins_links[<templateid>][<hostid>]
     * @param array $del_links[<templateid>][<hostid>]
     *
     * @throws Exception
     */
    protected static function checkCircularLinkageNew(array $ins_links, array $del_links): void
    {
        $links = [];
        $_hostids = $ins_links;

        do {
            $hostTemplates = HostsTemplates::find()
                ->select(['templateid', 'hostid'])
                ->where(SqlHelper::whereIn('hostid', array_keys($_hostids)))
                ->asArray()
                ->all();

            $_hostids = [];

            foreach ($hostTemplates as $hostTemplate) {
                if (
                    array_key_exists($hostTemplate['templateid'], $del_links)
                    && array_key_exists($hostTemplate['hostid'], $del_links[$hostTemplate['templateid']])
                ) {
                    continue;
                }

                if (!array_key_exists($hostTemplate['templateid'], $links)) {
                    $_hostids[$hostTemplate['templateid']] = true;
                }

                $links[$hostTemplate['templateid']][$hostTemplate['hostid']] = true;
            }
        } while ($_hostids);

        foreach ($ins_links as $templateid => $hostids) {
            if (array_key_exists($templateid, $links)) {
                $links[$templateid] += $hostids;
            } else {
                $links[$templateid] = $ins_links[$templateid];
            }
        }

        foreach ($ins_links as $templateid => $hostids) {
            foreach ($hostids as $hostid => $foo) {
                if (array_key_exists($hostid, $links)) {
                    $links_path = [$hostid => true];

                    if (self::circularLinkageExists($links, $templateid, $links[$hostid], $links_path)) {
                        $template_name = '';

                        $templates = Hosts::find()
                            ->select(['hostid', 'host'])
                            ->where(SqlHelper::whereIn('hostid', array_keys($links_path + [$templateid => true])))
                            ->indexBy('hostid')
                            ->all();

                        foreach ($templates as $template) {
                            $description = '"' . $template['host'] . '"';

                            if (bccomp($template['hostid'], $templateid) == 0) {
                                $template_name = $description;
                            } else {
                                $links_path[$template['hostid']] = $description;
                            }
                        }

                        $circular_linkage = (bccomp($templateid, $hostid) == 0)
                            ? $template_name . ' -> ' . $template_name
                            : $template_name . ' -> ' . implode(' -> ', $links_path) . ' -> ' . $template_name;

                        $format = 'Cannot link template "{src_name}" to template "{src_name}", because a circular linkage ({target_name}) would occur.';
                        self::exception(60750101, Yii::t('zapi', $format, [
                            'src_name' =>  $templates[$templateid]['host'],
                            'dst_name' => $templates[$hostid]['host'],
                            'target_name' => $circular_linkage,
                        ]));
                    }
                }
            }
        }
    }

    /**
     * Recursively check whether given template to link forms a circular linkage.
     *
     * @param array  $links[<templateid>][<hostid>]
     * @param string $templateid
     * @param array  $hostids[<hostid>]
     * @param array  $links_path                     Circular linkage path, collected performing the check.
     *
     * @return bool
     */
    private static function circularLinkageExists(array $links, string $templateid, array $hostids, array &$links_path): bool
    {
        if (array_key_exists($templateid, $hostids)) {
            return true;
        }

        $_links_path = $links_path;

        foreach ($hostids as $hostid => $foo) {
            if (array_key_exists($hostid, $links)) {
                $links_path = $_links_path;
                $hostid_links = array_diff_key($links[$hostid], $links_path);

                if ($hostid_links) {
                    $links_path[$hostid] = true;

                    if (self::circularLinkageExists($links, $templateid, $hostid_links, $links_path)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check whether double linkage occurs as a result of the given changes in template links.
     *
     * @param array      $ins_templates
     * @param array      $ins_templates[<templateid>][<hostid>]  Array of template IDs to replace on target object.
     * @param array      $del_links[<templateid>][<hostid>]
     * @param array|null $scope[<templateid>][<hostid>]          The scope of template links to perform the double
     *                                                           linkage check for. If null, all of $ins_templates
     *                                                           links will be checked.
     *
     * @throws APIException
     */
    protected static function checkDoubleLinkageNew(array $ins_templates, array $del_links, ?array $scope): void
    {
        $ins_hosts = self::getInsHosts($ins_templates, $scope);
        $scoped_ins_templates = self::getScopedInsTemplates($ins_templates, $scope, $db_templates);

        $targetids = $scoped_ins_templates + $db_templates;

        if ($scope === null) {
            $children = self::getChildren($ins_hosts + $ins_templates, $del_links, $ins_templates);

            $targetids += $ins_hosts + self::getTemplateOrTargetRelatedIds($children, $ins_hosts);
        }

        $parents = self::getParents($targetids, $del_links, $ins_hosts);

        self::checkParentsOfDbTemplatesLinkedTwice($db_templates, $parents);
        self::checkParentsOfInsTemplatesLinkedTwice($scoped_ins_templates, $parents);

        if ($scope === null) {
            self::addInsHostsParentsAndChildren($ins_hosts, $parents, $children);
            $children_parents = self::getChildrenParents($children, $parents);

            self::checkInsTemplatesLinkedTwiceOnTargetChildren($ins_hosts, $children_parents);
        }
    }

    /**
     * Get an array indexed by targets of the given $ins_templates and their templates. If the given scope is partial,
     * returns null.
     *
     * @param array      $ins_templates
     * @param array      $ins_templates[<templateid>][<hostid>]  Array of template IDs to replace on target object.
     * @param array|null $scope[<templateid>][<hostid>]          The scope of template links to perform the double
     *                                                           linkage check for.
     *
     * @return array|null
     */
    private static function getInsHosts(array $ins_templates, ?array $scope): ?array
    {
        if ($scope !== null) {
            return null;
        }

        $ins_hosts = [];

        foreach ($ins_templates as $templateid => $host_templates) {
            foreach ($host_templates as $hostid => $foo) {
                $ins_hosts[$hostid][$templateid] = [];
            }
        }

        return $ins_hosts;
    }

    /**
     * Get an array of template links from the given $ins_templates to check for double linkage.
     * The same target object will be referenced to a common array of template IDs to replace (to be updated later).
     * Skip template links out of the given scope.
     *
     * @param array      $ins_templates
     * @param array      $ins_templates[<templateid>][<hostid>]  Array of template IDs to replace on target object.
     * @param array|null $scope[<templateid>][<hostid>]          The scope of template links to perform the double
     *                                                           linkage check for. If null, all of $ins_templates
     *                                                           links will be processed.
     * @param array      $db_templates
     * @param array      $db_templates[<templateid>][<hostid>]   Reference to a common array of template IDs to replace.
     *
     * @return array|null
     */
    private static function getScopedInsTemplates(array $ins_templates, ?array $scope, array &$db_templates = null): array
    {
        $scoped_ins_templates = [];
        $db_templates = [];

        foreach ($ins_templates as $templateid => $host_templates) {
            if ($scope !== null && !array_key_exists($templateid, $scope)) {
                continue;
            }

            foreach ($host_templates as $hostid => &$templateids) {
                if (($scope !== null && !array_key_exists($hostid, $scope[$templateid]))
                    || (array_key_exists($templateid, $scoped_ins_templates)
                        && array_key_exists($hostid, $scoped_ins_templates[$templateid]))
                ) {
                    continue;
                }

                $scoped_ins_templates[$templateid][$hostid] = &$templateids;

                foreach ($templateids as $_templateid) {
                    if (bccomp($_templateid, $templateid) == 0) {
                        continue;
                    }

                    if (
                        array_key_exists($_templateid, $ins_templates)
                        && array_key_exists($hostid, $ins_templates[$_templateid])
                    ) {
                        $scoped_ins_templates[$_templateid][$hostid] = &$templateids;
                    } else {
                        $db_templates[$_templateid][$hostid] = &$templateids;
                    }
                }
            }
            unset($templateids);
        }

        return $scoped_ins_templates;
    }

    /**
     * Recursively get children of the given template IDs.
     *
     * @param array      $templateids[<templateid>]
     * @param array      $del_links
     * @param array|null $ins_templates
     *
     * @return array
     */
    private static function getChildren(array $templateids, array $del_links, ?array $ins_templates): array
    {
        $processed_templateids = $templateids;
        $children = [];

        do {
            $links = HostsTemplates::find()
                ->select(['templateid', 'hostid'])
                ->where(SqlHelper::whereIn('templateid', array_keys($templateids)))
                ->asArray()
                ->all();

            if ($ins_templates !== null) {
                foreach (array_intersect_key($ins_templates, $templateids) as $templateid => $hostids) {
                    foreach ($hostids as $hostid => $foo) {
                        $links[] = ['templateid' => $templateid, 'hostid' => $hostid];
                    }
                }
            }

            $templateids = [];

            foreach ($links as $link) {
                if ($ins_templates !== null) {
                    if (array_key_exists($link['templateid'], $del_links) && array_key_exists($link['hostid'], $del_links[$link['templateid']])) {
                        continue;
                    }
                }

                if (!array_key_exists($link['hostid'], $processed_templateids)) {
                    $templateids[$link['hostid']] = true;
                    $processed_templateids[$link['hostid']] = true;
                }

                $children[$link['templateid']][] = $link['hostid'];
            }
        } while ($templateids);

        return $children;
    }

    /**
     * Recursively get parents of the given target IDs.
     *
     * @param array      $targetids[<targetid>]
     * @param array      $del_links
     * @param array|null $ins_hosts
     *
     * @return array
     */
    private static function getParents(array $targetids, array $del_links, ?array $ins_hosts): array
    {
        $processed_targetids = $targetids;
        $parents = [];

        do {
            $links = HostsTemplates::find()
                ->select(['templateid', 'hostid'])
                ->where(SqlHelper::whereIn('hostid', array_keys($targetids)))
                ->asArray()
                ->all();

            if ($ins_hosts !== null) {
                foreach (array_intersect_key($ins_hosts, $targetids) as $hostid => $templateids) {
                    foreach ($templateids as $templateid => $foo) {
                        $links[] = ['templateid' => $templateid, 'hostid' => $hostid];
                    }
                }
            }

            $targetids = [];

            foreach ($links as $link) {
                if ($ins_hosts !== null) {
                    if (array_key_exists($link['templateid'], $del_links) && array_key_exists($link['hostid'], $del_links[$link['templateid']])) {
                        continue;
                    }
                }

                if (!array_key_exists($link['templateid'], $processed_targetids)) {
                    $targetids[$link['templateid']] = true;
                    $processed_targetids[$link['templateid']] = true;
                }

                $parents[$link['hostid']][] = $link['templateid'];
            }
        } while ($targetids);

        return $parents;
    }

    /**
     * Check whether parents of already linked templates would be linked twice to target hosts or templates through new
     * template linkage.
     * Populate the referenced arrays of target object template IDs with the parents of the given $db_templates.
     *
     * @param array $db_templates
     * @param array $parents
     *
     * @throws APIException
     */
    private static function checkParentsOfDbTemplatesLinkedTwice(array $db_templates, array $parents): void
    {
        $_templateids = $db_templates;
        $children = [];

        do {
            $links = array_intersect_key($parents, $_templateids);

            $_templateids = [];

            foreach ($links as $link_templateid => $link_parent_templateids) {
                $db_templateids = self::getRootTemplateIds([$link_templateid => true], $children);

                foreach ($db_templateids as $templateid => $foo) {
                    foreach ($db_templates[$templateid] as $hostid => &$templateids) {
                        $double_templateids = array_intersect($link_parent_templateids, $templateids);

                        if ($double_templateids) {
                            $double_templateid = reset($double_templateids);

                            [$objects,] = self::getObjectsAndTriggerDescription([$double_templateid, $hostid, $templateid]);

                            if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
                                $error = 'Cannot link template "{src_name}" to template "{dst_name}", because it would be linked twice through template "{target_name}".';
                            } elseif ($objects[$hostid]['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                                $error = 'Cannot link template "{src_name}" to host prototype "{dst_name}", because it would be linked twice through template "{target_name}".';
                            } else {
                                $error = 'Cannot link template "{src_name}" to host "{dst_name}", because it would be linked twice through template "{target_name}".';
                            }

                            self::exception(60750101, Yii::t('zapi', $error, [
                                'src_name' => $objects[$double_templateid]['host'],
                                'dst_name' => $objects[$hostid]['host'],
                                'target_name' => $objects[$templateid]['host'],
                            ]));
                        }

                        $templateids = array_merge($templateids, $link_parent_templateids);
                    }
                    unset($templateids);
                }

                foreach ($link_parent_templateids as $link_parent_templateid) {
                    if (!array_key_exists($link_parent_templateid, $children)) {
                        $_templateids[$link_parent_templateid] = true;
                    }

                    $children[$link_parent_templateid][] = $link_templateid;
                }
            }
        } while ($_templateids);
    }

    /**
     * Check whether parents of templates to link would be linked twice to target hosts or templates.
     * Populate the referenced arrays of target object template IDs with the parents of the given $ins_templates.
     *
     * @param array      $ins_templates
     * @param array      $ins_templates[<templateid>][<hostid>]  Referenced array of target object template IDs.
     * @param array      $parents
     *
     * @throws APIException
     */
    private static function checkParentsOfInsTemplatesLinkedTwice(array $ins_templates, array $parents): void
    {
        $_templateids = $ins_templates;
        $children = [];

        do {
            $links = array_intersect_key($parents, $_templateids);

            $_templateids = [];

            foreach ($links as $link_templateid => $link_parent_templateids) {
                $ins_templateids = self::getRootTemplateIds([$link_templateid => true], $children);

                foreach ($ins_templateids as $ins_templateid => $foo) {
                    foreach ($ins_templates[$ins_templateid] as $hostid => &$templateids) {
                        $double_templateids = array_intersect($link_parent_templateids, $templateids);

                        if ($double_templateids) {
                            $double_templateid = reset($double_templateids);

                            [$objects,] = self::getObjectsAndTriggerDescription([$ins_templateid, $hostid, $double_templateid]);

                            if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
                                $error = 'Cannot link template "{src_name}" to template "{dst_name}", because its parent template "{target_name}" would be linked twice.';
                            } elseif ($objects[$hostid]['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                                $error = 'Cannot link template "{src_name}" to host prototype "{dst_name}", because its parent template "{target_name}" would be linked twice.';
                            } else {
                                $error = 'Cannot link template "{src_name}" to host "{dst_name}", because its parent template "{target_name}" would be linked twice.';
                            }
                            $error = Yii::t('zapi', $error, [
                                'src_name' => $objects[$ins_templateid]['host'],
                                'dst_name' =>  $objects[$hostid]['host'],
                                'target_name' => $objects[$double_templateid]['host']
                            ]);
                            self::exception(60750101, $error);
                        } else {
                            $templateids = array_merge($templateids, $link_parent_templateids);
                        }
                    }
                    unset($templateids);
                }

                foreach ($link_parent_templateids as $link_parent_templateid) {
                    if (!array_key_exists($link_parent_templateid, $children)) {
                        $_templateids[$link_parent_templateid] = true;
                    }

                    $children[$link_parent_templateid][] = $link_templateid;
                }
            }
        } while ($_templateids);
    }

    /**
     * Add the parent and children relations of each template to link to the given $ins_hosts.
     *
     * @param array $ins_hosts
     * @param array $parents
     * @param array $children
     */
    private static function addInsHostsParentsAndChildren(array &$ins_hosts, array $parents, array $children): void
    {
        foreach ($ins_hosts as &$template_data) {
            foreach ($template_data as $templateid => &$data) {
                $data['parents'] = self::getTemplateOrTargetRelatedIds($parents, [$templateid => true]);
                $data['children'] = self::getTemplateOrTargetRelatedIds($children, [$templateid => true]);
            }
            unset($data);
        }
        unset($template_data);
    }

    /**
     * Get the direct parents of each given children template.
     *
     * @param array $children
     * @param array $parents
     *
     * @return array
     */
    private static function getChildrenParents(array $children, array $parents): array
    {
        $children_parents = [];

        foreach ($children as $templateid => $targetids) {
            foreach ($targetids as $targetid) {
                $children_parents[$templateid][$targetid] =
                    self::getTemplateOrTargetRelatedIds($parents, [$targetid => true], $templateid);
            }
        }

        return $children_parents;
    }

    /**
     * Check whether templates to link, its parents, or children are encountered between parents of target templates'
     * children.
     *
     * @param array $ins_hosts
     * @param array $children_templates
     *
     * @throws APIException
     */
    private static function checkInsTemplatesLinkedTwiceOnTargetChildren(
        array $ins_hosts,
        array $children_parents
    ): void {
        $_templateids = $ins_hosts;
        $_parents = [];

        do {
            $links = array_intersect_key($children_parents, $_templateids);

            $_templateids = [];

            foreach ($links as $link_templateid => $host_parent_templates) {
                $ins_hostids = self::getRootTemplateIds([$link_templateid => true], $_parents);

                foreach ($ins_hostids as $ins_hostid => $foo) {
                    foreach ($ins_hosts[$ins_hostid] as $templateid => $data) {
                        foreach ($host_parent_templates as $hostid => $parent_templateids) {
                            if (
                                array_key_exists($templateid, $parent_templateids)
                                || array_intersect_key($data['children'], $parent_templateids)
                            ) {
                                [$objects,] = self::getObjectsAndTriggerDescription([$templateid, $ins_hostid, $hostid]);

                                if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
                                    $error = 'Cannot link template "{src_name}" to template "{dst_name}", because it would be linked to template "{target_name}" twice.';
                                } elseif ($objects[$hostid]['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                                    $error = 'Cannot link template "{src_name}" to template "{dst_name}", because it would be linked to host prototype "{target_name}" twice.';
                                } else {
                                    $error = 'Cannot link template "{src_name}" to template "{dst_name}", because it would be linked to host "{target_name}" twice.';
                                }
                                $error = Yii::t('zapi', $error, [
                                    'src_name' => $objects[$templateid]['host'],
                                    'dst_name' =>  $objects[$ins_hostid]['host'],
                                    'target_name' => $objects[$hostid]['host']
                                ]);
                                self::exception(60750101, $error);
                            }

                            $double_templateids = array_intersect_key($data['parents'], $parent_templateids);

                            if ($double_templateids) {
                                $double_templateid = key($double_templateids);

                                [$objects,] = self::getObjectsAndTriggerDescription([$templateid, $ins_hostid, $double_templateid, $hostid]);

                                if ($objects[$hostid]['status'] == HOST_STATUS_TEMPLATE) {
                                    $error = 'Cannot link template "{src_name}" to template "{dst_name}", because its parent template "{source_name}" would be linked to template "{target_name}" twice.';
                                } elseif ($objects[$hostid]['flags'] == PRS_FLAG_DISCOVERY_PROTOTYPE) {
                                    $error = 'Cannot link template "{src_name}" to template "{dst_name}", because its parent template "{source_name}" would be linked to host prototype "{target_name}" twice.';
                                } else {
                                    $error = 'Cannot link template "{src_name}" to template "{dst_name}", because its parent template "{source_name}" would be linked to host "{target_name}" twice.';
                                }
                                $error = Yii::t('zapi', $error, [
                                    'src_name' => $objects[$templateid]['host'],
                                    'dst_name' =>  $objects[$ins_hostid]['host'],
                                    'source_name' => $objects[$double_templateid]['host'],
                                    'target_name' => $objects[$hostid]['host']
                                ]);
                                self::exception(60750101, $error);
                            }
                        }
                    }
                }

                foreach ($host_parent_templates as $hostid => $foo) {
                    if (!array_key_exists($hostid, $_parents)) {
                        $_templateids[$hostid] = true;
                        $_parents[$hostid][] = $link_templateid;
                    }
                }
            }
        } while ($_templateids);
    }

    /**
     * Get IDs of targets linked to given templates or IDs of templates linked to given targets.
     *
     * @param array       $links
     * @param array       $sourceids
     * @param string|null $ignore_relatedid
     *
     * @return array
     */
    private static function getTemplateOrTargetRelatedIds(
        array $links,
        array $sourceids,
        string $ignore_relatedid = null
    ): array {
        $processed_sourceids = $sourceids;
        $relatedids = [];

        do {
            $scoped_links = array_intersect_key($links, $sourceids);

            $sourceids = [];

            foreach ($scoped_links as $_relatedids) {
                foreach ($_relatedids as $relatedid) {
                    if ($ignore_relatedid !== null && bccomp($relatedid, $ignore_relatedid) == 0) {
                        continue;
                    }

                    if (!array_key_exists($relatedid, $processed_sourceids)) {
                        $sourceids[$relatedid] = true;
                        $processed_sourceids[$relatedid] = true;
                    }

                    $relatedids[$relatedid] = true;
                }
            }

            $ignore_relatedid = null;
        } while ($sourceids);

        return $relatedids;
    }

    /**
     * Recursively collects the roots of the given children or parent templates.
     *
     * @param array $templateids
     * @param array $template_links
     *
     * @return array
     */
    private static function getRootTemplateIds(array $templateids, array $template_links): array
    {
        $root_templateids = $templateids;

        foreach ($templateids as $templateid => $foo) {
            if (array_key_exists($templateid, $template_links)) {
                unset($root_templateids[$templateid]);

                $root_templateids +=
                    self::getRootTemplateIds(array_flip($template_links[$templateid]), $template_links);
            }
        }

        return $root_templateids;
    }


    /**
     * @return Query
     */
    private function getTriggerDependencyQuery(array $inTriggerIds,  array $outTriggerIds, array $hostIds): Query
    {
        $query = new Query();
        $query->from([
            'td' => TriggerDepends::tableName(),
            'f' => Functions::tableName(),
            'i' => Items::tableName(),
        ]);
        $query->where('td.triggerid_down=f.triggerid')
            ->andWhere('f.itemid=i.itemid');

        $query->andWhere(SqlHelper::whereIn('td.triggerid_up', $inTriggerIds))
            ->andWhere(SqlHelper::whereIn('td.triggerid_down', $outTriggerIds, true))
            ->andWhere(SqlHelper::whereIn('i.hostid', $hostIds));

        $query->select([new Expression('DISTINCT td.triggerid_up'), 'td.triggerid_down', 'i.hostid']);

        return $query;
    }

    /**
     * @param int[] $hostIds
     * @param int[]|int $triggerId
     * @return array
     */
    protected static function getObjectsAndTriggerDescription(array $hostIds, $triggerId = null)
    {
        $objects = Hosts::find()
            ->select(['hostid', 'host', 'status', 'flags'])
            ->where(['hostid' => $hostIds])
            ->indexBy('hostid')
            ->asArray()
            ->all();

        $triggerName = $triggerId ? Triggers::find()
            ->select(['description'])
            ->where(['triggerid' => $triggerId])
            ->asArray()
            ->scalar() : null;

        return [$objects, $triggerName];
    }

    /**
     * @param array $hosts
     * @param array $dbHosts
     */
    protected function addAffectedObjects(array $hosts, array &$dbHosts): void
    {
        $this->addAffectedTemplates($hosts, $dbHosts);
        $this->addAffectedTags($hosts, $dbHosts);
        $this->addAffectedMacros($hosts, $dbHosts);
    }

    /**
     * @param array $hosts
     * @param array $dbHosts
     */
    private function addAffectedTemplates(array $hosts, array &$dbHosts): void
    {
        $primaryKey = $this instanceof TemplateService ? 'templateid' : 'hostid';

        $hostIds = [];

        foreach ($hosts as $host) {
            if (array_key_exists('templates', $host) || array_key_exists('templates_clear', $host)) {
                $hostIds[] = $host[$primaryKey];
                $dbHosts[$host[$primaryKey]]['templates'] = [];
            }
        }

        if (!$hostIds) {
            return;
        }
        // TODO: 目前默认超管执行
        $userType = USER_TYPE_SUPER_ADMIN;

        $permittedTemplates = [];
        if ($userType == USER_TYPE_PERSEUS_ADMIN) {
            $permittedTemplates = TemplateHelper::getTemplates([
                'output' => [],
                'hostids' => $hostIds,
                'preserveKey' => true,
            ]);
        }

        $query = HostsTemplates::find()
            ->select(['hosttemplateid', 'hostid', 'templateid'])
            ->where(SqlHelper::whereIn('hostid', $hostIds))
            ->asArray();

        foreach ($query->each() as $hostTemplate) {
            if ($userType == USER_TYPE_SUPER_ADMIN || array_key_exists($hostTemplate['templateid'], $permittedTemplates)) {
                $dbHosts[$hostTemplate['hostid']]['templates'][$hostTemplate['hosttemplateid']] =
                    array_diff_key($hostTemplate, array_flip(['hostid']));
            } else {
                $dbHosts[$hostTemplate['hostid']]['nopermissions_templates'][$hostTemplate['hosttemplateid']] =
                    array_diff_key($hostTemplate, array_flip(['hostid']));
            }
        }
    }

    /**
     * @param array $hosts
     * @param array $db_hosts
     */
    protected function addAffectedTags(array $hosts, array &$dbHosts): void
    {
        $this->addAffectedData($hosts, $dbHosts, HostTag::tableName(), 'tags');
    }

    /**
     * @param array $hosts
     * @param array $dbHosts
     */
    private function addAffectedMacros(array $hosts, array &$dbHosts): void
    {
        $this->addAffectedData($hosts, $dbHosts, Hostmacro::tableName(), 'macros');
    }

    /**
     * @param array $hosts
     * @param array $dbHosts
     * @return void
     */
    private function addAffectedData(array $hosts, array &$dbHosts, string $table, string $group)
    {
        $primaryKey = $this instanceof TemplateService ? 'templateid' : 'hostid';

        $hostIds = [];

        foreach ($hosts as $host) {
            if (array_key_exists($group, $host)) {
                $hostIds[] = $host[$primaryKey];
                $dbHosts[$host[$primaryKey]][$group] = [];
            }
        }

        if (!$hostIds) {
            return;
        }

        $tbPrimaryKey = DB::getPk($table);

        $query = new Query();
        $query->from($table)
            ->where(SqlHelper::whereIn('hostid', $hostIds));

        foreach ($query->each() as $row) {
            $dbHosts[$row['hostid']][$group][$row[$tbPrimaryKey]] =
                array_diff_key($row, array_flip(['hostid']));
        }
    }



    /**
     * Links the templates to the given hosts.
     *
     * @param array $templateIds
     * @param array $targetIds		an array of host IDs to link the templates to
     *
     * @return array 	an array of added hosts_templates rows, with 'hostid' and 'templateid' set for each row
     */
    protected function link(array $templateIds, array $targetIds)
    {
        if (empty($templateIds)) {
            return;
        }

        // check if someone passed duplicate templates in the same query
        $templateIdDuplicates = prs_arrayFindDuplicates($templateIds);
        if ($templateIdDuplicates) {
            $duplicatesFound = [];
            foreach ($templateIdDuplicates as $value => $count) {
                $duplicatesFound[] = t('zapi', 'template ID "{id}" is passed {count} times', ['id' => $value, 'count' => $count]);
            }
            throw new ValidateException('60750101', t('zapi', 'Cannot pass duplicate template IDs for the linkage: {reason}.', ['reason' => implode(', ', $duplicatesFound)]));
        }

        $count = Hosts::find()
            ->where(SqlHelper::whereIn('hostid', $templateIds))
            ->andWhere(['status' => 3])
            ->asArray()
            ->count(1);


        if ($count != count($templateIds)) {
            throw new ValidateException(60750101, t('zapi', 'Invalid parameter {parameter}, {error}', [
                'parameter' => 'templates',
                'error' => ''
            ]));
        }
        // get DB templates which exists in all targets
        $query = HostsTemplates::find()
            ->where(SqlHelper::whereIn('hostid', $targetIds))
            ->asArray();

        $mas = [];
        foreach ($query->each() as $row) {
            if (!isset($mas[$row['templateid']])) {
                $mas[$row['templateid']] = [];
            }
            $mas[$row['templateid']][$row['hostid']] = 1;
        }

        $commonDBTemplateIds = [];
        foreach ($mas as $templateId => $targetList) {
            if (count($targetList) == count($targetIds)) {
                $commonDBTemplateIds[] = $templateId;
            }
        }

        // check if there are any template with triggers which depends on triggers in templates which will be not linked
        $commonTemplateIds = array_unique(array_merge($commonDBTemplateIds, $templateIds));
        $tSelect = new Expression('DISTINCT t.*');
        $dSelect = new Expression('DISTINCT h.host');
        foreach ($templateIds as $templateId) {
            $triggerIds = [];
            $query = new Query();
            $query->from([
                't' => Triggers::tableName(),
                'f' => Functions::tableName(),
                'i' => Items::tableName()
            ]);
            $query->where('f.itemid=i.itemid')
                ->andWhere('f.triggerid=t.triggerid')
                ->andWhere(['i.hostid' => $templateId]);
            $query->select($tSelect);
            foreach ($query->each() as $trigger) {
                $triggerIds[$trigger['triggerid']] = $trigger['triggerid'];
            }

            $query = new Query();
            $query->from([
                'td' => TriggerDepends::tableName(),
                'f' => Functions::tableName(),
                'i' => Items::tableName(),
                'h' => Hosts::tableName(),
            ]);
            $query->where('f.triggerid=td.triggerid_up')
                ->andWhere('i.itemid=f.itemid')
                ->andWhere('h.hostid=i.hostid')
                ->andWhere(['h.status' => HOST_STATUS_TEMPLATE])
                ->andWhere(SqlHelper::whereIn('{{td}}.triggerid_down', $triggerIds))
                ->andWhere(SqlHelper::whereIn('{{h}}.hostid', $commonTemplateIds, true));
            $query->select($dSelect);

            foreach ($query->each() as $host) {
                $name = Hosts::find()->select('host')->where(['hostid' => $templateId, 'status' => 3])->asArray()->scalar();

                throw new ValidateException(60750101, t('zapi', 'Trigger in template "{src_name}" has dependency with trigger in template "{dst_name}".', [
                    'src_name' => $name,
                    'dst_name' => $host['host']
                ]));
            }
        }

        $query = HostsTemplates::find()
            ->select(['hostid', 'templateid'])
            ->where(SqlHelper::whereIn('hostid', $targetIds))
            ->andWhere(SqlHelper::whereIn('templateid', $templateIds))
            ->asArray();
        $linked = [];
        foreach ($query->each() as $host) {
            $linked[$host['templateid']][$host['hostid']] = true;
        }

        // add template linkages, if problems rollback later
        $hostsLinkageInserts = [];

        foreach ($templateIds as $templateid) {
            $linked_targets = array_key_exists($templateid, $linked) ? $linked[$templateid] : [];

            foreach ($targetIds as $targetId) {
                if (array_key_exists($targetId, $linked_targets)) {
                    continue;
                }

                $hostsLinkageInserts[] = ['hostid' => $targetId, 'templateid' => $templateid];
            }
        }

        if ($hostsLinkageInserts) {
            self::checkCircularLinkage($hostsLinkageInserts);
            self::checkDoubleLinkage($hostsLinkageInserts);

            $hostTemplateIds = DB::insertBatch(HostsTemplates::tableName(), $hostsLinkageInserts);

            foreach ($hostsLinkageInserts as &$host_linkage) {
                $host_linkage['hosttemplateid'] = array_shift($hostTemplateIds);
            }
            unset($host_linkage);
        }

        // check if all trigger templates are linked to host.
        // we try to find template that is not linked to hosts ($targetids)
        // and exists trigger which reference that template and template from ($templateids)
        $query = new Query();
        $query->from([
            't' => Triggers::tableName(),
            'f' => Functions::tableName(),
            'i' => Items::tableName(),
            'h' => Hosts::tableName(),
        ]);
        $query->where('{{f}}.triggerid={{t}}.triggerid')
            ->andWhere('{{i}}.itemid={{f}}.itemid')
            ->andWhere('{{h}}.hostid={{i}}.hostid')
            ->andWhere(['h.hostid' => HOST_STATUS_TEMPLATE]);

        $selectNull = new Expression('NULL');

        $query->andWhere([
            'NOT EXISTS',
            HostsTemplates::find()
                ->alias('ht')
                ->select($selectNull)
                ->where('ht.templateid=i.hostid')
                ->andWhere(SqlHelper::whereIn('{{ht}}.hostid', $targetIds))
                ->asArray()
        ]);

        $query->andWhere([
            'EXISTS',
            (new Query())->select($selectNull)
                ->from([
                    'ff' => Functions::tableName(),
                    'ii' => Items::tableName(),
                ])
                ->where('ff.itemid=ii.itemid')
                ->andWhere('ff.triggerid=t.triggerid')
                ->andWhere(SqlHelper::whereIn('{{ii}}.hostid', $templateIds))
        ]);

        $query->select(new Expression('DISTINCT {{h}}.host'))
            ->limit(1);

        if ($dbNotLinkedTpl = $query->scalar()) {
            throw new ValidateException(60750101, t('zapi', 'Trigger has items from template "{name}" that is not linked to host.', [
                'name' => $dbNotLinkedTpl,
            ]));
        }

        return $hostsLinkageInserts;
    }

    /**
     * Searches for circular linkages.
     *
     * @param array  $hostTemplates
     * @param string $hostTemplates[]['templateid']
     * @param string $hostTemplates[]['hostid']
     */
    private static function checkCircularLinkage(array $hostTemplates)
    {
        $links = [];

        foreach ($hostTemplates as $hostTemplate) {
            $links[$hostTemplate['templateid']][$hostTemplate['hostid']] = true;
        }

        $templateIds = array_keys($links);
        $_templateIds = $templateIds;

        do {
            $query = HostsTemplates::find()
                ->select(['hostid', 'templateid'])
                ->andWhere(SqlHelper::whereIn('hostid', $_templateIds))
                ->asArray();

            $_templateIds = [];

            foreach ($query->each() as $row) {
                if (!array_key_exists($row['templateid'], $links)) {
                    $_templateIds[$row['templateid']] = $row['templateid'];
                }
                $links[$row['templateid']][$row['hostid']] = true;
            }
        } while ($_templateIds);

        foreach ($templateIds as $templateId) {
            self::checkTemplateCircularLinkage($links, $templateId, $links[$templateId]);
        }
    }

    /**
     * Searches for circular linkages for specific template.
     *
     * @param array  $links[<templateid>][<hostid>]  The list of linkages.
     * @param string $templateid                     ID of the template to check circular linkages.
     * @param array  $hostids[<hostid>]
     *
     * @throws APIException if circular linkage is found.
     */
    private static function checkTemplateCircularLinkage(array $links, $templateId, array $hostIds): void
    {
        if (array_key_exists($templateId, $hostIds)) {
            throw new ValidateException(60750101, t('zapi', 'Circular template linkage is not allowed.'));
        }

        foreach ($hostIds as $hostid => $foo) {
            if (array_key_exists($hostid, $links)) {
                self::checkTemplateCircularLinkage($links, $templateId, $links[$hostid]);
            }
        }
    }

    /**
     * Searches for double linkages.
     *
     * @param array  $host_templates
     * @param string $host_templates[]['templateid']
     * @param string $host_templates[]['hostid']
     */
    private static function checkDoubleLinkage(array $host_templates)
    {
        $links = [];
        $templateids = [];
        $hostids = [];

        foreach ($host_templates as $host_template) {
            $links[$host_template['hostid']][$host_template['templateid']] = true;
            $templateids[$host_template['templateid']] = true;
            $hostids[$host_template['hostid']] = true;
        }

        $_hostids = array_keys($hostids);

        do {
            $query = HostsTemplates::find()
                ->select(['hostid'])
                ->andWhere(SqlHelper::whereIn('templateid', $_hostids));

            $_hostids = [];


            $_hostids = [];

            foreach ($query->each() as $row) {
                if (!array_key_exists($row['hostid'], $hostids)) {
                    $_hostids[$row['hostid']] = true;
                }

                $hostids[$row['hostid']] = true;
            }

            $_hostids = array_keys($_hostids);
        } while ($_hostids);

        $_templateids = array_keys($templateids + $hostids);
        $templateids = [];

        do {
            $query = HostsTemplates::find()
                ->select(['hostid', 'templateid'])
                ->andWhere(SqlHelper::whereIn('hostid', $_hostids));

            $_templateids = [];

            foreach ($query->each() as $row) {
                if (!array_key_exists($row['templateid'], $templateids)) {
                    $_templateids[$row['templateid']] = true;
                }

                $templateids[$row['templateid']] = true;
                $links[$row['hostid']][$row['templateid']] = true;
            }

            $_templateids = array_keys($_templateids);
        } while ($_templateids);

        foreach ($hostids as $hostid => $foo) {
            self::checkTemplateDoubleLinkage($links, $hostid);
        }
    }

    /**
     * Searches for double linkages.
     *
     * @param array  $links[<hostid>][<templateid>]  The list of linked template IDs by host ID.
     * @param string $hostid
     *
     * @throws APIException if double linkage is found.
     *
     * @return array  An array of the linked templates for the selected host.
     */
    private static function checkTemplateDoubleLinkage(array $links, $hostid): array
    {
        $templateids = $links[$hostid];

        foreach ($links[$hostid] as $templateid => $foo) {
            if (array_key_exists($templateid, $links)) {
                $_templateids = self::checkTemplateDoubleLinkage($links, $templateid);

                if (array_intersect_key($templateids, $_templateids)) {
                    throw new ValidateException(60750101, t('zapi', 'Template cannot be linked to another template more than once even through other templates.'));
                }

                $templateids += $_templateids;
            }
        }

        return $templateids;
    }

    protected function unlink($templateids, $targetids = null)
    {
        $cond = ['templateid' => $templateids];
        if (!is_null($targetids)) {
            $cond['hostid'] = $targetids;
        }
        if (DB::delete(HostsTemplates::tableName(), $cond)) {
            return;
        }
        if (!is_null($targetids)) {
            $hosts = Hosts::find()->select(['hostid', 'host'])
                ->where(SqlHelper::whereIn('hostid', $targetids))
                ->andWhere(['status' => [0, 1]])
                ->asArray()
                ->all();
        } else {
            $query = new Query();
            $query->from([
                'h' => Hosts::tableName(),
                'ht' => Hosts::tableName(),
            ]);
            $query->where('h.hostid=ht.hostid')
                ->andWhere(SqlHelper::whereIn('{{ht}}.templateid', $templateids));

            $hosts = $query->all();
        }

        if (!empty($hosts)) {
            $templates = Hosts::find()->select(['hostid', 'host'])
                ->where(SqlHelper::whereIn('hostid', $templateids))
                ->andWhere(['status' => 3])
                ->asArray()
                ->all();

            Yii::info(t('zapi', 'Templates "{src_name}" unlinked from hosts "{dst_name}".', [
                'src_name' => implode(', ', array_column($hosts, 'host')),
                'dst_name' => implode(', ', array_column($templates, 'host'))
            ]));
        }
    }

    /**
     * Creates user macros for hosts, templates and host prototypes.
     *
     * @param array  $hosts
     * @param array  $hosts[]['templateid|hostid']
     * @param array  $hosts[]['macros']             (optional)
     */
    protected function createHostMacros(array $hosts): void
    {
        $indexFieldName = $this instanceof TemplateService ? 'templateid' : 'hostid';

        $hostMacros = [];

        foreach ($hosts as $host) {
            if (array_key_exists('macros', $host)) {
                foreach ($host['macros'] as $macro) {
                    $hostMacros[] = ['hostid' => $host[$indexFieldName]] + $macro;
                }
            }
        }

        if ($hostMacros) {
            DB::insert(Hostmacro::tableName(), $hostMacros);
        }
    }

    /**
     * Adding "macros" to the each host object.
     *
     * @param array  $db_hosts
     *
     * @return array
     */
    protected function getHostMacros(array $dbHosts): array
    {
        foreach ($dbHosts as &$db_host) {
            $db_host['macros'] = [];
        }
        unset($db_host);

        $query = Hostmacro::find()
            ->where(SqlHelper::whereIn('hostid', array_keys($dbHosts)))
            ->asArray();

        foreach ($query->each() as $dbMacro) {
            $hostid = $dbMacro['hostid'];
            unset($dbMacro['hostid']);
            $dbHosts[$hostid]['macros'][$dbMacro['hostmacroid']] = $dbMacro;
        }

        return $dbHosts;
    }

    /**
     * Checks user macros for host.update, template.update and hostprototype.update methods.
     *
     * @param array  $hosts
     * @param array  $hosts[]['templateid|hostid']
     * @param array  $hosts[]['macros']             (optional)
     * @param array  $dbHosts
     * @param array  $dbHosts[<hostid>]['macros']
     *
     * @return array Array of passed hosts/templates with padded macros data, when it's necessary.
     *
     * @throws Exception if input of host macros data is invalid.
     */
    protected function validateHostMacros(array $hosts, array $dbHosts): array
    {
        $hostMacroDefaults = [
            'type' => DB::getDefault(Hostmacro::tableName(), 'type')
        ];

        $primaryKey = $this instanceof TemplateService ? 'templateid' : 'hostid';

        foreach ($hosts as $i1 => &$host) {
            if (!array_key_exists('macros', $host)) {
                continue;
            }

            $dbHost = $dbHosts[$host[$primaryKey]];
            $path = '/' . ($i1 + 1) . '/macros';

            $dbMacros = array_column($dbHost['macros'], 'hostmacroid', 'macro');
            $macros = [];

            foreach ($host['macros'] as $i2 => &$hostMacro) {
                if (!array_key_exists('hostmacroid', $hostMacro)) {
                    foreach (['macro', 'value'] as $field) {
                        if (!array_key_exists($field, $hostMacro)) {
                            self::exception(60750101, t('zapi', 'Invalid parameter {parameter}, {error}', [
                                'parameter' => $path . '/' . ($i2 + 1),
                                'error' => t('zapi', 'the parameter "{parameter}" is missing', [
                                    'parameter' => $field
                                ])
                            ]));
                        }
                    }

                    $hostMacro += $hostMacroDefaults;
                } else {
                    if (!array_key_exists($hostMacro['hostmacroid'], $dbHost['macros'])) {
                        self::exception(60750101, t('yii', 'Invalid data received for parameter "{param}".', [
                            'param' => 'macros'
                        ]));
                    }

                    $dbHostMacro = $dbHost['macros'][$hostMacro['hostmacroid']];

                    // Check if this is not an attempt to modify automatic host macro.
                    if ($this instanceof HostService) {
                        $macroFields = array_flip(['macro', 'value', 'type', 'description']);
                        $hostMacro += array_intersect_key($dbHostMacro, array_flip(['automatic']));

                        if (
                            $hostMacro['automatic'] == PRS_USERMACRO_AUTOMATIC
                            && array_diff_assoc(array_intersect_key($hostMacro, $macroFields), $dbHostMacro)
                        ) {
                            self::exception(60750101, t('zapi', 'Not allowed to modify automatic user macro "{macro}".', [
                                'macro' => $dbHostMacro['macro']
                            ]));
                        }
                    }

                    $hostMacro += array_intersect_key($dbHostMacro, array_flip(['macro', 'type']));

                    if ($hostMacro['type'] != $dbHostMacro['type']) {
                        if ($dbHostMacro['type'] == PRS_MACRO_TYPE_SECRET) {
                            $hostMacro += ['value' => ''];
                        }

                        if ($hostMacro['type'] == PRS_MACRO_TYPE_VAULT) {
                            $hostMacro += ['value' => $dbHostMacro['value']];
                        }
                    }

                    $macros[$hostMacro['hostmacroid']] = $hostMacro['macro'];
                }

                if (array_key_exists('value', $hostMacro) && $hostMacro['type'] == PRS_MACRO_TYPE_VAULT) {
                    $validator = new VaultSecretValidator([
                        'provider' => SettingHelper::get(SettingHelper::VAULT_PROVIDER)
                    ]);
                    $validator->setPath([$path, ($i2 + 1), 'value']);
                    if (!$validator->validate($hostMacro['value'], $error)) {
                        self::exception(60750101, $error);
                    }
                }
            }
            unset($hostMacro);

            // Checking for cross renaming of existing macros.
            foreach ($macros as $hostmacroid => $macro) {
                if (
                    array_key_exists($macro, $dbMacros) && bccomp($hostmacroid, $dbMacros[$macro]) != 0
                    && array_key_exists($dbMacros[$macro], $macros)
                ) {
                    $name = Hosts::find()
                        ->select('name')
                        ->where(SqlHelper::whereIn('hostid', $host[$primaryKey]))
                        ->asArray()
                        ->scalar();
                    self::exception(60750101, t('zapi', 'Macro "{macro}" already exists on "{name}".', [
                        'macro' => $macro,
                        'name' => $name,
                    ]));
                }
            }

            $buf = array_column($host['macros'], 'macro');
            if ($diff = array_diff_key($buf, array_unique($buf))) {
                $val = current($diff);
                self::exception(60750101, t('zapi', 'Invalid parameter {parameter}, {error}', [
                    'parameter' => $path,
                    'error' => t('zapi', 'value {value} already exists', [
                        'value' => "(macro)=({$val})"
                    ])
                ]));
            }
        }
        unset($host);

        return $hosts;
    }
}
