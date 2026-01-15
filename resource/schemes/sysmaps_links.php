<?php

return [
    'key' => 'linkid',
    'fields' => [
        'linkid' => [
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
        'selementid1' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'sysmaps_elements',
            'ref_field' => 'selementid',
        ],
        'selementid2' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'sysmaps_elements',
            'ref_field' => 'selementid',
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
        'label' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
    ],
];
