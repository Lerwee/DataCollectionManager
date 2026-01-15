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
        'oldtag' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'newtag' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
