<?php

return [
    'key' => 'reportusrgrpid',
    'fields' => [
        'reportusrgrpid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'reportid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'report',
            'ref_field' => 'reportid',
        ],
        'usrgrpid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'usrgrp',
            'ref_field' => 'usrgrpid',
        ],
        'access_userid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'users',
            'ref_field' => 'userid',
        ],
    ],
];
