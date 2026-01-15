<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;

/**
 * Class ItemPrototypeSearchRequestData
 * @package app\customs\zapi\components\data
 */
class ItemPrototypeSearchRequestData extends RequestData
{
    /**
     * @return Result
     */
    public function validate(): Result
    {
        $sortField = $this->getRequest('sort', 'name');
        $sortOrder = $this->getRequest('sortorder', PRS_SORT_UP);
        $filter_itemids = $this->getRequest('filter_itemids', []);

        $options = [
            'discoveryids' => $this->getRequest('parent_discoveryid'),
            'output' => API_OUTPUT_EXTEND,
            'editable' => true,
            'selectTags' => ['tag', 'value'],
            'sortfield' => $sortField,
            'sortorder' => $sortOrder,
        ];
        if ($filter_itemids) {
            $options['itemids'] = filter_integer((array)$filter_itemids);
        }
        return $this->success($options);
    }
}