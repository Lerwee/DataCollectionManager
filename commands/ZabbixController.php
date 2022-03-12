<?php

namespace app\customs\zabbix\commands;

use app\common\base\BaseConsoleController;
use app\common\helpers\StringHelper;
use app\customs\zabbix\syncers\AssetSyncer;

class ZabbixController extends BaseConsoleController
{
    /**
     * 建立当面模块所需要的静态资源
     *
     * 在WEB目录的zbx文件夹内创建所有zbx模块需要的资源文件
     * 在window下，会直接复制相关文件夹，而在linux下会使用软链接
     */
    public function actionMakeAsset($output = true)
    {
        $syncer = new AssetSyncer([
            'output' => StringHelper::toBool($output)
        ]);
        $syncer->make();
    }
}
