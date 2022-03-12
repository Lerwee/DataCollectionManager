<?php

namespace app\customs\zabbix\components;

use app\common\helpers\ZabbixHelper;
use Yii;
use yii\base\Component;

class Hacker extends Component
{
    /**
     * login
     *
     * @param array $data
     * @return [type]
     */
    public static function login(array $data)
    {
        $z = config('zabbix_source', 'z');
        if ('z' == $z) {
            require_once Yii::getAlias('@webroot/z/include/classes/user/CWebUser.php');
            require_once Yii::getAlias('@webroot/z/include/classes/api/CApiService.php');
            require_once Yii::getAlias('@webroot/z/include/classes/api/services/CUser.php');
        } else {
            require_once rtrim($z, './\\') . '/include/classes/user/CWebUser.php';
            require_once rtrim($z, './\\') . '/include/classes/api/CApiService.php';
            require_once rtrim($z, './\\') . '/include/classes/api/services/CUser.php';
        }
        $user['userid']     = $data['userid'];
        $user['debug_mode'] = false;
        $lang = empty($data['lang']) ? Yii::$app->language : $data['lang'];
        $user['lang']       = str_replace('-', '_', $lang);
        $user['type']       = empty($data['type']) ? 3 : $data['type'];
        $user['gui_access'] = 0; //GROUP_GUI_ACCESS_SYSTEM
        $user['sessionid']  = static::getZBXSession();

        /**
         * see  z/include/classes/user/CWebUser.php #92 checkAuthentication
         */
        \CWebUser::$data = $user;
        /**
         * see z/include/classes/api/services/CUser.php #1179 checkAuthentication
         */
        \CApiService::$userData = $user;

        /**
         * 避免zabbix报错
         *
         * 当前路由不操作yii seesion避免出现未定义的问题
         */
         session_abort();
        /**
         * 避免zabbix session_start后与YII的冲突 导致的未定义的问题
         */
        ini_set('session.name', 'z');
    }

    public static function getZBXSession()
    {
        /**
         * 兼容zabbix 5.2
         */
        if (5.2 <= ZabbixHelper::getVersion()) {
            if (isset($_COOKIE['zbx_session'])) {
                /**
                 * @see z/include/classes/core/CEncryptedCookieSession.php #76 checkSign
                 */
                $zbxSession = json_decode(base64_decode($_COOKIE['zbx_session']), true);
                $_COOKIE['zbx_sessionid'] = $zbxSession['sessionid'];
            } else {
                $_COOKIE['zbx_sessionid'] = static::getAdminToken();

                $key = (new \yii\db\Query())->select(['session_key'])->from('config')->scalar();

                $sign = openssl_encrypt(json_encode([
                    'sessionid' => $_COOKIE['zbx_sessionid']
                ]), 'aes-256-ecb', $key);
                $_COOKIE['zbx_session']   = base64_encode(json_encode([
                    'sessionid' => $_COOKIE['zbx_sessionid'], 'sign' => $sign
                ]));
            }
        }

        if (isset($_COOKIE['zbx_sessionid'])) {
            return $_COOKIE['zbx_sessionid'];
        }
        $_COOKIE['zbx_sessionid'] = static::getAdminToken();

        return $_COOKIE['zbx_sessionid'];
    }

    /**
     * Admin token
     * @return string
     */
    public static function getAdminToken()
    {
        static $token;
        if ($token === null) {
            $client = new \app\modules\libzbx\api\ZClient();
            $token = $client->getToken();
        }
        return $token;
    }
}
