<?php
error_reporting(0);
define('ZBX_PAGE_NO_MENU', true);
define('ZBX_PAGE_FULLSCREEN', true);

chdir(config('zabbix_source', 'z'));

//fix bugs : Undefined variable: config
if ('adm.valuemapping' == $file) {
    $config = ['search_limit' => 1000];
}
//see lwjk_v3\web\z\include\func.inc.php #2205 uncheckTableRows
$_SERVER['SCRIPT_NAME'] = "/z/$file.php";

// 解决5.0使用js移除左侧菜单栏会导致闪烁问题
if (!Yii::$app->request->isPost 
    && (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strncasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest', 14) !== 0)) {
    if ($file != 'imgstore' && strncmp($_SERVER['SCRIPT_NAME'], '/z/chart', 8) != 0) {
        $zbxVer = \app\common\helpers\ZabbixHelper::getVersion();
        if ($zbxVer < 5.4) {
            $css = <<<css
<!DOCTYPE html>
<style type="text/css">
    .sidebar {
        display:none !important;
    }
</style>
css;
        } else {
            $css = <<<css
<!DOCTYPE html>
<style type="text/css">
    .sidebar {
        display: none !important;
        transform: translate3d(-110%, 0, 0) !important;
        position: fixed !important;
        top: 0 !important;
        bottom: 0 !important;
    }
    .sidebar-nav-toggle {
        display:none !important;
    }
</style>
css;
        }
        echo $css;
    }
}
require_once "$file.php";
