<?php

return [
    'key' => 'opgroupid',
    'fields' => [
        'opgroupid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'operationid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'operations',
            'ref_field' => 'operationid',
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
