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
        'idp_entityid' => [
            'null' => false,
            'type' => 'char',
            'length' => 1024,
            'default' => '',
        ],
        'sso_url' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'slo_url' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'username_attribute' => [
            'null' => false,
            'type' => 'char',
            'length' => 128,
            'default' => '',
        ],
        'sp_entityid' => [
            'null' => false,
            'type' => 'char',
            'length' => 1024,
            'default' => '',
        ],
        'nameid_format' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'sign_messages' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'sign_assertions' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'sign_authn_requests' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'sign_logout_requests' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'sign_logout_responses' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'encrypt_nameid' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'encrypt_assertions' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'group_name' => [
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
        'scim_status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
