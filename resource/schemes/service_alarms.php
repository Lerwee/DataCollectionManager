<?php

return [
    'key' => 'servicealarmid',
    'fields' => [
        'servicealarmid' => [
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
        'clock' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'value' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '-1',
        ],
    ],
];
