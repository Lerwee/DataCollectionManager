<?php

return [
    'key' => 'profileid',
    'fields' => [
        'profileid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'userid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
        'idx' => [
            'null' => false,
            'type' => 'char',
            'length' => 96,
            'default' => '',
        ],
        'idx2' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'default' => '0',
        ],
        'value_id' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'default' => '0',
        ],
        'value_int' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'value_str' => [
            'null' => false,
            'type' => 'nclob',
            'default' => '',
        ],
        'source' => [
            'null' => false,
            'type' => 'char',
            'length' => 96,
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
