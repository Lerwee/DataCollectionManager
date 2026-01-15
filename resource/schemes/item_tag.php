<?php

return [
    'key' => 'itemtagid',
    'fields' => [
        'itemtagid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'itemid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'items',
            'ref_field' => 'itemid',
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
