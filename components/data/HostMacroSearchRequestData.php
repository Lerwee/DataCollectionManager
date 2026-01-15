<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\MacroHelper;

/**
 * Class HostMacroSearchRequestData
 * @package app\customs\zapi\components\data
 */
class HostMacroSearchRequestData extends RequestData
{
    /**
     * @return Result
     */
    public function validate(): Result
    {
        $macros = $this->getRequest('macros', []);
        $show_inherited_macros = (bool) $this->getRequest('show_inherited_macros', 0);
        $readonly = (bool) $this->getRequest('readonly', 0);
        $parent_hostid = array_key_exists('parent_hostid', $this->data) ? $this->getRequest('parent_hostid') : null;
        if ($macros) {
            $macros = MacroHelper::cleanInheritedMacros($macros);

            // Remove empty new macro lines.
            $macros = array_filter($macros, function ($macro) {
                $keys = array_flip(['hostmacroid', 'macro', 'value', 'description']);

                return (bool) array_filter(array_intersect_key($macro, $keys));
            });
        }

        if ($show_inherited_macros) {
            $macros = MacroHelper::mergeInheritedMacros($macros,
                MacroHelper::getInheritedMacros($this->getRequest('templateids', []), $parent_hostid)
            );
        }

        $macros = array_values(order_macros($macros, 'macro'));

        if (!$macros && !$readonly) {
            $macro = ['macro' => '', 'value' => '', 'description' => '', 'type' => PRS_MACRO_TYPE_TEXT];
            if ($show_inherited_macros) {
                $macro['inherited_type'] = PRS_PROPERTY_OWN;
            }
            $macros[] = $macro;
        }

        foreach ($macros as &$macro) {
            if (!array_key_exists('discovery_state', $macro)) {
                $macro['discovery_state'] = MacroHelper::DISCOVERY_STATE_MANUAL;
            }

            self::addMacroOriginalValues($macro);
        }
        unset($macro);

        $data = [
            'macros' => $macros,
            'show_inherited_macros' => $show_inherited_macros,
            'readonly' => $readonly,
        ];

        if ($parent_hostid !== null) {
            $data['parent_hostid'] = $parent_hostid;
        }

        return $this->success($data);
    }

    /**
     * Create array of original macro values from input fields.
     *
     * @param array  $macro
     * @param string $macro['original_value']
     * @param string $macro['original_description']
     * @param string $macro['original_macro_type']
     */
    protected static function addMacroOriginalValues(array &$macro)
    {
        if ($macro['discovery_state'] == MacroHelper::DISCOVERY_STATE_MANUAL) {
            return;
        }

        $field_keys_map = [
            'original_value' => 'value',
            'original_description' => 'description',
            'original_macro_type' => 'type',
        ];

        $macro['original'] = array_intersect_key($macro, $field_keys_map);
        $macro['original'] = CArrayHelper::renameKeys($macro['original'], $field_keys_map);

        foreach (array_keys($field_keys_map) as $key) {
            unset($macro[$key]);
        }
    }
}
