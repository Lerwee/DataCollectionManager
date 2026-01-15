<?php

return [
    'key' => 'serviceid',
    'fields' => [
        'serviceid' => [
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
        'status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '-1',
        ],
        'algorithm' => [
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
        'weight' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'propagation_rule' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'propagation_value' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'description' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
        'uuid' => [
            'null' => false,
            'type' => 'char',
            'length' => 32,
            'default' => '',
        ],
        'created_at' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
