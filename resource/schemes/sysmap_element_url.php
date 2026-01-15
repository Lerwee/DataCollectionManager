<?php

return [
    'key' => 'sysmapelementurlid',
    'fields' => [
        'sysmapelementurlid' => [
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
    ],
];
