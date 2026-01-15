<?php

return [
    'key' => 'problemtagid',
    'fields' => [
        'problemtagid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'eventid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'problem',
            'ref_field' => 'eventid',
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
