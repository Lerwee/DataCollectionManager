<?php

namespace app\customs\zabbix\services\actions;

use app\common\helpers\ZabbixHelper;
use yii\base\Action;

class RepeatAction extends Action
{
    /**
     * run action
     *
     * 适用控制器
     * - `ActionconfController`
     * - `DiscoveryconfController`
     * - `HostsController`
     * - `TemplatesController`
     */
    public function run()
    {
        if ($this->controller->hasProperty('getParams') && ($getParams = $this->controller->getParams)) {
            $getParams = array_merge($getParams, $_GET);
            foreach ($getParams as $property => $value) {
                $_GET[$property] = $value;
            }
        }

        $file = $this->controller->id;

        $zbxVer = ZabbixHelper::getVersion();
        if (6.0 <= $zbxVer && $zbxVer < 7.0) {
            if ($this->id == 'hosts') {
                $_GET['action'] = "host.list";
                $file = 'zabbix';
            } elseif ($this->id == 'discoveryconf') {
                $_GET['action'] = "discovery.list";
                $file = 'zabbix';
            } elseif ($this->id == 'queue') {
                $_GET['action'] = "queue.details";
                $file = 'zabbix';
            }
        } elseif(7.0 <= $zbxVer) {
            if ($this->id == 'templates') {
                $_GET['action'] = "template.list";
                $file = 'zabbix';
            } elseif ($this->id == 'hosts') {
                $_GET['action'] = "host.list";
                $file = 'zabbix';
            } elseif ($this->id == 'discoveryconf') {
                $_GET['action'] = "discovery.list";
                $file = 'zabbix';
            } elseif ($this->id == 'queue') {
                $_GET['action'] = "queue.details";
                $file = 'zabbix';
            }
        }

        return $this->controller->render('/common/normal', [
            'file' => $file
        ]);
    }
}
