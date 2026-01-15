<?php

return [
    'key' => 'housekeeperid',
    'fields' => [
        'housekeeperid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'tablename' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'field' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'value' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'items',
            'ref_field' => 'value',
        ],
    ],
];
