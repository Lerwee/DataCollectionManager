<?php

return [
    'key' => 'lld_override_operationid',
    'fields' => [
        'lld_override_operationid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'lld_override_operation',
            'ref_field' => 'lld_override_operationid',
        ],
        'severity' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
