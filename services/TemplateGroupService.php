<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\forms\TemplateGroupForm;
use app\customs\zapi\services\assist\BaseAssist;
use Exception;
use yii\db\Query;

class TemplateGroupService extends BaseAssist
{
    /**
     * @param array $params
     * @return Result
     */
    public function create(array $params): Result
    {
        try {
            self::validateCreate($params);

            $groups = [];
            foreach ($params as $param) {
                $groups[] = $param + ['type' => HOST_GROUP_TYPE_TEMPLATE_GROUP];
            }

            $groupIds = DB::insert('hstgrp', $groups);
            foreach ($params as $index => &$param) {
                $param['groupid'] = $groupIds[$index];
            }
            unset($param);

            self::inheritUserGroupsData($params);

            return $this->success(['groupids' => $groupIds]);
        } catch (Exception $e) {
            return $this->errorException($e);
        }
    }

    /**
     * Validates input for create function.
     *
     * @param array $groups  [IN/OUT]
     *
     * @static
     *
     * @throws ValidateException if the input is invalid.
     */
    private static function validateCreate(array &$groups): void
    {
        $rules = TemplateGroupForm::getValidationRules();
        $bool = ValidateHelper::validateObjects($groups, $rules, [
            'flags' => API_NOT_EMPTY | API_NORMALIZE,
            'uniq' => [['uuid'], ['name']],
        ], $error);
        if (!$bool) {
            self::exception(60750501, $error);
        }

        self::addUuid($groups);

        self::checkUuidDuplicates($groups);
        self::checkDuplicates($groups);
    }

    /**
     * @param array $params
     * @return Result
     */
    public function update(array $params): Result
    {
        try {
            self::validateUpdate($params, $db_groups);

            $upd_groups = [];

            foreach ($params as $group) {
                $upd_group = DB::getUpdatedValues('hstgrp', $group, $db_groups[$group['groupid']]);

                if ($upd_group) {
                    $upd_groups[] = [
                        'values' => $upd_group,
                        'where' => ['groupid' => $group['groupid']]
                    ];
                }
            }

            if ($upd_groups) {
                DB::update('hstgrp', $upd_groups);
            }

            return $this->success(['groupids' => array_column($params, 'groupid')]);
        } catch (Exception $e) {
            return $this->errorException($e);
        }
    }

    /**
     * Validates input for create function.
     *
     * @param array $groups     [IN/OUT]
     * @param array $db_groups  [OUT]
     *
     * @throws ValidateException if the input is invalid.
     */
    protected function validateUpdate(array &$groups, array &$db_groups = null): void
    {

        $rules = TemplateGroupForm::getValidationRules('update');
        $bool = ValidateHelper::validateObjects($groups, $rules, [
            'flags' => API_NOT_EMPTY | API_NORMALIZE,
            'uniq' => [['uuid'], ['groupid'], ['name']],
        ], $error);
        if (!$bool) {
            self::exception(60750501, $error);
        }

        $db_groups = GroupHelper::getTemplateGroups([
            'output' => ['uuid', 'groupid', 'name'],
            'groupids' => array_column($groups, 'groupid'),
            'editable' => true,
            'preservekeys' => true,
        ]);

        if (count($db_groups) != count($groups)) {
            self::exception(60750501, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        self::checkUuidDuplicates($groups, $db_groups);
        self::checkDuplicates($groups, $db_groups);
    }

    /**
	 * @param array $groupids
	 *
	 * @return Result
	 */
	public function delete(array $groupids): Result {

        try {
            $this->validateDelete($groupids, $db_groups);

            self::deleteForce($db_groups);

            return $this->success(['groupids' => $groupids]);
        } catch (Exception $e) {
            return $this->errorException($e);
        }
        
	}

    /**
	 * @param array $db_groups
	 */
	public static function deleteForce(array $db_groups): void {
		$groupids = array_keys($db_groups);

		DB::delete('hstgrp', ['groupid' => $groupids]);

        // TODO: audit
		// self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_TEMPLATE_GROUP, $db_groups);
	}

    /**
     * Validates delete function input fields.
     *
     * @param array      $groupids   [IN]
     * @param array|null $db_groups  [OUT]
     *
     * @throws ValidateException if the input is invalid.
     */
    private function validateDelete(array $groupids, array &$db_groups = null): void
    {
        $ids = [
            'ids' => $groupids,
        ];

        $rules = [
            'ids' => [IdsValidator::class, 'flags' => API_NOT_EMPTY, 'uniq' => true],
        ];

        $bool = ValidateHelper::validateObject($ids, $rules, [], $error);
        if (!$bool) {
            self::exception(60750501, $error);
        }

        $db_groups = GroupHelper::getTemplateGroups([
            'output' => ['groupid', 'name'],
            'groupids' => $groupids,
            'editable' => true,
            'preservekeys' => true,
        ]);

        if (count($db_groups) != count($groupids)) {
            self::exception(PRS_API_ERROR_PERMISSIONS, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        self::validateDeleteForce($db_groups);
    }
    

    /**
     * Validates if groups can be deleted
     *
     * @param array $db_groups
     *
     * @throws ValidateException if unable to delete groups.
     */
    public static function validateDeleteForce(array $db_groups): void
    {
        $groupids = array_keys($db_groups);

        $db_templates = TemplateHelper::getTemplates([
            'output' => ['host'],
            'groupids' => $groupids,
            'nopermissions' => true,
            'preservekeys' => true,
        ]);

        if ($db_templates) {
            self::checkTemplatesWithoutGroups($db_templates, $groupids);
        }
    }

    /**
     * Check to exclude an opportunity to leave template without groups.
     *
     * @static
     *
     * @param array  $db_templates
     * @param string $db_templates[<templateid>]['host']
     * @param array  $groupids
     *
     * @throws APIException
     */
    public static function checkTemplatesWithoutGroups(array $db_templates, array $groupids): void
    {
        $templateids = array_keys($db_templates);

        $query = new Query();

        $query->select(['hostid']);

        $query->where(ZSqlHelper::dbConditionInt('groupid', $groupids, true))
            ->andWhere(ZSqlHelper::dbConditionInt('hostid', $templateids, true));

        $query->distinct()->indexBy('hostid');

        $templateids_with_groups = $query->column();

        $templateids_without_groups = array_diff($templateids, $templateids_with_groups);

        if ($templateids_without_groups) {
            $templateid = reset($templateids_without_groups);
            $error = t('zapi', 'Template "{name}" cannot be without template group.', [
                'name' => $db_templates[$templateid]['host'],
            ]);

            self::exception(PRS_API_ERROR_PARAMETERS, $error);
        }
    }

    /**
     * Add the UUID to those of the given template groups that don't have the 'uuid' parameter set.
     *
     * @param array $groups
     */
    private static function addUuid(array &$groups): void
    {
        foreach ($groups as &$group) {
            if (!array_key_exists('uuid', $group)) {
                $group['uuid'] = generateUuidV4();
            }
        }
        unset($group);
    }

    /**
     * Verify template group UUIDs are not repeated.
     *
     * @param array      $groups
     * @param array|null $db_groups
     *
     * @throws APIException
     */
    private static function checkUuidDuplicates(array $groups, array $db_groups = null): void
    {
        $group_indexes = [];

        foreach ($groups as $i => $group) {
            if (!array_key_exists('uuid', $group)) {
                continue;
            }

            if ($db_groups === null || $group['uuid'] !== $db_groups[$group['groupid']]['uuid']) {
                $group_indexes[$group['uuid']] = $i;
            }
        }

        if (!$group_indexes) {
            return;
        }

        $query = DB::makeQuery('hstgrp', [
            'output' => ['uuid'],
            'filter' => [
                'type' => HOST_GROUP_TYPE_TEMPLATE_GROUP,
                'uuid' => array_keys($group_indexes),
            ],
            'limit' => 1,
        ]);

        if ($uuid = $query->scalar()) {
            $error = t('zapi', 'Invalid parameter {parameter}, {error}', [
                'parameter' => '/' . ($group_indexes[$uuid] + 1),
                'error' => t('zapi', 'template group with the same UUID already exists'),
            ]);
            self::exception(60750501, $error);
        }
    }

    /**
     * Check for unique template group names.
     *
     * @static
     *
     * @param array      $groups
     * @param array|null $db_groups
     *
     * @throws APIException if template group names are not unique.
     */
    private static function checkDuplicates(array $groups, array $db_groups = null): void
    {
        $names = [];

        foreach ($groups as $group) {
            if (!array_key_exists('name', $group)) {
                continue;
            }

            if ($db_groups === null || $group['name'] !== $db_groups[$group['groupid']]['name']) {
                $names[] = $group['name'];
            }
        }

        if (!$names) {
            return;
        }

        $query = DB::makeQuery('hstgrp', [
            'output' => ['name'],
            'filter' => [
                'type' => HOST_GROUP_TYPE_TEMPLATE_GROUP,
                'name' => $names,
            ],
            'limit' => 1,
        ]);

        if ($name = $query->scalar()) {
            $error = t('zapi', 'Template group "{name}" already exists.', [
                'name' => $name,
            ]);
            self::exception(60750501, $error);
        }
    }

    /**
     * Inherit user groups data of parent template groups.
     *
     * @param array $groups
     */
    private static function inheritUserGroupsData(array $groups): void
    {
        $group_links = self::getGroupLinks($groups);

        if ($group_links) {
            $usrgrps = [];
            $db_usrgrps = [];

            self::prepareInheritedRights($group_links, $usrgrps, $db_usrgrps);

            if ($usrgrps) {
                UserGroupService::updateForce(array_values($usrgrps), $db_usrgrps);
            }
        }
    }

    /**
     * Get links of parent groups to given groups.
     *
     * @param array $groups  Template groups which group links need to be identified.
     *
     * @return array Array where keys are parent group IDs and values are the array of child group IDs.
     */
    private static function getGroupLinks(array $groups): array
    {
        $parent_names = [];

        foreach ($groups as $group) {
            $name = $group['name'];

            while (($pos = strrpos($name, '/')) !== false) {
                $name = substr($name, 0, $pos);
                $parent_names[$name] = true;
            }
        }

        if (!$parent_names) {
            return [];
        }

        $options = [
            'output' => ['groupid', 'name'],
            'filter' => ['name' => array_keys($parent_names), 'type' => HOST_GROUP_TYPE_TEMPLATE_GROUP],
        ];

        $query = DB::makeQuery('hstgrp', $options);

        $parents_groupids = $query->indexBy('name')->column();

        if (!$parents_groupids) {
            return [];
        }

        $group_links = [];

        foreach ($groups as $group) {
            $name = $group['name'];

            while (($pos = strrpos($name, '/')) !== false) {
                $name = substr($name, 0, $pos);

                if (array_key_exists($name, $parents_groupids)) {
                    $group_links[$parents_groupids[$name]][] = $group['groupid'];
                    break;
                }
            }
        }

        return $group_links;
    }

    /**
     * Prepare rights to inherit from parent template groups.
     *
     * @static
     *
     * @param array  $group_links
     * @param array  $usrgrps
     * @param array  $db_usrgrps
     */
    private static function prepareInheritedRights(array $group_links, array &$usrgrps, array &$db_usrgrps): void
    {
        $query = new Query();
        $query->from([
            'r' => 'rights',
            'g' => 'usrgrp',
        ]);

        $query->where('r.groupid=g.usergrpid')
            ->andWhere(ZSqlHelper::dbConditionInt('r.id', array_keys($group_links)));

        $query->select(['r.groupid', 'r.permission', 'r.id', 'g.name']);

        $db_rights = $query->all();

        foreach ($db_rights as $db_right) {
            if (!array_key_exists($db_right['groupid'], $usrgrps)) {
                $usrgrps[$db_right['groupid']] = ['usrgrpid' => $db_right['groupid']];
                $db_usrgrps[$db_right['groupid']] = [
                    'usrgrpid' => $db_right['groupid'],
                    'name' => $db_right['name'],
                ];
            }

            if (!array_key_exists('templategroup_rights', $db_usrgrps[$db_right['groupid']])) {
                $db_usrgrps[$db_right['groupid']]['templategroup_rights'] = [];
            }

            foreach ($group_links[$db_right['id']] as $hstgrpid) {
                $usrgrps[$db_right['groupid']]['templategroup_rights'][] = [
                    'permission' => $db_right['permission'],
                    'id' => $hstgrpid,
                ];
            }
        }
    }
}
