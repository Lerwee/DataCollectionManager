<?php

return [
    'key' => 'itemid,clock',
    'fields' => [
        'itemid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'items',
            'ref_field' => 'itemid',
        ],
        'clock' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'num' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'value_min' => [
            'null' => false,
            'type' => 'uint',
            'length' => 20,
            'default' => '0',
        ],
        'value_avg' => [
            'null' => false,
            'type' => 'uint',
            'length' => 20,
            'default' => '0',
        ],
        'value_max' => [
            'null' => false,
            'type' => 'uint',
            'length' => 20,
            'default' => '0',
        ],
    ],
];
