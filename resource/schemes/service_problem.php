<?php

return [
    'key' => 'service_problemid',
    'fields' => [
        'service_problemid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'eventid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'problem',
            'ref_field' => 'eventid',
        ],
        'serviceid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'services',
            'ref_field' => 'serviceid',
        ],
        'severity' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
