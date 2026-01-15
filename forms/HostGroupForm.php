<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\validators\HostGroupNameValidator;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\ObjectValidator;
use app\customs\zapi\common\validators\UuidValidator;
use app\modules\libzbx\models\zbx\Hstgrp;

class HostGroupForm extends BaseForm
{
    public $name;
    public $uuid;

    /**
     * {@inheritDoc}
     */
    public function rules(): array
    {
        return [
            ['name', 'required'],
            [['name', 'uuid'], 'string'],
            ['name', HostGroupNameValidator::class, 'length' => DB::getFieldLength(Hstgrp::tableName(), 'name')],
            ['uuid', UuidValidator::class],
        ];
    }

    /**
     * 生成UUID
     *
     * @return string
     */
    public function generateUUID()
    {
        return $this->uuid = generateUuidV4();
    }

    /**
     * 指定名称查找主机
     *
     * @param string[]|string $values
     * @param string $field
     * @return array|null
     */
    public static function getOneByValues($values, string $field = 'name', string $index = 'groupid')
    {
        $query = Hstgrp::find();
        $query->select($field);
        $query->where([$field => $values]);
        // 区分主机组
        $query->andWhere(['type' => HOST_GROUP_TYPE_HOST_GROUP]);
        $query->indexBy($index)
            ->asArray();
        return $query->limit(1)->one();
    }

    /**
     * 批量验证参数
     *
     * @param string $method
     * @param boolean $full
     * @return array
     */
    public static function getMassValidationRules(string $method = 'create', $full = false): array
    {
        $flags = API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE;
        if ($method == 'delete') {
            $rules = [
                'groupids' => [
                    IdsValidator::class,
                    'flags' => $flags,
                    'uniq' => true,
                ],
                'hostids' => [
                    IdsValidator::class,
                    'flags' => $flags,
                    'uniq' => true,
                ],
            ];
        } else {
            $rules = [
                'groups' => [
                    ObjectsValidator::class,
                    'flags' => $flags,
                    'uniq' => [['groupid']],
                    'fields' => [
                        'groupid' => [IdValidator::class, 'flags' => API_REQUIRED],
                    ],
                ],
                'hosts' => [
                    ObjectsValidator::class,
                    'flags' => $flags,
                    'uniq' => [['hostid']],
                    'fields' => [
                        'hostid' => [IdValidator::class, 'flags' => API_REQUIRED],
                    ],
                ],
            ];
        }

        if ($full) {
            return [
                ObjectValidator::class,
                'fields' => $rules,
            ];
        }
        return $rules;
    }
}
