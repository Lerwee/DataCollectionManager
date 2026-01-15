<?php

namespace app\customs\zapi\services\assist;

use app\common\components\Result;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\regexp\CGlobalRegexp;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\RegexValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\z\CRegexValidator;
use app\modules\libzbx\models\zbx\Regexps;
use Yii;
use yii\base\Exception;
use yii\db\Query;

/**
 * Class RegexpAssist
 * @package app\customs\zapi\services\assist
 */
class RegexpAssist extends BaseAssist
{
    /**
     * @param array $regexs
     * @return Result
     */
    public function create(array $regexs): Result
    {
        try {
            $data = $this->createRegex($regexs);
            return $this->success($data);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750801, $e->getMessage());
        }
    }

    /**
     * @param array $regexs
     * @return Result
     */
    public function update(array $regexs): Result
    {
        try {
            $data = $this->updateRegex($regexs);
            return $this->success($data);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750802, $e->getMessage());
        }
    }

    /**
     * @param array $regexpids
     * @return Result
     */
    public function delete(array $regexpids): Result
    {
        try {
            $data = $this->deleteRegex($regexpids);
            return $this->success($data);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750803, $e->getMessage());
        }
    }

    /**
     * @param array $regexs
     * @return array
     * @throws ValidateException
     * @throws \yii\base\Exception
     * @throws \yii\db\Exception
     */
    public function createRegex(array $regexs): array
    {
        $this->validateCreate($regexs);

        $regexids = DB::insert('regexps', $regexs);

        foreach ($regexs as $index => &$regex) {
            $regex['regexpid'] = $regexids[$index];
        }
        unset($regex);

        $this->updateExpressions($regexs, __FUNCTION__);

        return ['regexpids' => $regexids];
    }

    /**
     * @param array $regexs
     * @return array
     * @throws ValidateException
     * @throws \yii\base\Exception
     * @throws \yii\db\Exception
     */
    public function updateRegex(array $regexs): array
    {
        $this->validateUpdate($regexs, $db_regexs);

        $upd_regexs = [];
        foreach ($regexs as $regex) {
            $db_regex = $db_regexs[$regex['regexpid']];

            $upd_regex = DB::getUpdatedValues('regexps', $regex, $db_regex);

            if ($upd_regex) {
                $upd_regexs[] = [
                    'values' => $upd_regex,
                    'where' => ['regexpid' => $regex['regexpid']]
                ];
            }
        }

        if ($upd_regexs) {
            DB::update('regexps', $upd_regexs);
        }

        $this->updateExpressions($regexs, __FUNCTION__, $db_regexs);

        return ['regexpids' => array_column($regexs, 'regexpid')];
    }


    /**
     * @param array $regexpids
     * @return array
     * @throws ValidateException
     * @throws \yii\db\Exception
     */
    public function deleteRegex(array $regexpids): array
    {
        $regexpids = array_unique($regexpids);
        $db_regexs = (new Query())->from('regexps')
            ->select(['regexpid', 'name'])
            ->where(['regexpid' => filter_integer($regexpids)])
            ->indexBy('regexpid')
            ->all();

        if (count($db_regexs) != count($regexpids)) {
            throw new ValidateException(60750203);
        }

        DB::delete('regexps', ['regexpid' => $regexpids]);

        return ['regexpids' => $regexpids];
    }


    /**
     * @param array $proxies
     * @throws ValidateException
     * @throws \yii\base\Exception
     * @throws \yii\db\Exception
     */
    private static function validateCreate(array &$regexs): void
    {
        $fieldRules = [
            'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('regexps', 'name')],
            'test_string' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('regexps', 'test_string')],
            'expressions' => [ObjectsValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['expression_type', 'expression']], 'fields' => [
                'expression_type' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => implode(',', [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED, EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE])],
                'expression' => [MultipleValidator::class, 'flags' => API_REQUIRED, 'rules' => [
                    [RegexValidator::class, 'length' => DB::getFieldLength('expressions', 'expression'), 'when' => function ($model) {
                        return in_array($model->expression_type, [EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE]);
                    }],
                    [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('expressions', 'expression'), 'when' => function ($model) {
                        return in_array($model->expression_type, [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED]);
                    }]
                ]],
                'exp_delimiter' => [MultipleValidator::class, 'rules' => [
                    [Utf8StringValidator::class, 'in' => '\\,,.,/', 'when' => function ($model) {
                        return in_array($model->expression_type, [EXPRESSION_TYPE_ANY_INCLUDED]);
                    }],
                    [Utf8StringValidator::class, 'in' => '', 'when' => function ($model) {
                        return in_array($model->expression_type, [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED, EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE]);
                    }]
                ]],
                'case_sensitive' => [Int32Validator::class, 'in' => '0,1']
            ]]
        ];

        if (!ValidateHelper::validateObjects($regexs, $fieldRules, ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']]], $error)) {
            self::exception(60750201, $error);
        }

        self::checkDuplicates($regexs);
    }

    /**
     * @param array $regexs
     * @param array|null $db_regexs
     * @throws Exception
     * @throws ValidateException
     * @throws \yii\db\Exception
     */
    protected function validateUpdate(array &$regexs, array &$db_regexs = null): void
    {
        $fieldRules = [
            'regexpid' => [IdValidator::class, 'flags' => API_REQUIRED],
            'name' => [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('regexps', 'name')],
            'test_string' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('regexps', 'test_string')],
            'expressions' => [ObjectsValidator::class, 'flags' => API_NOT_EMPTY, 'uniq' => [['expression_type', 'expression']], 'fields' => [
                'expression_type' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => implode(',', [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED, EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE])],
                'expression' => [MultipleValidator::class, 'flags' => API_REQUIRED, 'rules' => [
                    [RegexValidator::class, 'length' => DB::getFieldLength('expressions', 'expression'), 'when' => function ($model) {
                        return in_array($model->expression_type, [EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE]);
                    }],
                    [Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('expressions', 'expression'), 'when' => function ($model) {
                        return in_array($model->expression_type, [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED]);
                    }]
                ]],
                'exp_delimiter' => [MultipleValidator::class, 'rules' => [
                    [Utf8StringValidator::class, 'in' => '\\,,.,/', 'when' => function ($model) {
                        return in_array($model->expression_type, [EXPRESSION_TYPE_ANY_INCLUDED]);
                    }],
                    [Utf8StringValidator::class, 'in' => '', 'when' => function ($model) {
                        return in_array($model->expression_type, [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED, EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE]);
                    }]
                ]],
                'case_sensitive' => [Int32Validator::class, 'in' => '0,1']
            ]]
        ];

        if (!ValidateHelper::validateObjects($regexs, $fieldRules, ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['regexpid'], ['name']]], $error)) {
            self::exception(60750201, $error);
        }

        $db_regexs = (new Query())->select(['regexpid', 'name', 'test_string'])
            ->from(Regexps::tableName())
            ->where(['regexpid' => array_column($regexs, 'regexpid')])
            ->indexBy(['regexpid'])
            ->all();

        if (count($db_regexs) != count($regexs)) {
            throw new ValidateException(60750101, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        self::checkDuplicates($regexs, $db_regexs);

        self::addAffectedObjects($regexs, $db_regexs);
    }

    /**
     * @param array $regexs
     * @param array|null $db_regexs
     * @throws ValidateException
     */
    private static function checkDuplicates(array $regexs, array $db_regexs = null): void
    {
        $names = [];

        foreach ($regexs as $regex) {
            if (!array_key_exists('name', $regex)) {
                continue;
            }

            if ($db_regexs === null || $regex['name'] !== $db_regexs[$regex['regexpid']]['name']) {
                $names[] = $regex['name'];
            }
        }

        if (!$names) {
            return;
        }

        $duplicate = (new Query())->from(['r' => 'regexps'])->select(['name'])->where([
            'name' => $names
        ])->limit(1)->one();

        if ($duplicate) {
            self::exception(60750201, t('zapi', 'Regular expression "{name}" already exists.', ['name' => $duplicate['name']]));
        }
    }


    protected function updateExpressions(array &$regexs, string $method, array $db_regexs = null): void
    {
        $ins_expressions = [];
        $upd_expressions = [];
        $del_expressionids = [];

        foreach ($regexs as &$regex) {
            if (!array_key_exists('expressions', $regex)) {
                continue;
            }

            $db_expressions = ($method === 'updateRegex') ? $db_regexs[$regex['regexpid']]['expressions'] : [];

            foreach ($regex['expressions'] as &$expression) {
                $db_expression = current(
                    array_filter($db_expressions, function (array $db_expression) use ($expression): bool {
                        return ($expression['expression_type'] == $db_expression['expression_type']
                            && $expression['expression'] === $db_expression['expression']);
                    })
                );

                /**
                 * Set default value for expression delimiter.
                 * Because Perseus agent 2 cannot work with regular expression when delimiter is empty.
                 * Bugfix for Perseus agent 2 5.0.22 and less.
                 */
                $expression += ['exp_delimiter' => ','];

                if ($db_expression) {
                    $expression['expressionid'] = $db_expression['expressionid'];
                    unset($db_expressions[$db_expression['expressionid']]);

                    $upd_expression = DB::getUpdatedValues('expressions', $expression, $db_expression);

                    if ($upd_expression) {
                        $upd_expressions[] = [
                            'values' => $upd_expression,
                            'where' => ['expressionid' => $db_expression['expressionid']]
                        ];
                    }
                } else {
                    $ins_expressions[] = ['regexpid' => $regex['regexpid']] + $expression;
                }

                /**
                 * Unset exp_delimiter from array for audit log records.
                 */
                if ($expression['expression_type'] != EXPRESSION_TYPE_ANY_INCLUDED) {
                    unset($expression['exp_delimiter']);
                }
            }
            unset($expression);

            $del_expressionids = array_merge($del_expressionids, array_keys($db_expressions));
        }
        unset($regex);

        if ($del_expressionids) {
            DB::delete('expressions', ['expressionid' => $del_expressionids]);
        }

        if ($upd_expressions) {
            DB::update('expressions', $upd_expressions);
        }

        if ($ins_expressions) {
            $expressionids = DB::insert('expressions', $ins_expressions);
        }

        foreach ($regexs as &$regex) {
            if (!array_key_exists('expressions', $regex)) {
                continue;
            }

            foreach ($regex['expressions'] as &$expression) {
                if (!array_key_exists('expressionid', $expression)) {
                    $expression['expressionid'] = array_shift($expressionids);
                }
            }
            unset($expression);
        }
        unset($regex);
    }


    /**
     * Add the existing expressions to $db_regexs whether these are affected by the update.
     *
     * @static
     *
     * @param array $regexs
     * @param array $db_regexs
     */
    private static function addAffectedObjects(array $regexs, array &$db_regexs): void
    {
        $regexids = [];

        foreach ($regexs as $regex) {
            if (array_key_exists('expressions', $regex)) {
                $regexids[] = $regex['regexpid'];
                $db_regexs[$regex['regexpid']]['expressions'] = [];
            }
        }

        if ($regexids) {
            $db_expressions = (new Query())->from('expressions')
                ->select(['expressionid', 'regexpid', 'expression', 'expression_type', 'exp_delimiter', 'case_sensitive'])
                ->where(['regexpid' => $regexids])
                ->all();

            foreach ($db_expressions as $db_expression) {
                $db_regexs[$db_expression['regexpid']]['expressions'][$db_expression['expressionid']] = array_diff_key($db_expression, array_flip(['regexpid']));
            }
        }
    }

    /**
     * @param $testString
     * @param array $expressions
     * @return array
     */
    public function testRegex($testString, array $expressions): array
    {
        $result = [
            'expressions' => [],
            'errors' => [],
            'final' => true
        ];
        foreach ($expressions as $id => $expression) {
            try {
                self::validateRegex($expression);
                $result['expressions'][$id] = CGlobalRegexp::matchExpression($expression, $testString);
                $result['final'] = $result['final'] && $result['expressions'][$id];
            } catch (Exception $e) {
                $result['errors'][$id] = $e->getMessage();
                $result['final'] = false;
            }
        }
        return $result;
    }

    /**
     * @param array $expression
     * @throws Exception
     */
    private static function validateRegex(array $expression): void
    {
        $validator = new CRegexValidator([
            'messageInvalid' => t('zapi', 'Regular expression must be a string'),
            'messageRegex' => t('zapi', 'Incorrect regular expression "%1$s": "%2$s"')
        ]);

        switch ($expression['expression_type']) {
            case EXPRESSION_TYPE_TRUE:
            case EXPRESSION_TYPE_FALSE:
                if (!$validator->validate($expression['expression'])) {
                    throw new Exception($validator->getError());
                }
                break;

            default:
                if ($expression['expression'] === '') {
                    throw new Exception(t('zapi', 'Expression cannot be empty'));
                }
        }
    }
}