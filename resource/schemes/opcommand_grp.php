<?php

return [
    'key' => 'opcommand_grpid',
    'fields' => [
        'opcommand_grpid' => [
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
