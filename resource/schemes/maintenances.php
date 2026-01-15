<?php

return [
    'key' => 'maintenanceid',
    'fields' => [
        'maintenanceid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 128,
            'default' => '',
        ],
        'maintenance_type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'description' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
        'active_since' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'active_till' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'tags_evaltype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
