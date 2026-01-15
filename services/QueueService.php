<?php

namespace app\customs\zapi\services;

use app\common\base\BaseService;
use app\common\components\Result;
use app\common\helpers\DatetimeHelper;
use app\common\helpers\StringHelper;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\ProxyHelper;
use app\customs\zapi\common\helpers\SettingsHelper;
use app\modules\libzbx\components\ZabbixServer;

class QueueService extends BaseService
{
    /**
     * 队列概况
     *
     * @param  array  $params
     * @return Result
     */
    public function getOverview(array $params = []): Result
    {
        $isProxy = 'proxy' == ($params['type'] ?? 'server');
        $result = $this->getQueueData($isProxy ? ZabbixServer::QUEUE_OVERVIEW_BY_PROXY : ZabbixServer::QUEUE_OVERVIEW);
        if (!$result->isSuccess()) {
            return $result;
        }
        $queueData = $result->getData();

        $types = $isProxy ? array_column(ProxyHelper::getProxies([
            'output' => ['host'],
            'preservekeys' => true,
        ]), 'host', 'hostid') + [0 => t('zapi', 'Perseus Server')] : [
            ITEM_TYPE_PERSEUS => t('zapi', 'Perseus agent'),
            ITEM_TYPE_PERSEUS_ACTIVE => t('zapi', 'Perseus agent (active)'),
            ITEM_TYPE_SIMPLE => t('zapi', 'Simple check'),
            ITEM_TYPE_SNMP => t('zapi', 'SNMP agent'),
            ITEM_TYPE_INTERNAL => t('zapi', 'Perseus internal'),
            ITEM_TYPE_EXTERNAL => t('zapi', 'External check'),
            ITEM_TYPE_DB_MONITOR => t('zapi', 'Database monitor'),
            ITEM_TYPE_HTTPAGENT => t('zapi', 'HTTP agent'),
            ITEM_TYPE_IPMI => t('zapi', 'IPMI agent'),
            ITEM_TYPE_SSH => t('zapi', 'SSH agent'),
            ITEM_TYPE_TELNET => t('zapi', 'TELNET agent'),
            ITEM_TYPE_JMX => t('zapi', 'JMX agent'),
            ITEM_TYPE_CALCULATED => t('zapi', 'Calculated'),
            ITEM_TYPE_SCRIPT => t('zapi', 'Script'),
        ];

        if (StringHelper::toBool($params['k2v'] ?? 'false')) {
            return $this->success([
                'types' => $types,
                'data' => array_column($queueData, null, 'itemtype'),
            ]);
        }

        $default = [
            'delay5' => 0,
            'delay10' => 0,
            'delay30' => 0,
            'delay60' => 0,
            'delay300' => 0,
            'delay600' => 0,
        ];

        $type2queue = array_column($queueData, null, 'itemtype');

        $data = [];
        foreach ($types as $type => $label) {
            $data[] = array_merge([
                'label' => $label,
                'value' => $type,

            ], array_key_exists($type, $type2queue) ? $type2queue[$type] : $default);
        }

        return $this->success($data);
    }

    /**
     * 队列明细
     *
     * @param  array  $params
     * @return Result
     */
    public function getDetail(array $params = []): Result
    {
        $result = $this->getQueueData(ZabbixServer::QUEUE_DETAILS);
        if (!$result->isSuccess()) {
            return $result;
        }
        $queueData = $result->getData();
        $queueData = array_column($queueData, 'nextcheck', 'itemid');

        $items = ItemHelper::getItems([
            'output' => ['hostid', 'name'],
            'selectHosts' => ['name'],
            'itemids' => array_keys($queueData),
            'webitems' => true,
            'preservekeys' => true,
        ]);

        if (count($queueData) != count($items)) {
            $items += DiscoverRuleHelper::getDiscoverRules([
                'output' => ['hostid', 'name'],
                'selectHosts' => ['name'],
                'itemids' => array_diff(array_keys($queueData), array_keys($items)),
                'preservekeys' => true,
            ]);
        }

        $proxyIds = \app\modules\libzbx\models\Hosts::find()
            ->select(['proxy_hostid'])
            ->where(['hostid' => array_column($items, 'hostid', 'hostid')])
            ->andWhere('proxy_hostid is not null')
            ->indexBy('hostid')
            ->column();

        $proxies = ProxyHelper::getProxies([
            'proxyids' => array_filter($proxyIds),
            'output' => ['proxyid', 'host'],
            'preservekeys' => true,
        ]);

        $data = [];
        $time = time();
        foreach ($queueData as $itemId => $clock) {
            $item = $items[$itemId];
            $proxy = array_key_exists($item['hostid'], $proxyIds) ? $proxies[$proxyIds[$item['hostid']]]['host'] : '';

            $data[] = [
                'itemid' => $item['itemid'],
                'hostid' => $item['hostid'],
                'item_name' => $item['name'],
                'host_name' => $item['hosts'][0]['name'],
                'proxy_name' => $proxy,
                'next_check' => date('Y-m-d H:i:s', $clock),
                'duration' => DatetimeHelper::readableTime($time - $clock),
            ];
        }

        return $this->success($data);
    }

    /**
     * @param  string $action
     * @return Result
     */
    private function getQueueData(string $action): Result
    {
        [$host, $port] = get_perseus_server_address();
        $server = new ZabbixServer([
            'host' => $host,
            'port' => $port,
            'connectTimeout' => DatetimeHelper::timeUnitToSeconds(SettingsHelper::get(SettingsHelper::CONNECT_TIMEOUT)),
            'timeout' => DatetimeHelper::timeUnitToSeconds(SettingsHelper::get(SettingsHelper::SOCKET_TIMEOUT)),
            'totalBytesLimit' => PRS_SOCKET_BYTES_LIMIT,
        ]);

        $data = $server->getQueue($action, SettingsHelper::get(SettingsHelper::SEARCH_LIMIT));
        if ($server->getError()) {
            return $this->error(60750000, $server->getError() . ', ' . t('zapi', 'Cannot display item queue.'));
        }
        return $this->success($data);
    }
}
