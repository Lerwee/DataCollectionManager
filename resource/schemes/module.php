<?php

return [
    'key' => 'moduleid',
    'fields' => [
        'moduleid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'id' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'relative_path' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'config' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
    ],
];
