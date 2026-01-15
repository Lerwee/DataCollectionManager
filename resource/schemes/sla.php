<?php

return [
    'key' => 'slaid',
    'fields' => [
        'slaid' => [
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
        'period' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'slo' => [
            'null' => false,
            'type' => 'float',
            'default' => '99.9',
        ],
        'effective_date' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'timezone' => [
            'null' => false,
            'type' => 'char',
            'length' => 50,
            'default' => 'UTC',
        ],
        'status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'description' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
    ],
];
