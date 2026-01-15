<?php

namespace app\customs\zapi;

use app\common\base\BaseModule;

//模块类型，方便开发
defined('RESOURCE_ZAPI') or define('RESOURCE_ZAPI', 6075);

/**
 * Zapi module
 */
class Module extends BaseModule
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\customs\zapi\controllers';
}
