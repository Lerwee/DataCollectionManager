<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;

/**
 * Class HostPrototypeSearchRequestData
 * @package app\customs\zapi\components\data
 */
class HostPrototypeSearchRequestData extends HostPrototypeRequestData
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
        // extract($request->getData());

        $sortField = $this->getRequest('sort', 'name');
        $sortOrder = $this->getRequest('sortorder', PRS_SORT_UP);

        $data = [
            'discoveryids' => $this->getRequest('parent_discoveryid'),
            'output' => API_OUTPUT_EXTEND,
            'selectTemplates' => ['templateid', 'name'],
            'selectTags' => ['tag', 'value'],
            'sortfield' => $sortField,
            'sortorder' => $sortOrder,
        ];

        return $this->success($data);
    }
}
