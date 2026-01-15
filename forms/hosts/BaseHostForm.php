<?php

namespace app\customs\zapi\forms\hosts;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\UserMacroValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\forms\BaseForm;
use app\modules\libzbx\models\zbx\Hostmacro;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\HostTag;
use app\modules\libzbx\models\zbx\Hstgrp;
use yii\db\Query;

abstract class BaseHostForm extends BaseForm
{
    /**
     * 模板验证规则
     *
     * @return array
     */
    public static function getTemplatesValidationRules(): array
    {
        return [
            ObjectsValidator::class,
            'flags' => API_NORMALIZE,
            'uniq' => [['templateid']],
            'fields' => [
                'templateid' => [IdValidator::class, 'flags' => API_REQUIRED]
            ]
        ];
    }

    /**
     * 模板分组ID集合验证规则
     *
     * @return array
     */
    public static function getGroupsValidationRules(): array
    {
        return [
            ObjectsValidator::class,
            'flags' => API_NOT_EMPTY | API_NORMALIZE,
            'uniq' => [['groupid']],
            'fields' => [
                'groupid' => [IdValidator::class, 'flags' => API_REQUIRED]
            ]
        ];
    }

    /**
     * 模板标签集合验证规则
     *
     * @return array
     */
    public static function getTagsValidationRules(): array
    {
        return [
            ObjectsValidator::class,
            'uniq' => [['tag', 'value']],
            'fields' => [
                'tag' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength(HostTag::tableName(), 'tag')],
                'value' => [Utf8StringValidator::class,  'length' => DB::getFieldLength(HostTag::tableName(), 'value'), 'default' => DB::getDefault(HostTag::tableName(), 'value')]
            ]
        ];
    }

    /**
     * 模板/主机宏集合验证规则
     *
     * @return array
     */
    public static function getMacrosValidationRules(string $method = 'create'): array
    {
        $rules = [
            ObjectsValidator::class,
            'flags' => API_NORMALIZE,
            'uniq' => [['hostmacroid']],
            'fields' => [
                'hostmacroid' => [IdValidator::class],
                'macro' => [UserMacroValidator::class, 'length' => DB::getFieldLength(Hostmacro::tableName(), 'macro')],
                'type' => [Int32Validator::class, 'in' => [PRS_MACRO_TYPE_TEXT, PRS_MACRO_TYPE_SECRET, PRS_MACRO_TYPE_VAULT]],
                'value' => [Utf8StringValidator::class, 'length' => DB::getFieldLength(Hostmacro::tableName(), 'value')],
                'description' => [Utf8StringValidator::class, 'length' => DB::getFieldLength(Hostmacro::tableName(), 'description')],
                'automatic' => [Int32Validator::class, 'in' => [PRS_USERMACRO_MANUAL]]
            ]
        ];

        if ($method == 'create') {
            $rules['uniq'] = [['macro']];
            unset($rules['fields']['hostmacroid']);
            unset($rules['fields']['automatic']);
        }

        return $rules;
    }

    /**
     * Check for unique host names.
     *
     * @param array      $hosts
     * @param array|null $dbHosts
     * @param string     $primaryKey
     *
     * @throws ValidateException if host names are not unique.
     */
    public static function checkDuplicates(array $hosts, array $dbHosts = null, $primaryKey = 'hostid'): void
    {
        $hNames = [];
        $vNames = [];
        foreach ($hosts as $host) {
            if (array_key_exists('host', $host)) {
                if ($dbHosts === null || $host['host'] !== $dbHosts[$host[$primaryKey]]['host']) {
                    $hNames[] = $host['host'];
                }
            }

            if (array_key_exists('name', $host)) {
                if ($dbHosts === null || $host['name'] !== $dbHosts[$host[$primaryKey]]['name']) {
                    $vNames[] = $host['name'];
                }
            }
        }

        $shortname = basename(str_replace('\\', '/', static::class));
        $shortname = substr($shortname, 0, -4);
        if ($hNames) {
            $duplicate = self::getOneByValues($hNames, 'host', [
                'flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED],
                'status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE],
            ]);

            if ($duplicate) {
                $error = t('zapi', '{target} with host name "{name}" already exists.', [
                    'target' => t('zapi', $shortname),
                    'name' => $duplicate['host'],
                ]);
                self::exception(60750001, $error);
            }
        }

        if ($vNames) {
            $duplicate = self::getOneByValues($vNames, 'name', [
                'flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED],
                'status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE],
            ]);

            if ($duplicate) {
                $error = t('zapi', '{target} with visible name "{name}" already exists.', [
                    'target' => t('zapi', $shortname),
                    'name' => $duplicate['name'],
                ]);
                self::exception(60750001, $error);
            }
        }
    }

    /**
     * Verify params field are not repeated.
     *
     * @param array $params        需要验证的参数
     * @param array|null $dbParams 数据库数据参数[<primaryKey> = []]
     * @param string $field        验证字段
     * @param string $index        下标字段
     * @param array $extraParams   额外参数
     * @return void
     * @throws ValidateException
     */
    public static function checkFieldDuplicates(array $params, array $dbParams = null, $field = 'name', $index = 'hostid', $extraParams = []): void
    {
        $indexes = [];

        foreach ($params as $i => $param) {
            if (!array_key_exists($field, $param)) {
                continue;
            }

            if ($dbParams === null || $param[$field] !== $dbParams[$param[$index]][$field]) {
                $indexes[$param[$field]] = $i;
            }
        }

        if (!$indexes) {
            return;
        }

        $duplicates = self::getOneByValues(array_keys($indexes), $field, $extraParams);
        if ($duplicates) {
            // 取短类名，精确区分（host 与 template）
            $shortname = basename(str_replace('\\', '/', static::class));
            $shortname = substr($shortname, 0, -4);

            $error = t('zapi', 'Invalid parameter {attribute}, {error}', [
                'attribute' => '/' . ($i + 1),
                'error' => t('zapi', '{target} with the same {field} already exists', [
                    'target' => t('zapi', $shortname),
                    'field' => $field
                ])
            ]);
            self::exception(60750001, $error);
        }
    }

    /**
     * 指定名称查找一条数据
     * 
     * @param string[]|string $values
     * @param string $field
     * @return array|null
     */
    public static function getOneByValues($values, string $field = 'name', array $params = [])
    {
        $query = new Query();
        $query->from(Hosts::tableName());

        $query->where([$field => $values]);
        if ($params) {
            $query->andWhere($params);
        }
        $query->limit(1);
        return $query->one();
    }

    /**
     * Check for valid host groups and template groups.
     *
     * @param array      $hosts
     * @param array|null $dbHosts
     * @param string     $primaryKey
     *
     * @throws ValidateException if groups are not valid.
     */
    public static function checkGroups(array $hosts, array $dbHosts = null, string $primaryKey = 'hostid'): void
    {
        $editGroupIds = [];
        foreach ($hosts as $host) {
            if (!array_key_exists('groups', $host)) {
                continue;
            }

            $groupIds = array_column($host['groups'], 'groupid');

            if ($dbHosts === null) {
                $editGroupIds += array_flip($groupIds);
            } else {
                $dbGroupIds = array_column($dbHosts[$host[$primaryKey]]['groups'], 'groupid');

                $insGroupIds = array_flip(array_diff($groupIds, $dbGroupIds));
                $delGroupIds = array_flip(array_diff($dbGroupIds, $groupIds));

                $editGroupIds += $insGroupIds + $delGroupIds;
            }
        }

        if (!$editGroupIds) {
            return;
        }

        $query = new Query();
        $query->from(Hstgrp::tableName());
        $query->where(['groupid' => array_keys($editGroupIds)]);
        $shortname = basename(str_replace('\\', '/', static::class));
        // HOST_GROUP_TYPE_HOST_GROUP | HOST_GROUP_TYPE_TEMPLATE_GROUP
        $query->andWhere(['type' => $shortname == 'TemplateForm' ? 1 : 0]);
        $count = $query->select('groupid')->count();

        if ($count != count($editGroupIds)) {
            self::exception(60750001, t('yii', 'Invalid data received for parameter "{param}".', ['param' => 'groups']));
        }
    }

    /**
     * Check for valid templates.
     *
     * @param array      $hosts
     * @param array|null $dbHosts
     * @param string     $primaryKey
     *
     * @throws ValidateException
     */
    public static function checkTemplates(array $hosts, array $dbHosts = null, string $primaryKey = 'hostid'): void
    {
        $edit_templates = [];

        foreach ($hosts as $i1 => $host) {
            if (array_key_exists('templates', $host) && array_key_exists('templates_clear', $host)) {
                $path_clear = '/' . ($i1 + 1) . '/templates_clear';
                $path = '/' . ($i1 + 1) . '/templates';

                foreach ($host['templates_clear'] as $i2_clear => $template_clear) {
                    foreach ($host['templates'] as $i2 => $template) {
                        if (bccomp($template['templateid'], $template_clear['templateid']) == 0) {
                            $error = t('zapi', 'Invalid parameter {attribute}, {error}', [
                                'attribute' => $path_clear . '/' . ($i2_clear + 1) . '/templateid',
                                'error' => t('zapi', 'cannot be specified the value of parameter "{parameter}"', [
                                    'param' => $path . '/' . ($i2 + 1) . '/templateid'
                                ])
                            ]);
                            self::exception(60750001, $error);
                        }
                    }
                }
            }

            if (array_key_exists('templates', $host)) {
                $templates = array_column($host['templates'], null, 'templateid');

                if ($dbHosts === null) {
                    $edit_templates += $templates;
                } else {
                    $db_templates = array_column($dbHosts[$host[$primaryKey]]['templates'], null, 'templateid');

                    $ins_templates = array_diff_key($templates, $db_templates);
                    $del_templates = array_diff_key($db_templates, $templates);

                    $edit_templates += $ins_templates + $del_templates;
                }
            }

            if (array_key_exists('templates_clear', $host)) {
                $edit_templates += array_column($host['templates_clear'], null, 'templateid');
            }
        }

        if (!$edit_templates) {
            return;
        }


        $query = new Query();
        $query->from(Hosts::tableName());

        $query->where(['hostid' => array_keys($edit_templates)]);

        $query->andWhere(['status' => 3]);

        $count = $query->count();

        if ($count != count($edit_templates)) {
            self::exception(60750001, t('yii', 'Invalid data received for parameter "{param}".', ['param' => 'templates']));
        }

        foreach ($hosts as $i1 => $host) {
            if (!array_key_exists('templates_clear', $host)) {
                continue;
            }

            $db_templates = array_column($dbHosts[$host[$primaryKey]]['templates'], null, 'templateid');
            $path = '/' . ($i1 + 1) . '/templates_clear';

            foreach ($host['templates_clear'] as $i2 => $template) {
                if (!array_key_exists($template['templateid'], $db_templates)) {
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', [
                        'attribute' => $path . '/' . ($i2 + 1) . '/templateid',
                        'error' => t('zapi', 'cannot be unlinked')
                    ]);
                    self::exception(60750001, $error);
                }
            }
        }
    }

    /**
     * 指定名称查找主机
     * 
     * @param string[]|string $names
     * @param string $field
     * @return array|null
     */
    public static function getOneByNames($names, string $field = 'name')
    {
        $query = Hosts::find();
        $query->select(['hostid', 'host', 'name', 'status']);
        $query->where([$field => $names]);
        $query->andWhere(['status' => [0, 1, 3]]);
        return $query->limit(1)->asArray()->one();
    }
}
