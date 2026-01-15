<?php

return [
    'key' => 'escalationid',
    'fields' => [
        'escalationid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'actionid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'actions',
            'ref_field' => 'actionid',
        ],
        'triggerid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'triggers',
            'ref_field' => 'triggerid',
        ],
        'eventid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'events',
            'ref_field' => 'eventid',
        ],
        'r_eventid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'events',
            'ref_field' => 'eventid',
        ],
        'nextcheck' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'esc_step' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'itemid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'items',
            'ref_field' => 'itemid',
        ],
        'acknowledgeid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'acknowledges',
            'ref_field' => 'acknowledgeid',
        ],
        'servicealarmid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'service_alarms',
            'ref_field' => 'servicealarmid',
        ],
        'serviceid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'services',
            'ref_field' => 'serviceid',
        ],
    ],
];
