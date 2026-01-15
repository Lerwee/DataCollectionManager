<?php

return [
    'key' => 'opmessage_grpid',
    'fields' => [
        'opmessage_grpid' => [
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
        'usrgrpid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'usrgrp',
            'ref_field' => 'usrgrpid',
        ],
    ],
];
