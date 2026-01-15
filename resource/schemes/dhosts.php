<?php

return [
    'key' => 'dhostid',
    'fields' => [
        'dhostid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'druleid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'drules',
            'ref_field' => 'druleid',
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
    ],
];
