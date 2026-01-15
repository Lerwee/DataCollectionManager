<?php

return [
    'key' => 'dashboardid',
    'fields' => [
        'dashboardid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
        ],
        'userid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
        'private' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'templateid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hosts',
            'ref_field' => 'hostid',
        ],
        'display_period' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '30',
        ],
        'auto_start' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'uuid' => [
            'null' => false,
            'type' => 'char',
            'length' => 32,
            'default' => '',
        ],
    ],
];
