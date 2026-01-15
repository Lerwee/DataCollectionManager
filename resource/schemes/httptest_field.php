<?php

return [
    'key' => 'httptest_fieldid',
    'fields' => [
        'httptest_fieldid' => [
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
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'value' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
    ],
];
