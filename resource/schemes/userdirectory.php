<?php

return [
    'key' => 'userdirectoryid',
    'fields' => [
        'userdirectoryid' => [
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
        'description' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
        'idp_type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'provision_status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
