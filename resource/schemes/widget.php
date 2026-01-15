<?php

return [
    'key' => 'widgetid',
    'fields' => [
        'widgetid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'type' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'x' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'y' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'width' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'height' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '2',
        ],
        'view_mode' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'dashboard_pageid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'dashboard_page',
            'ref_field' => 'dashboard_pageid',
        ],
    ],
];
