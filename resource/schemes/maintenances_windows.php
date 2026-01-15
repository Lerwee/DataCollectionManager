<?php

return [
    'key' => 'maintenance_timeperiodid',
    'fields' => [
        'maintenance_timeperiodid' => [
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
        'timeperiodid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'timeperiods',
            'ref_field' => 'timeperiodid',
        ],
    ],
];
