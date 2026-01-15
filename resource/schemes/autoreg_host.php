<?php

return [
    'key' => 'autoreg_hostid',
    'fields' => [
        'autoreg_hostid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'proxy_hostid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hosts',
            'ref_field' => 'hostid',
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
