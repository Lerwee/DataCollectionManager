<?php

return [
    'key' => 'sessionid',
    'fields' => [
        'sessionid' => [
            'null' => false,
            'type' => 'char',
            'length' => 32,
            'default' => '',
        ],
        'userid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
        'lastaccess' => [
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
        'secret' => [
            'null' => false,
            'type' => 'char',
            'length' => 32,
            'default' => '',
        ],
    ],
];
