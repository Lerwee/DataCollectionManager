<?php

namespace app\customs\zapi\actions\general;

use app\common\base\BaseAction;
use app\customs\zapi\services\GeneralSettingService;
use Yii;
use yii\web\Response;

/**
 * 常规设置
 */
class GeneralSetAction extends BaseAction
{
    public $name;
    
    public function run(): Response
    {
        $params = Yii::$app->request->post();
        $result = GeneralSettingService::instance()->update($params, $this->name);
        return $this->autoReturn($result);
    }
}