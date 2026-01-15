<?php

return [
    'key' => 'userdirectory_mediaid',
    'fields' => [
        'userdirectory_mediaid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'userdirectoryid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'userdirectory',
            'ref_field' => 'userdirectoryid',
        ],
        'mediatypeid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'media_type',
            'ref_field' => 'mediatypeid',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'attribute' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
