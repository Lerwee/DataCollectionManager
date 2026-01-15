<?php

return [
    'key' => 'expressionid',
    'fields' => [
        'expressionid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'regexpid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'regexps',
            'ref_field' => 'regexpid',
        ],
        'expression' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'expression_type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'exp_delimiter' => [
            'null' => false,
            'type' => 'char',
            'length' => 1,
            'default' => '',
        ],
        'case_sensitive' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
