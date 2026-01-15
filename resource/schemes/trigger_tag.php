<?php

return [
    'key' => 'triggertagid',
    'fields' => [
        'triggertagid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'triggerid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'triggers',
            'ref_field' => 'triggerid',
        ],
        'tag' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'value' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
