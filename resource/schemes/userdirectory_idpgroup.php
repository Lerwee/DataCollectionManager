<?php

return [
    'key' => 'userdirectory_idpgroupid',
    'fields' => [
        'userdirectory_idpgroupid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'userdirectoryid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'userdirectory',
            'ref_field' => 'userdirectoryid',
        ],
        'roleid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'role',
            'ref_field' => 'roleid',
        ],
        'name' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
    ],
];
