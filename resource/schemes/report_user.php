<?php

return [
    'key' => 'reportuserid',
    'fields' => [
        'reportuserid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'reportid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'report',
            'ref_field' => 'reportid',
        ],
        'userid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
        'exclude' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'access_userid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
    ],
];
