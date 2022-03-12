<?php

namespace app\customs\zabbix;

use app\common\base\BaseModule;

/**
 * Zabbix module
 */
class Module extends BaseModule
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\customs\zabbix\controllers';
    
    /**
     * {@inheritdoc}
     */
    public $layout = 'main';

    /**
     * {@inheritdoc}
     */
    public function afterInstall()
    {
        parent::afterInstall();
        $syncer = new \app\customs\zabbix\syncers\AssetSyncer();
        $syncer->make();
    }
}
