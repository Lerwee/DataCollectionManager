<?php

namespace app\customs\zapi\common\db;

use app\common\helpers\ArrayHelper;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use Yii;
use yii\db\Exception;
use yii\db\Query;

/**
 * Class DB
 * @package app\customs\zapi\common\db
 */
class DB
{
    const MAX_ID = '9223372036854775807';

    const FIELD_TYPE_INT = 'int';
    const FIELD_TYPE_CHAR = 'char';
    const FIELD_TYPE_ID = 'id';
    const FIELD_TYPE_FLOAT = 'float';
    const FIELD_TYPE_UINT = 'uint';
    const FIELD_TYPE_BLOB = 'blob';
    const FIELD_TYPE_TEXT = 'text';
    const FIELD_TYPE_NCLOB = 'nclob';
    const FIELD_TYPE_CUID = 'cuid';

    static $schemes;

    /**
     * 存储指数据表的主键递增记录
     *
     * NOTE: 如果表的记录不存在或值超出范围，使用该表最大/最小范围内创建`ids`表记录
     *
     * @static
     *
     * @param string $table 数据表
     * @param int $count 递增数量
     *
     * @return string
     * @throws Exception
     */
    public static function reserveIds(string $table, int $count)
    {
        $scheme = self::getSchema($table);
        $primaryKey = $scheme['key'];
        $db = Yii::$app->db;

        $sql = 'SELECT nextid FROM ids WHERE table_name=:table AND field_name=:field FOR UPDATE';
        $nextId = $db->createCommand($sql)
            ->bindValue(':table', $table)
            ->bindValue(':field', $primaryKey)
            ->queryScalar();

        if ($nextId) {
            $maxNextId = bcadd($nextId, $count, 0);
            if (bccomp($maxNextId, self::MAX_ID) == 1) {
                $nextId = self::refreshIds($table, $count);
            } else {
                $r = $db->createCommand()
                    ->update('ids', ['nextid' => $maxNextId], [
                        'table_name' => $table,
                        'field_name' => $primaryKey,
                    ])
                    ->execute();
                if (!$r) {
                    throw new Exception('sql execute error: ' . $sql);
                }
                $nextId = bcadd($nextId, 1, 0);
            }
        }
        /**
         * TODO: 查询未释放(理论上不会到这里, ,Yii框架本身throws)
         *
         * 检测查询是否完全可执行？如果查询有效且架构正确，但查询仍无法执行，
         * 那么很有可能以前的事务没有释放行级锁，或者它仍在运行。
         * 在这种情况下，必须停止执行，否则它将调用 self::refreshIds()方法。
         */
        elseif (false) {
            // Your database is not working properly.
            // Please wait a few minutes and try to repeat this action.
            // If the problem still persists, please contact system administrator.
            // The problem might be caused by long running transaction or row level lock accomplished by your database management system.
            $err = Yii::t('zapi', 'Your database is not working properly.');
            throw new Exception($err);
        } else {
            $nextId = self::refreshIds($table, $count);
        }
        return $nextId;
    }

    /**
     * 刷新给定表的 id 记录。
     *
     * 记录将被删除，然后重新创建，其值为表中的最大 id 或允许的最小值。
     *
     *
     * @static
     *
     * @param string $table 数据表
     * @param int $count 递增数量
     *
     * @return string
     * @throws Exception
     */
    private static function refreshIds(string $table, int $count): string
    {
        $tableSchema = self::getSchema($table);
        $primaryKey = $tableSchema['key'];
        $db = Yii::$app->db;
        // 清除ids记录
        $db->createCommand()
            ->delete('ids', [
                'table_name' => $table,
                'field_name' => $primaryKey,
            ])
            ->execute();
        // 查找当前表记录最大ID
        $nextId = $db->createCommand("SELECT MAX({$primaryKey}) AS id FROM {$table}")
            ->queryScalar() ?: 0;

        $maxNextId = bcadd($nextId, $count, 0);
        // 检查最大ID是否超标
        if (bccomp($maxNextId, self::MAX_ID) == 1) {
            $err = Yii::t('zapi', 'ID greater than maximum allowed for table "{table}"', ['table' => $table]);
            throw new Exception($err);
        }

        $db->createCommand()
            ->insert('ids', [
                'table_name' => $table,
                'field_name' => $primaryKey,
                'nextid' => $maxNextId,
            ])
            ->execute();

        return bcadd($nextId, 1, 0);
    }

    /**
     * @param string $table
     * @return array
     * @throws Exception
     * @todo 按zbx版本区分
     */
    public static function getSchema(string $table): ?array
    {
        if (isset(self::$schemes, self::$schemes[$table])) {
            return self::$schemes[$table];
        }

        // TODO:优先取指定版本
        $file = Yii::getAlias("@customs/zapi/resource/schemes/{$table}.php", false);
        if (!is_file($file)) {
            throw new Exception(Yii::t('zapi', 'Table scheme "{table}" does not exist.', ['table' => $table]));
        }
        self::$schemes[$table] = safe_require_file_data($file);
        return self::$schemes[$table];
    }


    /**
     * 数据插入数据库。
     *
     * @param string $table
     * @param array $values key => value 结构
     * @param bool $generatePrimaryKey
     *
     * @return array    返回写入的主键ID集合
     * @throws Exception
     */
    public static function insert(string $table, array $values, bool $generatePrimaryKey = true)
    {
        $scheme = self::getSchema($table);
        $fields = array_reduce($values, 'array_merge', []);
        $fields = array_intersect_key($fields, $scheme['fields']);

        foreach ($fields as $field => &$value) {
            $value = array_key_exists('default', $scheme['fields'][$field])
                ? $scheme['fields'][$field]['default']
                : null;
        }
        unset($value);

        foreach ($values as &$row) {
            $row = array_merge($fields, $row);
        }
        unset($row);

        return self::insertBatch($table, $values, $generatePrimaryKey);
    }

    /**
     * 数据批量插入数据库。
     *
     * @param string $table
     * @param array $values key => value 结构
     * @param bool $generatePrimaryKey
     *
     * @return array|true    返回写入的主键ID集合
     * @throws Exception
     */
    public static function insertBatch(string $table, array $values, bool $generatePrimaryKey = true)
    {
        if (empty($values)) {
            return true;
        }

        $resultIds = [];

        $schema = self::getSchema($table);

        if ($generatePrimaryKey) {
            $id = self::reserveIds($table, count($values));
        }

        $mandatoryFields = self::getMandatoryFields($schema);

        $rows = [];
        foreach ($values as $key => $row) {
            if ($generatePrimaryKey) {
                $resultIds[$key] = $id;
                $row[$schema['key']] = $id;
                $id = bcadd($id, 1, 0);
            }

            $row += $mandatoryFields;

            self::checkValueTypes($schema, $row);
            $rows[] = $row;
        }
        unset($row);
        unset($values);

        Yii::$app->db->createCommand()
            ->batchInsert($table, array_keys(current($rows)), $rows)
            ->execute();

        return $resultIds;
    }

    /**
     * Deletes data from DB
     *
     * @param string $table
     * @param array $condition
     * @param boolean $useOr
     * @return boolean
     * @throws Exception
     */
    public static function delete(string $table, array $condition, bool $useOr = false): bool
    {
        if (empty($condition)) {
            throw new Exception(t('zapi', 'Cannot perform delete statement on table "{table}" without where condition.', ['table' => $table]));
        }
        $tableSchema = self::getSchema($table);
        $where = [];
        foreach ($condition as $field => $values) {
            if (!isset($tableSchema['fields'][$field]) || is_null($values)) {
                throw new Exception(t('zapi', 'Incorrect field "{field}" name or value in where statement for table "{table}".', ['field' => $field, 'table' => $table]));
            }
            $values = to_array($values);
            sort($values);
            $where[] = SqlHelper::stringWhereIn($field, $values);
        }

        $sql = 'DELETE FROM ' . $table . ' WHERE ' . implode(($useOr ? ' OR ' : ' AND '), $where);
        return Yii::$app->db->createCommand($sql)
            ->execute();
    }

    /**
     * Update data in DB.
     *
     * @param string $table
     *
     * $data
     * [...]['values'] pair of fieldname => fieldvalue for SET clause
     * [...]['where'] pair of fieldname => fieldvalue for WHERE clause
     * @param array $data
     *
     * @return bool|int of ids
     * @throws Exception
     */
    public static function update(string $table, array $data)
    {
        if (empty($data)) {
            return true;
        }

        $tableSchema = self::getSchema($table);

        $data = prs_toArray($data);
        foreach ($data as $row) {
            // check
            self::checkValueTypes($tableSchema, $row['values']);
            if (empty($row['values'])) {
                throw new Exception(t('zapi', 'Cannot perform update statement on table "{table}" without values.', ['table' => $table]));
            }

            if (!isset($row['where']) || empty($row['where']) || !is_array($row['where'])) {
                throw new Exception(t('zapi', 'Cannot perform update statement on table "{table}" without where condition.', ['table' => $table]));
            }

            // where condition processing
            $sqlWhere = [];
            foreach ($row['where'] as $field => $values) {
                if (!isset($tableSchema['fields'][$field]) || is_null($values)) {
                    throw new Exception(t('zapi', 'Incorrect field "{field}" name or value in where statement for table "{table}".', ['field' => $field, 'table' => $table]));
                }
                $values = prs_toArray($values);
                sort($values); // sorting ids to prevent deadlocks when two transactions depend on each other
                $sqlWhere[] = ZSqlHelper::dbConditionString($field, $values);
            }
            $where = implode(' AND ', $sqlWhere);
            Yii::$app->db->createCommand()->update($table, $row['values'], $where)->execute();
        }
        return true;
    }

    /**
     * Updates the values by the given PK.
     *
     * @param string $tableName
     * @param int|string $pk
     * @param array $values
     *
     * @return bool
     * @throws Exception
     */
    public static function updateByPk(string $tableName, $pk, array $values)
    {
        return self::update($tableName, [
            'where' => [self::getPk($tableName) => $pk],
            'values' => $values
        ]);
    }

    /**
     * Saves the given records to the database. If the record has the primary key set, it is updated, otherwise - a new
     * record is inserted. For new records the newly generated PK is added to the result.
     *
     * @param string $tableName
     * @param array $data
     *
     * @return array    the same records, that have been passed with the primary keys set for new records
     * @throws Exception
     */
    public static function save(string $tableName, array $data): array
    {
        $pk = self::getPk($tableName);
        $newRecords = [];
        foreach ($data as $key => $record) {
            // if the pk is set - update the record
            if (isset($record[$pk])) {
                self::updateByPk($tableName, $record[$pk], $record);
            } else { // if no pk is set, create the record later
                $newRecords[$key] = $record;
            }
        }

        // insert the new records
        if ($newRecords) {
            $newIds = self::insert($tableName, $newRecords);
            foreach ($newIds as $key => $id) {
                $data[$key][$pk] = $id;
            }
        }

        return $data;
    }

    /**
     * Returns the list of mandatory fields with default values for INSERT statements.
     *
     * @static
     *
     * @param array $tableSchema
     *
     * @return array
     */
    private static function getMandatoryFields(array $tableSchema): array
    {
        $mandatoryFields = [];
        if (Yii::$app->db->driverName == 'mysql') {
            foreach ($tableSchema['fields'] as $name => $field) {
                if ($field['type'] == self::FIELD_TYPE_TEXT || $field['type'] == self::FIELD_TYPE_NCLOB) {
                    $mandatoryFields += [$name => $field['default']];
                }
            }
        }
        return $mandatoryFields;
    }

    /**
     * 检查指定数据表是否存在指定字段
     *
     * @static
     *
     * @param string $tableName
     * @param string $fieldName
     *
     * @return bool
     * @throws Exception
     */
    public static function hasField(string $tableName, string $fieldName): bool
    {
        $schema = self::getSchema($tableName);

        return isset($schema['fields'][$fieldName]);
    }

    /**
     * Returns the names of the fields that are used as the primary key of the table.
     *
     * @param string $table_name
     *
     * @return string
     * @throws Exception
     */
    public static function getPk(string $table_name): string
    {
        $schema = self::getSchema($table_name);
        return $schema['key'];
    }

    /**
     * 返回指定字段长度
     *
     * @static
     *
     * @param string $tableName
     * @param string $fieldName
     *
     * @return int
     * @throws Exception
     */
    public static function getFieldLength(string $tableName, string $fieldName): int
    {
        $schema = self::getSchema($tableName);

        if ($schema['fields'][$fieldName]['type'] == self::FIELD_TYPE_TEXT) {
            return (Yii::$app->db->driverName == 'oci') ? 2048 : 65535;
        }

        if ($schema['fields'][$fieldName]['type'] == self::FIELD_TYPE_NCLOB) {
            return 65535;
        }

        return $schema['fields'][$fieldName]['length'];
    }

    public static function getDefaults($table)
    {
        $table = self::getSchema($table);

        $defaults = [];
        foreach ($table['fields'] as $name => $field) {
            if (isset($field['default'])) {
                $defaults[$name] = $field['default'];
            }
        }
        return $defaults;
    }

    /**
     * 返回给定字段的默认值。
     *
     * @param string $table 字段所在表
     * @param string $field 字段名称
     *
     * @return string|null
     * @throws Exception
     */
    public static function getDefault(string $table, string $field): ?string
    {
        $table = self::getSchema($table);
        $field = $table['fields'][$field];

        return $field['default'] ?? null;
    }

    /**
     * Get the updated values of a record by correctly comparing the new and old ones, taking field types into account.
     *
     * @param string $table_name
     * @param array $new_values
     * @param array $old_values
     *
     * @return array
     * @throws Exception
     */
    public static function getUpdatedValues(string $table_name, array $new_values, array $old_values): array
    {
        $updated_values = [];

        // Discard field names not existing in the target table.
        $fields = array_intersect_key(DB::getSchema($table_name)['fields'], $new_values);

        foreach ($fields as $name => $spec) {
            if (!array_key_exists($name, $old_values)) {
                $updated_values[$name] = $new_values[$name];
                continue;
            }

            switch ($spec['type']) {
                case DB::FIELD_TYPE_ID:
                    if (bccomp($new_values[$name], $old_values[$name]) != 0) {
                        $updated_values[$name] = $new_values[$name];
                    }
                    break;

                case DB::FIELD_TYPE_INT:
                case DB::FIELD_TYPE_UINT:
                case DB::FIELD_TYPE_FLOAT:
                    if ($new_values[$name] != $old_values[$name]) {
                        $updated_values[$name] = $new_values[$name];
                    }
                    break;

                default:
                    if ($new_values[$name] !== $old_values[$name]) {
                        $updated_values[$name] = $new_values[$name];
                    }
                    break;
            }
        }

        return $updated_values;
    }

    /**
     * @param $tableSchema
     * @param $values
     * @throws Exception
     */
    private static function checkValueTypes($tableSchema, &$values)
    {
        foreach ($values as $field => $value) {
            if (!isset($tableSchema['fields'][$field])) {
                unset($values[$field]);
                continue;
            }

            if (isset($tableSchema['fields'][$field]['ref_table'])) {
                if ($tableSchema['fields'][$field]['null']) {
                    $values[$field] = ($value == '0') ? NULL : $value;
                }
            }

            if (is_null($values[$field])) {
                if ($tableSchema['fields'][$field]['null']) {
                    $values[$field] = NULL;
                } elseif (isset($tableSchema['fields'][$field]['default'])) {
                    $values[$field] = $tableSchema['fields'][$field]['default'];
                } else {
                    throw new Exception(t('zapi', 'Field "{field}" cannot be set to NULL.', ['field' => $field]));
                }
            } else {
                switch ($tableSchema['fields'][$field]['type']) {
                    case self::FIELD_TYPE_CHAR:
                        $length = mb_strlen($values[$field]);
                        if ($length > $tableSchema['fields'][$field]['length']) {
                            $msg = 'Value "{value}" is too long for field "{field}" - {len} characters. Allowed length is {max_len} characters.';
                            throw new Exception(t('zapi', $msg, [
                                'value' => $values[$field],
                                'field' => $field,
                                'len' => $length,
                                'max_len' => $tableSchema['fields'][$field]['length'],
                            ]));
                        }
                        break;
                    case self::FIELD_TYPE_ID:
                    case self::FIELD_TYPE_UINT:
                        if (!ctype_digit(strval($values[$field]))) {
                            throw new Exception(t('zapi', 'Incorrect value "{value}" for unsigned int field "{field}".', [
                                'value' => $values[$field],
                                'field' => $field,
                            ]));
                        }
                        break;
                    case self::FIELD_TYPE_INT:
                        if (!is_int_val($values[$field])) {
                            throw new Exception(t('zapi', 'Incorrect value "{value}" for int field "{field}".', [
                                'value' => $values[$field],
                                'field' => $field,
                            ]));
                        }
                        break;
                    case self::FIELD_TYPE_FLOAT:
                        if (!is_numeric($values[$field])) {
                            throw new Exception(t('zapi', 'Incorrect value "{value}" for float field "{field}".', [
                                'value' => $values[$field],
                                'field' => $field,
                            ]));
                        }
                        break;
                    case self::FIELD_TYPE_TEXT:
                        if (Yii::$app->db->driverName == 'oracle') {
                            $length = mb_strlen($values[$field]);

                            if ($length > 2048) {
                                $msg = 'Value "{value}" is too long for field "{field}" - {len} characters. Allowed length is {max_len} characters.';
                                throw new Exception(t('zapi', $msg, [
                                    'value' => $values[$field],
                                    'field' => $field,
                                    'len' => $length,
                                    'max_len' => 2048,
                                ]));
                            }
                        }
                        break;
                    case self::FIELD_TYPE_NCLOB:
                        // Using strlen because 4000 bytes is largest possible string literal in oracle query.
                        if (Yii::$app->db->driverName == 'oracle' && strlen($values[$field]) > ORACLE_MAX_STRING_SIZE) {
                            $chunks = self::chunkMultiByteStr($values[$field], ORACLE_MAX_STRING_SIZE);
                            $values[$field] = 'TO_NCLOB(' . implode(') || TO_NCLOB(', $chunks) . ')';
                        }
                        break;
                }
            }
        }
    }

    /**
     * @param string $str
     * @param int $chunkSize
     *
     * @return array
     */
    public static function chunkMultiByteStr(string $str, int $chunkSize): array
    {
        $chunks = [];
        $offset = 0;
        $size = strlen($str);

        while ($offset < $size) {
            $chunk = mb_strcut($str, $offset, $chunkSize);
            $chunks[] = $chunk;
            $offset = strlen($chunk) + $offset;
        }

        return $chunks;
    }

    /**
     * Convert field to uppercase or substitute it with its pre-upcased variant.
     *
     * @param string      $field_name
     * @param string      $table_name
     * @param string|null $table_alias
     *
     * @return string
     */
    public static function uppercaseField(string $field_name, string $table_name, string $table_alias = null): string
    {
        if ($table_alias === null) {
            $table_alias = $table_name;
        }

        if ($field_name === 'name' && self::hasField($table_name, 'name_upper')) {
            return $table_alias . '.name_upper';
        }

        return 'UPPER(' . $table_alias . '.' . $field_name . ')';
    }

    /**
     * Replaces the records given in $groupedOldRecords with the ones given in $groupedNewRecords.
     *
     * This method can be used to replace related objects in one-to-many relations. Both old and new records
     * must be grouped by the ID of the record they belong to. The records will be matched by position, instead of
     * the primary key as in DB::replace(). That is, the first new record will update the first old one, second new
     * record - the second old one, etc. Since the records are matched by position, the new records should not contain
     * primary keys.
     *
     * Example 1:
     * $old = array(2 => array( array('gitemid' => 1, 'color' => 'FF0000') ));
     * $new = array(2 => array( array('color' => '00FF00') ));
     * var_dump(DB::replaceByPosition('items', $old, $new));
     * // array(array('gitemid' => 1, 'color' => '00FF00'))
     *
     * The new record updated the old one.
     *
     * Example 2:
     * $old = array(2 => array( array('gitemid' => 1, 'color' => 'FF0000') ));
     * $new = array(
     *     2 => array(
     *         array('color' => '00FF00'),
     *         array('color' => '0000FF')
     *     )
     * );
     * var_dump(DB::replaceByPosition('items', $old, $new));
     * // array(array('gitemid' => 1, 'color' => '00FF00'), array('gitemid' => 2, 'color' => '0000FF'))
     *
     * The first record was updated, the second one - created.
     *
     * Example 3:
     * $old = array(
     *     2 => array(
     *         array('gitemid' => 1, 'color' => 'FF0000'),
     *         array('gitemid' => 2, 'color' => '0000FF')
     *     )
     * );
     * $new = array(2 => array( array('color' => '00FF00') ));
     * var_dump(DB::replaceByPosition('items', $old, $new));
     * // array(array('gitemid' => 1, 'color' => '00FF00'))
     *
     * The first record was updated, the second one - deleted.
     *
     * @param string 	$tableName			table to update
     * @param array 	$groupedOldRecords	grouped old records
     * @param array 	$groupedNewRecords	grouped new records
     *
     * @return array	array of new records not grouped (!).
     */
    public static function replaceByPosition($tableName, array $groupedOldRecords, array $groupedNewRecords) {
        $pk = self::getPk($tableName);

        $allOldRecords = [];
        $allNewRecords = [];
        foreach ($groupedNewRecords as $key => $newRecords) {
            // if records exist for the parent object - replace them, otherwise create new records
            if (isset($groupedOldRecords[$key])) {
                $oldRecords = $groupedOldRecords[$key];

                // updated the records by position
                $newRecords = self::mergeRecords($oldRecords, $newRecords, $pk);

                foreach ($oldRecords as $record) {
                    $allOldRecords[] = $record;
                }
            }

            foreach ($newRecords as $record) {
                $allNewRecords[] = $record;
            }
        }

        // replace the old records with the new ones
        return self::replace($tableName, $allOldRecords, $allNewRecords);
    }

    /**
     * Replaces the records given in $oldRecords with the ones in $newRecords.
     *
     * If a record with the same primary key as a new one already exists in the old records, the record is updated
     * only if they are different. For new records the newly generated PK is added to the result. Old records that are
     * not present in the new records are deleted.
     *
     * All of the records must have the primary key defined.
     *
     * @param string $tableName
     * @param array  $oldRecords
     * @param array  $newRecords
     *
     * @return array    the new records, that have been passed with the primary keys set for newly inserted records
     */
    public static function replace($tableName, array $oldRecords, array $newRecords) {
        $pk = self::getPk($tableName);
        $oldRecords = prs_toHash($oldRecords, $pk);

        $modifiedRecords = [];
        foreach ($newRecords as $key => $record) {
            // if it's a new or modified record - save it later
            if (!isset($record[$pk]) || self::recordModified($tableName, $oldRecords[$record[$pk]], $record)) {
                $modifiedRecords[$key] = $record;
            }

            // remove the existing records from the collection, the remaining ones will be deleted
            if(isset($record[$pk])) {
                unset($oldRecords[$record[$pk]]);
            }
        }

        // save modified records
        if ($modifiedRecords) {
            $modifiedRecords = self::save($tableName, $modifiedRecords);

            // add the new IDs to the new records
            foreach ($modifiedRecords as $key => $record) {
                $newRecords[$key][$pk] = $record[$pk];
            }
        }

        // delete remaining records
        if ($oldRecords) {
            DB::delete($tableName, [
                $pk => array_keys($oldRecords)
            ]);
        }

        return $newRecords;
    }

    /**
     * Compares the fields, that are present in both records, and returns true if any of the values differ.
     *
     * @param string $tableName
     * @param array  $oldRecord
     * @param array  $newRecord
     *
     * @return bool
     */
    public static function recordModified($tableName, array $oldRecord, array $newRecord) {
        foreach ($oldRecord as $field => $value) {
            if (self::hasField($tableName, $field)
                && isset($newRecord[$field])
                && (string) $value !== (string) $newRecord[$field]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Replace each record in $oldRecords with a corresponding record in $newRecords, but keep the old record IDs.
     * The records are match by position, that is, the first new record, replaces the first old record and etc.
     * If there are less $newRecords than $oldRecords, the remaining old records will be discarded.
     *
     * @param array 	$oldRecords		array of old records
     * @param array 	$newRecords		array of new records
     * @param string 	$pk				name of the private key column
     *
     * @return array	array of new records with the primary keys from the old ones
     */
    protected static function mergeRecords(array $oldRecords, array $newRecords, $pk) {
        $result = [];
        foreach ($newRecords as $i => $record) {
            if (isset($oldRecords[$i])) {
                $record[$pk] = $oldRecords[$i][$pk];
            }

            $result[] = $record;
        }

        return $result;
    }


    /**
     * Create Query
     *
     * @param  string      $tableName
     * @param  array       $options
     * @param  string|null $tableAlias
     * @return Query
     * @throws Exception
     */
    public static function makeQuery(string $tableName, array $options, ?string $tableAlias = null): Query
    {
        $defaults = [
			'output' => [],
			'countOutput' => false,
			'filter' => [],
			'search' => [],
			'startSearch' => false,
			'searchByAny' => false,
			'sortfield' => [],
			'sortorder' => [],
			'limit' => null,
			'preservekeys' => false
		];

        if ($array_diff = array_diff_key($options, $defaults)) {
			unset($array_diff[self::getPk($tableName).'s']);
			if ($array_diff) {
                throw new Exception(t('zapi', '{func}: unsupported option "{option}".', [
                    'func' => __FUNCTION__,
                    'option' => key($array_diff)
                ]));
			}
		}
		$options = ArrayHelper::merge($defaults, $options);
        $tableSchema = self::getSchema($tableName);

        $query = new Query();
        $query->from($tableName);

        // output
        $query->select($options['countOutput'] ? ['rowscount' => 'COUNT(1)'] : $options['output']);

        $pk = self::getPk($tableName);
		$pk_option = $pk.'s';

		// pks
		if (array_key_exists($pk_option, $options)) {
			if (!is_array($options[$pk_option])) {
				$options[$pk_option] = [$options[$pk_option]];
			}

			switch ($tableSchema['fields'][$pk]['type']) {
				case self::FIELD_TYPE_ID:
				case self::FIELD_TYPE_INT:
				case self::FIELD_TYPE_UINT:
					$query->where(SqlHelper::whereIn($pk, $options[$pk_option]));
					break;

				default:
                    $query->where(SqlHelper::stringWhereIn($pk, $options[$pk_option]));
			}
		}

		// filters
		if (is_array($options['filter'])) {
            foreach ($options['filter'] as $field => $value) {
                if (!array_key_exists($field, $tableSchema['fields'])) {
                    throw new Exception(t('zapi', '{func}: field "{table}.{field}" does not exist.', [
                        'func' => __FUNCTION__,
                        'table' => $tableName,
                        'field' => $field,
                    ]));
                }

                $fieldSchema = $tableSchema['fields'][$field];
                if ($fieldSchema['type'] == self::FIELD_TYPE_TEXT || $fieldSchema['type'] == self::FIELD_TYPE_NCLOB) {
                    throw new Exception(t('zapi', '{func}: field "{table}.{field}" has an unsupported type.', [
                        'func' => __FUNCTION__,
                        'table' => $tableName,
                        'field' => $field,
                    ]));
                }

                if ($value === null) {
                    continue;
                }
                if (is_array($value)) {
                    switch ($fieldSchema['type']) {
                        case self::FIELD_TYPE_ID:
                        case self::FIELD_TYPE_INT:
                        case self::FIELD_TYPE_UINT:
                            $query->andWhere(SqlHelper::whereIn($field, $value));
                            break;
                        default:
                            $query->andWhere(SqlHelper::stringWhereIn($field, $value)); 
                    }
                } else {
                    $query->andWhere([$field => $value]);
                }
            }
		}

        // search
        if ($options['search']) {
            $unsupported_types = [self::FIELD_TYPE_INT, self::FIELD_TYPE_ID, self::FIELD_TYPE_FLOAT, self::FIELD_TYPE_UINT, self::FIELD_TYPE_BLOB];

		    $start = $options['startSearch'] ? '' : '%';

		    $search = [];
            foreach ($options['search'] as $field => $patterns) {
                if (!array_key_exists($field, $tableSchema['fields'])) {
                    throw new Exception(t('zapi', '{func}: field "{table}.{field}" does not exist.', [
                        'func' => __FUNCTION__,
                        'table' => $tableName,
                        'field' => $field,
                    ]));
                }

                $fieldSchema = $tableSchema['fields'][$field];
                if (in_array($fieldSchema['type'], $unsupported_types)) {
                    throw new Exception(t('zapi', '{func}: field "{table}.{field}" has an unsupported type.', [
                        'func' => __FUNCTION__,
                        'table' => $tableName,
                        'field' => $field,
                    ]));
                }

                if ($patterns === null) {
                    continue;
                }

                foreach ((array) $patterns as $pattern) {
                    // escaping parameter that is about to be used in LIKE statement
                    $pattern = mb_strtoupper(strtr($pattern, ['!' => '!!', '%' => '!%', '_' => '!_']));
                    $pattern = $start.$pattern.'%';
                    if (SqlHelper::isOracle() && $fieldSchema['type'] === DB::FIELD_TYPE_NCLOB && strlen($pattern) > ORACLE_MAX_STRING_SIZE) {
                        $chunks = SqlHelper::dbEscapeString(DB::chunkMultibyteStr($pattern, ORACLE_MAX_STRING_SIZE));
                        $pattern = 'TO_NCLOB('.implode(') || TO_NCLOB(', $chunks).')';
                    } else {
                        $pattern = SqlHelper::dbEscapeString($pattern);

                    }
                    $search[] = self::uppercaseField($field, $tableName, $tableAlias).' LIKE '.$pattern." ESCAPE '!'";
                }
            }

            if ($search) {
                $glue = $options['searchByAny'] ? ' OR ' : ' AND ';
                $query->andWhere(implode($glue, $search));
            }
        }

        // order
        if ($options['sortfield']) {
            $orderBy = [];
            foreach ($options['sortfield'] as $index => $field) {
                if (!array_key_exists($field, $tableSchema['fields'])) {
                    throw new Exception(t('zapi', '{func}: field "{table}.{field}" does not exist.', [
                        'func' => __FUNCTION__,
                        'table' => $tableName,
                        'field' => $field,
                    ]));
                }
                if (array_key_exists($index, $options['sortorder']) && $options['sortorder'][$index] == PRS_SORT_DOWN) {
                    $orderBy[$field] = SORT_DESC;
                } else {
                    $orderBy[$field] = SORT_ASC;
                }
            }
            $query->orderBy($orderBy);
        }
        return $query;
    }
}
