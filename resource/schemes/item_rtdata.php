<?php

return [
    'key' => 'itemid',
    'fields' => [
        'itemid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'items',
            'ref_field' => 'itemid',
        ],
        'lastlogsize' => [
            'null' => false,
            'type' => 'uint',
            'length' => 20,
            'default' => '0',
        ],
        'state' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'mtime' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'error' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
    ],
];
