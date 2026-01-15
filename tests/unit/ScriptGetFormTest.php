<?php

namespace app\customs\zapi\tests\unit;

use app\customs\zapi\forms\scripts\ScriptGetForm;
use app\modules\inform\models\MediaFactory;

/**
 * Class CommonTest
 */
class ScriptGetFormTest extends \_base\BaseUnitTest
{

    /**
     * ./vendor/bin/codecept run unit #zapi/ScriptGetFormTest::testValidator
     */
    public function testValidator()
    {
        $options = [
            'output' => [
                "scriptid",
                "name",
                "command",
                "host_access",
                "usrgrpid",
                "groupid",
                "type",
                "execute_on",
                "scope",
                "menu_path1",
            ],
            'search' => [
                'name' => null
            ],
            'filter' => [
                'scope1' => "2",
            ],
            'editable' => true,
            'limit' => 1001,
            'preservekeys' => true,
        ];
        $form = new ScriptGetForm();
        $form->load($options, '');
        $res = $form->validate();
        $this->assertTrue($res);
//        var_dump($form->getErrors());exit;
    }

}
