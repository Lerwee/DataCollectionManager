<?php

return [
    'key' => 'user_scim_groupid',
    'fields' => [
        'user_scim_groupid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'userid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
        'scim_groupid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'scim_group',
            'ref_field' => 'scim_groupid',
        ],
    ],
];
