<?php

return [
    'key' => 'globalvarid',
    'fields' => [
        'globalvarid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'snmp_lastsize' => [
            'null' => false,
            'type' => 'uint',
            'length' => 20,
            'default' => '0',
        ],
    ],
];
