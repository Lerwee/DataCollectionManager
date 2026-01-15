<?php

return [
    'key' => 'role_ruleid',
    'fields' => [
        'role_ruleid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'roleid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'role',
            'ref_field' => 'roleid',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'value_int' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'value_str' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'value_moduleid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'module',
            'ref_field' => 'moduleid',
        ],
        'value_serviceid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'services',
            'ref_field' => 'serviceid',
        ],
    ],
];
