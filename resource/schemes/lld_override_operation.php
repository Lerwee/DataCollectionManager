<?php

return [
    'key' => 'lld_override_operationid',
    'fields' => [
        'lld_override_operationid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'lld_overrideid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'lld_override',
            'ref_field' => 'lld_overrideid',
        ],
        'operationobject' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'operator' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
