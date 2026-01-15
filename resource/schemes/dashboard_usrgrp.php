<?php

return [
    'key' => 'dashboard_usrgrpid',
    'fields' => [
        'dashboard_usrgrpid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'dashboardid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'dashboard',
            'ref_field' => 'dashboardid',
        ],
        'usrgrpid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'usrgrp',
            'ref_field' => 'usrgrpid',
        ],
        'permission' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '2',
        ],
    ],
];
