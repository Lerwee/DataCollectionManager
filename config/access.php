<?php

return [
    // 无需授权的路由，e.g. '/gii/*', '/debug/*'
    'except' => [
        '/zapi/template/download',
    ],
    // 需要授权的路由(需要登录)
    'only' => [

    ],
    // 不计算license的菜单ID集合 (7.x有效)
    // 'free' => [
    //
    // ]
];
