<?php

return [
    'key' => 'httptestid',
    'fields' => [
        'httptestid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'delay' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '1m',
        ],
        'status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'agent' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => 'Perseus',
        ],
        'authentication' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'http_user' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'http_password' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'hostid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hosts',
            'ref_field' => 'hostid',
        ],
        'templateid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'httptest',
            'ref_field' => 'httptestid',
        ],
        'http_proxy' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'retries' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'ssl_cert_file' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'ssl_key_file' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'ssl_key_password' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'verify_peer' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'verify_host' => [
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
