<?php

return [
    'key' => 'maintenance_hostid',
    'fields' => [
        'maintenance_hostid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'maintenanceid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'maintenances',
            'ref_field' => 'maintenanceid',
        ],
        'hostid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hosts',
            'ref_field' => 'hostid',
        ],
    ],
];
