<?php

return [
    'key' => 'alertid',
    'fields' => [
        'alertid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'actionid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'actions',
            'ref_field' => 'actionid',
        ],
        'eventid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'events',
            'ref_field' => 'eventid',
        ],
        'userid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
        'clock' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'mediatypeid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'media_type',
            'ref_field' => 'mediatypeid',
        ],
        'sendto' => [
            'null' => false,
            'type' => 'char',
            'length' => 1024,
            'default' => '',
        ],
        'subject' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'message' => [
            'null' => false,
            'type' => 'nclob',
            'default' => '',
        ],
        'status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'retries' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'error' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'esc_step' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'alerttype' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'p_eventid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'events',
            'ref_field' => 'eventid',
        ],
        'acknowledgeid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'acknowledges',
            'ref_field' => 'acknowledgeid',
        ],
        'parameters' => [
            'null' => false,
            'type' => 'nclob',
            'default' => '{}',
        ],
    ],
];
