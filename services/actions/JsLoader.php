<?php

namespace app\customs\zabbix\services\actions;

use yii\base\Action;

class JsLoader extends Action
{
    /**
     * run action
     */
    public function run()
    {
        chdir(config('zabbix_source', 'z'));
        $_GET['showGuiMessaging'] = 1;
        require_once './jsLoader.php';
        if (!isset($_GET['files'])) {
            require_once __dir__ . '/../../assets/res/iframeResizer.contentWindow.min.js';
        }
        exit();
    }
}
