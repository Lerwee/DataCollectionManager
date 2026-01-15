<?php

return [
    'key' => 'lld_override_optagid',
    'fields' => [
        'lld_override_optagid' => [
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
        'tag' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
