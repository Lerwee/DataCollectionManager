<?php

namespace app\customs\zabbix\controllers;

use Yii;

class HostsController extends Controller
{
    /**
     * @var array allow actions
     */
    public $allowedActions = [
        'applications', // 应用集
        'items', // 监控项
        'triggers', // 触发器
        'graphs', // 图形
        'templates', // 模板
        'host_discovery', // 自动发现
        'disc_prototypes', // 监控项原型
        'host_prototypes', // 主机模板
        'trigger_prototypes', // 触发器类型
        'httpconf', // Web监测
        'chart',
        'chart2',
        'chart3',
        'chart4',
        'chart5',
        'chart6',
        'chart7',
    ];

    /**
     * {@inheritDoc}
     */
    public $defaultAction = 'hosts';
}
