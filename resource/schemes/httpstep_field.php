<?php

return [
    'key' => 'httpstep_fieldid',
    'fields' => [
        'httpstep_fieldid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'httpstepid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'httpstep',
            'ref_field' => 'httpstepid',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'value' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
    ],
];
