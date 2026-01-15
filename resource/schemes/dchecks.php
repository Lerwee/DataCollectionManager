<?php

return [
    'key' => 'dcheckid',
    'fields' => [
        'dcheckid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'druleid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
            'ref_table' => 'drules',
            'ref_field' => 'druleid',
        ],
        'type' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'key_' => [
            'null' => false,
            'type' => 'char',
            'length' => 2048,
            'default' => '',
        ],
        'snmp_community' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'ports' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '0',
        ],
        'snmpv3_securityname' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'snmpv3_securitylevel' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'snmpv3_authpassphrase' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'snmpv3_privpassphrase' => [
            'null' => false,
            'type' => 'char',
            'length' => 64,
            'default' => '',
        ],
        'uniq' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'snmpv3_authprotocol' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'snmpv3_privprotocol' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
        'snmpv3_contextname' => [
            'null' => false,
            'type' => 'char',
            'length' => 255,
            'default' => '',
        ],
        'host_source' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '1',
        ],
        'name_source' => [
            'null' => false,
            'type' => 'int',
            'length' => 10,
            'default' => '0',
        ],
    ],
];
