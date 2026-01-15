<?php

return [
    'key' => 'id',
    'fields' => [
        'id' => [
            'null' => false,
            'type' => 'uint',
            'length' => 20,
        ],
        'clock' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'host' => [
            'null' => false,
            'type' => 'char',
            'length' => 128,
            'default' => '',
        ],
        'listen_ip' => [
            'null' => false,
            'type' => 'char',
            'length' => 39,
            'default' => '',
        ],
        'listen_port' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'listen_dns' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'host_metadata' => [
            'null' => false,
            'type' => 'nclob',
            'default' => '',
        ],
        'flags' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'tls_accepted' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
    ],
];
