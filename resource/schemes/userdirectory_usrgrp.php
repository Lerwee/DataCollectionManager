<?php

return [
    'key' => 'userdirectory_usrgrpid',
    'fields' => [
        'userdirectory_usrgrpid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'userdirectory_idpgroupid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'userdirectory_idpgroup',
            'ref_field' => 'userdirectory_idpgroupid',
        ],
        'usrgrpid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'usrgrp',
            'ref_field' => 'usrgrpid',
        ],
    ],
];
