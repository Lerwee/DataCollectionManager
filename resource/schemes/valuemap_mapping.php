<?php

return [
    'key' => 'valuemap_mappingid',
    'fields' => [
        'valuemap_mappingid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'valuemapid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'valuemap',
            'ref_field' => 'valuemapid',
        ],
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'newvalue' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'sortorder' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
