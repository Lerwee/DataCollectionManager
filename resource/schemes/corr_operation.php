<?php

return [
    'key' => 'corr_operationid',
    'fields' => [
        'corr_operationid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'correlationid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'correlation',
            'ref_field' => 'correlationid',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
