<?php

return [
    'key' => 'sla_service_tagid',
    'fields' => [
        'sla_service_tagid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'slaid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'sla',
            'ref_field' => 'slaid',
        ],
        'tag' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'operator' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
