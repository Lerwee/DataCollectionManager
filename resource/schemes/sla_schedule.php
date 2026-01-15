<?php

return [
    'key' => 'sla_scheduleid',
    'fields' => [
        'sla_scheduleid' => [
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
