<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Items;

/**
 * Class ItemFormRequestData
 * @package app\customs\zapi\components\data
 */
class ItemBatchFormRequestData extends ItemRequestData
{
    public function validate(): Result
    {
        $item_prototypes = (bool)$this->getRequest('prototype', false);
        $default = [
            'type' => DB::getDefault('items', 'type'),
            'value_type' => ITEM_VALUE_TYPE_UINT64,
            'units' => DB::getDefault('items', 'units'),
            'history' => ITEM_NO_STORAGE_VALUE,
            'trends' => ITEM_NO_STORAGE_VALUE,
            'valuemapid' => 0,
            'logtimefmt' => DB::getDefault('items', 'logtimefmt'),
            'description' => DB::getDefault('items', 'description'),
            'status' => DB::getDefault('items', 'status'),
            'discover' => DB::getDefault('items', 'discover'),
            'tags' => [],
            'preprocessing' => [],

            // The fields used for multiple item types.
            'interfaceid' => 0,
            'authtype' => DB::getDefault('items', 'authtype'),
            'username' => DB::getDefault('items', 'username'),
            'password' => DB::getDefault('items', 'password'),
            'timeout' => '',
            'delay' => DB::getDefault('items', 'delay'),
            'trapper_hosts' => DB::getDefault('items', 'trapper_hosts'),

            // Dependent item type specific fields.
            'master_itemid' => 0,

            // HTTP Agent item type specific fields.
            'url' => DB::getDefault('items', 'url'),
            'post_type' => DB::getDefault('items', 'post_type'),
            'posts' => DB::getDefault('items', 'posts'),
            'headers' => [],
            'allow_traps' => DB::getDefault('items', 'allow_traps'),

            // JMX item type specific fields.
            'jmx_endpoint' => DB::getDefault('items', 'jmx_endpoint'),

            // SSH item type specific fields.
            'publickey' => DB::getDefault('items', 'publickey'),
            'privatekey' => DB::getDefault('items', 'privatekey')
        ];
        $input = array_intersect_key($default, $this->getRequest('visible', []));
        $this->getInputs($input, array_keys($input));

        $options = [];

        if (array_key_exists('tags', $input)) {
            $input['tags'] = ItemHelper::prepareItemTags($input['tags']);
            $fieldRules = [
                'tag' => [Utf8StringValidator::class],
                'value' => [Utf8StringValidator::class],
            ];

            if (ValidateHelper::validateObjects($input['tags'], $fieldRules, ['uniq' => [['tag', 'value']]], $error)) {
                return $this->error(60750201, $error);
            }

            $tag_values = [];

            foreach ($input['tags'] as $tag) {
                $tag_values[$tag['tag']][] = $tag['value'];
            }

            $options['selectTags'] = ['tag', 'value'];
        }

        if (array_key_exists('preprocessing', $input)) {
            $input['preprocessing'] = ItemHelper::normalizeItemPreprocessingSteps($input['preprocessing']);
        }

        if (array_key_exists('delay', $input)) {
            $delay_flex = $this->getRequest('delay_flex', []);

            if (!ItemHelper::isValidCustomIntervals($delay_flex)) {
                return $this->error(60750201, $item_prototypes ? t('zapi', 'Cannot update item prototypes') : t('zapi', 'Cannot update items'));
            }

            $input['delay'] = ItemHelper::getDelayWithCustomIntervals($input['delay'], $delay_flex);
        }

        if (array_key_exists('headers', $input)) {
            $input['headers'] = ItemHelper::prepareItemHeaders($input['headers']);
        }

        $itemids = $this->getRequest('ids');

        if ($item_prototypes) {
            $db_items = ItemHelper::getItemPrototypes([
                    'output' => ['hostid', 'type', 'key_', 'value_type', 'templateid', 'authtype', 'allow_traps'],
                    'selectHosts' => ['status'],
                    'itemids' => $itemids,
                    'preservekeys' => true
                ] + $options);
        } else {
            $db_items = ItemHelper::getItems([
                    'output' => ['hostid', 'type', 'key_', 'value_type', 'templateid', 'flags', 'authtype', 'allow_traps'],
                    'selectHosts' => ['status'],
                    'itemids' => $itemids,
                    'preservekeys' => true
                ] + $options);
        }
        if (empty($db_items)) {
            return $this->error(60750203);
        }

        $items = [];

        foreach ($itemids as $itemid) {
            $db_item = $db_items[$itemid];

            if ($item_prototypes) {
                $db_item['flags'] = PRS_FLAG_DISCOVERY_PROTOTYPE;
            }

            $item = array_intersect_key($input, $this->getSanitizedItemFields($input + $db_item));

            if (array_key_exists('tags', $input)) {
                $item['tags'] = $this->getTagsToUpdate($db_item, $tag_values);
            }

            if ($item) {
                $items[] = ['itemid' => $itemid] + $item;
            }
        }
        return $this->success($items);
    }

    /**
     * Get item tags to update or null if no tags to update found.
     *
     * @param array $db_item
     * @param array $tag_values
     *
     * @return array
     */
    private function getTagsToUpdate(array $db_item, array $tag_values): ?array
    {
        $tags = [];

        switch ($this->getRequest('mass_update_tags', PRS_ACTION_ADD)) {
            case PRS_ACTION_ADD:
                foreach ($db_item['tags'] as $db_tag) {
                    if (array_key_exists($db_tag['tag'], $tag_values)
                        && in_array($db_tag['value'], $tag_values[$db_tag['tag']])) {
                        unset($tag_values[$db_tag['tag']][$db_tag['value']]);
                    }
                }

                foreach ($tag_values as $tag => $values) {
                    foreach ($values as $value) {
                        $tags[] = ['tag' => (string)$tag, 'value' => $value];
                    }
                }

                $tags = array_merge($db_item['tags'], $tags);
                break;

            case PRS_ACTION_REPLACE:
                foreach ($tag_values as $tag => $values) {
                    foreach ($values as $value) {
                        $tags[] = ['tag' => (string)$tag, 'value' => $value];
                    }
                }

                ArrayHelper::multisort($tags, ['tag', 'value']);
                ArrayHelper::multisort($db_item['tags'], ['tag', 'value']);
                break;

            case PRS_ACTION_REMOVE:
                foreach ($db_item['tags'] as $db_tag) {
                    if (!array_key_exists($db_tag['tag'], $tag_values)
                        || !in_array($db_tag['value'], $tag_values[$db_tag['tag']])) {
                        $tags[] = ['tag' => $db_tag['tag'], 'value' => $db_tag['value']];
                    }
                }
                break;
        }

        return $tags;
    }

    /**
     * Get several input parameters.
     *
     * @param array $var
     * @param array $names
     */
    protected function getInputs(&$var, $names)
    {
        foreach ($names as $name) {
            if (array_key_exists($name, $this->data)) {
                $var[$name] = $this->getRequest($name);
            }
        }
    }
}