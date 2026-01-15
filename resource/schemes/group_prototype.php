<?php

return [
    'key' => 'group_prototypeid',
    'fields' => [
        'group_prototypeid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'hostid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hosts',
            'ref_field' => 'hostid',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'groupid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hstgrp',
            'ref_field' => 'groupid',
        ],
        'templateid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'group_prototype',
            'ref_field' => 'group_prototypeid',
        ],
    ],
];
