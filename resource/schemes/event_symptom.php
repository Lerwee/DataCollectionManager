<?php

return [
    'key' => 'eventid',
    'fields' => [
        'eventid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'events',
            'ref_field' => 'eventid',
        ],
        'cause_eventid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'events',
            'ref_field' => 'eventid',
        ],
    ],
];
