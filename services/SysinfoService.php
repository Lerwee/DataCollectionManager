<?php

namespace app\customs\zabbix\services;

use app\common\base\BaseService;
use app\common\helpers\ZabbixHelper;
use app\modules\libzbx\components\ZabbixServer;
use app\modules\libzbx\models\Classification;
use Yii;

class SysinfoService extends BaseService
{
    /**
     * delegate
     * @return array
     */
    public function delegate()
    {
        $server = new ZabbixServer();
        $summary = $this->getSummary($server);

        return [
            'hosts' => $this->prepareHostSummary($summary),
            'items' => $this->prepareItemSummary($summary),
            'triggers' => $this->prepareTriggerSummary($summary),
            'server' => $this->prepareServerSummary($server),
            'performance' => $this->prepareLatestSummary($summary),
        ];
    }

    public function diagnose()
    {
    }

    /**
     * format host data
     * @param array $summary
     * @return array
     */
    protected function prepareHostSummary($summary)
    {
        $category = $this->module->id ?? 'zbx';
        return [
            'label'    => Yii::t($category, 'Host'),
            'value'    => $summary['hosts_count'],
            'status'   => [
                [
                    'label' => Yii::t($category, 'enabled'),
                    'value' => $summary['hosts_count_monitored'],
                ],
                [
                    'label' => Yii::t($category, 'disabled'),
                    'value' => $summary['hosts_count_not_monitored'],
                ],
                [
                    'label' => Yii::t($category, 'Template'),
                    'value' => $summary['hosts_count_template'],
                ],
            ],
            'classifications' => Classification::getObjectAttributeLabels(false),
        ];
    }

    /**
     * format item data
     * @param array $summary
     * @return array
     */
    protected function prepareItemSummary($summary)
    {
        $category = $this->module->id ?? 'zbx';
        return [
            'label'  => Yii::t($category, 'Number of items'),
            'value'  => $summary['items_count'],
            'status' => [
                [
                    'label' => Yii::t($category, 'enabled'),
                    'value' => $summary['items_count_monitored'],
                ],
                [
                    'label' => Yii::t($category, 'disabled'),
                    'value' => $summary['items_count_disabled'],
                ],
                [
                    'label' => Yii::t($category, 'not supported'),
                    'value' => $summary['items_count_not_supported'],
                ],
            ],
        ];
    }

    /**
     * format trigger data
     * @param array $summary
     * @return array
     */
    protected function prepareTriggerSummary($summary)
    {
        $category = $this->module->id ?? 'zbx';
        return [
            'label'  => Yii::t($category, 'Number of triggers'),
            'value'  => $summary['triggers_count'],
            'status' => [
                [
                    'label' => Yii::t($category, 'disabled'),
                    'value' => $summary['triggers_count_enabled'],
                ],
                [
                    'label' => Yii::t($category, 'enabled'),
                    'value' => $summary['triggers_count_disabled'],
                ],
            ],
            'state'  => [
                [
                    'label' => Yii::t($category, 'problem'),
                    'value' => $summary['triggers_count_on'],
                ],
                [
                    'label' => Yii::t($category, 'ok'),
                    'value' => $summary['triggers_count_off'],
                ],
            ],
        ];
    }

    /**
     * format server data
     *
     * @param ZabbixServer $server
     * @return array
     */
    protected function prepareServerSummary(ZabbixServer $server)
    {
        $category = $this->module->id ?? 'zbx';
        return [
            'label'  => Yii::t($category, 'Zabbix Server'),
            'value'  => $server->port ? $server->host . ':' . $server->port : $server->host,
            'status' => $server->isRunning,
            'version' => ZabbixHelper::getVersion(false),
        ];
    }

    /**
     * format latest data
     *
     * @param array $summary
     * @return array
     */
    protected function prepareLatestSummary($summary)
    {
        $category = $this->module->id ?? 'zbx';
        return [
            'label' => Yii::t($category, 'Required server performance, new values per second'),
            'value' => round($summary['vps_total'], 2),
        ];
    }

    /**
     * Returns summary
     *
     * @param ZabbixServer $server
     * @param bool $raw
     * @return array
     */
    private function getSummary(ZabbixServer $server, $raw = false)
    {
        $status = $server->getStatus();
        if ($raw) {
            return $status;
        }

        $data = [
            'is_running'                => $server->isRunning,
            'has_status'                => (bool) $status,
            'items_count'               => 0,
            'items_count_monitored'     => 0,
            'items_count_disabled'      => 0,
            'items_count_not_supported' => 0,
            'hosts_count'               => 0,
            'hosts_count_monitored'     => 0,
            'hosts_count_not_monitored' => 0,
            'hosts_count_template'      => 0,
            'triggers_count'            => 0,
            'triggers_count_enabled'    => 0,
            'triggers_count_disabled'   => 0,
            'triggers_count_off'        => 0,
            'triggers_count_on'         => 0,
            'users_count'               => 0,
            'users_online'              => 0,
            'vps_total'                 => 0,
        ];

        if (empty($status)) {
            return $data;
        }

        // hosts
        foreach ($status['template stats'] as $stats) {
            $data['hosts_count_template'] += $stats['count'];
        }
        foreach ($status['host stats'] as $stats) {
            if ($stats['attributes']['proxyid'] == 0) {
                switch ($stats['attributes']['status']) {
                    case 0: // HOST_STATUS_MONITORED
                        $data['hosts_count_monitored'] += $stats['count'];
                        break;

                    case 1: // HOST_STATUS_NOT_MONITORED
                        $data['hosts_count_not_monitored'] += $stats['count'];
                        break;
                }
            }
        }
        $data['hosts_count'] = $data['hosts_count_monitored'] + $data['hosts_count_not_monitored']
             + $data['hosts_count_template'];

        // items
        foreach ($status['item stats'] as $stats) {
            if ($stats['attributes']['proxyid'] == 0) {
                switch ($stats['attributes']['status']) {
                    case 0: // ITEM_STATUS_ACTIVE
                        if (array_key_exists('state', $stats['attributes'])) {
                            switch ($stats['attributes']['state']) {
                                case 0: // ITEM_STATE_NORMAL
                                    $data['items_count_monitored'] += $stats['count'];
                                    break;

                                case 1: // ITEM_STATE_NOTSUPPORTED
                                    $data['items_count_not_supported'] += $stats['count'];
                                    break;
                            }
                        }
                        break;

                    case 1: // ITEM_STATUS_DISABLED
                        $data['items_count_disabled'] += $stats['count'];
                        break;
                }
            }
        }
        $data['items_count'] = $data['items_count_monitored'] + $data['items_count_disabled']
             + $data['items_count_not_supported'];

        // triggers
        foreach ($status['trigger stats'] as $stats) {
            switch ($stats['attributes']['status']) {
                case 0: // TRIGGER_STATUS_ENABLED
                    if (array_key_exists('value', $stats['attributes'])) {
                        switch ($stats['attributes']['value']) {
                            case 0: // TRIGGER_VALUE_FALSE
                                $data['triggers_count_off'] += $stats['count'];
                                break;

                            case 1: // TRIGGER_VALUE_TRUE
                                $data['triggers_count_on'] += $stats['count'];
                                break;
                        }
                    }
                    break;

                case 1: // TRIGGER_STATUS_DISABLED
                    $data['triggers_count_disabled'] += $stats['count'];
                    break;
            }
        }
        $data['triggers_count_enabled'] = $data['triggers_count_off'] + $data['triggers_count_on'];
        $data['triggers_count']         = $data['triggers_count_enabled'] + $data['triggers_count_disabled'];

        // users
        foreach ($status['user stats'] as $stats) {
            switch ($stats['attributes']['status']) {
                case 0: // ZBX_SESSION_ACTIVE
                    $data['users_online'] += $stats['count'];
                    break;

                case 1: // ZBX_SESSION_PASSIVE
                    $data['users_count'] += $stats['count'];
                    break;
            }
        }
        $data['users_count'] += $data['users_online'];

        // performance
        if (array_key_exists('required performance', $status)) {
            $data['vps_total'] = 0;

            foreach ($status['required performance'] as $stats) {
                if ($stats['attributes']['proxyid'] == 0) {
                    $data['vps_total'] += $stats['count'];
                }
            }
        }
        return $data;
    }
}
