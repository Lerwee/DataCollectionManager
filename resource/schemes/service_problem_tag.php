<?php

return [
    'key' => 'service_problem_tagid',
    'fields' => [
        'service_problem_tagid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'serviceid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'services',
            'ref_field' => 'serviceid',
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
