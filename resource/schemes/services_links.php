<?php

return [
    'key' => 'linkid',
    'fields' => [
        'linkid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'serviceupid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'services',
            'ref_field' => 'serviceid',
        ],
        'servicedownid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'services',
            'ref_field' => 'serviceid',
        ],
    ],
];
