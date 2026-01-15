<?php

return [
    'key' => 'sla_excluded_downtimeid',
    'fields' => [
        'sla_excluded_downtimeid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'slaid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'sla',
            'ref_field' => 'slaid',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'period_from' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'period_to' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
