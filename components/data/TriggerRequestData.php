<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\common\macros\CMacrosResolverHelper;
use app\customs\zapi\components\RelationMap;
use app\modules\libzbx\models\Triggers;
use yii\db\Query;

/**
 * Class TriggerRequestData
 * @package app\customs\zapi\components\data
 */
class TriggerRequestData extends RequestData
{
    /**
     * @var string
     */
    public $action = 'create';

    public function validate(): Result
    {
        $tags = $this->getRequest('tags', []);
        // Unset empty and inherited tags.
        foreach ($tags as $key => $tag) {
            if ($tag['tag'] === '' && $tag['value'] === '') {
                unset($tags[$key]);
            }
            elseif (array_key_exists('type', $tag) && !($tag['type'] & PRS_PROPERTY_OWN)) {
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

        if ($this->action == 'create') {
            $trigger = [
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
                'status' => $status
            ];

            switch ($recovery_mode) {
                case PRS_RECOVERY_MODE_RECOVERY_EXPRESSION:
                    $trigger['recovery_expression'] = $recovery_expression;
                // break; is not missing here.

                case PRS_RECOVERY_MODE_EXPRESSION:
                    $trigger['correlation_mode'] = $correlation_mode;

                    if ($correlation_mode == PRS_TRIGGER_CORRELATION_TAG) {
                        $trigger['correlation_tag'] = $correlation_tag;
                    }
                    break;
            }
        } else {
            $db_triggers = (new Query())->select(['triggerid', 'expression', 'description', 'url_name', 'url', 'status', 'priority', 'comments', 'templateid',
                'type', 'flags', 'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag',
                'manual_close', 'opdata', 'event_name'])
                ->from('triggers')
                ->where(['triggerid' => $this->getRequest('triggerid')])
                ->indexBy('triggerid')
                ->all();
            $db_triggers = $this->setTriggerDependence($db_triggers);
            $db_triggers = $this->setTriggerTags($db_triggers);
            $db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($db_triggers,
                ['sources' => ['expression', 'recovery_expression']]
            );

            $db_trigger = reset($db_triggers);

            $trigger = [];

            if ($db_trigger['flags'] == PRS_FLAG_DISCOVERY_NORMAL) {
                if ($db_trigger['templateid'] == 0) {
                    if ($db_trigger['description'] !== $description) {
                        $trigger['description'] = $description;
                    }
                    if ($db_trigger['event_name'] !== $event_name) {
                        $trigger['event_name'] = $event_name;
                    }
                    if ($db_trigger['opdata'] !== $opdata) {
                        $trigger['opdata'] = $opdata;
                    }
                    if ($db_trigger['expression'] !== $expression) {
                        $trigger['expression'] = $expression;
                    }
                    if ($db_trigger['recovery_mode'] != $recovery_mode) {
                        $trigger['recovery_mode'] = $recovery_mode;
                    }

                    switch ($recovery_mode) {
                        case PRS_RECOVERY_MODE_RECOVERY_EXPRESSION:
                            if ($db_trigger['recovery_expression'] !== $recovery_expression) {
                                $trigger['recovery_expression'] = $recovery_expression;
                            }
                        // break; is not missing here.
                        case PRS_RECOVERY_MODE_EXPRESSION:
                            if ($db_trigger['correlation_mode'] != $correlation_mode) {
                                $trigger['correlation_mode'] = $correlation_mode;
                            }

                            if ($correlation_mode == PRS_TRIGGER_CORRELATION_TAG
                                && $db_trigger['correlation_tag'] !== $correlation_tag) {
                                $trigger['correlation_tag'] = $correlation_tag;
                            }
                            break;
                    }
                }

                if ($db_trigger['type'] != $type) {
                    $trigger['type'] = $type;
                }
                if ($db_trigger['url_name'] !== $url_name) {
                    $trigger['url_name'] = $url_name;
                }
                if ($db_trigger['url'] !== $url) {
                    $trigger['url'] = $url;
                }
                if ($db_trigger['priority'] != $priority) {
                    $trigger['priority'] = $priority;
                }
                if ($db_trigger['comments'] !== $comments) {
                    $trigger['comments'] = $comments;
                }

                $db_tags = $db_trigger['tags'];
                CArrayHelper::sort($db_tags, ['tag', 'value']);
                CArrayHelper::sort($tags, ['tag', 'value']);
                if (array_values($db_tags) !== array_values($tags)) {
                    $trigger['tags'] = $tags;
                }

                if ($db_trigger['manual_close'] != $manual_close) {
                    $trigger['manual_close'] = $manual_close;
                }

                $db_dependencies = $db_trigger['dependencies'];
                CArrayHelper::sort($db_dependencies, ['triggerid']);
                CArrayHelper::sort($dependencies, ['triggerid']);

                if (array_values($db_dependencies) !== array_values($dependencies)) {
                    $trigger['dependencies'] = $dependencies;
                }
            }

            if ($db_trigger['status'] != $status) {
                $trigger['status'] = $status;
            }

            if ($trigger) {
                $trigger['triggerid'] = $this->getRequest('triggerid');
            } else {
                $trigger = [];
            }
        }
        return $this->success($trigger);
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