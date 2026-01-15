<?php

namespace app\customs\zapi\services\assist;

use app\common\components\Result;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\ProxyHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\DnsValidator;
use app\customs\zapi\common\validators\HostNameValidator;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\IpRangesValidator;
use app\customs\zapi\common\validators\IpValidator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\ObjectValidator;
use app\customs\zapi\common\validators\PortValidator;
use app\customs\zapi\common\validators\PSKValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\modules\libzbx\models\Hosts;
use app\modules\libzbx\models\Interfaces;
use app\modules\libzbx\models\zbx\Drules;
use yii\base\Exception;
use yii\db\Query;

/**
 * Class ProxyAssist
 * @package app\customs\zapi\services\assist
 */
class ProxyAssist extends BaseAssist
{

    /**
     * @param array $proxies
     * @return Result
     */
    public function create(array $proxies): Result
    {
        try {
            $data = $this->createProxy($proxies);
            return $this->success($data);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750701, $e->getMessage());
        }
    }

    /**
     * @param array $proxies
     * @return Result
     */
    public function update(array $proxies): Result
    {
        try {
            $data = $this->updateProxy($proxies);
            return $this->success($data);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750702, $e->getMessage());
        }
    }

    /**
     * @param array $proxyIds
     * @return Result
     */
    public function delete(array $proxyIds): Result
    {
        try {
            $data = $this->deleteProxy($proxyIds);
            return $this->success($data);
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750702, $e->getMessage());
        }
    }

    /**
     * @param array $proxies
     * @return array
     * @throws ValidateException
     * @throws \yii\base\Exception
     * @throws \yii\db\Exception
     */
    public function createProxy(array $proxies): array
    {
        self::validateCreate($proxies);
        $proxyids = DB::insert('hosts', $proxies);
        $host_rtdata = [];

        foreach ($proxies as $index => &$proxy) {
            $proxy['proxyid'] = $proxyids[$index];
            $host_rtdata[] = ['hostid' => $proxyids[$index]];
        }
        unset($proxy);

        DB::insert('host_rtdata', $host_rtdata, false);
        self::updateInterfaces($proxies);
        self::updateHosts($proxies);

        return ['proxyids' => $proxyids];
    }


    /**
     * @param array $proxies
     * @return array
     * @throws Exception
     * @throws ValidateException
     * @throws \yii\db\Exception
     */
    public function updateProxy(array $proxies): array {

        $this->validateUpdate($proxies, $db_proxies);

        $upd_proxies = [];

        foreach ($proxies as $proxy) {
            $upd_proxy = DB::getUpdatedValues('hosts', $proxy, $db_proxies[$proxy['proxyid']]);

            if ($upd_proxy) {
                $upd_proxies[] = [
                    'values' => $upd_proxy,
                    'where' => ['hostid' => $proxy['proxyid']]
                ];
            }
        }

        if ($upd_proxies) {
            DB::update('hosts', $upd_proxies);
        }

        self::updateInterfaces($proxies, $db_proxies);
        self::updateHosts($proxies, $db_proxies);

        return ['proxyids' => array_column($proxies, 'proxyid')];
    }

    /**
     * @param array $proxyids
     * @return array[]
     * @throws ValidateException
     * @throws \yii\db\Exception
     */
    public function deleteProxy(array $proxyids): array
    {
        $this->validateDelete($proxyids, $db_proxies);

        DB::delete('host_rtdata', ['hostid' => $proxyids]);
        DB::delete('interface', ['hostid' => $proxyids]);
        DB::delete('hosts', ['hostid' => $proxyids]);

        return ['proxyids' => $proxyids];
    }


    /**
     * @param array $proxies
     * @throws ValidateException
     * @throws \yii\base\Exception
     * @throws \yii\db\Exception
     */
    private static function validateCreate(array &$proxies): void
    {
        $fieldRules = [
            'host' => [HostNameValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hosts', 'host')],
            'status' => [Int32Validator::class, 'flags' => API_REQUIRED, 'in' => implode(',', [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE])],
            'description' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('hosts', 'description')],
            'proxy_address' => [Utf8StringValidator::class, 'flags' => API_ALLOW_DNS, 'length' => DB::getFieldLength('hosts', 'proxy_address')],
            'hosts' => [ObjectsValidator::class, 'uniq' => [['hostid']], 'fields' => [
                'hostid' => [IdValidator::class, 'flags' => API_REQUIRED]
            ]],
            'interface' => [ObjectValidator::class, 'fields' => [
                'useip' => [Int32Validator::class, 'in' => implode(',', [INTERFACE_USE_DNS, INTERFACE_USE_IP])],
                'ip' => [IpValidator::class, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('interface', 'ip')],
                'dns' => [DnsValidator::class, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('interface', 'dns')],
                'port' => [PortValidator::class, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('interface', 'port')]
            ]],
            'tls_connect' => [MultipleValidator::class, 'default' => HOST_ENCRYPTION_NONE, 'rules' => [
                [Int32Validator::class, 'in' => HOST_ENCRYPTION_NONE, 'when' => function ($model) {
                    return $model->status == HOST_STATUS_PROXY_ACTIVE;
                }],
                [Int32Validator::class, 'in' => implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE]), 'when' => function ($model) {
                    return $model->status == HOST_STATUS_PROXY_PASSIVE;
                }]
            ]],
            'tls_accept' => [MultipleValidator::class, 'default' => HOST_ENCRYPTION_NONE, 'rules' => [
                [Int32Validator::class, 'in' => HOST_ENCRYPTION_NONE . ':' . (HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE), 'when' => function ($model) {
                    return $model->status == HOST_STATUS_PROXY_ACTIVE;
                }],
                [Int32Validator::class, 'in' => HOST_ENCRYPTION_NONE, 'when' => function ($model) {
                    return $model->status == HOST_STATUS_PROXY_PASSIVE;
                }]
            ]],
            'tls_psk_identity' => [MultipleValidator::class, 'rules' => [
                [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk_identity'), 'when' => function ($model) {
                            return $model->tls_connect == HOST_ENCRYPTION_PSK;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_PASSIVE;
                    }
                ],
                [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk_identity'), 'when' => function ($model) {
                            return ($model->tls_connect & HOST_ENCRYPTION_PSK) != 0;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_ACTIVE;
                    }
                ]
            ]],
            'tls_psk' => [MultipleValidator::class, 'rules' => [
                [
                    MultipleValidator::class,
                    'rules' => [
                        PSKValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk'), 'when' => function ($model) {
                            return $model->tls_connect == HOST_ENCRYPTION_PSK;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_PASSIVE;
                    }
                ],
                [
                    MultipleValidator::class,
                    'rules' => [
                        PSKValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk'), 'when' => function ($model) {
                            return ($model->tls_connect & HOST_ENCRYPTION_PSK) != 0;
                        }
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_ACTIVE;
                    }
                ]
            ]],
            'tls_issuer' => [MultipleValidator::class, 'rules' => [
                [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class, 'length' => DB::getFieldLength('hosts', 'tls_issuer'), 'when' => function ($model) {
                            return $model->tls_connect == HOST_ENCRYPTION_CERTIFICATE;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_PASSIVE;
                    }
                ],
                [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class, 'length' => DB::getFieldLength('hosts', 'tls_issuer'), 'when' => function ($model) {
                            return ($model->tls_connect & HOST_ENCRYPTION_CERTIFICATE) != 0;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_ACTIVE;
                    }
                ]
            ]],
            'tls_subject' => [MultipleValidator::class, 'rules' => [
                [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class, 'length' => DB::getFieldLength('hosts', 'tls_subject'), function ($model) {
                            return $model->tls_connect == HOST_ENCRYPTION_CERTIFICATE;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_PASSIVE;
                    }
                ],
                [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class, 'length' => DB::getFieldLength('hosts', 'tls_subject'), 'when' => function ($model) {
                            return ($model->tls_connect & HOST_ENCRYPTION_CERTIFICATE) != 0;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_ACTIVE;
                    }
                ]
            ]]
        ];

        if (!ValidateHelper::validateObjects($proxies, $fieldRules, ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['host']]], $error)) {
            self::exception(60750201, $error);
        }

        self::checkDuplicates($proxies);
        self::checkHosts($proxies);
        self::checkProxyAddress($proxies);
        self::checkInterface($proxies, 'create');
    }

    /**
     * @param array $proxies
     * @param array|null $db_proxies
     * @throws Exception
     * @throws ValidateException
     * @throws \yii\db\Exception
     */
    private function validateUpdate(array &$proxies, ?array &$db_proxies): void
    {
        $fieldRules = [
            'proxyid' => [IdValidator::class, 'flags' => API_REQUIRED],
            'status' => [Int32Validator::class, 'in' => implode(',', [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE])],
            'host' => [HostNameValidator::class, 'length' => DB::getFieldLength('hosts', 'host')],
            'description' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('hosts', 'description')],
            'proxy_address' => [IpRangesValidator::class, 'flags' => API_ALLOW_DNS, 'length' => DB::getFieldLength('hosts', 'proxy_address')],
            'hosts' => [ObjectsValidator::class, 'uniq' => [['hostid']], 'fields' => [
                'hostid' => ['type' => IdValidator::class, 'flags' => API_REQUIRED]
            ]],
            'interface' => [ObjectValidator::class, 'fields' => [
                'useip' => [Int32Validator::class, 'in' => implode(',', [INTERFACE_USE_DNS, INTERFACE_USE_IP])],
                'ip' => [IpValidator::class, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('interface', 'ip')],
                'dns' => [DnsValidator::class, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('interface', 'dns')],
                'port' => [PortValidator::class, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('interface', 'port')]
            ]]
        ];


        if (!ValidateHelper::validateObjects($proxies, $fieldRules, ['flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['proxyid']], '_path'=> '/'], $error)) {
            self::exception(60750201, $error);
        }

        $db_proxies = ProxyHelper::getProxies([
            'output' => ['proxyid', 'host', 'status', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject',
                'description', 'proxy_address'
            ],
            'proxyids' => array_column($proxies, 'proxyid'),
            'editable' => true,
            'preservekeys' => true
        ]);

        if (count($proxies) != count($db_proxies)) {
            self::exception(60750004);
        }

        $proxies = $this->extendObjectsByKey($proxies, $db_proxies, 'proxyid', ['status']);

        foreach ($proxies as &$proxy) {
            if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE) {
                $proxy += [
                    'tls_connect' => $db_proxies[$proxy['proxyid']]['tls_connect'],
                    'tls_accept' => HOST_ENCRYPTION_NONE
                ];
            }
            else {
                $proxy += [
                    'tls_connect' => HOST_ENCRYPTION_NONE,
                    'tls_accept' => $db_proxies[$proxy['proxyid']]['tls_accept']
                ];
            }
        }
        unset($proxy);

        $fieldRules = [
            'tls_connect' => [MultipleValidator::class, 'rules' => [
                [Int32Validator::class, 'in' => HOST_ENCRYPTION_NONE, 'when' => function ($model) {
                    return $model->status == HOST_STATUS_PROXY_ACTIVE;
                }],
                [Int32Validator::class, 'in' => implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE]), 'when' => function ($model) {
                    return $model->status == HOST_STATUS_PROXY_PASSIVE;
                }],
            ]],
            'tls_accept' => [MultipleValidator::class,  'rules' => [
                [Int32Validator::class, 'in' => HOST_ENCRYPTION_NONE . ':' . (HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE), 'when' => function ($model) {
                    return $model->status == HOST_STATUS_PROXY_ACTIVE;
                }],
                [Int32Validator::class, 'in' => HOST_ENCRYPTION_NONE, 'when' => function ($model) {
                    return $model->status == HOST_STATUS_PROXY_PASSIVE;
                }]
            ]]
        ];

        if (!ValidateHelper::validateObjects($proxies, $fieldRules, ['flags' => API_ALLOW_UNEXPECTED], $error)) {
            self::exception(60750201, $error);
        }

        // Load PSK data directly from the DB, since the API won't return secret data.
        $proxies_psk_fields = Hosts::find()->select(['hostid', 'tls_psk_identity', 'tls_psk'])
            ->where(['hostid' => array_keys($db_proxies)])
            ->asArray()->indexBy('hostid')->all();

        foreach ($proxies_psk_fields as $hostid => $psk_fields) {
            $db_proxies[$hostid] += $psk_fields;
        }

        foreach ($proxies as &$proxy) {
            if (($proxy['status'] == HOST_STATUS_PROXY_PASSIVE && $proxy['tls_connect'] != HOST_ENCRYPTION_PSK)
                || ($proxy['status'] == HOST_STATUS_PROXY_ACTIVE
                    && ($proxy['tls_accept'] & HOST_ENCRYPTION_PSK) == 0)) {
                if ($db_proxies[$proxy['proxyid']]['tls_psk_identity'] !== '') {
                    $proxy += ['tls_psk_identity' => ''];
                }

                if ($db_proxies[$proxy['proxyid']]['tls_psk'] !== '') {
                    $proxy += ['tls_psk' => ''];
                }
            }
            if (($proxy['status'] == HOST_STATUS_PROXY_PASSIVE && $proxy['tls_connect'] != HOST_ENCRYPTION_CERTIFICATE)
                || ($proxy['status'] == HOST_STATUS_PROXY_ACTIVE
                    && ($proxy['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) == 0)) {
                $proxy += ['tls_issuer' => '', 'tls_subject' => ''];
            }
        }
        unset($proxy);

        $fieldRules = [
            'tls_psk_identity' => [MultipleValidator::class, 'rules' => [
                [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk_identity'), 'when' => function ($model) {
                            return $model->tls_connect == HOST_ENCRYPTION_PSK;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_PASSIVE;
                    }
                ],
                [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk_identity'), 'when' => function ($model) {
                            return ($model->tls_connect & HOST_ENCRYPTION_PSK) != 0;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_ACTIVE;
                    }
                ]
            ]],
            'tls_psk' => [MultipleValidator::class, 'rules' => [
                [
                    MultipleValidator::class,
                    'rules' => [
                        PSKValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk'), 'when' => function ($model) {
                            return $model->tls_connect == HOST_ENCRYPTION_PSK;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_PASSIVE;
                    }
                ],
                [
                    MultipleValidator::class,
                    'rules' => [
                        PSKValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk'), 'when' => function ($model) {
                            return ($model->tls_connect & HOST_ENCRYPTION_PSK) != 0;
                        }
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_ACTIVE;
                    }
                ]
            ]],
            'tls_issuer' => [MultipleValidator::class, 'rules' => [
                [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class, 'length' => DB::getFieldLength('hosts', 'tls_issuer'), 'when' => function ($model) {
                            return $model->tls_connect == HOST_ENCRYPTION_CERTIFICATE;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_PASSIVE;
                    }
                ],
                [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class, 'length' => DB::getFieldLength('hosts', 'tls_issuer'), 'when' => function ($model) {
                            return ($model->tls_connect & HOST_ENCRYPTION_CERTIFICATE) != 0;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_ACTIVE;
                    }
                ]
            ]],
            'tls_subject' => [MultipleValidator::class, 'rules' => [
                [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class, 'length' => DB::getFieldLength('hosts', 'tls_subject'), function ($model) {
                            return $model->tls_connect == HOST_ENCRYPTION_CERTIFICATE;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_PASSIVE;
                    }
                ],
                [
                    MultipleValidator::class,
                    'rules' => [
                        Utf8StringValidator::class, 'length' => DB::getFieldLength('hosts', 'tls_subject'), 'when' => function ($model) {
                            return ($model->tls_connect & HOST_ENCRYPTION_CERTIFICATE) != 0;
                        },
                    ],
                    'else' => [Utf8StringValidator::class, 'in' => ''],
                    'when' => function ($model) {
                        return $model->status == HOST_STATUS_PROXY_ACTIVE;
                    }
                ]
            ]]
        ];

        if (!ValidateHelper::validateObjects($proxies, $fieldRules, ['flags' => API_ALLOW_UNEXPECTED, '_path' => '/'], $error)) {
            self::exception(60750201, $error);
        }

        self::addAffectedObjects($proxies, $db_proxies);
        self::checkDuplicates($proxies, $db_proxies);
        self::checkHosts($proxies, $db_proxies);
        self::checkProxyAddress($proxies);
        self::checkInterface($proxies, 'update');
    }


    /**
     * @param array $proxyids
     * @param array|null $db_proxies
     * @throws ValidateException
     */
    private function validateDelete(array &$proxyids, ?array &$db_proxies): void
    {
        $proxyids = array_unique(filter_integer((array)$proxyids));

        $db_proxies = ProxyHelper::getProxies([
            'output' => ['proxyid', 'host'],
            'proxyids' => $proxyids,
            'editable' => true,
            'preservekeys' => true
        ]);

        if (count($proxyids) != count($db_proxies)) {
            self::exception(60750004);
        }

        self::checkUsedInDiscovery($db_proxies);
        self::checkUsedInHosts($db_proxies);
        self::checkUsedInActions($db_proxies);
    }

    /**
     * @param array $proxies
     * @throws ValidateException
     */
    private static function checkUsedInDiscovery(array $proxies): void
    {
        $db_drules = Drules::find()->select(['proxy_hostid', 'name'])
            ->where(['proxy_hostid' => array_keys($proxies)])
            ->limit(1)->asArray()->one();

        if ($db_drules) {
            self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Proxy "{proxy}" is used by discovery rule "{rule}".',
                [
                    'proxy' => $proxies[$db_drules[0]['proxy_hostid']]['host'],
                    'rule' => $db_drules[0]['name']
                ]
            ));
        }
    }

    /**
     * @param array $proxies
     * @throws ValidateException
     */
    private static function checkUsedInHosts(array $proxies): void {
        $db_hosts = Hosts::find()->select(['proxy_hostid', 'name'])
            ->where(['proxy_hostid' => array_keys($proxies)])
            ->limit(1)->asArray()->one();
        if ($db_hosts) {
            self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Host "{host}" is monitored by proxy "{proxy}".',
                [
                    'host' => $db_hosts['name'],
                    'proxy' =>  $proxies[$db_hosts['proxy_hostid']]['host']
                ]
            ));
        }
    }

    /**
     * @param array $proxies
     * @throws ValidateException
     */
    private static function checkUsedInActions(array $proxies): void
    {
        $db_actions = (new Query())->select(['a.name', 'c.value', 'proxy_hostid' => 'c.value'])
            ->from(['a' => 'actions', 'c' => 'conditions'])
            ->where('a.actionid=c.actionid')
            ->andWhere(['c.conditiontype' => PRS_CONDITION_TYPE_PROXY])
            ->andWhere(['c.value' => array_keys($proxies)])
            ->all();

        if ($db_actions) {
            self::exception(PRS_API_ERROR_PARAMETERS, t('zapi', 'Proxy "{proxy}" is used by action "{action}".',
                [
                    'proxy' => $proxies[$db_actions[0]['proxy_hostid']]['host'],
                    'action' => $db_actions[0]['name']
                ]
            ));
        }
    }

    /**
     * @param array $proxies
     * @param array|null $db_proxies
     * @throws ValidateException
     */
    private static function checkDuplicates(array $proxies, array $db_proxies = null): void
    {
        $names = [];

        foreach ($proxies as $proxy) {
            if (!array_key_exists('host', $proxy)) {
                continue;
            }

            if ($db_proxies === null || $proxy['host'] !== $db_proxies[$proxy['proxyid']]['host']) {
                $names[] = $proxy['host'];
            }
        }

        if (!$names) {
            return;
        }

        $duplicate = (new Query())->from(['h' => 'hosts'])->select(['host'])->where([
            'host' => $names,
            'status' => [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE]
        ])->limit(1)->one();

        if ($duplicate) {
            self::exception(60750201, t('zapi', 'Proxy "{name}" already exists.', ['name' => $duplicate['host']]));
        }
    }

    /**
     * @param array $proxies
     * @param array|null $db_proxies
     * @throws ValidateException
     */
    private static function checkHosts(array $proxies, array $db_proxies = null): void
    {
        $hostids = [];

        foreach ($proxies as $proxy) {
            if (!array_key_exists('hosts', $proxy)) {
                continue;
            }

            $proxy_hostids = array_column($proxy['hosts'], null, 'hostid');
            $db_proxy_hostids = $db_proxies !== null
                ? array_column($db_proxies[$proxy['proxyid']]['hosts'], null, 'hostid')
                : [];

            $hostids += array_diff_key($proxy_hostids, $db_proxy_hostids);
        }

        if (!$hostids) {
            return;
        }

        $db_hosts = (new Query())->from(['h' => 'hosts'])
            ->select(['hostid', 'host', 'flags'])
            ->where(['hostids' => array_keys($hostids)])
            ->all();

        if (count($db_hosts) != count($hostids)) {
            throw new ValidateException(60750004);
        }

        foreach ($db_hosts as $db_host) {
            if ($db_host['flags'] == PRS_FLAG_DISCOVERY_CREATED) {
                throw new ValidateException(60750201, t('zapi', 'Cannot update proxy for discovered host "{name}".', ['name' => $db_host['host']]));
            }
        }
    }

    /**
     * @param array $proxies
     * @throws ValidateException
     */
    private static function checkProxyAddress(array &$proxies): void
    {
        foreach ($proxies as $i => &$proxy) {
            if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE) {
                $proxy += ['proxy_address' => ''];

                if ($proxy['proxy_address'] !== '') {
                    self::invalidAttrException('/' . ($i + 1) . '/proxy_address', t('zapi', 'should be empty'));
                }
            }
        }
        unset($proxy);
    }

    /**
     * @param array $proxies
     * @param string $method
     * @throws ValidateException
     */
    private static function checkInterface(array &$proxies, string $method): void
    {
        foreach ($proxies as $i => &$proxy) {
            if ($proxy['status'] == HOST_STATUS_PROXY_ACTIVE) {
                $proxy += ['interface' => []];

                if ($proxy['interface']) {
                    self::invalidAttrException('/' . ($i + 1) . '/interface', t('zapi', 'should be empty'));
                }
            } else {
                if ($method === 'create' && !array_key_exists('interface', $proxy)) {
                    self::invalidAttrException('/' . ($i + 1), t('zapi', 'the parameter "{parameter}" is missing', ['parameter' => 'interface']));
                }

                if (array_key_exists('interface', $proxy)) {
                    $proxy['interface'] += ['useip' => INTERFACE_USE_IP];
                    $field_names = [($proxy['interface']['useip'] == INTERFACE_USE_IP) ? 'ip' : 'dns', 'port'];

                    foreach ($field_names as $field_name) {
                        if (!array_key_exists($field_name, $proxy['interface'])) {
                            self::invalidAttrException('/' . ($i + 1) . '/interface', t('zapi', 'the parameter "{parameter}" is missing', ['parameter' => '$field_name']));
                        }

                        if ($proxy['interface'][$field_name] === '') {
                            self::invalidAttrException('/' . ($i + 1) . '/interface/' . $field_name, t('zapi', 'cannot be empty'));
                        }
                    }

                    $proxy['interface']['type'] = INTERFACE_TYPE_UNKNOWN;
                    $proxy['interface']['main'] = INTERFACE_PRIMARY;
                }
            }
        }
        unset($proxy);
    }

    /**
     * @param array $proxies
     * @param array|null $db_proxies
     */
    private static function updateInterfaces(array &$proxies, array $db_proxies = null): void
    {
        $ins_interfaces = [];
        $upd_interfaces = [];
        $del_interfaceids = [];

        foreach ($proxies as &$proxy) {
            if (!array_key_exists('interface', $proxy)) {
                continue;
            }

            $db_interface = $db_proxies !== null ? $db_proxies[$proxy['proxyid']]['interface'] : [];

            if ($proxy['interface']) {
                if ($db_interface) {
                    $upd_interface = DB::getUpdatedValues('interface', $proxy['interface'], $db_interface);
                    $proxy['interface']['interfaceid'] = $db_interface['interfaceid'];

                    if ($upd_interface) {
                        $upd_interfaces[] = [
                            'values' => $upd_interface,
                            'where' => ['interfaceid' => $db_interface['interfaceid']]
                        ];
                    }
                } else {
                    $ins_interfaces[] = $proxy['interface'] + ['hostid' => $proxy['proxyid']];
                }
            } elseif ($db_interface) {
                $del_interfaceids[] = $db_interface['interfaceid'];
            }
        }
        unset($proxy);

        if ($ins_interfaces) {
            $interfaceids = DB::insert('interface', $ins_interfaces);
        }

        if ($upd_interfaces) {
            DB::update('interface', $upd_interfaces);
        }

        if ($del_interfaceids) {
            DB::delete('interface', ['interfaceid' => $del_interfaceids]);
        }

        foreach ($proxies as &$proxy) {
            if (!array_key_exists('interface', $proxy)) {
                continue;
            }

            if ($proxy['status'] != HOST_STATUS_PROXY_ACTIVE && !array_key_exists('interfaceid', $proxy['interface'])) {
                $proxy['interface']['interfaceid'] = array_shift($interfaceids);
            }
        }
        unset($proxy);
    }

    /**
     * @param array $proxies
     * @param array|null $db_proxies
     */
    private static function updateHosts(array &$proxies, array $db_proxies = null): void
    {
        $upd_hosts = [];

        foreach ($proxies as &$proxy) {
            if (!array_key_exists('hosts', $proxy)) {
                continue;
            }

            $db_hosts = $db_proxies !== null ? $db_proxies[$proxy['proxyid']]['hosts'] : [];

            foreach ($proxy['hosts'] as $host) {
                if (!array_key_exists($host['hostid'], $db_hosts)) {
                    $upd_hosts[$host['hostid']] = [
                        'values' => ['proxy_hostid' => $proxy['proxyid']],
                        'where' => ['hostid' => $host['hostid']]
                    ];
                } else {
                    unset($db_hosts[$host['hostid']]);
                }
            }

            foreach ($db_hosts as $db_host) {
                if (!array_key_exists($db_host['hostid'], $upd_hosts)) {
                    $upd_hosts[$db_host['hostid']] = [
                        'values' => ['proxy_hostid' => 0],
                        'where' => ['hostid' => $db_host['hostid']]
                    ];
                }
            }
        }
        unset($proxy);

        if ($upd_hosts) {
            DB::update('hosts', array_values($upd_hosts));
        }
    }

    /**
     * @param array $proxies
     * @param array $db_proxies
     */
    private static function addAffectedObjects(array $proxies, array &$db_proxies): void
    {
        $proxyids = ['hosts' => [], 'interface' => []];

        foreach ($proxies as $proxy) {
            if (array_key_exists('hosts', $proxy)) {
                $proxyids['hosts'][] = $proxy['proxyid'];
                $db_proxies[$proxy['proxyid']]['hosts'] = [];
            }

            $proxyids['interface'][] = $proxy['proxyid'];
            $db_proxies[$proxy['proxyid']]['interface'] = [];
        }

        if ($proxyids['hosts']) {
            $db_hosts = Hosts::find()->select(['hostid', 'proxy_hostid'])
                ->where(['proxy_hostid' => $proxyids['hosts']])
                ->asArray()->all();
           foreach ($db_hosts as $db_host) {
                $db_proxies[$db_host['proxy_hostid']]['hosts'][$db_host['hostid']] = [
                    'hostid' => $db_host['hostid']
                ];
            }
        }

        $db_interfaces = Interfaces::find()->select(['interfaceid', 'hostid', 'type', 'main', 'useip', 'ip', 'dns', 'port'])
            ->where(['hostid' => $proxyids['interface']])
            ->asArray()->all();

        foreach ($db_interfaces as $db_interface) {
            $db_proxies[$db_interface['hostid']]['interface'] = array_diff_key($db_interface, array_flip(['hostid']));
        }
    }
}