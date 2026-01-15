<?php

return [
    'key' => 'interfaceid',
    'fields' => [
        'interfaceid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'interface',
            'ref_field' => 'interfaceid',
        ],
        'parent_interfaceid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'interface',
            'ref_field' => 'interfaceid',
        ],
    ],
];
