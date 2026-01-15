<?php

return [
    'key' => 'corr_conditionid',
    'fields' => [
        'corr_conditionid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'corr_condition',
            'ref_field' => 'corr_conditionid',
        ],
        'tag' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
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
