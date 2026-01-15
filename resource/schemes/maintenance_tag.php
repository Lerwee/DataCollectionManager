<?php

return [
    'key' => 'maintenancetagid',
    'fields' => [
        'maintenancetagid' => [
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
        'tag' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'operator' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '2',
        ],
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
