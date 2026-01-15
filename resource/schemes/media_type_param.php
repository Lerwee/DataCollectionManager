<?php

return [
    'key' => 'mediatype_paramid',
    'fields' => [
        'mediatype_paramid' => [
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
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'sortorder' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
