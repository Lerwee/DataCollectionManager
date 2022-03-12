<?php
/**
 * 模块配置参数
 * 安装时，以模块名`{zabbix}`为前缀，会自动合并到`web/params.php`；
 * 统一使用`config()`函数读取，少用[[Yii::$app->params]]
 * 注：若当前配置文件是空数组，则不会加载
 * @example
 * 配置示例
 * ```php
 * return [
 *     'test' => 'hello world',
 *     // ...
 * ];
 *
 * ```
 * 读取示例
 * ```php
 * config('zabbix.test');
 *
 * ```
 */
return [
    // 'test' => 'hello world',
];
