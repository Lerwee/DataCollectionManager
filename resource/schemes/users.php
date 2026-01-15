<?php

return [
    'key' => 'userid',
    'fields' => [
        'userid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'username' => [
            'null' => false,
            'type' => 'char',
            'length' => 100,
            'default' => '',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 100,
            'default' => '',
        ],
        'surname' => [
            'null' => false,
            'type' => 'char',
            'length' => 100,
            'default' => '',
        ],
        'passwd' => [
            'null' => false,
            'type' => 'char',
            'length' => 60,
            'default' => '',
        ],
        'url' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'autologin' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'autologout' => [
            'null' => false,
            'type' => 'char',
            'length' => 32,
            'default' => '15m',
        ],
        'lang' => [
            'null' => false,
            'type' => 'char',
            'length' => 7,
            'default' => 'default',
        ],
        'refresh' => [
            'null' => false,
            'type' => 'char',
            'length' => 32,
            'default' => '30s',
        ],
        'theme' => [
            'null' => false,
            'type' => 'char',
            'length' => 128,
            'default' => 'default',
        ],
        'attempt_failed' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
        ],
        'attempt_ip' => [
            'null' => false,
            'type' => 'char',
            'length' => 39,
            'default' => '',
        ],
        'attempt_clock' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
        ],
        'rows_per_page' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => 50,
        ],
        'timezone' => [
            'null' => false,
            'type' => 'char',
            'length' => 50,
            'default' => 'default',
        ],
        'roleid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'default' => null,
            'ref_table' => 'role',
            'ref_field' => 'roleid',
        ],
        'userdirectoryid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'default' => null,
            'ref_table' => 'userdirectory',
            'ref_field' => 'userdirectoryid',
        ],
        'ts_provisioned' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
