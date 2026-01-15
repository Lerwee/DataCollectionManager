<?php

return [
    'key' => 'selement_triggerid',
    'fields' => [
        'selement_triggerid' => [
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
        'triggerid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'triggers',
            'ref_field' => 'triggerid',
        ],
    ],
];
