<?php
/**
 * 报表类型配置
 * ID结构：moduleID + 3位自增数字
 */
return [
    //门户报表
    'dashboard' => [
        [
            'id' => 'MonitorGraphTableCard',
            'class' => app\modules\zapi\cards\MonitorGraphTableCard::class,//放一个不存在的类，覆盖隐藏卡片
            'sort' => 8,
        ],

    ],
];
