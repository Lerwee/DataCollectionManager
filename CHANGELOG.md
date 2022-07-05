ZABBIX-UI Change Log
==========================

5.0 -> 6.0
------------------------

- 工具栏样式：`.sidebar` -> `.sidebar-nav-toggle`
- 主机列表：`hosts.php` -> `zabbix.php?action=host.list`
- 自动发现：`discoveryconf.php` -> `zabbix.php?action=discovery.list`
- 签名算法：自`5.2`之后，cookie字段 `zbx_sessionid` -> `zbx_session`
```php
$sessionId = 'xxxxx';
$key = (new \yii\db\Query())->select(['session_key'])->from('config')->scalar();
empty($key) && $key = bin2hex(openssl_random_pseudo_bytes(16));
# >= 6.0
hash_hmac('sha256', json_encode(['sessionid' => $sessionId]), $key, false);
# >= 5.2
$sign = openssl_encrypt(json_encode(['sessionid' => $sessionId]]), 'aes-256-ecb', $key);

$_COOKIE['zbx_session'] = base64_encode(json_encode([
    'sessionid' => $sessionId,
    'sign' => $sign
]));

```
