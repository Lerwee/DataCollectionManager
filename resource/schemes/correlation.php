<?php

return [
    'key' => 'correlationid',
    'fields' => [
        'correlationid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'description' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
        'evaltype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'formula' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
