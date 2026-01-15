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
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'data' => [
            'null' => false,
            'type' => 'nclob',
            'default' => '',
        ],
        'parent_taskid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'task',
            'ref_field' => 'taskid',
        ],
    ],
];
