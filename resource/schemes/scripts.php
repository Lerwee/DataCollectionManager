<?php

return [
    'key' => 'scriptid',
    'fields' => [
        'scriptid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'command' => [
            'null' => false,
            'type' => 'nclob',
            'default' => '',
        ],
        'host_access' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '2',
        ],
        'usrgrpid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'usrgrp',
            'ref_field' => 'usrgrpid',
        ],
        'groupid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hstgrp',
            'ref_field' => 'groupid',
        ],
        'description' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
        'confirmation' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '5',
        ],
        'execute_on' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '2',
        ],
        'timeout' => [
            'null' => false,
            'type' => 'char',
            'length' => 32,
            'default' => '30s',
        ],
        'scope' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'port' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'authtype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'username' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'password' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'publickey' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'privatekey' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'menu_path' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'url' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'new_window' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
    ],
];
