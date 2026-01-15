<?php

return [
    'key' => 'operationid',
    'fields' => [
        'operationid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'operations',
            'ref_field' => 'operationid',
        ],
        'default_msg' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'subject' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'message' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
        'mediatypeid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'media_type',
            'ref_field' => 'mediatypeid',
        ],
    ],
];
