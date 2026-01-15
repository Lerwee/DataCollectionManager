<?php

return [
    'key' => 'userdirectoryid',
    'fields' => [
        'userdirectoryid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'userdirectory',
            'ref_field' => 'userdirectoryid',
        ],
        'host' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'port' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '389',
        ],
        'base_dn' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'search_attribute' => [
            'null' => false,
            'type' => 'char',
            'length' => 128,
            'default' => '',
        ],
        'bind_dn' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'bind_password' => [
            'null' => false,
            'type' => 'char',
            'length' => 128,
            'default' => '',
        ],
        'start_tls' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'search_filter' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'group_basedn' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'group_name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'group_member' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'user_ref_attr' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'group_filter' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'group_membership' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'user_username' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'user_lastname' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
