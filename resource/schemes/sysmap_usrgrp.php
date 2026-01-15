<?php

return [
    'key' => 'sysmapusrgrpid',
    'fields' => [
        'sysmapusrgrpid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'sysmapid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'sysmaps',
            'ref_field' => 'sysmapid',
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
