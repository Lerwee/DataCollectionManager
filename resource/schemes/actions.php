<?php

return [
    'key' => 'actionid',
    'fields' => [
        'actionid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'eventsource' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'evaltype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'esc_period' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '1h',
        ],
        'formula' => [
            'null' => false,
            'type' => 'char',
            'length' => 1024,
            'default' => '',
        ],
        'pause_suppressed' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'notify_if_canceled' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'pause_symptoms' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
    ],
];
