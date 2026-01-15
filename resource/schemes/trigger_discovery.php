<?php

return [
    'key' => 'triggerid',
    'fields' => [
        'triggerid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'triggers',
            'ref_field' => 'triggerid',
        ],
        'parent_triggerid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'triggers',
            'ref_field' => 'triggerid',
        ],
        'lastcheck' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'ts_delete' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
