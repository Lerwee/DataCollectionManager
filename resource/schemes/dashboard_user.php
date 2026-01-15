<?php

return [
    'key' => 'dashboard_userid',
    'fields' => [
        'dashboard_userid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'dashboardid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'dashboard',
            'ref_field' => 'dashboardid',
        ],
        'userid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
        'permission' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '2',
        ],
    ],
];
