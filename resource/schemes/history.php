<?php

return [
    'key' => 'itemid,clock,ns',
    'fields' => [
        'itemid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'items',
            'ref_field' => 'itemid',
        ],
        'clock' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'value' => [
            'null' => false,
            'type' => 'float',
            'default' => '0.0000',
        ],
        'ns' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
