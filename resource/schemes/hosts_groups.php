<?php

return [
    'key' => 'hostgroupid',
    'fields' => [
        'hostgroupid' => [
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
        'groupid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hstgrp',
            'ref_field' => 'groupid',
        ],
    ],
];
