<?php

return [
    'key' => 'opcommand_hstid',
    'fields' => [
        'opcommand_hstid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'operationid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'operations',
            'ref_field' => 'operationid',
        ],
        'hostid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hosts',
            'ref_field' => 'hostid',
        ],
    ],
];
