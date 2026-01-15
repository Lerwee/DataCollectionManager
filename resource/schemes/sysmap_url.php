<?php

return [
    'key' => 'sysmapurlid',
    'fields' => [
        'sysmapurlid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'sysmapid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'sysmaps',
            'ref_field' => 'sysmapid',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
        ],
        'url' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'elementtype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
