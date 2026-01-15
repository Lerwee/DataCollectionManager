<?php

return [
    'key' => 'interfaceid',
    'fields' => [
        'interfaceid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'interface',
            'ref_field' => 'interfaceid',
        ],
        'version' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '2',
        ],
        'bulk' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'community' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'securityname' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'securitylevel' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'authpassphrase' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'privpassphrase' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'authprotocol' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'privprotocol' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'contextname' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'max_repetitions' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '10',
        ],
    ],
];
