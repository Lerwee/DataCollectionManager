<?php

namespace app\customs\zabbix\controllers;

class TemplatesController extends Controller
{
    /**
     * 模板管理
     * @return Response
     */
    public function actionTemplates()
    {
        return $this->render('/common/normal', [
            'file' => $this->id
        ]);
    }
}
