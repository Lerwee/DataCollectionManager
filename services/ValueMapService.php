<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\UuidValidator;
use app\customs\zapi\forms\ValueMapForm;
use app\customs\zapi\services\assist\BaseAssist;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\Valuemap;
use app\modules\libzbx\models\zbx\ValuemapMapping;
use Exception;

class ValueMapService extends BaseAssist
{

    public function create(array $params): Result
    {
        $rules = ValueMapForm::getValidationRules(__FUNCTION__);
        $bool = ValidateHelper::validateObjects($params, $rules, [
            'flags' => API_NOT_EMPTY | API_NORMALIZE,
            'uniq' => [['hostid', 'name']]
        ], $error);
        if (!$bool) {
            return $this->error(error_code(60750401), $error);
        }

        try {
            $this->validateValuemapMappings($params);

            $hostIds = [];

            foreach ($params as $param) {
                $hostIds[$param['hostid']] = true;
            }

            $hosts = Hosts::find()
                ->select(['status', 'hostid'])
                ->where(SqlHelper::whereIn('hostid', array_keys($hostIds)))
                ->indexBy('hostid')
                ->asArray()
                ->all();

            $hostId2names = [];

            foreach ($params as $param) {
                // check permissions by hostid
                if (!array_key_exists($param['hostid'], $hosts)) {
                    return $this->error(60750404, t('zapi', 'Invalid parameter {attribute}, {error}'), [
                        'attribute' => 'hostid',
                        'error' => ''
                    ]);
                }
                $hostId2names[$param['hostid']][] = $param['name'];
            }
            self::addUuid($params, $hosts);

            self::validateUuid($params, $hosts);

            self::checkUuidDuplicates($params);
            $this->checkDuplicates($hostId2names);

            $valueMapIds = DB::insert(Valuemap::tableName(), $params);

            $mappings = [];

            foreach ($params as $index => &$param) {
                $param['valuemapid'] = $valueMapIds[$index];
                $sortOrder = 0;

                foreach ($param['mappings'] as $mapping) {
                    $mappings[] = [
                        'type' => array_key_exists('type', $mapping) ? $mapping['type'] : VALUEMAP_MAPPING_TYPE_EQUAL,
                        'valuemapid' => $param['valuemapid'],
                        'value' => array_key_exists('value', $mapping) ? $mapping['value'] : '',
                        'newvalue' => $mapping['newvalue'],
                        'sortorder' => $sortOrder++
                    ];
                }
            }
            unset($param);

            DB::insert(ValuemapMapping::tableName(), $mappings);

            // TODO:ZBX AUDIT

            return $this->success(['valuemapids' => $valueMapIds]);
        } catch (Exception $e) {
            return $this->errorException($e);
        }
    }

    /**
     * 编辑
     *
     * @param array $params
     * @return Result
     */
    public function update(array $params): Result
    {
        $rules = ValueMapForm::getValidationRules(__FUNCTION__);
        $bool = ValidateHelper::validateObjects($params, $rules, [
            'flags' => API_NOT_EMPTY | API_NORMALIZE,
            'uniq' => [['valuemapid']]
        ], $error);
        if (!$bool) {
            return $this->error(60750401, $error);
        }

        try {
            $this->validateValuemapMappings($params);

            $dbValueMaps = Valuemap::find()
                ->where(['valuemapid' => array_column($params, 'valuemapid')])
                ->indexBy('valuemapid')
                ->asArray()
                ->all();
            foreach ($params as $param) {
                if (!array_key_exists($param['valuemapid'], $dbValueMaps)) {
                    return $this->error(60750404, t('zapi', 'Invalid parameter {attribute}, {error}'), [
                        'attribute' => 'valuemapid',
                        'error' => ''
                    ]);
                }
            }

            $params = $this->extendObjectsByKey($params, $dbValueMaps, 'valuemapid', ['hostid']);
            $bool = ValidateHelper::validateObjects($params, ValueMapForm::getBaseValidationRules(), [
                'flags' => API_ALLOW_UNEXPECTED,
                'uniq' => [['hostid', 'name']]
            ], $error);
            if (!$bool) {
                return $this->error(error_code(60750401), $error);
            }
            $hosts = Hosts::find()
                ->select(['status', 'hostid'])
                ->where(SqlHelper::whereIn('hostid', array_unique(array_column($dbValueMaps, 'hostid'))))
                ->indexBy('hostid')
                ->asArray()
                ->all();


            self::validateUuid($params, $hosts);

            self::checkUuidDuplicates($params, $dbValueMaps);

            $hostId2names = [];

            foreach ($params as $param) {
                $dbValueMap = $dbValueMaps[$param['valuemapid']];

                if (array_key_exists('name', $param) && $param['name'] !== $dbValueMap['name']) {
                    $hostId2names[$param['hostid']][] = $param['name'];
                }
            }

            if ($hostId2names) {
                $this->checkDuplicates($hostId2names);
            }

            $updateValueMaps = $valueMapsMappings = [];

            foreach ($params as $param) {
                $valuemapid = $param['valuemapid'];

                $dbValueMap = $dbValueMaps[$valuemapid];

                if (array_key_exists('name', $param) && $param['name'] !== $dbValueMap['name']) {
                    $updateValueMaps[] = [
                        'values' => ['name' => $param['name']],
                        'where' => ['valuemapid' => $param['valuemapid']]
                    ];
                }

                if (array_key_exists('uuid', $param) && $param['uuid'] !== $dbValueMap['uuid']) {
                    $updateValueMaps[] = [
                        'values' => ['uuid' => $param['uuid'], 'name' => $param['name']],
                        'where' => ['valuemapid' => $param['valuemapid']]
                    ];
                }

                if (array_key_exists('mappings', $param)) {
                    $valueMapsMappings[$valuemapid] = [];
                    $sortOrder = 0;

                    foreach ($param['mappings'] as $mapping) {
                        $mapping += ['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => ''];
                        $valueMapsMappings[$valuemapid][] = [
                            'type' => $mapping['type'],
                            'value' => $mapping['value'],
                            'newvalue' => $mapping['newvalue'],
                            'sortorder' => $sortOrder++
                        ];
                    }
                }
            }

            if ($updateValueMaps) {
                DB::update(Valuemap::tableName(), $updateValueMaps);
            }

            if ($valueMapsMappings) {
                $this->updateMappings($valueMapsMappings);
            }

            // TODO: zbx audit

            return $this->success(['valuemapids' => array_column($params, 'valuemapid')]);
        } catch (Exception $e) {
            return $this->errorException($e);
        }
    }

    protected function updateMappings(array $valueMappings)
    {
        $dbMappings = ValuemapMapping::find()
            ->select(['valuemap_mappingid', 'valuemapid', 'type', 'value', 'newvalue', 'sortorder'])
            ->where(['valuemapid' => array_keys($valueMappings)])
            ->asArray()
            ->all();

        ArrayHelper::multisort($dbMappings, 'sortorder');

        $ins_mapings = [];
        $upd_mapings = [];
        $del_mapingids = [];
        $valuemapid_db_mappings = array_fill_keys(array_keys($valueMappings), []);

        foreach ($dbMappings as $db_mapping) {
            $valuemapid_db_mappings[$db_mapping['valuemapid']][] = $db_mapping;
        }

        foreach ($valueMappings as $valuemapid => $mappings) {
            $db_mappings = &$valuemapid_db_mappings[$valuemapid];

            foreach ($mappings as $mapping) {
                $exists = false;

                foreach ($db_mappings as $i => $db_mapping) {
                    if ($db_mapping['type'] == $mapping['type'] && $db_mapping['value'] == $mapping['value']) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $ins_mapings[] = ['valuemapid' => $valuemapid] + $mapping;
                    continue;
                }

                $update_fields = array_diff_assoc($mapping, $db_mapping);

                if ($update_fields) {
                    $upd_mapings[] = [
                        'values' => $update_fields,
                        'where' => ['valuemap_mappingid' => $db_mapping['valuemap_mappingid']]
                    ];
                }

                unset($db_mappings[$i]);
            }
        }
        unset($db_mappings);

        foreach ($valuemapid_db_mappings as $db_mappings) {
            if ($db_mappings) {
                $del_mapingids = array_merge($del_mapingids, array_column($db_mappings, 'valuemap_mappingid'));
            }
        }

        if ($del_mapingids) {
            DB::delete('valuemap_mapping', ['valuemap_mappingid' => $del_mapingids]);
        }

        if ($upd_mapings) {
            DB::update('valuemap_mapping', $upd_mapings);
        }

        if ($ins_mapings) {
            DB::insert('valuemap_mapping', $ins_mapings);
        }
    }

    public function delete(array $ids): Result
    {
        $rules = [
            'ids' => [IdsValidator::class, 'flags' => API_NOT_EMPTY, 'uniq' => true],
        ];

        $params = [
            'ids' => $ids,
        ];

        $bool = ValidateHelper::validateObject($params, $rules, [
            'flags' => API_NOT_EMPTY | API_NORMALIZE,
            'uniq' => [['valuemapid']]
        ], $error);
        if (!$bool) {
            return $this->error(60750401, $error);
        }

        $dbValueMaps = Valuemap::find()
            ->select(['valuemapid', 'name'])
            ->where(['valuemapid' => $ids])
            ->asArray()
            ->indexBy('valuemapid')
            ->all();


        foreach ($ids as $id) {
            if (!array_key_exists($id, $dbValueMaps)) {
                return $this->error(60750404, t('zapi', 'Invalid parameter {attribute}, {error}'), [
                    'attribute' => 'valuemapid',
                    'error' => ''
                ]);
            }
        }

        DB::update('items', [[
            'values' => ['valuemapid' => 0],
            'where' => ['valuemapid' => $ids]
        ]]);

        Valuemap::deleteAll(['valuemapid' => $ids]);

        // zbx audit

        return $this->success(['valuemapids' => $ids]);
    }


    /**
     * Validate uniqueness of mapping value in value maps, type VALUEMAP_MAPPING_TYPE_DEFAULT can be defined only once
     * per value map mappings.
     *
     * @param array $valuemaps  Array of valuemaps
     *
     * @throws ValidateException when non unique
     */
    protected function validateValuemapMappings(array $valuemaps)
    {
        $i = 0;
        $error = '';

        foreach ($valuemaps as $valuemap) {
            $i++;

            if (!array_key_exists('mappings', $valuemap)) {
                continue;
            }

            $type_uniq = array_fill_keys(
                [
                    VALUEMAP_MAPPING_TYPE_EQUAL, VALUEMAP_MAPPING_TYPE_GREATER_EQUAL,
                    VALUEMAP_MAPPING_TYPE_LESS_EQUAL, VALUEMAP_MAPPING_TYPE_IN_RANGE, VALUEMAP_MAPPING_TYPE_REGEXP
                ],
                []
            );
            $has_default = false;

            foreach (array_values($valuemap['mappings']) as $j => $mapping) {
                $type = array_key_exists('type', $mapping) ? $mapping['type'] : VALUEMAP_MAPPING_TYPE_EQUAL;
                $value = array_key_exists('value', $mapping) ? (string) $mapping['value'] : '';

                if ($has_default && $type == VALUEMAP_MAPPING_TYPE_DEFAULT) {
                    $error = t('zapi', 'value {value} already exists', ['value' => '(type)=(' . VALUEMAP_MAPPING_TYPE_DEFAULT . ')']);
                } elseif (!array_key_exists('value', $mapping) && $type != VALUEMAP_MAPPING_TYPE_DEFAULT) {
                    $error = t('zapi', 'the parameter {parameter} is missing', ['parameter' => 'value']);
                } elseif ($value !== '' && $type == VALUEMAP_MAPPING_TYPE_DEFAULT) {
                    throw new ValidateException(60750401, t('zapi', 'Invalid parameter {attribute}, {error}', [
                        'attribute' => sprintf('/%1$s/mappings/%2$s/value', $i, $j + 1),
                        'error' => t('zapi', 'should be empty')
                    ]));
                } elseif ($type != VALUEMAP_MAPPING_TYPE_DEFAULT && array_key_exists($value, $type_uniq[$type])) {
                    $error = t('zapi', 'value {value} already exists', ['value' => '(type)=(' . $value . ')']);
                }

                if ($error !== '') {
                    throw new ValidateException(60750401, t('zapi', 'Invalid parameter {attribute}, {error}', [
                        'attribute' => sprintf('/%1$s/mappings/%2$s', $i, $j + 1),
                        'error' => $error
                    ]));
                }

                $has_default = ($has_default || $type == VALUEMAP_MAPPING_TYPE_DEFAULT);
                $type_uniq[$type][$value] = true;
            }
        }
    }

    /**
     * @param array $valuemaps
     * @param array $hosts
     *
     * @throws ValidateException
     */
    private static function validateUuid(array $valuemaps, array $hosts): void
    {
        foreach ($valuemaps as &$valuemap) {
            $valuemap['host_status'] = $hosts[$valuemap['hostid']]['status'];
        }
        unset($valuemap);
        $rules = [
            'host_status' => ['safe'],
            'uuid' => [
                MultipleValidator::class,
                'rules' => [UuidValidator::class, 'when' => function ($model) {
                    return $model->host_status == HOST_STATUS_TEMPLATE;
                }],
                'else' => [
                    Utf8StringValidator::class,
                    'in' => DB::getDefault('valuemap', 'uuid'),
                    'unset' => true
                ]
            ]
        ];
        $bool = ValidateHelper::validateObjects($valuemaps, $rules, [
            'flags' => API_ALLOW_UNEXPECTED,
            'uniq' => [['uuid']]
        ], $error);
        if (!$bool) {
            self::exception(60750401, $error);
        }
    }

    /**
     * Add the UUID to those of the given value maps that belong to a template and don't have the 'uuid' parameter set.
     *
     * @param array $valuemaps
     * @param array $hosts
     */
    private static function addUuid(array &$valuemaps, array $hosts): void
    {
        foreach ($valuemaps as &$valuemap) {
            if (
                $hosts[$valuemap['hostid']]['status'] == HOST_STATUS_TEMPLATE
                && !array_key_exists('uuid', $valuemap)
            ) {
                $valuemap['uuid'] = generateUuidV4();
            }
        }
        unset($valuemap);
    }

    /**
     * Verify value map UUIDs are not repeated.
     *
     * @param array      $valuemaps
     * @param array|null $db_valuemaps
     *
     * @throws ValidateException
     */
    private static function checkUuidDuplicates(array $valuemaps, array $dbValuemaps = null): void
    {
        $valuemap_indexes = [];

        foreach ($valuemaps as $i => $valuemap) {
            if (!array_key_exists('uuid', $valuemap) || $valuemap['uuid'] === '') {
                continue;
            }

            if ($dbValuemaps === null || $valuemap['uuid'] !== $dbValuemaps[$valuemap['valuemapid']]['uuid']) {
                $valuemap_indexes[$valuemap['uuid']] = $i;
            }
        }

        if (!$valuemap_indexes) {
            return;
        }

        $duplicate = Valuemap::find()->select(['uuid'])
            ->where(['uuid' => array_keys($valuemap_indexes)])
            ->asArray()
            ->limit(1)
            ->one();

        if ($duplicate) {
            throw new ValidateException(60750401, t('zapi', 'Invalid parameter {attribute}, {error}', [
                'attribute' =>  '/' . ($valuemap_indexes[$duplicate['uuid']] + 1),
                'error' => t('zapi', 'value map with the same UUID already exists')
            ]));
        }
    }

    /**
     * Check for duplicated value maps.
     *
     * @param array $hostId2names
     *
     * @throws ValidateException  if value map already exists.
     */
    private function checkDuplicates(array $hostId2names)
    {
        $query = Valuemap::find()->select(['name']);
        foreach ($hostId2names as $hostid => $names) {
            $query->andWhere([
                'AND',
                ['hostid' => $hostid],
                SqlHelper::stringWhereIn('name', $names)
            ]);
        }

        $query->limit(1);
        $name = $query->asArray()->scalar();

        if ($name) {
            self::exception(60750401, t('zapi', 'Value map "{value}" already exists.', [
                'value' => $name
            ]));
        }
    }
}
