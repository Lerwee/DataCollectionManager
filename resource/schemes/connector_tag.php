<?php

return [
    'key' => 'connector_tagid',
    'fields' => [
        'connector_tagid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'connectorid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'connector',
            'ref_field' => 'connectorid',
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
