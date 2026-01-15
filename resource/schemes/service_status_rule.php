<?php

return [
    'key' => 'service_status_ruleid',
    'fields' => [
        'service_status_ruleid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'serviceid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'services',
            'ref_field' => 'serviceid',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'limit_value' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'limit_status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'new_status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
