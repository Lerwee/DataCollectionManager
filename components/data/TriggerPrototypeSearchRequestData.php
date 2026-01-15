<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\modules\libzbx\models\Items;

/**
 * Class TriggerPrototypeSearchRequestData
 * @package app\customs\zapi\components\data
 */
class TriggerPrototypeSearchRequestData extends RequestData
{
    /**
     * @return Result
     */
    public function validate(): Result
    {
        $discoveryRule = Items::find()->select(['name', 'itemid', 'hostid'])
            ->where(['itemid' => $this->getRequest('parent_discoveryid')])
            ->andWhere(['flags' => PRS_FLAG_DISCOVERY_RULE])
            ->asArray()->one();
        if (empty($discoveryRule)) {
            return $this->error(60750004);
        }
        $sortField = $this->getRequest('sort','description');
        $sortOrder = $this->getRequest('sortorder', PRS_SORT_UP);
        $filter_triggerids = $this->getRequest('filter_triggerids', []);

        $data = [
            'parent_discoveryid' => $this->getRequest('parent_discoveryid'),
            'discovery_rule' => $discoveryRule,
            'hostid' => $discoveryRule['hostid'],
            'triggers' => [],
            'sort' => $sortField,
            'sortorder' => $sortOrder,
            'dependencyTriggers' => [],
            'context' => $this->getRequest('context')
        ];


        // get triggers
        $options = [
            'editable' => true,
            'output' => ['triggerid', $sortField],
            'discoveryids' => $data['parent_discoveryid'],
            'sortfield' => $sortField,
        ];
        if ($filter_triggerids) {
            $options['triggerids'] = $filter_triggerids;
        }

        return $this->success($options);
    }
}