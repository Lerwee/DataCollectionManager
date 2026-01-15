<?php

return [
    'key' => 'globalmacroid',
    'fields' => [
        'globalmacroid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'macro' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'description' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
