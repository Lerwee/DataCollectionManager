<?php

return [
    'key' => 'operationid',
    'fields' => [
        'operationid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'actionid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'actions',
            'ref_field' => 'actionid',
        ],
        'operationtype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'esc_period' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '0',
        ],
        'esc_step_from' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'esc_step_to' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'evaltype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'recovery' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
