<?php

return [
    'key' => 'mediaid',
    'fields' => [
        'mediaid' => [
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
        'mediatypeid' => [
            'null' => false,
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
        'active' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'severity' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '63',
        ],
        'period' => [
            'null' => false,
            'type' => 'char',
            'length' => 1024,
            'default' => '1-7,00:00-24:00',
        ],
    ],
];
