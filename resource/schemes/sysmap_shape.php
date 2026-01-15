<?php

return [
    'key' => 'sysmap_shapeid',
    'fields' => [
        'sysmap_shapeid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'sysmapid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'sysmaps',
            'ref_field' => 'sysmapid',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'x' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'y' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'width' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '200',
        ],
        'height' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '200',
        ],
        'text' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
        'font' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '9',
        ],
        'font_size' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '11',
        ],
        'font_color' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '000000',
        ],
        'text_halign' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'text_valign' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'border_type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'border_width' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'border_color' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '000000',
        ],
        'background_color' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '',
        ],
        'zindex' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
