<?php

return [
    'key' => 'graphthemeid',
    'fields' => [
        'graphthemeid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'theme' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'backgroundcolor' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '',
        ],
        'graphcolor' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '',
        ],
        'gridcolor' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '',
        ],
        'maingridcolor' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '',
        ],
        'gridbordercolor' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '',
        ],
        'textcolor' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '',
        ],
        'highlightcolor' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '',
        ],
        'leftpercentilecolor' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '',
        ],
        'rightpercentilecolor' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '',
        ],
        'nonworktimecolor' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '',
        ],
        'colorpalette' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
