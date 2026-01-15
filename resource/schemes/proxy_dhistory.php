<?php

return [
    'key' => 'id',
    'fields' => [
        'id' => [
            'null' => false,
            'type' => 'uint',
            'length' => 20,
        ],
        'clock' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'druleid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'drules',
            'ref_field' => 'druleid',
        ],
        'ip' => [
            'null' => false,
            'type' => 'char',
            'length' => 39,
            'default' => '',
        ],
        'port' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'value' => [
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
        'dcheckid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'dchecks',
            'ref_field' => 'dcheckid',
        ],
        'dns' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
