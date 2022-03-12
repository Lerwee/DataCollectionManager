<?php

namespace app\customs\zabbix\services\actions;

use Yii;
use yii\base\Action;

class JsRpc extends Action
{
    /**
     * run action
     */
    public function run()
    {
        if (Yii::$app->request->isPost) {
            // exit('{"jsonrpc":"2.0","result":{"result":"true","message":""},"id":1}');
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return [
                'jsonrpc' => '2.0',
                'result' => [
                    'result' => true,
                    'message' => ''
                ],
                'id' => 1
            ];
        }
        chdir(config('zabbix_source', 'z'));
        require_once './jsrpc.php';
        exit();
    }
}
