<?php

namespace app\customs\zapi\traits;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;

trait ApiFunc
{
    /**
     * Authorized user data.
     *
     * @var array
     */
    public static $userData;

    /**
     * The name of the table.
     *
     * @var string
     */
    protected $tableName;

    /**
     * The alias of the table.
     *
     * @var string
     */
    protected $tableAlias = 't';

    /**
     * The name of the field used as a private key.
     *
     * @var string
     */
    protected $pk;

    /**
     * An array of field that can be used for sorting.
     *
     * @var array
     */
    protected $sortColumns = [];

    /**
     * An array of allowed get() options that are supported by all APIs.
     *
     * @var array
     */
    protected $globalGetOptions = [];

    /**
     * An array containing all of the allowed get() options for the current API.
     *
     * @var array
     */
    protected $getOptions = [];

    /**
     * An array containing all of the error strings.
     *
     * @var array
     */
    protected $errorMessages = [];

    public function __construct()
    {
        // set the PK of the table
        $this->pk = $this->pk($this->tableName());

        $this->globalGetOptions = [
            // filter
            'filter' => null,
            'search' => null,
            'searchByAny' => null,
            'startSearch' => false,
            'excludeSearch' => false,
            'searchWildcardsEnabled' => null,
            // output
            'output' => API_OUTPUT_EXTEND,
            'countOutput' => false,
            'groupCount' => false,
            'preservekeys' => false,
            'limit' => null
        ];
        $this->getOptions = $this->globalGetOptions;
    }

    /**
     * Returns the name of the database table that contains the objects.
     *
     * @return string
     */
    public function tableName()
    {
        return $this->tableName;
    }

    /**
     * Returns the alias of the database table that contains the objects.
     *
     * @return string
     */
    protected function tableAlias()
    {
        return $this->tableAlias;
    }

    /**
     * Returns the table name with the table alias. If the $tableName and $tableAlias
     * parameters are not given, the name and the alias of the current table will be used.
     *
     * @param string $tableName
     * @param string $tableAlias
     *
     * @return string
     */
    protected function tableId($tableName = null, $tableAlias = null)
    {
        $tableName = $tableName ? $tableName : $this->tableName();
        $tableAlias = $tableAlias ? $tableAlias : $this->tableAlias();

        return $tableName . ' ' . $tableAlias;
    }

    protected static function exception($code = 111, $error = '')
    {
        throw new \Exception($error, $code);
    }

    /**
     * For each object in $objects the method copies fields listed in $fields that are not present in the target
     * object from the source object.
     *
     * @param array $objects
     * @param array $source
     * @param string $field_name
     * @param array $fields
     *
     * @return array
     */
    protected function extendObjectsByKey(array $objects, array $source, $field_name, array $fields)
    {
        $fields = array_flip($fields);

        foreach ($objects as &$object) {
            if (array_key_exists($field_name, $object) && array_key_exists($object[$field_name], $source)) {
                $object += array_intersect_key($source[$object[$field_name]], $fields);
            }
        }
        unset($object);

        return $objects;
    }

    /**
     * For each object in $objects the method copies fields listed in $fields that are not present in the target
     * object from the source object.
     *
     * Matching objects in both arrays must have the same keys.
     *
     * @param array $objects
     * @param array $sourceObjects
     *
     * @return array
     */
    protected function extendFromObjects(array $objects, array $sourceObjects, array $fields)
    {
        $fields = array_flip($fields);

        foreach ($objects as $key => &$object) {
            if (isset($sourceObjects[$key])) {
                $object += array_intersect_key($sourceObjects[$key], $fields);
            }
        }
        unset($object);

        return $objects;
    }

    /**
     * Adds the given field to the SELECT part of the $sqlParts array if it's not already present.
     * If $sqlParts['select'] not present it is created and field appended.
     *
     * @param string $fieldId
     * @param array $sqlParts
     *
     * @return array
     */
    protected function addQuerySelect($fieldId, array $sqlParts)
    {
        if (!isset($sqlParts['select'])) {
            return ['select' => [$fieldId]];
        }

        list($tableAlias, $field) = explode('.', $fieldId);

        if (!in_array($fieldId, $sqlParts['select']) && !in_array($this->fieldId('*', $tableAlias), $sqlParts['select'])) {
            // if we want to select all of the columns, other columns from this table can be removed
            if ($field == '*') {
                foreach ($sqlParts['select'] as $key => $selectFieldId) {
                    list($selectTableAlias, ) = explode('.', $selectFieldId);

                    if ($selectTableAlias == $tableAlias) {
                        unset($sqlParts['select'][$key]);
                    }
                }
            }

            $sqlParts['select'][] = $fieldId;
        }

        return $sqlParts;
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
    protected function fieldId($fieldName, $tableAlias = null)
    {
        $tableAlias = $tableAlias ? $tableAlias : $this->tableAlias();

        return $tableAlias . '.' . $fieldName;
    }

    public function dbFilter($table, $options, &$sqlParts)
    {
        list($table, $tableShort) = explode(' ', $table);

        $tableSchema = DB::getSchema($table);

        $filter = [];
        foreach ($options['filter'] as $field => $value) {
            // skip missing fields and text fields (not supported by Oracle)
            // skip empty values
            if (!isset($tableSchema['fields'][$field]) || $tableSchema['fields'][$field]['type'] == DB::FIELD_TYPE_TEXT
                || $tableSchema['fields'][$field]['type'] == DB::FIELD_TYPE_NCLOB
                || prs_empty($value)) {
                continue;
            }

            $values = [];

            switch ($tableSchema['fields'][$field]['type']) {
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

            $fieldName = $this->fieldId($field, $tableShort);
            switch ($tableSchema['fields'][$field]['type']) {
                case DB::FIELD_TYPE_ID:
                case DB::FIELD_TYPE_INT:
                case DB::FIELD_TYPE_UINT:
                    $filter[$field] = SqlHelper::whereIn($fieldName, $values);
                    break;

                default:
                    $filter[$field] = SqlHelper::stringWhereIn($fieldName, $values);
            }
        }

        if ($filter) {
            if (isset($sqlParts['where']['filter'])) {
                $filter[] = $sqlParts['where']['filter'];
            }

            if (is_null($options['searchByAny']) || $options['searchByAny'] === false || count($filter) == 1) {
                $sqlParts['where']['filter'] = implode(' AND ', $filter);
            } else {
                $sqlParts['where']['filter'] = '(' . implode(' OR ', $filter) . ')';
            }

            return true;
        }

        return false;
    }

    public function dbSearch($table, $options, &$sql_parts)
    {
        list($table, $tableShort) = explode(' ', $table);

        $tableSchema = DB::getSchema($table);
        if (!$tableSchema) {
            info(t('zapi', 'Error in search request for table "{table}".', ['table' => $table]));
        }

        $start = $options['startSearch'] ? '' : '%';
        $exclude = $options['excludeSearch'] ? ' NOT' : '';
        $glue = $options['searchByAny'] ? ' OR ' : ' AND ';

        $search = [];
        foreach ($options['search'] as $field => $patterns) {
            if (!isset($tableSchema['fields'][$field]) || $patterns === null) {
                continue;
            }

            $patterns = array_filter((array)$patterns, function ($pattern) {
                return ($pattern !== '');
            });

            if (!$patterns) {
                continue;
            }

            if ($tableSchema['fields'][$field]['type'] !== DB::FIELD_TYPE_CHAR
                && $tableSchema['fields'][$field]['type'] !== DB::FIELD_TYPE_NCLOB
                && $tableSchema['fields'][$field]['type'] !== DB::FIELD_TYPE_TEXT
                && $tableSchema['fields'][$field]['type'] !== DB::FIELD_TYPE_CUID) {
                continue;
            }

            $fieldSearch = [];
            foreach ($patterns as $pattern) {
                // escaping parameter that is about to be used in LIKE statement
                $pattern = mb_strtoupper(strtr($pattern, ['!' => '!!', '%' => '!%', '_' => '!_']));

                $pattern = !$options['searchWildcardsEnabled']
                    ? $start . $pattern . '%'
                    : str_replace('*', '%', $pattern);

                if (SqlHelper::isOracle() && $tableSchema['fields'][$field]['type'] === DB::FIELD_TYPE_NCLOB
                    && strlen($pattern) > ORACLE_MAX_STRING_SIZE) {
                    $chunks = SqlHelper::dbEscapeString(DB::chunkMultibyteStr($pattern, ORACLE_MAX_STRING_SIZE));
                    $pattern = 'TO_NCLOB(' . implode(') || TO_NCLOB(', $chunks) . ')';
                } else {
                    $pattern = SqlHelper::dbEscapeString($pattern);
                }

                $fieldSearch[] = DB::uppercaseField($field, $table, $tableShort) . $exclude . ' LIKE ' . $pattern . " ESCAPE '!'";
            }

            $search[$field] = '(' . implode($glue, $fieldSearch) . ')';
        }

        if ($search) {
            if (isset($sql_parts['where']['search'])) {
                $search[] = $sql_parts['where']['search'];
            }

            $sql_parts['where']['search'] = '(' . implode($glue, $search) . ')';
            return true;
        }

        return false;
    }

    /**
     * Modifies the SQL parts to implement all of the output related options.
     *
     * @param string $tableName
     * @param string $tableAlias
     * @param array $options
     * @param array $sqlParts
     *
     * @return array        The resulting SQL parts array
     */
    protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts)
    {
        $pk = $this->pk($tableName);
        $pk_composite = (strpos($pk, ',') !== false);

        if (array_key_exists('countOutput', $options) && $options['countOutput']
            && !$this->requiresPostSqlFiltering($options)) {
            $has_joins = (count($sqlParts['from']) > 1
                || (array_key_exists('left_join', $sqlParts) && $sqlParts['left_join']));

            if ($pk_composite && $has_joins) {
                throw new \Exception('Joins with composite primary keys are not supported in this API version.');
            }

            $sqlParts['select'] = $has_joins
                ? ['COUNT(DISTINCT ' . $this->fieldId($pk, $tableAlias) . ') AS rowscount']
                : ['COUNT(*) AS rowscount'];

            // Select columns used by group count.
            if (array_key_exists('groupCount', $options) && $options['groupCount']) {
                foreach ($sqlParts['group'] as $fields) {
                    $sqlParts['select'][] = $fields;
                }
            }
        } // custom output
        elseif (is_array($options['output'])) {
            $sqlParts['select'] = $pk_composite ? [] : [$this->fieldId($pk, $tableAlias)];

            foreach ($options['output'] as $field) {
                if ($this->hasField($field, $tableName)) {
                    $sqlParts['select'][] = $this->fieldId($field, $tableAlias);
                }
            }

            $sqlParts['select'] = array_unique($sqlParts['select']);
        } // extended output
        elseif ($options['output'] == API_OUTPUT_EXTEND) {
            // TODO: API_OUTPUT_EXTEND must return ONLY the fields from the base table
            $sqlParts = $this->addQuerySelect($this->fieldId('*', $tableAlias), $sqlParts);
        }

        return $sqlParts;
    }

    /**
     * Returns an array that describes the schema of the database table. If no $tableName
     * is given, the schema of the current table will be returned.
     *
     * @param $tableName ;
     *
     * @return array
     */
    protected function getTableSchema($tableName = null)
    {
        $tableName = $tableName ? $tableName : $this->tableName();

        return DB::getSchema($tableName);
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
    protected function hasField($fieldName, $tableName = null)
    {
        $schema = $this->getTableSchema($tableName);

        return isset($schema['fields'][$fieldName]);
    }

    /**
     * Checks if post SQL filtering necessary.
     *
     * @param array $options API call parameters
     *
     * @return bool                true if filtering necessary false otherwise
     */
    protected function requiresPostSqlFiltering(array $options)
    {
        // must be implemented in each API separately

        return false;
    }

    /**
     * Returns the name of the field that's used as a private key. If the $tableName is not given,
     * the PK field of the given table will be returned.
     *
     * @param string $tableName ;
     *
     * @return string
     */
    public function pk($tableName = null)
    {
        if ($tableName) {
            $schema = $this->getTableSchema($tableName);

            return $schema['key'];
        }

        return $this->pk;
    }

    /**
     * Adds a specific property from the 'sortfield' parameter to the $sqlParts array.
     *
     * @param string $sortfield
     * @param string $sortorder
     * @param string $alias
     * @param array $sqlParts
     *
     * @return array
     */
    protected function applyQuerySortField($sortfield, $sortorder, $alias, array $sqlParts)
    {
        // add sort field to select if distinct is used
        if ((count($sqlParts['from']) > 1 || (isset($sqlParts['left_join']) && count($sqlParts['left_join'])))
            && !str_in_array($alias . '.' . $sortfield, $sqlParts['select'])
            && !str_in_array($alias . '.*', $sqlParts['select'])) {
            $sqlParts['select'][$sortfield] = $alias . '.' . $sortfield;
        }

        $sqlParts['order'][$alias . '.' . $sortfield] = $alias . '.' . $sortfield . $sortorder;

        return $sqlParts;
    }

    /**
     * Creates a SELECT SQL query from the given SQL parts array.
     *
     * @param array $sqlParts An SQL parts array
     *
     * @return string            The resulting SQL query
     */
    protected static function createSelectQueryFromParts(array $sqlParts)
    {
        $sql_left_join = '';
        if (array_key_exists('left_join', $sqlParts)) {
            $l_table = DB::getSchema($sqlParts['left_table']['table']);

            foreach ($sqlParts['left_join'] as $left_join) {
                $sql_left_join .= ' LEFT JOIN ' . $left_join['table'] . ' ' . $left_join['alias'] .
                    ' ON ' . $sqlParts['left_table']['alias'] . '.' . $l_table['key'] .
                    '=' . $left_join['alias'] . '.' . $left_join['using'];
            }

            // Moving a left table to the end.
            $table_id = $sqlParts['left_table']['table'] . ' ' . $sqlParts['left_table']['alias'];
            unset($sqlParts['from'][array_search($table_id, $sqlParts['from'])]);
            $sqlParts['from'][] = $table_id;
        }

        $sqlSelect = implode(',', array_unique($sqlParts['select']));
        $sqlFrom = implode(',', array_unique($sqlParts['from']));
        $sqlWhere = empty($sqlParts['where']) ? '' : ' WHERE ' . implode(' AND ', array_unique($sqlParts['where']));
        $sqlGroup = empty($sqlParts['group']) ? '' : ' GROUP BY ' . implode(',', array_unique($sqlParts['group']));
        $sqlOrder = empty($sqlParts['order']) ? '' : ' ORDER BY ' . implode(',', array_unique($sqlParts['order']));

        return 'SELECT' . self::dbDistinct($sqlParts) . ' ' . $sqlSelect .
            ' FROM ' . $sqlFrom .
            $sql_left_join .
            $sqlWhere .
            $sqlGroup .
            $sqlOrder;
    }

    /**
     * Returns DISTINCT modifier for sql statements with multiple joins and without aggregations.
     *
     * @param array $sql_parts An SQL parts array.
     *
     * @return string
     */
    protected static function dbDistinct(array $sql_parts)
    {
        if (preg_grep('/^COUNT\(/', $sql_parts['select'])) {
            return '';
        }

        $count = count($sql_parts['from']);

        if ($count == 1 && array_key_exists('left_join', $sql_parts)) {
            foreach ($sql_parts['left_join'] as $left_join) {
                $r_table = DB::getSchema($left_join['table']);

                // Increase count when table linked by non-unique column.
                if ($left_join['using'] !== $r_table['key']) {
                    $count++;
                    break;
                }
            }
        }

        return ($count > 1 ? ' DISTINCT' : '');
    }

    /**
     * Adds the related objects requested by "select*" options to the resulting object set.
     *
     * @param array $options
     * @param array $result An object hash with PKs as keys.
     *
     * @return array mixed
     */
    protected function addRelatedObjects(array $options, array $result)
    {
        // must be implemented in each API separately

        return $result;
    }

    /**
     * Returns true if the given field is requested in the output parameter.
     *
     * @param $field
     * @param $output
     *
     * @return bool
     */
    protected function outputIsRequested($field, $output)
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
                return is_array($output) ? in_array($field, $output) : false;
        }
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
     * Add the LIMIT clause to the given query.
     *
     * NOTE:
     * LIMIT and OFFSET records
     *
     * Example: select 6-15 row.
     *
     * MySQL:
     * SELECT a FROM tbl LIMIT 5,10
     * SELECT a FROM tbl LIMIT 10 OFFSET 5
     *
     * PostgreSQL:
     * SELECT a FROM tbl LIMIT 10 OFFSET 5
     *
     * Oracle:
     * SELECT a FROM tbl WHERE rownum < 15 // ONLY < 15
     * SELECT * FROM (SELECT * FROM tbl) WHERE rownum BETWEEN 6 AND 15
     *
     * @param $query
     * @param int $limit    max number of record to return
     * @param int $offset   return starting from $offset record
     *
     * @return bool|string
     */
    public function dbAddLimit($query, $limit = 0, $offset = 0)
    {
        global $DB;

        if ((isset($limit) && ($limit < 0 || !prs_ctype_digit($limit))) || $offset < 0 || !prs_ctype_digit($offset)) {
            $moreDetails = isset($limit) ? ' Limit [' . $limit . '] Offset [' . $offset . ']' : ' Offset [' . $offset . ']';
            error('Incorrect parameters for limit and/or offset. Query [' . $query . ']' . $moreDetails, true);

            return false;
        }

        // Process limit and offset
        if (isset($limit)) {
            if (SqlHelper::isOracle()) {
                $till = $offset + $limit;
                $query = 'SELECT * FROM (' . $query . ') WHERE rownum BETWEEN ' . intval($offset) . ' AND ' . intval($till);
            } else {
                $query .= ' LIMIT ' . intval($limit);
                $query .= $offset != 0 ? ' OFFSET ' . intval($offset) : '';
            }
        }

        return $query;
    }

    /**
     * Modifies the SQL parts to implement all of the sorting related options.
     * Sorting is currently only supported for CApiService::get() methods.
     *
     * @param string $tableName
     * @param string $tableAlias
     * @param array  $options
     * @param array  $sqlParts
     *
     * @return array
     */
    protected function applyQuerySortOptions($tableName, $tableAlias, array $options, array $sqlParts)
    {
        if ($this->sortColumns && !prs_empty($options['sortfield'])) {
            $options['sortfield'] = is_array($options['sortfield'])
                ? array_unique($options['sortfield'])
                : [$options['sortfield']];

            foreach ($options['sortfield'] as $i => $sortfield) {
                // validate sortfield
                if (!str_in_array($sortfield, $this->sortColumns)) {
                    self::exception(PRS_API_ERROR_INTERNAL, t('zapi', 'Sorting by field "{field}" not allowed.', ['field' => $sortfield]));
                }

                // add sort field to order
                $sortorder = '';
                if (is_array($options['sortorder'])) {
                    if (!empty($options['sortorder'][$i])) {
                        $sortorder = ($options['sortorder'][$i] == PRS_SORT_DOWN) ? ' ' . PRS_SORT_DOWN : '';
                    }
                } else {
                    $sortorder = ($options['sortorder'] == PRS_SORT_DOWN) ? ' ' . PRS_SORT_DOWN : '';
                }

                $sqlParts = $this->applyQuerySortField($sortfield, $sortorder, $tableAlias, $sqlParts);
            }
        }

        return $sqlParts;
    }

    /**
     * Unset those fields of the objects, which are not requested for the $output.
     *
     * @param array        $objects
     * @param array        $fields
     * @param string|array $output   requested output
     *
     * @return array
     */
    protected function unsetExtraFields(array $objects, array $fields, $output = [])
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
}
