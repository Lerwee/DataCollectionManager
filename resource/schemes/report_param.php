<?php

return [
    'key' => 'reportparamid',
    'fields' => [
        'reportparamid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'reportid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'report',
            'ref_field' => 'reportid',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'value' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
    ],
];
