<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\modules\libzbx\models\Hosts;
use yii\db\Query;

/**
 * Class TriggerSearchRequestData
 * @package app\customs\zapi\components\data
 */
class TriggerSearchRequestData extends RequestData
{
    /**
     * @return Result
     */
    public function validate(): Result
    {
        $data = [
            'context' => $this->getRequest('context')
        ];

        $filter_inherited = $this->getRequest('filter_inherited', -1);
        $filter_discovered = $this->getRequest('filter_discovered', -1);
        $filter_dependent = $this->getRequest('filter_dependent', -1);
        $filter_name = $this->getRequest('filter_name', '');
        $filter_priority = $this->getRequest('filter_priority', []);
        $filter_groupids = $this->getRequest('filter_groupids', []);
        $filter_hostids = $this->getRequest('filter_hostids', []);
        $filter_state = $this->getRequest('filter_state', -1);
        $filter_status = $this->getRequest('filter_status', -1);
        $filter_value = $this->getRequest('filter_value', -1);
        $filter_evaltype = $this->getRequest('filter_evaltype', TAG_EVAL_TYPE_AND_OR);
        $filter_tags = $this->getRequest('filter_tags', []);
        $filter_triggerids = $this->getRequest('filter_triggerids', []);

        $filter_groupids_enriched = $this->getSubGroups($filter_groupids, $data['context']);

        if ($filter_hostids) {
            if ($data['context'] === 'host') {
                $filter_hostids = Hosts::find()->select(['hostid', 'name'])
                    ->where(['hostid' => $filter_hostids])
                    ->indexBy('hostid')
                    ->asArray()->all();

                $filter_hostids_ms = CArrayHelper::renameObjectsKeys($filter_hostids, ['hostid' => 'id']);
            } else {
                $filter_hostids = Hosts::find()->select(['templateid' => 'hostid', 'name'])
                    ->where(['hostid' => $filter_hostids])
                    ->indexBy('templateid')
                    ->asArray()->all();

                $filter_hostids_ms = CArrayHelper::renameObjectsKeys($filter_hostids, ['templateid' => 'id']);
            }

            $filter_hostids = array_keys($filter_hostids_ms);
        }

        // Skip empty tags.
        $filter_tags = array_filter($filter_tags, function ($v) {
            return (bool)$v['tag'];
        });

        $sort = $this->getRequest('sort', 'description');
        $sortOrder = $this->getRequest('sortorder', PRS_SORT_UP);

        // Get triggers (build options).
        $options = [
            'output' => ['triggerid', $sort],
            'hostids' => $filter_hostids ?: null,
            'groupids' => $filter_groupids ? $filter_groupids_enriched : null,
            'editable' => true,
            'dependent' => ($filter_dependent != -1) ? $filter_dependent : null,
            'templated' => $filter_value == -1 && $data['context'] === 'template',
            'inherited' => ($filter_inherited != -1) ? $filter_inherited : null,
            'preservekeys' => false,
            'sortfield' => $sort,
            'sortorder' => $sortOrder,
            'filter_value' => $filter_value
        ];

        if ($sort === 'status') {
            $options['output'][] = 'state';
        }

        if ($filter_discovered != -1) {
            $options['filter']['flags'] = ($filter_discovered == 1)
                ? PRS_FLAG_DISCOVERY_CREATED
                : PRS_FLAG_DISCOVERY_NORMAL;
        }

        if ($filter_value != -1) {
            $options['filter']['value'] = $filter_value;
        }

        if ($filter_name !== '') {
            $options['search']['description'] = $filter_name;
        }
        if ($filter_priority) {
            $options['filter']['priority'] = $filter_priority;
        }

        switch ($filter_state) {
            case TRIGGER_STATE_NORMAL:
                $options['filter']['state'] = TRIGGER_STATE_NORMAL;
                $options['filter']['status'] = TRIGGER_STATUS_ENABLED;
                break;

            case TRIGGER_STATE_UNKNOWN:
                $options['filter']['state'] = TRIGGER_STATE_UNKNOWN;
                $options['filter']['status'] = TRIGGER_STATUS_ENABLED;
                break;

            default:
                if ($filter_status != -1) {
                    $options['filter']['status'] = $filter_status;
                }
        }

        if ($filter_tags) {
            $options['evaltype'] = $filter_evaltype;
            $options['tags'] = $filter_tags;
        }
        if ($filter_triggerids) {
            $options['triggerids'] = filter_integer((array)$filter_triggerids);
        }

        return $this->success($options);
    }

    protected function getSubGroups($groupIds, string $context = 'host'): array
    {
        $groups = (new Query())->select(['name'])
            ->from(['hstgrp'])
            ->where(['groupid' => $groupIds])
            ->andWhere(['type' => $context == 'host' ? HOST_GROUP_TYPE_HOST_GROUP : HOST_GROUP_TYPE_TEMPLATE_GROUP])
            ->all();
        $where = array_map(function ($name) {
            return ['like', 'name', "$name/%", false];
        }, $groups);
        if ($where) {
            array_unshift($where, 'or');
            return (new Query())->select(['groupid'])
                ->from(['hstgrp'])
                ->where($where)
                ->andWhere(['type' => $context == 'host' ? HOST_GROUP_TYPE_HOST_GROUP : HOST_GROUP_TYPE_TEMPLATE_GROUP])
                ->column();
        }
        return [];
    }
}