<?php

return [
    'key' => 'dbversionid',
    'fields' => [
        'dbversionid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'mandatory' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'optional' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
