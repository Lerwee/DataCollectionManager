<?php

return [
    'key' => 'graphid',
    'fields' => [
        'graphid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 128,
            'default' => '',
        ],
        'width' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '900',
        ],
        'height' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '200',
        ],
        'yaxismin' => [
            'null' => false,
            'type' => 'float',
            'default' => '0',
        ],
        'yaxismax' => [
            'null' => false,
            'type' => 'float',
            'default' => '100',
        ],
        'templateid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'graphs',
            'ref_field' => 'graphid',
        ],
        'show_work_period' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'show_triggers' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'graphtype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'show_legend' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'show_3d' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'percent_left' => [
            'null' => false,
            'type' => 'float',
            'default' => '0',
        ],
        'percent_right' => [
            'null' => false,
            'type' => 'float',
            'default' => '0',
        ],
        'ymin_type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'ymax_type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'ymin_itemid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'items',
            'ref_field' => 'itemid',
        ],
        'ymax_itemid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'items',
            'ref_field' => 'itemid',
        ],
        'flags' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'discover' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'uuid' => [
            'null' => false,
            'type' => 'char',
            'length' => 32,
            'default' => '',
        ],
    ],
];
