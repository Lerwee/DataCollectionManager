<?php

namespace app\customs\zapi\common\helpers;

use app\customs\zapi\models\search\DiscoverRuleSearch;
use yii\db\Exception;

/**
 * Class DiscoverRuleHelper
 * @package app\customs\zapi\common\helpers
 */
class DiscoverRuleHelper extends ItemHelper
{
    /**
     * @param array $options
     * @return array
     * @throws Exception
     */
    public static function getDiscoverRules(array $options): array
    {
        $search = new DiscoverRuleSearch();
        $search->is_all = true;
        $provider = $search->search($options);
        return $provider->getModels();
    }

    /**
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function getDiscoverRuleFromData(array $data): array
    {
        $items = static::getDiscoverRules([
            'itemids' => $data['itemid'],
            'output' => API_OUTPUT_EXTEND,
            'selectHosts' => ['hostid', 'name', 'status', 'flags'],
            'selectFilter' => ['formula', 'evaltype', 'conditions'],
            'selectLLDMacroPaths' => ['lld_macro', 'path'],
            'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
            'selectOverrides' => ['name', 'step', 'stop', 'filter', 'operations'],
            'editable' => true
        ]);
        if (empty($items)) {
            return [];
        }
        $item = reset($items);
        $host = current($item['hosts'] ?? []);
        $item['host_status'] = $host ? $host['status'] : -1;
        foreach ($item['overrides'] as &$override) {
            if (!array_key_exists('operations', $override)) {
                continue;
            }

            foreach ($override['operations'] as &$operation) {
                if (array_key_exists('optag', $operation)) {
                    CArrayHelper::sort($operation['optag'], ['tag', 'value']);
                    $operation['optag'] = array_values($operation['optag']);
                }
            }
            unset($operation);
        }
        unset($override);
        if ($item['type'] == ITEM_TYPE_DEPENDENT) {
            // Unset master item if submitted form has no master_itemid set.
            if ($item['master_itemid']) {
                $master_item_options = [
                    'output' => ['itemid', 'type', 'hostid', 'name', 'key_'],
                    'itemids' => $item['master_itemid'],
                    'webitems' => true
                ];
                $master_items = self::getItems($master_item_options);
                if ($master_items) {
                    $item['master_item'] = reset($master_items);
                }
            }
        }
        $data = self::formatFormData($item, $data);
        $i = 0;
        foreach ($data['preprocessing'] as &$step) {
            if ($step['type'] == PRS_PREPROC_SCRIPT) {
                $step['params'] = [$step['params'], ''];
            }
            else {
                $step['params'] = explode("\n", $step['params']);
            }
            $step['sortorder'] = $i++;
        }
        unset($step);

        $data['lifetime'] = $item['lifetime'];
        $data['evaltype'] = $item['filter']['evaltype'];
        $data['formula'] = $item['filter']['formula'];
        $data['conditions'] = static::sortLldRuleFilterConditions($item['filter']['conditions'], $item['filter']['evaltype']);
        $data['lld_macro_paths'] = $item['lld_macro_paths'];
        $data['overrides'] = $item['overrides'];

        foreach ($data['overrides'] as &$override) {
            if ($override['filter']['conditions']) {
                $override['filter']['conditions'] = static::sortLldRuleFilterConditions($override['filter']['conditions'],
                    $override['filter']['evaltype']
                );
            }
        }
        unset($override);

        // Sort overrides to be listed in step order.
        CArrayHelper::sort($data['overrides'], ['step']);
        return $data;
    }
}