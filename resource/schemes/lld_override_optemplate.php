<?php

return [
    'key' => 'lld_override_optemplateid',
    'fields' => [
        'lld_override_optemplateid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'lld_override_operationid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'lld_override_operation',
            'ref_field' => 'lld_override_operationid',
        ],
        'templateid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hosts',
            'ref_field' => 'hostid',
        ],
    ],
];
