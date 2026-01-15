<?php

namespace app\customs\zapi\services\assist;

use app\common\components\Result;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\macros\CMacrosResolverHelper;
use app\customs\zapi\common\managers\TriggerPrototypeManager;
use app\modules\libzbx\models\Triggers;
use yii\base\Exception;

/**
 * Class TriggerPrototypeAssist
 * @package app\customs\zapi\services\assist
 */
class TriggerPrototypeAssist extends BaseTriggerAssist
{
    public const ACCESS_RULES = [
        'get' => ['min_user_type' => USER_TYPE_PERSEUS_USER],
        'create' => ['min_user_type' => USER_TYPE_PERSEUS_ADMIN],
        'update' => ['min_user_type' => USER_TYPE_PERSEUS_ADMIN],
        'delete' => ['min_user_type' => USER_TYPE_PERSEUS_ADMIN]
    ];

    protected const FLAGS = PRS_FLAG_DISCOVERY_PROTOTYPE;

    /**
     * Create new trigger prototypes.
     *
     * @param array $trigger_prototypes
     * @return Result
     */
    public function create(array $trigger_prototypes): Result
    {
        try {
            $this->validateCreate($trigger_prototypes);
            $this->createReal($trigger_prototypes);
            $this->checkDependenciesLinks($trigger_prototypes);
            $this->inherit($trigger_prototypes);
            $this->updateDependencies($trigger_prototypes);
            return $this->success(['triggerids' => prs_objectValues($trigger_prototypes, 'triggerid')]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750301, $e->getMessage());
        }
    }

    /**
     * Update existing trigger prototypes.
     *
     * @param array $trigger_prototypes
     *
     * @return Result
     */
    public function update(array $trigger_prototypes): Result
    {
        try {
            $this->validateUpdate($trigger_prototypes, $db_triggers);
            $this->updateReal($trigger_prototypes, $db_triggers);
            $this->inherit($trigger_prototypes);
            $this->updateDependencies($trigger_prototypes, $db_triggers);
            return $this->success(['triggerids' => array_column($trigger_prototypes, 'triggerid')]);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750302, $e->getMessage());
        }
    }

    /**
     * Delete existing trigger prototypes.
     *
     * @param array $triggerids
     *
     * @return Result
     */
    public function delete(array $triggerids): Result
    {
        try {
            $this->validateDelete($triggerids, $db_triggers);
            TriggerPrototypeManager::delete($triggerids);
//        $this->addAuditBulk(CAudit::ACTION_DELETE, CAudit::RESOURCE_TRIGGER_PROTOTYPE, $db_triggers);
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
     * @param array $db_triggers [OUT]
     *
     * @throws ValidateException
     */
    protected function validateDelete(array &$triggerids, array &$db_triggers = null)
    {
        $triggerids = filter_integer($triggerids);

        $db_triggers = Triggers::find()->select(['triggerid', 'description', 'expression', 'templateid'])
            ->where(['triggerid' => $triggerids])
            ->andWhere(['flags' => PRS_FLAG_DISCOVERY_PROTOTYPE])
            ->indexBy('triggerid')
            ->asArray()
            ->all();

        foreach ($triggerids as $triggerid) {
            if (!array_key_exists($triggerid, $db_triggers)) {
                self::exception(60750003, t('zai', 'No permissions to referred object or it does not exist!'));
            }
            $db_trigger = $db_triggers[$triggerid];
            if ($db_trigger['templateid'] != 0) {
                self::exception(60750003, t('zai', 'Cannot delete templated trigger prototype "{name}:{error}".', ['name' => $db_trigger['description'], 'error' => CMacrosResolverHelper::resolveTriggerExpression($db_trigger['expression'])]));
            }
        }
    }
}