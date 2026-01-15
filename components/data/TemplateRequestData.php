<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\customs\zapi\services\HostGroupService;

/**
 * Class TemplateRequestData
 * @package app\customs\zapi\components\Template
 */
class TemplateRequestData extends RequestData
{
    /**
     * @var string
     */
    public $action = 'create';

    /**
     * @var integer
     */
    public $templateId;

    /**
     * @return Result
     */
    public function validate(): Result
    {
        $tags = $this->getRequest('tags', []);
        foreach ($tags as $key => $tag) {
            // remove empty new tag lines
            if ($tag['tag'] === '' && $tag['value'] === '') {
                unset($tags[$key]);
                continue;
            }

            // remove inherited tags
            if (array_key_exists('type', $tag) && !($tag['type'] & PRS_PROPERTY_OWN)) {
                unset($tags[$key]);
            } else {
                unset($tags[$key]['type']);
            }
        }

        // Remove inherited macros data (actions: 'add', 'update' and 'form').
        $macros = $this->cleanInheritedMacros($this->getRequest('macros', []));
        // Remove empty new macro lines.
        $macros = array_filter($macros, function ($macro) {
            $keys = array_flip(['hostmacroid', 'macro', 'value', 'description']);
            return (bool) array_filter(array_intersect_key($macro, $keys));
        });

        foreach ($macros as &$macro) {
            unset($macro['discovery_state']);
            unset($macro['allow_revert']);
        }
        unset($macro);

        if ($this->action == 'create') {
            $macros = array_map(function($macro) {
                return array_diff_key($macro, array_flip(['hostmacroid']));
            }, $macros);
        }

        $this->templateId = $this->getRequest('templateid', 0);

        // Add new group.
        $groups = $this->getRequest('groups', []);
        // $newGroups = [];

        // foreach ($groups as $idx => $group) {
        //     if (is_array($group) && array_key_exists('new', $group)) {
        //         $newGroups[] = ['name' => $group['new']];
        //         unset($groups[$idx]);
        //     }
        // }

        // if ($newGroups) {
        //     $result = HostGroupService::instance()->createByInternal($newGroups);

        //     if (!$result->isSuccess()) {
        //         return $result;
        //     }

        //     $groups = array_merge($groups, $result->getData());
        // }

        // Linked templates.
        $templates = [];
        $linkTemplates = $this->getRequest('templates', []);
        foreach ($linkTemplates as $templateId) {
            $templates[] = ['templateid' => $templateId];
        }

        $templateName = $this->getRequest('template_name', '');

        // create / update template
        $template = [
            'host' => $templateName,
            'name' => ($this->getRequest('visiblename', '') === '') ? $templateName : $this->getRequest('visiblename'),
            'description' => $this->getRequest('description', ''),
            'groups' => prs_toObject($groups, 'groupid'),
            'templates' => $templates,
            'tags' => $tags,
            'macros' => $macros,
            'valuemaps' => $this->getRequest('valuemaps', [])
        ];

        if ($this->action == 'update') {
            $clearTemplates = $this->getRequest('clear_templates', []);
            $template['templateid'] = $this->templateId;
            $template['templates_clear'] = prs_toObject($clearTemplates, 'templateid');
        }

        return $this->success($template);
    }

    /**
     * Remove inherited macros data.
     *
     * @param array $macros
     *
     * @return array
     */
    public function cleanInheritedMacros(array $macros)
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
