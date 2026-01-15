<?php

return [
    'key' => 'acknowledgeid',
    'fields' => [
        'acknowledgeid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'userid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
        'eventid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'events',
            'ref_field' => 'eventid',
        ],
        'clock' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'message' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'action' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'old_severity' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'new_severity' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'suppress_until' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'taskid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'task',
            'ref_field' => 'taskid',
        ],
    ],
];
