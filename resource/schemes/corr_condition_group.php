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
        'operator' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'groupid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hstgrp',
            'ref_field' => 'groupid',
        ],
    ],
];
