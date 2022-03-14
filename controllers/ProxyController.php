<?php

namespace app\customs\zabbix\controllers;

use app\customs\zabbix\services\actions\ListAction;

class ProxyController extends Controller
{
    /**
     * {@inheritDoc}
     */
    public function actions()
    {
        $actions = parent::actions();
        if (isset($actions[$this->id])) {
            unset($actions[$this->id]);
        }
        $actions['list'] = ListAction::class;
        return $actions;
    }
}
