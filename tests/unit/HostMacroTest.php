<?php

namespace app\customs\zapi\tests\unit;

use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\forms\HostMacroForm;
use yii\base\InvalidConfigException;
use yii\helpers\VarDumper;

/**
 * Class HostMacroTest
 * @package app\customs\zapi\tests\unit
 */
class HostMacroTest extends \_base\BaseUnitTest
{

    /**
     * @throws InvalidConfigException
     */
    public function testCreate()
    {
        $rules = HostMacroForm::getValidationRules();

        $data = [
            [
                'hostid' => 10712,
                'macro' => '{$ABC}',
                'value' => '1334'
            ]
        ];

        $bool = ValidateHelper::validateObjects($data, $rules, [
            'flags' => API_NOT_EMPTY | API_NORMALIZE,
            'uniq' => [['hostid', 'macro']]
        ], $error);
        $this->stdout->writeln(VarDumper::export($data));
        $error && $this->stdout->warningLine("error:$error");
        $this->assertTrue($bool);
    }
}
