<?php

return [
    'key' => 'taskid',
    'fields' => [
        'taskid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'task',
            'ref_field' => 'taskid',
        ],
        'acknowledgeid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'acknowledges',
            'ref_field' => 'acknowledgeid',
        ],
    ],
];
