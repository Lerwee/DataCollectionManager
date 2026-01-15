<?php

return [
    'key' => 'lld_override_conditionid',
    'fields' => [
        'lld_override_conditionid' => [
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
        'operator' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '8',
        ],
        'macro' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
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
