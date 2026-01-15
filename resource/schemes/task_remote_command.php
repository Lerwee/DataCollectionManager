<?php

return [
    'key' => 'taskid',
    'fields' => [
        'taskid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'task',
            'ref_field' => 'taskid',
        ],
        'command_type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'execute_on' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'port' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
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
        'command' => [
            'null' => false,
            'type' => 'nclob',
            'default' => '',
        ],
        'alertid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'alerts',
            'ref_field' => 'alertid',
        ],
        'parent_taskid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'task',
            'ref_field' => 'taskid',
        ],
        'hostid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hosts',
            'ref_field' => 'hostid',
        ],
    ],
];
