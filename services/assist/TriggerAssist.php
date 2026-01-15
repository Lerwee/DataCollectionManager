<?php

namespace app\customs\zapi\services\assist;

use app\common\components\Result;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\macros\CMacrosResolverHelper;
use app\customs\zapi\common\managers\TriggerManager;
use app\modules\libzbx\models\Triggers;
use yii\base\Exception;
use yii\db\Query;

/**
 * Class TriggerAssist
 * @package app\customs\zapi\services\assist
 */
class TriggerAssist extends BaseTriggerAssist
{
    public const ACCESS_RULES = [
        'get' => ['min_user_type' => USER_TYPE_PERSEUS_USER],
        'create' => ['min_user_type' => USER_TYPE_PERSEUS_ADMIN],
        'update' => ['min_user_type' => USER_TYPE_PERSEUS_ADMIN],
        'delete' => ['min_user_type' => USER_TYPE_PERSEUS_ADMIN]
    ];

    protected const FLAGS = PRS_FLAG_DISCOVERY_NORMAL;

    /**
     * Add triggers.
     *
     * Trigger params: expression, description, type, priority, status, comments, url_name, url, templateid
     *
     * @param array $triggers
     * @return Result
     */
    public function create(array $triggers): Result
    {
        try {
            $this->validateCreate($triggers);
            $this->createReal($triggers);
            $this->checkDependenciesLinks($triggers);
            $this->inherit($triggers);
            $this->updateDependencies($triggers);
            return $this->success(['triggerids' => array_column($triggers, 'triggerid')]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750301, $e->getMessage());
        }
    }

    /**
     * Update triggers.
     *
     * If a trigger expression is passed in any of the triggers, it must be in it's exploded form.
     *
     * @param array $triggers
     * @return Result
     */
    public function update(array $triggers): Result
    {
        try {
            $this->validateUpdate($triggers, $db_triggers);
            $this->updateReal($triggers, $db_triggers);
            self::checkExistingDependencies($triggers, $db_triggers);
            $this->inherit($triggers);
            $this->updateDependencies($triggers, $db_triggers);
            return $this->success(['triggerids' => array_column($triggers, 'triggerid')]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750302, $e->getMessage());
        }
    }

    /**
     * Delete triggers.
     *
     * @param array $triggerids
     * @return Result
     */
    public function delete(array $triggerids): Result
    {
        try {
            $this->validateDelete($triggerids, $db_triggers);
            TriggerManager::delete($triggerids);
//            $this->addAuditBulk(CAudit::ACTION_DELETE, CAudit::RESOURCE_TRIGGER, $db_triggers);
            return $this->success(['triggerids' => $triggerids]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750303, $e->getMessage());
        }
    }

    /**
     * Validates the input parameters for the delete() method.
     *
     * @param array $triggerids [IN/OUT]
     * @param array|null $db_triggers [OUT]
     *
     * @throws ValidateException
     */
    protected function validateDelete(array &$triggerids, array &$db_triggers = null)
    {
        $triggerids = filter_integer($triggerids);

        $db_triggers = Triggers::find()->select(['triggerid', 'description', 'expression', 'templateid'])
            ->where(['triggerid' => $triggerids])
            ->indexBy('triggerid')
            ->asArray()
            ->all();

        foreach ($triggerids as $triggerid) {
            if (!array_key_exists($triggerid, $db_triggers)) {
                self::exception(60750003, t('zai', 'No permissions to referred object or it does not exist!'));
            }

            $db_trigger = $db_triggers[$triggerid];

            if ($db_trigger['templateid'] != 0) {
                self::exception(60750003, t('zai', 'Cannot delete templated trigger "{name}:{error}".', ['name' => $db_trigger['description'], 'error' => CMacrosResolverHelper::resolveTriggerExpression($db_trigger['expression'])]));
            }
        }
    }

    /**
     * Check the existing dependencies if the trigger expressions were changed.
     *
     * @param array $triggers
     * @param array $db_triggers
     * @throws ValidateException
     */
    private static function checkExistingDependencies(array $triggers, array $db_triggers): void
    {
        $triggerids = [];
        $hostids = [];

        foreach ($triggers as $trigger) {
            if (array_key_exists('hosts', $db_triggers[$trigger['triggerid']])) {
                $triggerids[$trigger['triggerid']] = true;
                $hostids += $db_triggers[$trigger['triggerid']]['hosts'];
            }
        }

        if (!$triggerids) {
            return;
        }

        /*
         * It's necessary to perform the check of existing dependencies only if, as the result of the expression change,
         * the trigger no longer belongs to any of the previous hosts.
         */
        $rows = (new Query())->select(['f.triggerid','i.hostid'])
            ->from(['f' => 'functions', 'i' => 'items'])
            ->where('f.itemid=i.itemid')
            ->andWhere(['f.triggerid' => array_keys($triggerids)])
            ->andWhere(['i.hostid' => array_keys($hostids)])
            ->distinct()
            ->all();

        foreach ($rows as $row) {
            if (array_key_exists($row['hostid'], $db_triggers[$row['triggerid']]['hosts'])) {
                unset($db_triggers[$row['triggerid']]['hosts'][$row['hostid']]);
            }
        }

        $trigger_dependencies = [];

        foreach ($triggers as $trigger) {
            if (!array_key_exists($trigger['triggerid'], $triggerids)
                || !$db_triggers[$trigger['triggerid']]['hosts']) {
                continue;
            }

            $dependencies = array_key_exists('dependencies', $trigger)
                ? $trigger['dependencies']
                : $db_triggers[$trigger['triggerid']]['dependencies'];

            foreach ($dependencies as $trigger_up) {
                $trigger_dependencies[$trigger_up['triggerid']][$trigger['triggerid']] = true;
            }
        }

        if (!$trigger_dependencies) {
            return;
        }

        /*
         * There is no need to perform a check for dependency duplicates, because we are checking for existing
         * dependencies that cannot have them. Also there is no need to perform the check on circular
         * dependencies, because the dependencies are based on trigger IDs. Even if the expressions have changed, the
         * trigger IDs remain the same.
         */

        $trigger_hosts = self::getTriggerHosts($trigger_dependencies);

        /*
         * The template trigger can become a host trigger. Therefore the template trigger dependencies may remain
         * after the update.
         */
        self::checkDependenciesOfHostTriggers($trigger_dependencies, $trigger_hosts);

        /*
         * The template (or host) trigger can become a trigger of another template. If after the update
         * the trigger became owned by another template and it also has a dependency on a trigger from another
         * template remaining, then we should check that:
         *  - Such dependency did not become a dependency on a trigger from the parent template.
         *  - Such dependency did not become a dependency on a trigger from the child template or host.
         *  - The template of the trigger-up is linked to all child templates of the new trigger's template.
         */
        self::checkDependenciesOfTemplateTriggers($trigger_dependencies, $trigger_hosts);
    }
}