<?php

namespace app\customs\zapi\tests\unit;

use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\ObjectValidator;
use app\customs\zapi\common\validators\Utf8StringsValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use yii\base\InvalidConfigException;
use yii\helpers\VarDumper;

/**
 * Class ValidatorTest
 */
class ValidatorTest extends \_base\BaseUnitTest
{

    /**
     * @throws InvalidConfigException
     */
    public function testObject1()
    {
        $rules = [
            'tags' => [IdsValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY],
            'age' => [Int32Validator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => '0,1:100'],
            'name' => [Int32Validator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => [12, [30, 60]]],
        ];
        $data = [
            'tags' => [1, 12],
            'age' => 200,//异常值
            'name' => 20,//异常值
        ];

        $error = '';
        $bool = ValidateHelper::validateObject($data, $rules, ['flags' => API_ALLOW_UNEXPECTED], $error);
        $this->stdout->writeln(VarDumper::export($data));
        $this->stdout->writeln("error:$error");
        $this->assertFalse($bool);
    }


    /**
     * @throws InvalidConfigException
     */
    public function testObject2()
    {
        $rules = [
            'name' => [Int32Validator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => [12, [30, 60]]],
        ];
        $data = [
            ['name' => 12],
            ['name' => 50],
            ['name' => 120]
        ];

        $error = '';
        $bool = ValidateHelper::validateObjects($data, $rules, ['flags' => API_ALLOW_UNEXPECTED], $error);
        $this->stdout->writeln(VarDumper::export($data));
        $this->stdout->writeln("error:$error");
        $this->assertFalse($bool);
    }

    /**
     * @throws InvalidConfigException
     */
    public function testObject3()
    {
        $rules = [
            'demo' => [ObjectsValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY,
                'fields' => [
                    'name' => [Int32Validator::class]
                ]
            ],
        ];
        $data = [
            'demo' => [
                ['name' => 12],
                ['name' => 123],
                ['name' => "abc"]
            ]
        ];

        $error = '';
        $bool = ValidateHelper::validateObject($data, $rules, ['flags' => API_ALLOW_UNEXPECTED], $error);
        $this->stdout->writeln(VarDumper::export($data));
        $this->stdout->writeln("error:$error");
        $this->assertFalse($bool);
    }

    /**
     * @throws InvalidConfigException
     */
    public function testObject4()
    {
        $rules = [
            'demo' => [ObjectsValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => ['name'],
                'fields' => [
                    'name' => [Int32Validator::class]
                ]
            ],
        ];
        $data = [
            'demo' => [
                ['name' => 12],
                ['name' => 12],
            ]
        ];

        $error = '';
        $bool = ValidateHelper::validateObject($data, $rules, ['flags' => API_ALLOW_UNEXPECTED], $error);
        $this->stdout->writeln(VarDumper::export($data));
        $this->stdout->writeln("error:$error");
        $this->assertFalse($bool);
    }

    public function testObject5()
    {
        $rules = [
            'demo' => [ObjectValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY,
                'fields' => [
                    'list' => [ObjectsValidator::class, 'uniq' => [['name', 'age']],
                        'fields' => [
                            'name' => [Utf8StringValidator::class],
                            'age' => [Int32Validator::class]
                        ]
                    ]
                ]
            ],
        ];
        $data = [
            'demo' => [
                'list' => [
                    ['name' => "ss", 'age' => 20],
                    ['name' => 'ss', 'age' => 20],
                ]
            ]
        ];

        $error = '';
        $bool = ValidateHelper::validateObject($data, $rules, ['flags' => API_ALLOW_UNEXPECTED], $error);
        $this->stdout->writeln(VarDumper::export($data));
        $this->stdout->writeln("error:$error");
        $this->assertFalse($bool);
    }

    public function testObject6()
    {
        $rules = [
            'name' => [Int32Validator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => [12, [30, 60]], 'replacement' => '_name'],
        ];
        $data = [
            ['name' => 12],
            ['name' => 50, '_name' => 'asd'],//异常
        ];

        $error = '';
        $bool = ValidateHelper::validateObjects($data, $rules, ['flags' => API_ALLOW_UNEXPECTED], $error);
        $this->stdout->writeln(VarDumper::export($data));
        $this->stdout->writeln("error:$error");
        $this->assertFalse($bool);
    }
}
