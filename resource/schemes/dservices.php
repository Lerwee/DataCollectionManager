<?php

return [
    'key' => 'dserviceid',
    'fields' => [
        'dserviceid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'dhostid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'dhosts',
            'ref_field' => 'dhostid',
        ],
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'port' => [
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
        'lastup' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'lastdown' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'dcheckid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'dchecks',
            'ref_field' => 'dcheckid',
        ],
        'ip' => [
            'null' => false,
            'type' => 'char',
            'length' => 39,
            'default' => '',
        ],
        'dns' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
