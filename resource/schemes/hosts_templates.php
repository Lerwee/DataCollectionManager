<?php

return [
    'key' => 'hosttemplateid',
    'fields' => [
        'hosttemplateid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'hostid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hosts',
            'ref_field' => 'hostid',
        ],
        'templateid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hosts',
            'ref_field' => 'hostid',
        ],
        'link_type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
