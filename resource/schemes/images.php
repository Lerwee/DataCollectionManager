<?php

return [
    'key' => 'imageid',
    'fields' => [
        'imageid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'imagetype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '0',
        ],
        'image' => [
            'null' => false,
            'type' => 'blob',
            'length' => 2048,
            'default' => '',
        ],
    ],
];
