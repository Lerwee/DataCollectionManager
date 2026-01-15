<?php

return [
    'key' => 'auditid',
    'fields' => [
        'auditid' => [
            'null' => false,
            'type' => 'cuid',
            'length' => 25,
        ],
        'userid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
        ],
        'username' => [
            'null' => false,
            'type' => 'char',
            'length' => 100,
            'default' => '',
        ],
        'clock' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'ip' => [
            'null' => false,
            'type' => 'char',
            'length' => 39,
            'default' => '',
        ],
        'action' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'resourcetype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'resourceid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
        ],
        'resource_cuid' => [
            'null' => true,
            'type' => 'cuid',
            'length' => 25,
        ],
        'resourcename' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'recordsetid' => [
            'null' => false,
            'type' => 'cuid',
            'length' => 25,
        ],
        'details' => [
            'null' => false,
            'type' => 'nclob',
            'default' => '',
        ],
    ],
];
