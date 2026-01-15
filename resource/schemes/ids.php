<?php

return [
    'key' => 'table_name,field_name',
    'fields' => [
        'table_name' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'field_name' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'nextid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
    ],
];
