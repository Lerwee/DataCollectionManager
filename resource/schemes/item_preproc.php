<?php

return [
    'key' => 'item_preprocid',
    'fields' => [
        'item_preprocid' => [
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
        'step' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'params' => [
            'null' => false,
            'type' => 'nclob',
            'default' => '',
        ],
        'error_handler' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'error_handler_params' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
