<?php

namespace app\customs\zapi\tests\unit;

use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\ZFilterValidator;
use yii\base\InvalidConfigException;
use yii\helpers\VarDumper;

/**
 * Class FilterValidatorTest
 * @package app\customs\zapi\tests\unit
 */
class FilterValidatorTest extends \_base\BaseUnitTest
{

    /**
     * @throws InvalidConfigException
     */
    public function testFilter()
    {
        $rules = [
            'filter' => [ZFilterValidator::class, 'flags' => API_ALLOW_NULL, 'fields' => ['name']],
            'filter2' => [ZFilterValidator::class, 'flags' => API_ALLOW_NULL | API_ALLOW_UNEXPECTED, 'fields' => ['name']],
        ];
        $data = [
            'filter' => [
                'name' => 12,
                'age' => 15,//没有设置['flags' => API_ALLOW_UNEXPECTED]时，报错
            ],
            'filter2' => [
                'name' => 12,
                'age' => 15
            ],
        ];

        $error = '';
        $bool = ValidateHelper::validateObject($data, $rules, [], $error);
        $this->stdout->writeln(VarDumper::export($data));
        $this->stdout->writeln("error:$error");
        $this->assertFalse($bool);
    }
}
