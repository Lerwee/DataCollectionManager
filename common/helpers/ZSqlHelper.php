<?php

namespace app\customs\zapi\common\helpers;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\components\RelationMap;
use Yii;
use yii\db\Exception;
use yii\db\Query;

/**
 * Class ZSqlHelper
 * @package app\customs\zapi\common\helpers
 */
class ZSqlHelper extends SqlHelper
{
    /**
     * @param string $table
     * @param array $fieldFilter
     * @param string $tableAlias
     * @param bool $searchAny
     * @return string|null
     * @throws Exception
     */
    public static function dbFilter(string $table, array $fieldFilter, string $tableAlias = '', bool $searchAny = false): ?string
    {
        $tableSchema = DB::getSchema($table);
        $tableFields = $tableSchema['fields'] ?? [];

        $filter = [];
        foreach ($fieldFilter as $field => $value) {
            // skip missing fields and text fields (not supported by Oracle)
            // skip empty values
            if (!isset($tableFields[$field]) || $tableFields[$field]['type'] == DB::FIELD_TYPE_TEXT
                || $tableFields[$field]['type'] == DB::FIELD_TYPE_NCLOB
                || prs_empty($value)) {
                continue;
            }

            $values = [];

            switch ($tableFields[$field]['type']) {
                case DB::FIELD_TYPE_INT:
                    foreach ((array)$value as $val) {
                        if (!is_int($val) && (!is_string($val) || !preg_match('/^' . PRS_PREG_INT . '$/', $val))) {
                            continue;
                        }

                        if ($val < PRS_MIN_INT32 || $val > PRS_MAX_INT32) {
                            continue;
                        }

                        $values[] = $val;
                    }
                    break;

                case DB::FIELD_TYPE_ID:
                    foreach ((array)$value as $val) {
                        if (!is_int($val) && (!is_string($val) || !ctype_digit($val))) {
                            continue;
                        }

                        if ($val < 0 || bccomp((string)$val, PRS_DB_MAX_ID) > 0) {
                            continue;
                        }

                        $values[] = $val;
                    }
                    break;

                case DB::FIELD_TYPE_UINT:
                    foreach ((array)$value as $val) {
                        if (!is_int($val) && (!is_string($val) || !ctype_digit($val))) {
                            continue;
                        }

                        if (bccomp((string)$val, PRS_MIN_INT64) < 0 || bccomp((string)$val, PRS_MAX_INT64) > 0) {
                            continue;
                        }

                        $values[] = $val;
                    }
                    break;

                case DB::FIELD_TYPE_FLOAT:
                    foreach ((array)$value as $val) {
                        if (!is_numeric($val)) {
                            continue;
                        }

                        $values[] = $val;
                    }
                    break;

                default:
                    $values = (array)$value;
            }

            $fieldName = $tableAlias ? "{$tableAlias}.{$field}" : $field;
            switch ($tableFields[$field]['type']) {
                case DB::FIELD_TYPE_ID:
                    $filter[$field] = self::dbConditionId($fieldName, $values);
                    break;

                case DB::FIELD_TYPE_INT:
                case DB::FIELD_TYPE_UINT:
                    $filter[$field] = self::dbConditionInt($fieldName, $values);
                    break;

                default:
                    $filter[$field] = self::dbConditionString($fieldName, $values);
            }
        }

        if ($filter) {
            if (!$searchAny || count($filter) == 1) {
                return implode(' AND ', $filter);
            } else {
                return '(' . implode(' OR ', $filter) . ')';
            }
        }

        return null;
    }


    /**
     * Create condition SQL for field matching against numeric values.
     *
     * @param string $field_name
     * @param array $values
     * @param bool $not_in Create inverse condition.
     * @param bool $zero_to_null Cast zero to null.
     *
     * @return string
     */
    public static function dbConditionInt($field_name, array $values, $not_in = false, $zero_to_null = false)
    {
        $MIN_NUM_BETWEEN = 4; // Minimum number of consecutive values for using "BETWEEN <id1> AND <idN>".
        $MAX_NUM_IN = 950; // Maximum number of values for using "IN (<id1>,<id2>,...,<idN>)".

        if (is_bool(reset($values))) {
            return $not_in ? '1=1' : '1=0';
        }

        $values = array_flip($values);

        $has_zero = false;

        if ($zero_to_null && array_key_exists(0, $values)) {
            $has_zero = true;
            unset($values[0]);
        }

        $values = array_keys($values);
        natsort($values);
        $values = array_values($values);

        $intervals = [];
        $singles = [];

        if (Yii::$app->db->driverName == 'oci') {
            // For better performance, use "BETWEEN" constructs for sequential integer values, for Oracle database.

            for ($i = 0, $size = count($values); $i < $size; $i++) {
                if ($i + $MIN_NUM_BETWEEN < $size && bcsub($values[$i + $MIN_NUM_BETWEEN], $values[$i]) == $MIN_NUM_BETWEEN) {
                    $interval_first = $values[$i];

                    // Search for the last sequential integer value.
                    for ($i += $MIN_NUM_BETWEEN; $i < $size && bcsub($values[$i], $values[$i - 1]) == 1; $i++) ;
                    $i--;

                    $interval_last = $values[$i];

                    // Save the first and last values of the sequential interval.
                    $intervals[] = [self::dbQuoteInt($interval_first), self::dbQuoteInt($interval_last)];
                } else {
                    $singles[] = self::dbQuoteInt($values[$i]);
                }
            }
        } else {
            // For better performance, use only "IN" constructs all other databases, except Oracle.

            $singles = array_map(function ($value) {
                return self::dbQuoteInt($value);
            }, $values);
        }

        $condition = '';

        // Process intervals.

        foreach ($intervals as $interval) {
            if ($condition !== '') {
                $condition .= $not_in ? ' AND ' : ' OR ';
            }

            $condition .= ($not_in ? 'NOT ' : '') . $field_name . ' BETWEEN ' . $interval[0] . ' AND ' . $interval[1];
        }

        // Process individual values.

        $single_chunks = array_chunk($singles, $MAX_NUM_IN);

        foreach ($single_chunks as $chunk) {
            if ($condition !== '') {
                $condition .= $not_in ? ' AND ' : ' OR ';
            }

            if (count($chunk) == 1) {
                $condition .= $field_name . ($not_in ? '!=' : '=') . $chunk[0];
            } else {
                $condition .= $field_name . ($not_in ? ' NOT' : '') . ' IN (' . implode(',', $chunk) . ')';
            }
        }

        if ($has_zero) {
            if ($condition !== '') {
                $condition .= $not_in ? ' AND ' : ' OR ';
            }

            $condition .= $field_name . ($not_in ? ' IS NOT NULL' : ' IS NULL');
        }

        if (!$not_in) {
            if ((int)$has_zero + count($intervals) + count($single_chunks) > 1) {
                $condition = '(' . $condition . ')';
            }
        }

        return $condition;
    }

    /**
     * Takes an initial part of SQL query and appends a generated WHERE condition.
     *
     * @param string $fieldName field name to be used in SQL WHERE condition
     * @param array $values array of numerical values sorted in ascending order to be included in WHERE
     * @param bool $notIn builds inverted condition
     *
     * @return string
     */
    public static function dbConditionId($fieldName, array $values, $notIn = false)
    {
        return self::dbConditionInt($fieldName, $values, $notIn, true);
    }

    /**
     * Takes an initial part of SQL query and appends a generated WHERE condition.
     *
     * @param string $fieldName field name to be used in SQL WHERE condition
     * @param array $values array of string values sorted in ascending order to be included in WHERE
     * @param bool $notIn builds inverted condition
     *
     * @return string
     */
    public static function dbConditionString($fieldName, array $values, $notIn = false)
    {
        switch (count($values)) {
            case 0:
                return '1=0';
            case 1:
                return $notIn
                    ? $fieldName . '!=' . self::zbxDbstr(reset($values))
                    : $fieldName . '=' . self::zbxDbstr(reset($values));
        }

        $in = $notIn ? ' NOT IN ' : ' IN ';
        $concat = $notIn ? ' AND ' : ' OR ';
        $items = array_chunk($values, 950);

        $condition = '';
        foreach ($items as $values) {
            $condition .= !empty($condition) ? ')' . $concat . $fieldName . $in . '(' : '';
            $condition .= implode(',', self::zbxDbstr($values));
        }

        return '(' . $fieldName . $in . '(' . $condition . '))';
    }

    /**
     * Quote a value if not an integer or out of BC Math bounds.
     *
     * @param mixed $value Either the original or quoted value.
     */
    public static function dbQuoteInt($value)
    {
        if (!ctype_digit((string)$value) || bccomp($value, PRS_MAX_UINT64) > 0) {
            $value = self::zbxDbstr($value);
        }

        return $value;
    }

    /**
     * Return SQL for COALESCE like select. For fields with type NCHAR, NVARCHAR or NTEXT in Oracle NVL should be used
     * instead of COALESCE because it will not check that all arguments have same type.
     *
     * @param string $field_name Field name to be used in returned query part.
     * @param int|string $default_value Default value to be returned.
     * @param string $alias Alias to be used in 'AS' query part.
     * @return string
     */
    public static function dbConditionCoalesce($field_name, $default_value, $alias = '')
    {
        if (is_string($default_value)) {
            $default_value = ($default_value == '') ? '\'\'' : self::zbxDbstr($default_value);
        }

        $query = (Yii::$app->db->driverName == 'oci' ? 'NVL(' : 'COALESCE(') . $field_name . ',' . $default_value . ')';

        if ($alias) {
            $query .= ' AS ' . $alias;
        }

        return $query;
    }

    /**
     * @param $table
     * @param $options
     * @param Query $query
     * @return bool
     * @throws Exception
     */
    public static function zbxDbSearch($table, $options, Query &$query): bool
    {
        list($table, $tableShort) = explode(' ', $table);

        $tableSchema = DB::getSchema($table);
        if (!$tableSchema) {
            throw new Exception(t('zapi', 'Error in search request for table "{table}".', ['table' => $table]));
        }

        $start = $options['startSearch'] ? '' : '%';
        $exclude = $options['excludeSearch'] ? ' NOT' : '';
        $glue = $options['searchByAny'] ? ' OR ' : ' AND ';

        $search = [];
        foreach ($options['search'] as $field => $patterns) {
            if (!isset($tableSchema['fields'][$field]) || $patterns === null) {
                continue;
            }

            $patterns = array_filter((array)$patterns, function($pattern) {
                return ($pattern !== '');
            });

            if (!$patterns) {
                continue;
            }

            if ($tableSchema['fields'][$field]['type'] !== DB::FIELD_TYPE_CHAR
                && $tableSchema['fields'][$field]['type'] !== DB::FIELD_TYPE_NCLOB
                && $tableSchema['fields'][$field]['type'] !== DB::FIELD_TYPE_TEXT) {
                continue;
            }

            $fieldSearch = [];
            foreach ($patterns as $pattern) {
                // escaping parameter that is about to be used in LIKE statement
                $pattern = mb_strtoupper(strtr($pattern, ['!' => '!!', '%' => '!%', '_' => '!_']));

                $pattern = !$options['searchWildcardsEnabled']
                    ? $start.$pattern.'%'
                    : str_replace('*', '%', $pattern);

                if (Yii::$app->db->driverName == 'oci' && $tableSchema['fields'][$field]['type'] === DB::FIELD_TYPE_NCLOB
                    && strlen($pattern) > ORACLE_MAX_STRING_SIZE) {
                    $chunks = self::zbxDbstr(DB::chunkMultibyteStr($pattern, ORACLE_MAX_STRING_SIZE));
                    $pattern = 'TO_NCLOB('.implode(') || TO_NCLOB(', $chunks).')';
                }
                else {
                    $pattern = self::zbxDbstr($pattern);
                }

                $fieldSearch[] = 'UPPER('.$tableShort.'.'.$field.')'.$exclude.' LIKE '.$pattern." ESCAPE '!'";
            }

            $search[$field] = '('.implode($glue, $fieldSearch).')';
        }

        if ($search) {
            $query->andWhere(implode($glue, $search));
            return true;
        }

        return false;
    }


    /**
     * Escape string for safe usage in SQL queries.
     * Works for mysql, oracle, postgresql.
     *
     * @param array|string $var
     *
     * @return array|bool|string
     */
    public static function zbxDbstr($var)
    {
        $driver = Yii::$app->db->driverName;
        switch ($driver) {
            case 'mysql':
                $mysqli = static::getMysqli();
                if (is_array($var)) {
                    foreach ($var as $vnum => $value) {
                        $var[$vnum] = "'" . mysqli_real_escape_string($mysqli, $value) . "'";
                    }
                    return $var;
                }
                return "'" . mysqli_real_escape_string($mysqli, $var) . "'";

            case 'oci':
                if (is_array($var)) {
                    foreach ($var as $vnum => $value) {
                        $var[$vnum] = "'" . preg_replace('/\'/', '\'\'', $value) . "'";
                    }
                    return $var;
                }
                return "'" . preg_replace('/\'/', '\'\'', $var) . "'";

            case 'pgsql':
                if (is_array($var)) {
                    foreach ($var as $vnum => $value) {
                        $var[$vnum] = "'" . pg_escape_string($value) . "'";
                    }
                    return $var;
                }
                return "'" . pg_escape_string($var) . "'";

            default:
                return false;
        }
    }

    protected static function getMysqli()
    {
        static $mysqli;
        if ($mysqli) {
            return $mysqli;
        }
        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', 3306);
        $dbname = env('DB_DATABASE', 'perseus');
        $mysqli = new \mysqli(
            $host,
            Yii::$app->db->username,
            Yii::$app->db->password,
            $dbname,
            $port
        );
        return $mysqli;
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
    public static function createRelationMap(array $objects, $baseField, $foreignField, $table = null) {
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
}