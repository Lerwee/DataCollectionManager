<?php

return [
    'key' => 'maintenance_groupid',
    'fields' => [
        'maintenance_groupid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'maintenanceid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'maintenances',
            'ref_field' => 'maintenanceid',
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
