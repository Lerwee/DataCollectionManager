<?php

return [
    'key' => 'conditionid',
    'fields' => [
        'conditionid' => [
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
        'conditiontype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'operator' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'value2' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
