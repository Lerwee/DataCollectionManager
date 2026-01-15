<?php

return [
    'key' => 'reportid',
    'fields' => [
        'reportid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'userid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'description' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'dashboardid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'dashboard',
            'ref_field' => 'dashboardid',
        ],
        'period' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'cycle' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'weekdays' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'start_time' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'active_since' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'active_till' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'state' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'lastsent' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'info' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
    ],
];
