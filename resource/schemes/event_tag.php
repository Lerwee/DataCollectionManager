<?php

return [
    'key' => 'eventtagid',
    'fields' => [
        'eventtagid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'eventid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'events',
            'ref_field' => 'eventid',
        ],
        'tag' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
