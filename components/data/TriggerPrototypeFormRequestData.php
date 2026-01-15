<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\customs\zapi\common\db\DB;
use app\modules\libzbx\models\Items;

/**
 * Class TriggerPrototypeFormRequestData
 * @package app\customs\zapi\components\data
 */
class TriggerPrototypeFormRequestData extends RequestData
{
    /**
     * @return Result
     * @throws \yii\db\Exception
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
        $options = [
            'form' => $this->getRequest('form'),
            'form_refresh' => $this->getRequest('form_refresh', 0),
            'parent_discoveryid' => $this->getRequest('parent_discoveryid'),
            'dependencies' => $this->getRequest('dependencies', []),
            'db_dependencies' => [],
            'triggerid' => $this->getRequest('triggerid'),
            'expression' => $this->getRequest('expression', ''),
            'recovery_expression' => $this->getRequest('recovery_expression', ''),
            'expr_temp' => $this->getRequest('expr_temp', ''),
            'recovery_expr_temp' => $this->getRequest('recovery_expr_temp', ''),
            'recovery_mode' => $this->getRequest('recovery_mode', PRS_RECOVERY_MODE_EXPRESSION),
            'description' => $this->getRequest('description', ''),
            'event_name' => $this->getRequest('event_name', ''),
            'opdata' => $this->getRequest('opdata', ''),
            'type' => $this->getRequest('type', 0),
            'priority' => $this->getRequest('priority', TRIGGER_SEVERITY_NOT_CLASSIFIED),
            'status' => $this->getRequest('status', TRIGGER_STATUS_ENABLED),
            'discover' => $this->getRequest('discover', DB::getDefault('triggers', 'discover')),
            'comments' => $this->getRequest('comments', ''),
            'url_name' => $this->getRequest('url_name', ''),
            'url' => $this->getRequest('url', ''),
            'expression_constructor' => $this->getRequest('expression_constructor', IM_ESTABLISHED),
            'recovery_expression_constructor' => $this->getRequest('recovery_expression_constructor', IM_ESTABLISHED),
            'limited' => false,
            'templates' => [],
            'parent_templates' => [],
            'hostid' => $discoveryRule['hostid'],
            'expression_action' => '',
            'recovery_expression_action' => '',
            'tags' => [],
            'show_inherited_tags' => $this->getRequest('show_inherited_tags', 0),
            'correlation_mode' => $this->getRequest('correlation_mode', PRS_TRIGGER_CORRELATION_NONE),
            'correlation_tag' => $this->getRequest('correlation_tag', ''),
            'manual_close' => $this->getRequest('manual_close', PRS_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED),
            'context' => $this->getRequest('context'),
            'backurl' => $this->getRequest('backurl')
        ];
        return $this->success($options);
    }
}