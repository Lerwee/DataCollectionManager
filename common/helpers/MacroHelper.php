<?php

namespace app\customs\zapi\common\helpers;

use app\customs\zapi\common\parsers\CUserMacroParser;
use app\customs\zapi\models\search\UserMacroSearch;

/**
 * Class MacroHelper
 * @package app\customs\zapi\common\helpers
 */
class MacroHelper
{
    public const DISCOVERY_STATE_AUTOMATIC = 0x1;
	public const DISCOVERY_STATE_CONVERTING = 0x2;
	public const DISCOVERY_STATE_MANUAL = 0x3;
    /**
     * Returns macro without spaces and curly braces.
     *
     * "{$MACRO}" => "MACRO"
     * "{$MACRO:}" => "MACRO:context:"
     * "{$MACRO: /var}" => "MACRO:context:/var"
     * "{$MACRO: /"var"}" => "MACRO:context:/var"
     * "{$MACRO:regex: ^[a-z]+}" => "MACRO:regex:^[a-z]+"
     *
     * @param string $macro
     *
     * @return string
     */
    public static function trimMacro(string $macro): string {
        $user_macro_parser = new CUserMacroParser();

        $user_macro_parser->parse($macro);

        $macro = $user_macro_parser->getMacro();
        $context = $user_macro_parser->getContext();
        $regex = $user_macro_parser->getRegex();

        if ($context !== null) {
            $macro .= ':context:'.$context;
        }
        elseif ($regex !== null) {
            $macro .= ':regex:'.$regex;
        }

        return $macro;
    }

    /**
     * 获取全局宏数据
     *
     * @param  array $params
     * @return array
     */
    public static function getGlobalMacros(array $params)
    {
        $searcher = new UserMacroSearch();
        $searcher->is_all = true;
        $searcher->globalmacro = true;

        if (empty($params['output'])) {
            $searcher->output = ['globalmacroid', 'macro', 'value', 'type', 'description'];
        }
        $provider = $searcher->search($params);
        return $provider->getModels();
    }

    public static function getHostMacros(array $params)
    {
        $searcher = new UserMacroSearch();
        $searcher->is_all = true;
        $searcher->globalmacro = null;
        $provider = $searcher->search($params);
        return $provider->getModels();
    }

    
    /**
     * Get list of inherited macros by host ids.
     *
     * Returns an array like:
     *   array(
     *       '{$MACRO}' => array(
     *           'macro' => '{$MACRO}',
     *           'parent_host' => array(                <- optional
     *               'value' => 'parent host level value',
     *               'type' => 0,
     *               'description' => ''
     *           ),
     *           'template' => array(                   <- optional
     *               'value' => 'template-level value'
     *               'templateid' => 10001,
     *               'name' => 'Template OS Linux by Perseus agent'
     *           ),
     *           'global' => array(                     <- optional
     *               'value' => 'global-level value'
     *           )
     *       )
     *   )
     *
     * @param array     $hostids        Host or template ids.
     * @param int|null  $parent_hostid  Parent host id of host prototype.
     *
     * @return array
     */
    public static function getInheritedMacros(array $hostids, ?int $parent_hostid = null): array
    {
        $user_macro_parser = new CUserMacroParser();

        $all_macros = [];
        $global_macros = [];

        $db_global_macros = static::getHostMacros([
            'output' => ['macro', 'value', 'description', 'type'],
            'globalmacro' => true,
        ]);

        foreach ($db_global_macros as $db_global_macro) {
            $all_macros[$db_global_macro['macro']] = true;
            $global_macros[$db_global_macro['macro']] = [
                'value' => getMacroConfigValue($db_global_macro),
                'description' => $db_global_macro['description'],
                'type' => $db_global_macro['type'],
            ];
        }

        // hostid => array('name' => name, 'macros' => array(macro => value), 'templateids' => array(templateid))
        $hosts = [];

        $templateids = $hostids;

        do {
            $db_templates = TemplateHelper::getTemplates([
                'output' => ['name'],
                'selectParentTemplates' => ['templateid'],
                'selectMacros' => ['macro', 'value', 'description', 'type'],
                'templateids' => $templateids,
                'preservekeys' => true,
            ]);

            $templateids = [];

            foreach ($db_templates as $hostid => $db_template) {
                $hosts[$hostid] = [
                    'templateid' => $hostid,
                    'name' => $db_template['name'],
                    'templateids' => prs_objectValues($db_template['parentTemplates'], 'templateid'),
                    'macros' => [],
                ];

                /*
                 * Global macros are overwritten by template macros and template macros are overwritten by host macros.
                 * Macros with contexts require additional checking for contexts, since {$MACRO:} is the same as
                 * {$MACRO:""}.
                 */
                foreach ($db_template['macros'] as $dbMacro) {
                    if (array_key_exists($dbMacro['macro'], $all_macros)) {
                        $hosts[$hostid]['macros'][$dbMacro['macro']] = [
                            'value' => getMacroConfigValue($dbMacro),
                            'description' => $dbMacro['description'],
                            'type' => $dbMacro['type'],
                        ];
                        $all_macros[$dbMacro['macro']] = true;
                    } else {
                        $user_macro_parser->parse($dbMacro['macro']);
                        $tpl_macro = $user_macro_parser->getMacro();
                        $tpl_context = $user_macro_parser->getContext();

                        if ($tpl_context === null) {
                            $hosts[$hostid]['macros'][$dbMacro['macro']] = [
                                'value' => getMacroConfigValue($dbMacro),
                                'description' => $dbMacro['description'],
                                'type' => $dbMacro['type'],
                            ];
                            $all_macros[$dbMacro['macro']] = true;
                        } else {
                            $match_found = false;

                            foreach ($global_macros as $global_macro => $global_value) {
                                $user_macro_parser->parse($global_macro);
                                $gbl_macro = $user_macro_parser->getMacro();
                                $gbl_context = $user_macro_parser->getContext();

                                if ($tpl_macro === $gbl_macro && $tpl_context === $gbl_context) {
                                    $match_found = true;

                                    unset($global_macros[$global_macro], $hosts[$hostid][$global_macro],
                                        $all_macros[$global_macro]
                                    );

                                    $hosts[$hostid]['macros'][$dbMacro['macro']] = [
                                        'value' => getMacroConfigValue($dbMacro),
                                        'description' => $dbMacro['description'],
                                        'type' => $dbMacro['type'],
                                    ];
                                    $all_macros[$dbMacro['macro']] = true;
                                    $global_macros[$dbMacro['macro']] = $global_value;

                                    break;
                                }
                            }

                            if (!$match_found) {
                                $hosts[$hostid]['macros'][$dbMacro['macro']] = [
                                    'value' => getMacroConfigValue($dbMacro),
                                    'description' => $dbMacro['description'],
                                    'type' => $dbMacro['type'],
                                ];
                                $all_macros[$dbMacro['macro']] = true;
                            }
                        }
                    }
                }
            }

            foreach ($db_templates as $db_template) {
                // only unprocessed templates will be populated
                foreach ($db_template['parentTemplates'] as $template) {
                    if (!array_key_exists($template['templateid'], $hosts)) {
                        $templateids[$template['templateid']] = $template['templateid'];
                    }
                }
            }
        } while ($templateids);

        $all_templates = [];
        $inherited_macros = [];
        $parent_host_macros = [];

        if ($parent_hostid !== null) {
            $parent_host_macros = static::getHostMacros([
                'output' => ['macro', 'type', 'value', 'description'],
                'hostids' => [$parent_hostid],
            ]);

            $parent_host_macros = array_column($parent_host_macros, null, 'macro');
            $all_macros += array_fill_keys(array_keys($parent_host_macros), true);
        }

        $all_macros = array_keys($all_macros);

        // resolving
        foreach ($all_macros as $macro) {
            $inherited_macro = ['macro' => $macro];

            if (array_key_exists($macro, $parent_host_macros)) {
                $inherited_macro['parent_host'] = [
                    'value' => getMacroConfigValue($parent_host_macros[$macro]),
                    'description' => $parent_host_macros[$macro]['description'],
                    'type' => $parent_host_macros[$macro]['type'],
                ];
            } elseif (array_key_exists($macro, $global_macros)) {
                $inherited_macro['global'] = [
                    'value' => $global_macros[$macro]['value'],
                    'description' => $global_macros[$macro]['description'],
                    'type' => $global_macros[$macro]['type'],
                ];
            }

            $templateids = $hostids;

            do {
                natsort($templateids);

                foreach ($templateids as $templateid) {
                    if (array_key_exists($templateid, $hosts) && array_key_exists($macro, $hosts[$templateid]['macros'])) {
                        $inherited_macro['template'] = [
                            'value' => $hosts[$templateid]['macros'][$macro]['value'],
                            'description' => $hosts[$templateid]['macros'][$macro]['description'],
                            'templateid' => $hosts[$templateid]['templateid'],
                            'name' => $hosts[$templateid]['name'],
                            'rights' => PERM_READ,
                            'type' => $hosts[$templateid]['macros'][$macro]['type'],
                        ];

                        if (!array_key_exists($hosts[$templateid]['templateid'], $all_templates)) {
                            $all_templates[$hosts[$templateid]['templateid']] = [];
                        }
                        $all_templates[$hosts[$templateid]['templateid']][] = &$inherited_macro['template'];

                        break 2;
                    }
                }

                $parent_templateids = [];

                foreach ($templateids as $templateid) {
                    if (array_key_exists($templateid, $hosts)) {
                        foreach ($hosts[$templateid]['templateids'] as $templateid) {
                            $parent_templateids[$templateid] = $templateid;
                        }
                    }
                }

                $templateids = $parent_templateids;
            } while ($templateids);

            $inherited_macros[$macro] = $inherited_macro;
        }

        // checking permissions
        if ($all_templates) {
            $db_templates = TemplateHelper::getTemplates([
                'output' => ['templateid'],
                'templateids' => array_keys($all_templates),
                'editable' => true,
            ]);

            foreach ($db_templates as $db_template) {
                foreach ($all_templates[$db_template['templateid']] as &$template) {
                    $template['rights'] = PERM_READ_WRITE;
                }
                unset($template);
            }
        }

        return $inherited_macros;
    }

    /**
     * Merge list of inherited and host-level macros.
     *
     * Returns an array like:
     *   array(
     *       '{$MACRO}' => array(
     *           'macro' => '{$MACRO}',
     *           'type' => 0,                           <- PRS_MACRO_TYPE_TEXT or PRS_MACRO_TYPE_SECRET
     *           'inherited_type' => 0x03,              <- PRS_PROPERTY_INHERITED, PRS_PROPERTY_OWN or PRS_PROPERTY_BOTH
     *           'value' => 'effective value',
     *           'hostmacroid' => 7532,                 <- optional
     *           'parent_host' => array(                <- optional
     *               'value' => 'parent host value',
     *               'type' => 0,
     *               'description' => ''
     *           ),
     *           'template' => array(                   <- optional
     *               'value' => 'template-level value'
     *               'templateid' => 10001,
     *               'name' => 'Template OS Linux by Perseus agent'
     *           ),
     *           'global' => array(                     <- optional
     *               'value' => 'global-level value'
     *           )
     *       )
     *   )
     *
     * @param array $host_macros       The list of host macros.
     * @param array $inherited_macros  The list of inherited macros (the output of the getInheritedMacros() function).
     *
     * @return array
     */
    public static function mergeInheritedMacros(array $host_macros, array $inherited_macros): array
    {
        $user_macro_parser = new CUserMacroParser();
        $inherit_order = ['parent_host', 'template', 'global'];

        foreach ($inherited_macros as &$inherited_macro) {
            [$inherited_level] = array_values(array_intersect($inherit_order, array_keys($inherited_macro)));
            $inherited_macro['inherited_type'] = PRS_PROPERTY_INHERITED;
            $inherited_macro['inherited_level'] = $inherited_level;
            $inherited_macro['value'] = $inherited_macro[$inherited_level]['value'];
            $inherited_macro['type'] = $inherited_macro[$inherited_level]['type'];
            $inherited_macro['description'] = $inherited_macro[$inherited_level]['description'];

            // Secret macro value cannot be inherited.
            if ($inherited_macro['type'] == PRS_MACRO_TYPE_SECRET) {
                unset($inherited_macro['value']);
            }
        }
        unset($inherited_macro);

        /*
         * Global macros and template macros are overwritten by host macros. Macros with contexts require additional
         * checking for contexts, since {$MACRO:} is the same as {$MACRO:""}.
         */
        foreach ($host_macros as &$host_macro) {
            // Secret macro value cannot be inherited.
            if ($host_macro['type'] == PRS_MACRO_TYPE_SECRET) {
                unset($inherited_macros[$host_macro['macro']]['value']);
            }

            if (array_key_exists($host_macro['macro'], $inherited_macros)) {
                $host_macro = array_merge($inherited_macros[$host_macro['macro']], $host_macro);
                unset($inherited_macros[$host_macro['macro']]);
            } else {
                /*
                 * Cannot use array dereferencing because "$host_macro['macro']" may contain invalid macros
                 * which results in empty array.
                 */
                if ($user_macro_parser->parse($host_macro['macro']) == CUserMacroParser::PARSE_SUCCESS) {
                    $hst_macro = $user_macro_parser->getMacro();
                    $hst_context = $user_macro_parser->getContext();

                    if ($hst_context === null) {
                        $host_macro['inherited_type'] = 0x00;
                    } else {
                        $match_found = false;

                        foreach ($inherited_macros as $inherited_macro => $inherited_values) {
                            // Safe to use array dereferencing since these values come from database.
                            $user_macro_parser->parse($inherited_macro);
                            $inh_macro = $user_macro_parser->getMacro();
                            $inh_context = $user_macro_parser->getContext();

                            if ($hst_macro === $inh_macro && $hst_context === $inh_context) {
                                $match_found = true;

                                $host_macro = array_merge($inherited_macros[$inherited_macro], $host_macro);
                                unset($inherited_macros[$inherited_macro]);

                                break;
                            }
                        }

                        if (!$match_found) {
                            $host_macro['inherited_type'] = 0x00;
                        }
                    }
                } else {
                    $host_macro['inherited_type'] = 0x00;
                }
            }

            $host_macro['inherited_type'] |= PRS_PROPERTY_OWN;
        }
        unset($host_macro);

        foreach ($inherited_macros as $inherited_macro) {
            $host_macros[] = $inherited_macro;
        }

        return $host_macros;
    }

    /**
     * Remove inherited macros data.
     *
     * @param array $macros
     *
     * @return array
     */
    public static function cleanInheritedMacros(array $macros)
    {
        foreach ($macros as $idx => $macro) {
            if (array_key_exists('inherited_type', $macro) && !($macro['inherited_type'] & PRS_PROPERTY_OWN)) {
                unset($macros[$idx]);
            } else {
                unset($macros[$idx]['inherited_type'], $macros[$idx]['inherited']);
            }
        }

        return $macros;
    }
}