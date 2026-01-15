<?php

return [
    'key' => 'changelogid',
    'fields' => [
        'changelogid' => [
            'null' => false,
            'type' => 'uint',
            'length' => 20,
        ],
        'object' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'objectid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'operation' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'clock' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
