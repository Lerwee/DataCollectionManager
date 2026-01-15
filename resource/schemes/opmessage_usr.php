<?php

return [
    'key' => 'opmessage_usrid',
    'fields' => [
        'opmessage_usrid' => [
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
        'userid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
    ],
];
