<?php

return [
    'key' => 'mediatype_messageid',
    'fields' => [
        'mediatype_messageid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'mediatypeid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'media_type',
            'ref_field' => 'mediatypeid',
        ],
        'eventsource' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
        ],
        'recovery' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
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
    ],
];
