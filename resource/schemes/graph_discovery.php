<?php

return [
    'key' => 'graphid',
    'fields' => [
        'graphid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'graphs',
            'ref_field' => 'graphid',
        ],
        'parent_graphid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'graphs',
            'ref_field' => 'graphid',
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
