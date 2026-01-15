<?php

namespace app\customs\zapi\tests\unit;

use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\FloatValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\TimestampValidator;
use yii\base\InvalidConfigException;
use yii\helpers\VarDumper;

/**
 * Class NumberValidatorTest
 * @package app\customs\zapi\tests\unit
 */
class NumberValidatorTest extends \_base\BaseUnitTest
{
    /**
     * @throws InvalidConfigException
     */
    public function testNumber()
    {
        $time = time();
        $rules = [
            'age' => [Int32Validator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => [10, [20, 100]]],
            'distance' => [FloatValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY,  'in' => [10, [20, 100]]],
            'timestamp' => [TimestampValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY,  'in' => [[$time - 3600, $time + 3600]], 'format' => 'Y-m-d H:i'],
        ];
        $data = [
            'age' => 25,
            'distance' => 55.5,
            'timestamp' => $time - 3600 * 2
        ];

        $error = '';
        $bool = ValidateHelper::validateObject($data, $rules, ['flags' => API_ALLOW_UNEXPECTED], $error);
        $this->stdout->writeln(VarDumper::export($data));
        $this->stdout->writeln("error:$error");
        $this->assertFalse($bool);
    }
}
