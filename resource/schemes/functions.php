<?php

return [
    'key' => 'functionid',
    'fields' => [
        'functionid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'itemid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'items',
            'ref_field' => 'itemid',
        ],
        'triggerid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'triggers',
            'ref_field' => 'triggerid',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 12,
            'default' => '',
        ],
        'parameter' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '0',
        ],
    ],
];
