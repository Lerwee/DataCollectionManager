<?php

namespace app\customs\zabbix\controllers;

use app\customs\zabbix\services\actions\GeneralAction;
use Yii;

class GeneralController extends Controller
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
        
        $action = basename(Yii::$app->request->pathinfo);
        if (in_array($action, GeneralAction::acceptActions())) {
            $actions[$action]['class'] = GeneralAction::class;
        } else {
            $actions['gui.edit'] = GeneralAction::class;
        }
        return $actions;
    }

    /**
     * {@inheritDoc}
     */
    public $defaultAction = 'gui.edit';
}
