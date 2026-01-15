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
        'delay' => [
            'null' => false,
            'type' => 'char',
            'length' => 1024,
            'default' => '0',
        ],
    ],
];
