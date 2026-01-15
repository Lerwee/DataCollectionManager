<?php

return [
    'key' => 'usrgrpid',
    'fields' => [
        'usrgrpid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'gui_access' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'users_status' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'debug_mode' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'userdirectoryid' => [
            'null' => true,
            'type' => 'id',
            'length' => 20,
            'default' => null,
            'ref_table' => 'userdirectory',
            'ref_field' => 'userdirectoryid',
        ],
    ],
];
