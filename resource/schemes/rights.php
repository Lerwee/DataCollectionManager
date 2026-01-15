<?php

return [
    'key' => 'rightid',
    'fields' => [
        'rightid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'groupid' => [
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
            'default' => '0',
        ],
        'id' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'hstgrp',
            'ref_field' => 'groupid',
        ],
    ],
];
