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
if (!Yii::$app->request->isAjax) {
    if ($file != 'imgstore' && strncmp($_SERVER['SCRIPT_NAME'], '/z/chart', 8) != 0) {
        $zbxVer = \app\common\helpers\ZabbixHelper::getVersion();
        $routes = ['/zbx/hosts/hosts', '/zbx/hosts/hosts.php'];
        $displayHide = isset($_SERVER['REQUEST_URI']) && in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), $routes) ? 'display:none !important;' : '';
        if ($zbxVer < 5.4) {
            $css = <<<css
<!DOCTYPE html>
<style type="text/css">
    .sidebar {
        display:none !important;
    }
    footer {
        visibility:hidden !important;
    }

    header .header-controls ul li:first-child {
        $displayHide
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

    .sidebar.is-compact + .wrapper {
        margin-left: 0px !important;
    }
    
    footer {
        visibility:hidden !important;
    }

    header .header-controls ul li:first-child {
        $displayHide
    }
</style>
css;
            if (Yii::$app->request->isPost) {
                if ($contentType = Yii::$app->request->headers->get('Content-Type')) {
                    strncasecmp($contentType, 'application/x-www-form-urlencoded', 33) == 0 && $css = '';
                }
                if ($action = Yii::$app->request->get('action')) {
                    // 针对6.x模板导入弹窗页面
                    if (strncasecmp($action, 'popup.import', 12) == 0 || Yii::$app->request->get('output') == 'ajax') {
                        $css = '';
                    }
                }
            }
        }
        echo $css;
    }
}
require_once "$file.php";
