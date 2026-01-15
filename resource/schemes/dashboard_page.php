<?php

return [
    'key' => 'dashboard_pageid',
    'fields' => [
        'dashboard_pageid' => [
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
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'display_period' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'sortorder' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
