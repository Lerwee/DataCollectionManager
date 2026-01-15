<?php

return [
    'key' => 'httptesttagid',
    'fields' => [
        'httptesttagid' => [
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
        'tag' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
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
