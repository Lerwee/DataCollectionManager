<?php
namespace app\customs\zapi\common\user;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\modules\libzbx\models\zbx\Profiles;
use Yii;
use yii\db\Exception;
use yii\db\Query;

class Profile
{
    private static $userId;
    private static $userDetails = [];
    private static $profiles = null;
    private static $update = [];
    private static $insert = [];
    private static $stringProfileMaxLength;
    private static $is_initialized = false;

    public static function init()
    {
        self::$userId = Yii::$app->user ? Yii::$app->user->id : null;
        self::$profiles = [];

        $userId = self::$userId;
        self::$stringProfileMaxLength = DB::getFieldLength('profiles', 'value_str');
        \Yii::$app->db->createCommand("SELECT NULL FROM users u WHERE u.userid = {$userId} FOR UPDATE")->execute();

        if (!self::$is_initialized) {
            register_shutdown_function(function () {
                $transaction = Profiles::getDb()->beginTransaction();
                try {
                    self::flush();
                    $transaction->commit();
                } catch (Exception $e) {
                    $transaction->rollBack();
                }
            });
        }

        self::$is_initialized = true;
    }

    /**
     * Check if data needs to be inserted or updated.
     *
     * @return bool
     */
    public static function isModified()
    {
        return (self::$insert || self::$update);
    }

    public static function flush()
    {
        $result = false;

        if (self::$profiles !== null && self::$userId > 0 && self::isModified()) {
            $result = true;

            foreach (self::$insert as $idx => $profile) {
                foreach ($profile as $idx2 => $data) {
                    if (!self::insertDB($idx, $data['value'], $data['type'], $idx2)) {
                        $result = false;
                    }
                }
            }

            ksort(self::$update);
            foreach (self::$update as $idx => $profile) {
                ksort($profile);
                foreach ($profile as $idx2 => $data) {
                    if (!self::updateDB($idx, $data['value'], $data['type'], $idx2)) {
                        $result = false;
                    }
                }
            }

            self::clear();
        }

        return $result;
    }

    public static function clear()
    {
        self::$insert = [];
        self::$update = [];
    }

    /**
     * Return array of matched idx keys for current user.
     *
     * @param string $idx_pattern Search pattern, SQL like wildcards can be used.
     * @param int $idx2 Numerical index will be matched against idx2 index.
     *
     * @return array
     */
    public static function findByIdxPattern($idx_pattern, $idx2)
    {
        if (!self::$userId) {
            return null;
        }

        if (self::$profiles === null) {
            self::init();
        }

        // Convert SQL _ and % wildcard characters to regexp.
        $regexp = str_replace(['_', '%'], ['.', '.*'], preg_quote($idx_pattern, '/'));
        $regexp = '/^' . $regexp . '/';

        $results = [];
        foreach (self::$profiles as $k => $v) {
            if (preg_match($regexp, $k, $match) && array_key_exists($idx2, $v)) {
                $results[] = $k;
            }
        }

        if ($results) {
            return $results;
        }

        // Aggressive caching, cache all items matched $idx key.
        $rows = (new Query())->select(['type','value_id','value_int','value_str','idx','idx2'])
            ->from('profiles')
            ->where(['userid' => self::$userId])
            ->andWhere(['like', 'idx', ZSqlHelper::zbxDbstr($idx_pattern)])
            ->all();

        foreach ($rows as $row) {
            $value_type = self::getFieldByType($row['type']);
            $idx = $row['idx'];

            if (!array_key_exists($idx, self::$profiles)) {
                self::$profiles[$idx] = [];
            }

            self::$profiles[$idx][$row['idx2']] = $row[$value_type];

            if ($row['idx2'] == $idx2) {
                $results[] = $idx;
            }
        }

        return $results;
    }

    /**
     * Return matched idx value for current user.
     *
     * @param string $idx Search pattern.
     * @param mixed $default_value Default value if no rows was found.
     * @param int|null $idx2 Numerical index will be matched against idx2 index.
     *
     * @return mixed
     */
    public static function get($idx, $default_value = null, $idx2 = 0)
    {
        // no user data available, just return the default value
        if (!self::$userId || $idx2 === null) {
            return $default_value;
        }

        if (self::$profiles === null) {
            self::init();
        }

        if (array_key_exists($idx, self::$profiles)) {
            // When there is cached data for $idx but $idx2 was not found we should return default value.
            return array_key_exists($idx2, self::$profiles[$idx]) ? self::$profiles[$idx][$idx2] : $default_value;
        }

        self::$profiles[$idx] = [];
        // Aggressive caching, cache all items matched $idx key.
        $rows = (new Query())->select(['type','value_id','value_int','value_str','idx','idx2'])
            ->from('profiles')
            ->where(['userid' => self::$userId])
            ->andWhere(['like', 'idx', ZSqlHelper::zbxDbstr($idx)])
            ->all();

        foreach ($rows as $row) {
            $value_type = self::getFieldByType($row['type']);

            self::$profiles[$idx][$row['idx2']] = $row[$value_type];
        }

        ksort(self::$profiles[$idx], SORT_NUMERIC);

        return array_key_exists($idx2, self::$profiles[$idx]) ? self::$profiles[$idx][$idx2] : $default_value;
    }

    /**
     * Returns the values stored under the given $idx as an array.
     *
     * @param string $idx
     * @param mixed $defaultValue
     *
     * @return mixed
     */
    public static function getArray($idx, $defaultValue = null)
    {
        if (self::get($idx, null, 0) === null) {
            return $defaultValue;
        }

        return self::$profiles[$idx];
    }

    /**
     * Removes profile values from DB and profiles cache.
     *
     * @param string $idx first identifier
     * @param int|array $idx2 second identifier, which can be list of identifiers as well
     */
    public static function delete($idx, $idx2 = 0)
    {
        if (self::$profiles === null) {
            self::init();
        }

        $idx2 = (array)$idx2;
        self::deleteValues($idx, $idx2);

        if (array_key_exists($idx, self::$profiles)) {
            foreach ($idx2 as $index) {
                unset(self::$profiles[$idx][$index]);
            }
        }
    }

    /**
     * Removes all values stored under the given idx.
     *
     * @param string $idx
     */
    public static function deleteIdx($idx)
    {
        if (self::$profiles === null) {
            self::init();
        }

        DB::delete('profiles', ['idx' => $idx, 'userid' => self::$userId]);
        unset(self::$profiles[$idx]);
    }

    /**
     * Deletes the given values from the DB.
     *
     * @param string $idx
     * @param array $idx2
     */
    protected static function deleteValues($idx, array $idx2)
    {
        // remove from DB
        DB::delete('profiles', ['idx' => $idx, 'idx2' => $idx2, 'userid' => self::$userId]);
    }

    /**
     * Update favorite values in DB profiles table.
     *
     * @param string $idx max length is 96
     * @param mixed $value max length 255 for string
     * @param int $type
     * @param int $idx2
     */
    public static function update($idx, $value, $type, $idx2 = 0)
    {
        if (self::$profiles === null) {
            self::init();
        }

        if (is_array($value)) {
            return;
        }

        if (!self::checkValueType($value, $type)) {
            return;
        }

        $profile = [
            'idx' => $idx,
            'value' => $value,
            'type' => $type,
            'idx2' => $idx2
        ];

        $current = self::get($idx, null, $idx2);
        if (is_null($current)) {
            if (!isset(self::$insert[$idx])) {
                self::$insert[$idx] = [];
            }
            self::$insert[$idx][$idx2] = $profile;
        } else {
            if ($current != $value) {
                if (!isset(self::$update[$idx])) {
                    self::$update[$idx] = [];
                }
                self::$update[$idx][$idx2] = $profile;
            }
        }

        if (!isset(self::$profiles[$idx])) {
            self::$profiles[$idx] = [];
        }

        self::$profiles[$idx][$idx2] = $value;
    }

    /**
     * Stores an array in the profiles.
     *
     * Each value is stored under the given idx and a sequentially generated idx2.
     *
     * @param string $idx
     * @param array $values
     * @param int $type
     */
    public static function updateArray($idx, array $values, $type)
    {
        // save new values
        $i = 0;
        foreach ($values as $value) {
            self::update($idx, $value, $type, $i);

            $i++;
        }

        // delete remaining old values
        $idx2 = [];
        while (self::get($idx, null, $i) !== null) {
            $idx2[] = $i;

            $i++;
        }

        self::delete($idx, $idx2);
    }

    private static function insertDB($idx, $value, $type, $idx2)
    {
        $value_type = self::getFieldByType($type);

        $values = [
                'profileid' => get_dbid('profiles', 'profileid'),
                'userid' => self::$userDetails['userid'],
                'idx' => prs_dbstr($idx),
                $value_type => prs_dbstr($value),
                'type' => $type,
                'idx2' => prs_dbstr($idx2)
            ] + [
                'value_str' => prs_dbstr('')
            ];

        return Profiles::getDb()->createCommand()->batchInsert(Profiles::tableName(), array_keys($values), [$values])->execute();
    }

    private static function updateDB($idx, $value, $type, $idx2)
    {
        $valueType = self::getFieldByType($type);

        return Profiles::updateAll([$valueType => ZSqlHelper::zbxDbstr($value), 'type' => $type], [
            'userid' => self::$userId,
            'idx' => ZSqlHelper::zbxDbstr($idx),
            'idx2' => ZSqlHelper::zbxDbstr($idx2)
        ]);
    }

    private static function getFieldByType($type)
    {
        switch ($type) {
            case PROFILE_TYPE_INT:
                $field = 'value_int';
                break;
            case PROFILE_TYPE_STR:
                $field = 'value_str';
                break;
            case PROFILE_TYPE_ID:
            default:
                $field = 'value_id';
        }

        return $field;
    }

    private static function checkValueType($value, $type)
    {
        switch ($type) {
            case PROFILE_TYPE_ID:
                return prs_ctype_digit($value);
            case PROFILE_TYPE_INT:
                return prs_is_int($value);
            case PROFILE_TYPE_STR:
                return mb_strlen($value) <= self::$stringProfileMaxLength;
            default:
                return true;
        }
    }
}
