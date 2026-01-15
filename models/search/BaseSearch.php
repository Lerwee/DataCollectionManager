<?php

namespace app\customs\zapi\models\search;

use app\common\base\BaseModel;
use app\common\helpers\ArrayHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\components\RelationMap;
use ReflectionClass;
use yii\db\Exception;
use yii\db\Query;

/**
 * Class BaseSearch
 * @package app\customs\zapi\models\search
 */
class BaseSearch extends BaseModel
{
    // $output
    public $output = API_OUTPUT_EXTEND;
    public $countOutput = false;
    public $groupCount = false;
    // sort and limit
    public $sortfield = [];
    public $sortorder = [];
    public $limit = null;
    public $limitSelect = null;
    // flags
    public $editable =	false;
    public $nopermissions = null;
    public $preservekeys =false;
    //filter
    public $filter = null;
    public $search = null;
    public $searchByAny = null;
    public $startSearch = false;
    public $excludeSearch = false;
    public $searchWildcardsEnabled = null;

    public $pk;

    protected $sortColumns = [];

    /**
     * {@inheritDoc}
     */
    public function rules()
    {
        $attributes = [];
        $properties = (new ReflectionClass($this))->getProperties();
        foreach ($properties as $property) {
            $property->isPublic() && array_push($attributes, $property->name);
        }
        return [
            [$attributes, 'safe']
        ];
    }

    /**
     * @param Query $query
     * @param string $tableName
     * @param string $tableAlias
     * @param string|array $outPut extend/count/[field, ...]
     * @param array $options
     * @throws Exception
     */
    protected function applyQueryOutputOptions(Query &$query, string $tableName, string $tableAlias, $outPut = 'extent', array $options = [])
    {
        $pk = $this->pk($tableName);
        $pk_composite = (strpos($pk, ',') !== false);
        $fieldName = $tableAlias ? "{$tableAlias}.{$pk}" : $pk;
        if ($outPut == 'count' && !$this->requiresPostSqlFiltering($options)) {
            $from = $query->from;
            $joins = ArrayHelper::getColumn((array)$query->join, '0');
            $hasJoins = (count($from) > 1 || in_array('LEFT JOIN', $joins));
            if ($pk_composite && $hasJoins) {
                throw new Exception('Joins with composite primary keys are not supported in this API version.');
            }
            if ($hasJoins) {
                $query->select(['rowscount' => 'COUNT(DISTINCT ' . $fieldName . ')']);
            } else {
                $query->select(['rowscount' => 'COUNT(*)']);
            }
            if ($query->groupBy) {
                $selects = (array)$query->select;
                foreach ($query->groupBy as $field) {
                    !in_array($field, $selects) && $query->addSelect([$field]);
                }
            }
        } // custom output
        elseif (is_array($outPut)) {
            $query->select($pk_composite ? [] : [$fieldName]);
            array_map(function ($field) use (&$query, $tableAlias, $tableName) {
                if ($this->hasField($field, $tableName)) {
                    $query->addSelect($tableAlias ? "{$tableAlias}.{$field}" : $field);
                }
            }, $outPut);
        } // extended output
        elseif ($outPut == API_OUTPUT_EXTEND) {
            $query->select(["$tableAlias.*"]);
        }
    }


    /**
     * @param Query $query
     * @param string $tableName
     * @param string $tableAlias
     * @param $sortField
     * @param $sortOrder
     * @throws Exception
     */
    protected function applyQuerySortOptions(Query &$query, string $tableName, string $tableAlias, $sortField, $sortOrder)
    {
        if ($this->sortColumns && !prs_empty($sortField)) {
            $sortField = is_array($sortField) ? array_unique($sortField) : [$sortField];
            foreach ($sortField as $i => $field) {
                if (!str_in_array($field, $this->sortColumns)) {
                    throw new Exception(t('zapi', 'Sorting by field "{field}" not allowed.', ['field' => $field]));
                }

                $order = '';
                if (is_array($sortOrder)) {
                    if (!empty($sortOrder[$i])) {
                        $order = $sortOrder[$i];
                    }
                } else {
                    $order = $sortOrder;
                }

                $this->applyQuerySortField($query, $field, $order, $tableAlias);
            }
        }
    }

    /**
     * @param Query $query
     * @param string $sortField
     * @param string $sortOrder
     * @param string $alias
     */
    protected function applyQuerySortField(Query &$query, string $sortField, string $sortOrder, string $alias)
    {
        // add sort field to select if distinct is used
        $from = (array)$query->from;
        $select = (array)$query->select;
        $joins = ArrayHelper::getColumn((array)$query->join, '0');
        $hasJoins = (count($from) > 1 || in_array('LEFT JOIN', $joins));
        if ($hasJoins && !str_in_array($alias . '.' . $sortField, $select)
            && !str_in_array($alias . '.*', $select)) {
            $query->addSelect([$sortField => $alias . '.' . $sortField]);
        }
        $query->addOrderBy([$alias . '.' . $sortField => $sortOrder == 'DESC' ? SORT_DESC : SORT_ASC]);
    }

    /**
     * @param Query $query
     * @return string
     * @throws Exception
     */
    protected static function dbDistinct(Query $query): string
    {
        $select = (array)$query->select;
        if (preg_grep('/^COUNT\(/i', $select)) {
            return '';
        }
        $count = count($query->from);
        $joins = ArrayHelper::getColumn((array)$query->join, '0');
        if ($count == 1 && in_array('LEFT JOIN', $joins)) {
            foreach ((array)$query->join as $join) {
                if ($join[0] == 'LEFT JOIN') {
                    if (is_array($join[1])) {
                        $leftTable = current($join[1]);
                        $alias = array_key_first($join[1]);
                        if (is_numeric($alias)) {
                            $alias = $leftTable;
                        }
                    } else {
                        $arr = explode('.', $join[1]);
                        $leftTable = end($arr);
                        $alias = count($arr) == 1 ? $leftTable : current($arr);
                    }
                    $relationKeys = array_map('trim', explode('=', $join[2]));
                    foreach ($relationKeys as $key) {
                        if ($key == "$alias.$leftTable") {
                            $usingKey = $key;
                            break;
                        }
                    }
                    if (empty($usingKey)) {
                        break;
                    }
                    $table = DB::getSchema($leftTable);
                    // Increase count when table linked by non-unique column.
                    if ($usingKey !== $table['key']) {
                        $count++;
                        break;
                    }
                }
            }
        }

        return ($count > 1 ? ' DISTINCT' : '');
    }


    /**
     * Returns true if the given field is requested in the output parameter.
     *
     * @param $field
     * @param $output
     *
     * @return bool
     */
    protected function outputIsRequested($field, $output): bool
    {
        switch ($output) {
            // if all fields are requested, just return true
            case API_OUTPUT_EXTEND:
                return true;

            // return false if nothing or an object count is requested
            case API_OUTPUT_COUNT:
            case null:
                return false;

            // if an array of fields is passed, check if the field is present in the array
            default:
                return is_array($output) && in_array($field, $output);
        }
    }


    /**
     * Unset those fields of the objects, which are not requested for the $output.
     *
     * @param array $objects
     * @param array $fields
     * @param string|array $output requested output
     *
     * @return array
     */
    protected function unsetExtraFields(array $objects, array $fields, $output = []): array
    {
        // find the fields that have not been requested
        $extraFields = [];
        foreach ($fields as $field) {
            if (!$this->outputIsRequested($field, $output)) {
                $extraFields[] = $field;
            }
        }

        // unset these fields
        if ($extraFields) {
            foreach ($objects as &$object) {
                foreach ($extraFields as $field) {
                    unset($object[$field]);
                }
            }
            unset($object);
        }

        return $objects;
    }

    /**
     * @param null $tableName
     * @return mixed
     * @throws Exception
     */
    public function pk($tableName = null)
    {
        if ($tableName) {
            $schema = DB::getSchema($tableName);
            return $schema['key'];
        }
        return $this->pk;
    }

    /**
     * @param array $options
     * @return bool
     */
    protected function requiresPostSqlFiltering(array $options): bool
    {
        return false;
    }

    /**
     * Adds the given fields to the "output" option if it's not already present.
     *
     * @param string $output
     * @param array $fields either a single field name, or an array of fields
     *
     * @return mixed
     */
    protected function outputExtend($output, array $fields)
    {
        if ($output === null) {
            return $fields;
        } // if output is set to extend, it already contains that field; return it as is
        elseif ($output === API_OUTPUT_EXTEND) {
            return $output;
        }

        // if output is an array, add the additional fields
        return array_keys(array_flip(array_merge($output, $fields)));
    }

    /**
     * Returns true if the table has the given field. If no $tableName is given,
     * the current table will be used.
     *
     * @param string $fieldName
     * @param string $tableName
     *
     * @return boolean
     */
    protected function hasField($fieldName, $tableName = null) {
        $schema = DB::getSchema($tableName);

        return isset($schema['fields'][$fieldName]);
    }

    /**
     * Adds the related objects requested by "select*" options to the resulting object set.
     *
     * @param array $result   An object hash with PKs as keys.
     *
     * @return array mixed
     */
    protected function addRelatedObjects(array $result) {
        // must be implemented in each API separately

        return $result;
    }


    /**
     * Creates a relation map for the given objects.
     *
     * If the $table parameter is set, the relations will be loaded from a database table, otherwise the map will be
     * built from two base object properties.
     *
     * @param array  $objects			a hash of base objects
     * @param string $baseField			the base object ID field
     * @param string $foreignField		the related objects ID field
     * @param string $table				table to load the relation from
     *
     * @return RelationMap
     */
    protected function createRelationMap(array $objects, $baseField, $foreignField, $table = null)
    {
        $relationMap = new RelationMap();

        // create the map from a database table
        if ($table) {
            $rows = (new Query())->select([$baseField, $foreignField])
                ->from($table)
                ->where([$baseField => array_keys($objects)])
                ->all();
            foreach ($rows as $relation) {
                $relationMap->addRelation($relation[$baseField], $relation[$foreignField]);
            }
        }

        // create a map from the base objects
        else {
            foreach ($objects as $object) {
                $relationMap->addRelation($object[$baseField], $object[$foreignField]);
            }
        }

        return $relationMap;
    }

    protected function setRelationMap($baseIds, $table, $baseField, $relationTable, $relationField, $relationFields): array
    {
        $list = (new Query())->select([$baseField, $relationField])
            ->from($table)
            ->where([$baseField => $baseIds])
            ->all();

        $relationIds = [];
        $targetIds = [];
        foreach ($list as $item) {
            $relationIds[$item[$baseField]][$item[$relationField]] = $item[$relationField];
            $targetIds[$item[$relationField]] = $item[$relationField];
        }
        $data = [];
        if ($targetIds) {
            $relationModels = (new Query())->select($relationFields)
                ->from($relationTable)
                ->where([$relationField => $targetIds])
                ->indexBy($relationField)
                ->all();
            foreach ($relationIds as $baseId => $targetIds) {
                foreach ($targetIds as $relationId) {
                    if (isset($relationModels[$relationId])) {
                        $data[$baseId][] = $relationModels[$relationId];
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Prepends the table alias to the given field name. If no $tableAlias is given,
     * the alias of the current table will be used.
     *
     * @param string $fieldName
     * @param string $tableAlias
     *
     * @return string
     */
    protected function fieldId($fieldName, $tableAlias = null) {
        return $tableAlias ? $tableAlias.'.'.$fieldName : $fieldName;
    }
}