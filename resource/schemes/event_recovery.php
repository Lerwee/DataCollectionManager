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
        'r_eventid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'events',
            'ref_field' => 'eventid',
        ],
        'c_eventid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'events',
            'ref_field' => 'eventid',
        ],
        'correlationid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'correlation',
            'ref_field' => 'correlationid',
        ],
        'userid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
    ],
];
