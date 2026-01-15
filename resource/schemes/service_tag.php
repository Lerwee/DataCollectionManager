<?php

return [
    'key' => 'servicetagid',
    'fields' => [
        'servicetagid' => [
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
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
