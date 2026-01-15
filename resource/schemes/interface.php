<?php

return [
    'key' => 'interfaceid',
    'fields' => [
        'interfaceid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'hostid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hosts',
            'ref_field' => 'hostid',
        ],
        'main' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'useip' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'ip' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '127.0.0.1',
        ],
        'dns' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'port' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '10050',
        ],
        'available' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'error' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'errors_from' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'disable_until' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
