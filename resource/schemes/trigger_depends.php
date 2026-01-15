<?php

return [
    'key' => 'triggerdepid',
    'fields' => [
        'triggerdepid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'triggerid_down' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'triggers',
            'ref_field' => 'triggerid',
        ],
        'triggerid_up' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'triggers',
            'ref_field' => 'triggerid',
        ],
    ],
];
