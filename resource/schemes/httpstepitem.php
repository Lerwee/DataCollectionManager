<?php

return [
    'key' => 'httpstepitemid',
    'fields' => [
        'httpstepitemid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'httpstepid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'httpstep',
            'ref_field' => 'httpstepid',
        ],
        'itemid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'items',
            'ref_field' => 'itemid',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
