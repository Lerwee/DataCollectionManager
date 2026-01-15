<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\validators\LimitedSetValidator;
use app\customs\zapi\common\validators\EventSourceObjectValidator;
use app\customs\zapi\models\search\EventSearch;

/**
 * EventGetForm - 事件查询参数验证模型
 */
class EventGetForm extends BaseForm
{
    public $eventids;
    public $groupids;
    public $hostids;
    public $objectids;
    public $editable;
    public $object;
    public $source;
    public $severities;
    public $nopermissions;
    public $value;
    public $time_from;
    public $time_till;
    public $eventid_from;
    public $eventid_till;
    public $problem_time_from;
    public $problem_time_till;
    public $acknowledged;
    public $suppressed;
    public $symptom;
    public $evaltype;
    public $tags;
    public $filter;
    public $search;
    public $searchByAny;
    public $startSearch;
    public $excludeSearch;
    public $searchWildcardsEnabled;
    public $output;
    public $selectHosts;
    public $selectRelatedObject;
    public $select_alerts;
    public $select_acknowledges;
    public $selectSuppressionData;
    public $selectTags;
    public $countOutput;
    public $groupCount;
    public $preservekeys;
    public $sortfield;
    public $sortorder;
    public $limit;

    /**
     * 默认值
     */
    public static function getDefaults(): array
    {
        return [
            'eventids' => null,
            'groupids' => null,
            'hostids' => null,
            'objectids' => null,
            'editable' => false,
            'object' => EVENT_OBJECT_TRIGGER,
            'source' => EVENT_SOURCE_TRIGGERS,
            'severities' => null,
            'nopermissions' => null,
            'value' => null,
            'time_from' => null,
            'time_till' => null,
            'eventid_from' => null,
            'eventid_till' => null,
            'problem_time_from' => null,
            'problem_time_till' => null,
            'acknowledged' => null,
            'suppressed' => null,
            'symptom' => null,
            'evaltype' => TAG_EVAL_TYPE_AND_OR,
            'tags' => null,
            'filter' => null,
            'search' => null,
            'searchByAny' => null,
            'startSearch' => false,
            'excludeSearch' => false,
            'searchWildcardsEnabled' => null,
            'output' => API_OUTPUT_EXTEND,
            'selectHosts' => null,
            'selectRelatedObject' => null,
            'select_alerts' => null,
            'select_acknowledges' => null,
            'selectSuppressionData' => null,
            'selectTags' => null,
            'countOutput' => false,
            'groupCount' => false,
            'preservekeys' => false,
            'sortfield' => '',
            'sortorder' => '',
            'limit' => null
        ];
    }

    /**
     * 验证查询参数
     *
     * @param array $options
     * @throws \app\customs\zapi\common\exceptions\ValidateException
     */
    public static function validate(array $options): void
    {
        $sourceValidator = new LimitedSetValidator([
            'values' => array_keys(EventSearch::eventSource())
        ]);
        if (!$sourceValidator->validate($options['source'])) {
            self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Incorrect source value.'));
        }

        $objectValidator = new LimitedSetValidator([
            'values' => array_keys(EventSearch::eventObject())
        ]);
        if (!$objectValidator->validate($options['object'])) {
            self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Incorrect object value.'));
        }

        $sourceObjectValidator = new EventSourceObjectValidator();
        if (!$sourceObjectValidator->validate(['source' => $options['source'], 'object' => $options['object']])) {
            self::exception(PRS_API_ERROR_PARAMETERS, $sourceObjectValidator->getError());
        }

        $evaltypeValidator = new LimitedSetValidator([
            'values' => [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]
        ]);
        if (!$evaltypeValidator->validate($options['evaltype'])) {
            self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Incorrect evaltype value.'));
        }
    }

    /**
     * 合并默认值
     *
     * @param array $options
     * @return array
     */
    public static function mergeDefaults(array $options): array
    {
        return prs_array_merge(self::getDefaults(), $options);
    }
}
