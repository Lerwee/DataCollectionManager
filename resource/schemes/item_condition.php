<?php

return [
    'key' => 'item_conditionid',
    'fields' => [
        'item_conditionid' => [
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
        'operator' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '8',
        ],
        'macro' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
