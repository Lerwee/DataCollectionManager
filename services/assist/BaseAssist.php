<?php

namespace app\customs\zapi\services\assist;

use app\common\base\BaseService;
use app\common\components\Result;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\validators\z\CNewValidator;
use app\customs\zapi\common\validators\z\CPartialValidatorInterface;
use app\customs\zapi\common\validators\z\CValidator;
use app\customs\zapi\components\RelationMap;
use yii\db\Query;

/**
 * Class BaseAssist
 * @package app\customs\zapi\services\assist
 */
class BaseAssist extends BaseService
{
    /**
     * Validated input parameters.
     *
     * @var array
     */
    protected $input = [];

    protected $raw_input;

    /**
     * For each object in $objects the method copies fields listed in $fields that are not present in the target
     * object from the source object.
     *
     * @param array $objects
     * @param array $source
     * @param string $fieldName
     * @param array $fields
     *
     * @return array
     */
    protected function extendObjectsByKey(array $objects, array $source, string $fieldName, array $fields): array
    {
        $fields = array_flip($fields);

        foreach ($objects as &$object) {
            if (array_key_exists($fieldName, $object) && array_key_exists($object[$fieldName], $source)) {
                $object += array_intersect_key($source[$object[$fieldName]], $fields);
            }
        }
        unset($object);

        return $objects;
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
     * Unset those fields of the objects, which are not requested for the $output.
     *
     * @param array $objects
     * @param array $fields
     * @param string|array $output requested output
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

    /**
     * Creates a relation map for the given objects.
     *
     * If the $table parameter is set, the relations will be loaded from a database table, otherwise the map will be
     * built from two base object properties.
     *
     * @param array $objects a hash of base objects
     * @param string $baseField the base object ID field
     * @param string $foreignField the related objects ID field
     * @param string $table table to load the relation from
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
        } // create a map from the base objects
        else {
            foreach ($objects as $object) {
                $relationMap->addRelation($object[$baseField], $object[$foreignField]);
            }
        }

        return $relationMap;
    }

    /**
     * Runs the given partial validator and throws an exception if it fails.
     *
     * @param array $array
     * @param CPartialValidatorInterface $validator
     * @param array $fullArray
     * @throws ValidateException
     */
    protected function checkPartialValidator(array $array, CPartialValidatorInterface $validator, $fullArray = [])
    {
        if (!$validator->validatePartial($array, $fullArray)) {
            self::exception(60750003, $validator->getError());
        }
    }

    /**
     * @param array $object
     * @param array $params
     * @param $error
     * @param $objectName
     * @throws ValidateException
     */
    protected function checkNoParameters(array $object, array $params, $error, $objectName)
    {
        foreach ($params as $param) {
            if (array_key_exists($param, $object)) {
                $error = _params($error, [$param, $objectName]);
                self::exception(60750003, $error);
            }
        }
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
     * Fetches the fields given in $fields from the database and extends the objects with the loaded data.
     *
     * @param string $tableName
     * @param array $objects
     * @param array $fields
     *
     * @return array
     */
    protected function extendObjects($tableName, array $objects, array $fields)
    {
        if ($objects) {
            $pk = $this->pk($tableName);
            $dbObjects = (new Query())->select($fields)
                ->addSelect($pk)
                ->from($tableName)
                ->where([$pk => prs_objectValues($objects, $pk)])
                ->indexBy($pk)
                ->all();

            foreach ($objects as &$object) {
                $id = $object[$pk];
                if (isset($dbObjects[$id])) {
                    check_db_fields($dbObjects[$id], $object, true);
                }
            }
            unset($object);
        }

        return $objects;
    }

    /**
     * Returns the name of the field that's used as a private key. If the $tableName is not given,
     * the PK field of the given table will be returned.
     *
     * @param string $tableName ;
     *
     * @return string
     */
    public function pk($tableName)
    {
        $schema = $this->getTableSchema($tableName);
        return $schema['key'];
    }

    /**
     * Returns an array that describes the schema of the database table. If no $tableName
     * is given, the schema of the current table will be returned.
     *
     * @param $tableName ;
     *
     * @return array
     */
    protected function getTableSchema($tableName)
    {
        return DB::getSchema($tableName);
    }

    /**
     * Runs the given validator and throws an exception if it fails.
     *
     * @param $value
     * @param CValidator $validator
     */
    protected function checkValidator($value, CValidator $validator)
    {
        if (!$validator->validate($value)) {
            self::exception(60750003, $validator->getError());
        }
    }

    /**
     * @param int $errCode
     * @param string|null $errMsg
     * @throws ValidateException
     */
    protected static function exception(int $errCode, ?string $errMsg = null)
    {
        throw new ValidateException($errCode, $errMsg ?: t('zapi', 'Incorrect arguments passed to function.'));
    }

    /**
     * @param string $attr
     * @param string|null $errMsg
     * @throws ValidateException
     */
    protected static function invalidAttrException(string $attr, ?string $errMsg = null)
    {
        throw new ValidateException(60750001, t('zapi', 'Invalid parameter "{attribute}": {error}.', [
            'attribute' => $attr,
            'error' => $errMsg
        ]));
    }

    /**
     * @param \Throwable   $e
     * @param integer|null $errCode
     * @param string|null  $errMsg
     * @return Result
     */
    protected function errorException($e, ?int $errCode= null, ?string $errMsg = null)
    { 
        if ($e instanceof ValidateException) {
            return $this->error($e->getErrorCode(), $errMsg ?: $e->getMessage(), YII_DEBUG ? explode(PHP_EOL, (string) $e) : []);
        }

        \Yii::error(parse_exception($e)); 
        $data = YII_DEBUG ? explode(PHP_EOL, (string) $e) : explode(PHP_EOL, str_replace(APP_PATH . DIRECTORY_SEPARATOR, '', (string) $e));
        return $this->error($errCode ?: 60750001, $errMsg ?: $e->getMessage(), $data);
    }

    /**
     * Check if input parameter exists.
     *
     * @param string $var
     *
     * @return bool
     */
    protected function hasInput($var) {
        return array_key_exists($var, $this->input);
    }

    /**
     * Get single input parameter.
     *
     * @param string $var
     * @param mixed $default
     *
     * @return mixed
     */
    protected function getInput($var, $default = null) {
        if ($default === null) {
            return $this->input[$var];
        }
        else {
            return array_key_exists($var, $this->input) ? $this->input[$var] : $default;
        }
    }

    /**
     * Get several input parameters.
     *
     * @param array $var
     * @param array $names
     */
    protected function getInputs(&$var, $names) {
        foreach ($names as $name) {
            if ($this->hasInput($name)) {
                $var[$name] = $this->getInput($name);
            }
        }
    }

    /**
     * Return all input parameters.
     *
     * @return array
     */
    protected function getInputAll() {
        return $this->input;
    }

    /**
     * Validate input parameters.
     *
     * @param array $validation_rules
     *
     * @return bool
     */
    protected function validateInput(array $validation_rules): bool {
        if ($this->raw_input === null) {

            return false;
        }

        $validator = new CNewValidator($this->raw_input, $validation_rules);

        foreach ($validator->getAllErrors() as $error) {
            self::exception(60750001, $error);
        }

        if ($validator->isErrorFatal()) {
            return false;
        } else {
            $this->input = $validator->getValidInput();
            return !$validator->isError();
        }
    }
}
