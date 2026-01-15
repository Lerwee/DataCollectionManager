<?php

return [
    'key' => 'script_paramid',
    'fields' => [
        'script_paramid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'scriptid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'scripts',
            'ref_field' => 'scriptid',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
    ],
];
