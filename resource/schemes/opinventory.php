<?php

return [
    'key' => 'operationid',
    'fields' => [
        'operationid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'operations',
            'ref_field' => 'operationid',
        ],
        'inventory_mode' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
