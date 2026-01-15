<?php

return [
    'key' => 'iconmapid',
    'fields' => [
        'iconmapid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'default_iconid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'images',
            'ref_field' => 'imageid',
        ],
    ],
];
