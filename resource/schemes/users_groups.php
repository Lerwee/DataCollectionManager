<?php

return [
    'key' => 'id',
    'fields' => [
        'id' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'usrgrpid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'usrgrp',
            'ref_field' => 'usrgrpid',
        ],
        'userid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
    ],
];
