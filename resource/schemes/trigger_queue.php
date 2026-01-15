<?php

return [
    'key' => 'trigger_queueid',
    'fields' => [
        'trigger_queueid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'objectid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'type' => [
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
        'ns' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
