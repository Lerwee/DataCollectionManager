<?php

return [
    'key' => 'gitemid',
    'fields' => [
        'gitemid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'graphid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'graphs',
            'ref_field' => 'graphid',
        ],
        'itemid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'items',
            'ref_field' => 'itemid',
        ],
        'drawtype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'sortorder' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'color' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '009600',
        ],
        'yaxisside' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'calc_fnc' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '2',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
