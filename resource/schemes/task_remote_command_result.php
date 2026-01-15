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
        'status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'parent_taskid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'task',
            'ref_field' => 'taskid',
        ],
        'info' => [
            'null' => false,
            'type' => 'text',
            'default' => '',
        ],
    ],
];
