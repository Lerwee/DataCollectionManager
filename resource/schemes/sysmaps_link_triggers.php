<?php

return [
    'key' => 'linktriggerid',
    'fields' => [
        'linktriggerid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'linkid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'sysmaps_links',
            'ref_field' => 'linkid',
        ],
        'triggerid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'triggers',
            'ref_field' => 'triggerid',
        ],
        'drawtype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'color' => [
            'null' => false,
            'type' => 'char',
            'length' => 6,
            'default' => '000000',
        ],
    ],
];
