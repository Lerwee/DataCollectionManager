<?php

return [
    'key' => 'ha_nodeid',
    'fields' => [
        'ha_nodeid' => [
            'null' => false,
            'type' => 'cuid',
            'length' => 25,
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'address' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'port' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '10051',
        ],
        'lastaccess' => [
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
        'ha_sessionid' => [
            'null' => false,
            'type' => 'cuid',
            'length' => 25,
            'default' => '',
        ],
    ],
];
