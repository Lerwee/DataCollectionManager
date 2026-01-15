<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\common\macros\CMacrosResolverHelper;
use app\customs\zapi\components\RelationMap;
use app\customs\zapi\models\search\trigger\TriggerPrototypeSearch;
use app\modules\libzbx\models\Triggers;
use yii\db\Query;

/**
 * Class TriggerPrototypeRequestData
 * @package app\customs\zapi\components\data
 */
class TriggerPrototypeRequestData extends RequestData
{
    /**
     * @var string
     */
    public $action = 'create';

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
            }
            else {
                unset($tags[$key]['type']);
            }
        }
        $dependencies = prs_toObject($this->getRequest('dependencies', []), 'triggerid');
        $description = $this->getRequest('description', '');
        $event_name = $this->getRequest('event_name', '');
        $opdata = $this->getRequest('opdata', '');
        $expression = $this->getRequest('expression', '');
        $recovery_mode = $this->getRequest('recovery_mode', PRS_RECOVERY_MODE_EXPRESSION);
        $recovery_expression = $this->getRequest('recovery_expression', '');
        $type = $this->getRequest('type', 0);
        $url_name = $this->getRequest('url_name', '');
        $url = $this->getRequest('url', '');
        $priority = $this->getRequest('priority', TRIGGER_SEVERITY_NOT_CLASSIFIED);
        $comments = $this->getRequest('comments', '');
        $correlation_mode = $this->getRequest('correlation_mode', PRS_TRIGGER_CORRELATION_NONE);
        $correlation_tag = $this->getRequest('correlation_tag', '');
        $manual_close = $this->getRequest('manual_close', PRS_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED);
        $status = $this->getRequest('status', TRIGGER_STATUS_ENABLED);
        $discover = $this->getRequest('discover', DB::getDefault('triggers', 'discover'));

        if ($this->action == 'create') {
            $trigger_prototype = [
                'description' => $description,
                'event_name' => $event_name,
                'opdata' => $opdata,
                'expression' => $expression,
                'recovery_mode' => $recovery_mode,
                'type' => $type,
                'url_name' => $url_name,
                'url' => $url,
                'priority' => $priority,
                'comments' => $comments,
                'tags' => $tags,
                'manual_close' => $manual_close,
                'dependencies' => $dependencies,
                'status' => $status,
                'discover' => $discover
            ];
            switch ($recovery_mode) {
                case PRS_RECOVERY_MODE_RECOVERY_EXPRESSION:
                    $trigger_prototype['recovery_expression'] = $recovery_expression;
                // break; is not missing here

                case PRS_RECOVERY_MODE_EXPRESSION:
                    $trigger_prototype['correlation_mode'] = $correlation_mode;
                    if ($correlation_mode == PRS_TRIGGER_CORRELATION_TAG) {
                        $trigger_prototype['correlation_tag'] = $correlation_tag;
                    }
                    break;
            }
        } else {
            $search = new TriggerPrototypeSearch();
            $provider = $search->search([
                'output' => ['expression', 'description', 'url_name', 'url', 'status', 'priority', 'comments', 'templateid',
                    'type', 'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag', 'manual_close',
                    'opdata', 'discover', 'event_name'
                ],
                'selectDependencies' => ['triggerid'],
                'selectTags' => ['tag', 'value'],
                'triggerids' => $this->getRequest('triggerid')
            ]);
            $db_trigger_prototypes = $provider->getModels();

            $db_trigger_prototypes = CMacrosResolverHelper::resolveTriggerExpressions($db_trigger_prototypes,
                ['sources' => ['expression', 'recovery_expression']]
            );

            $db_trigger_prototype = reset($db_trigger_prototypes);

            $trigger_prototype = [];

            if ($db_trigger_prototype['templateid'] == 0) {
                if ($db_trigger_prototype['description'] !== $description) {
                    $trigger_prototype['description'] = $description;
                }
                if ($db_trigger_prototype['event_name'] !== $event_name) {
                    $trigger_prototype['event_name'] = $event_name;
                }
                if ($db_trigger_prototype['opdata'] !== $opdata) {
                    $trigger_prototype['opdata'] = $opdata;
                }
                if ($db_trigger_prototype['expression'] !== $expression) {
                    $trigger_prototype['expression'] = $expression;
                }
                if ($db_trigger_prototype['recovery_mode'] != $recovery_mode) {
                    $trigger_prototype['recovery_mode'] = $recovery_mode;
                }
                switch ($recovery_mode) {
                    case PRS_RECOVERY_MODE_RECOVERY_EXPRESSION:
                        if ($db_trigger_prototype['recovery_expression'] !== $recovery_expression) {
                            $trigger_prototype['recovery_expression'] = $recovery_expression;
                        }
                    // break; is not missing here

                    case PRS_RECOVERY_MODE_EXPRESSION:
                        if ($db_trigger_prototype['correlation_mode'] != $correlation_mode) {
                            $trigger_prototype['correlation_mode'] = $correlation_mode;
                        }
                        if ($correlation_mode == PRS_TRIGGER_CORRELATION_TAG
                            && $db_trigger_prototype['correlation_tag'] !== $correlation_tag) {
                            $trigger_prototype['correlation_tag'] = $correlation_tag;
                        }
                        break;
                }
            }

            if ($db_trigger_prototype['type'] != $type) {
                $trigger_prototype['type'] = $type;
            }
            if ($db_trigger_prototype['url_name'] !== $url_name) {
                $trigger_prototype['url_name'] = $url_name;
            }
            if ($db_trigger_prototype['url'] !== $url) {
                $trigger_prototype['url'] = $url;
            }
            if ($db_trigger_prototype['priority'] != $priority) {
                $trigger_prototype['priority'] = $priority;
            }
            if ($db_trigger_prototype['comments'] !== $comments) {
                $trigger_prototype['comments'] = $comments;
            }

            $db_tags = $db_trigger_prototype['tags'];
            CArrayHelper::sort($db_tags, ['tag', 'value']);
            CArrayHelper::sort($tags, ['tag', 'value']);
            if (array_values($db_tags) !== array_values($tags)) {
                $trigger_prototype['tags'] = $tags;
            }

            if ($db_trigger_prototype['manual_close'] != $manual_close) {
                $trigger_prototype['manual_close'] = $manual_close;
            }

            $db_dependencies = $db_trigger_prototype['dependencies'];
            CArrayHelper::sort($db_dependencies, ['triggerid']);
            CArrayHelper::sort($dependencies, ['triggerid']);
            if (array_values($db_dependencies) !== array_values($dependencies)) {
                $trigger_prototype['dependencies'] = $dependencies;
            }

            if ($db_trigger_prototype['status'] != $status) {
                $trigger_prototype['status'] = $status;
            }
            if ($db_trigger_prototype['discover'] != $discover) {
                $trigger_prototype['discover'] = $discover;
            }
            if ($trigger_prototype) {
                $trigger_prototype['triggerid'] = $this->getRequest('triggerid');
            }
        }
        return $this->success($trigger_prototype);
    }

    protected function setTriggerDependence($triggers): array
    {
        $triggerids = ArrayHelper::getColumn($triggers, 'triggerid');
        $dependencies = [];
        $relationMap = new RelationMap();
        $rows = (new Query())->select(['td.triggerid_up','td.triggerid_down'])
            ->from(['td' => 'trigger_depends'])
            ->where(['td.triggerid_down' => $triggerids])
            ->all();
        foreach ($rows as $relation) {
            $relationMap->addRelation($relation['triggerid_down'], $relation['triggerid_up']);
        }

        $related_ids = $relationMap->getRelatedIds();

        if ($related_ids) {
            $dependencies = Triggers::find()->select(['triggerid'])
                ->where(['triggerid' => $related_ids])
                ->indexBy(['triggerid'])
                ->asArray()->all();
        }

        return $relationMap->mapMany($triggers, $dependencies, 'dependencies');
    }


    public function setTriggerTags($triggers): array
    {
        $triggerids = ArrayHelper::getColumn($triggers, 'triggerid');
        $tags = (new Query())
            ->select(['triggertagid', 'triggerid', 'tag', 'value'])
            ->from(['trigger_tag'])
            ->where(['triggerid' => $triggerids])
            ->indexBy('triggertagid')
            ->all();

        $relationMap = ZSqlHelper::createRelationMap($tags, 'triggerid', 'triggertagid');
        $tags = array_map(function ($tag) {
            unset($tag['triggertagid']);
            unset($tag['triggerid']);
            return $tag;
        }, $tags);
        return $relationMap->mapMany($triggers, $tags, 'tags');
    }
}