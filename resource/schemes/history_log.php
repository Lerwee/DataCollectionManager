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
        'timestamp' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'source' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'severity' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'value' => [
            'null' => false,
            'type' => 'nclob',
            'default' => '',
        ],
        'logeventid' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'ns' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
