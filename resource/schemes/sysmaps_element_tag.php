<?php

return [
    'key' => 'selementtagid',
    'fields' => [
        'selementtagid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'selementid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'sysmaps_elements',
            'ref_field' => 'selementid',
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
        'operator' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
