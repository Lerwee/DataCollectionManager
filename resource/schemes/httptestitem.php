<?php

return [
    'key' => 'httptestitemid',
    'fields' => [
        'httptestitemid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'httptestid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'httptest',
            'ref_field' => 'httptestid',
        ],
        'itemid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'items',
            'ref_field' => 'itemid',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
