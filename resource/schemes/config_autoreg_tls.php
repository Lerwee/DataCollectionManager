<?php

return [
    'key' => 'autoreg_tlsid',
    'fields' => [
        'autoreg_tlsid' => [
            'null' => false,
            'type' => 'id',
            'length' => 20,
        ],
        'tls_psk_identity' => [
            'null' => false,
            'type' => 'char',
            'length' => 128,
            'default' => '',
        ],
        'tls_psk' => [
            'null' => false,
            'type' => 'char',
            'length' => 512,
            'default' => '',
        ],
    ],
];
