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
        'scriptid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'scripts',
            'ref_field' => 'scriptid',
        ],
    ],
];
