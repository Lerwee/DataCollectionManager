<?php

return [
    'key' => 'groupid',
    'fields' => [
        'groupid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hstgrp',
            'ref_field' => 'groupid',
        ],
        'parent_group_prototypeid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'group_prototype',
            'ref_field' => 'group_prototypeid',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'lastcheck' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'ts_delete' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
