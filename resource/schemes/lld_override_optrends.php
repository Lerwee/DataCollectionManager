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
        'trends' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '365d',
        ],
    ],
];
