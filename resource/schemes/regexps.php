<?php

return [
    'key' => 'regexpid',
    'fields' => [
        'regexpid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 128,
            'default' => '',
        ],
        'test_string' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
    ],
];
