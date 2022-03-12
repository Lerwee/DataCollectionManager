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
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    if (strncmp($_SERVER['SCRIPT_NAME'], '/z/chart', 8) != 0 && \app\common\helpers\ZabbixHelper::getVersion(true) < 5.4) {
        $css = <<<css
    <style type="text/css">
        .sidebar {
            display:none !important;
        }
    </style>
css;
        echo $css;
    }
}

require_once "$file.php";
