<?php

return [
    'key' => 'scim_groupid',
    'fields' => [
        'scim_groupid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
    ],
];
