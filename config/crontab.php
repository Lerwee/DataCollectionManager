<?php
/**
 * 定时任务配置
 * 统一使用crontab函数，名称全局是唯一的，避免出现重复，请以模块名为前缀(模块名-任务名)
 */
return [
    // crontab('zapi-test', '* * * * *', 'zapi/test', '定时任务简介')
    crontab('zapi-server-listen', '* * * * *', 'zapi/listen', '监听采集服务器状态变更', 1)
];
