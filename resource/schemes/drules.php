<?php

return [
    'key' => 'druleid',
    'fields' => [
        'druleid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'proxy_hostid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hosts',
            'ref_field' => 'hostid',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'iprange' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'delay' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '1h',
        ],
        'status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
