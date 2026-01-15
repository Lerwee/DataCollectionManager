<?php

return [
    'key' => 'httpstepid',
    'fields' => [
        'httpstepid' => [
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
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'no' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'url' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'timeout' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '15s',
        ],
        'posts' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
        'required' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'status_codes' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'follow_redirects' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'retrieve_mode' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'post_type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
