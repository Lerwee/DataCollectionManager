<?php

return [
    'key' => 'event_suppressid',
    'fields' => [
        'event_suppressid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'eventid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'events',
            'ref_field' => 'eventid',
        ],
        'maintenanceid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'maintenances',
            'ref_field' => 'maintenanceid',
        ],
        'suppress_until' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'userid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
    ],
];
