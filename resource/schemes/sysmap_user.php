<?php

return [
    'key' => 'sysmapuserid',
    'fields' => [
        'sysmapuserid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'sysmapid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'sysmaps',
            'ref_field' => 'sysmapid',
        ],
        'userid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
        'permission' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '2',
        ],
    ],
];
