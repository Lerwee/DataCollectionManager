<?php

return [
    'key' => 'iconmappingid',
    'fields' => [
        'iconmappingid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'iconmapid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'icon_map',
            'ref_field' => 'iconmapid',
        ],
        'iconid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'images',
            'ref_field' => 'imageid',
        ],
        'inventory_link' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'expression' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'sortorder' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
