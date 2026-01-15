<?php

return [
    'key' => 'taskid',
    'fields' => [
        'taskid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
        ],
        'status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'clock' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'ttl' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'proxy_hostid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hosts',
            'ref_field' => 'hostid',
        ],
    ],
];
