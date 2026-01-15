<?php

namespace app\customs\zapi\controllers;

use app\common\base\BaseController;
use app\customs\zapi\services\GeneralSettingService;
use Yii;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\web\Response;

/**
 * Class GeneralSettingController
 * @package app\customs\zapi\controllers
 */
class GeneralSettingController extends BaseController
{
    public function actions()
    {
        $files = FileHelper::findFiles(Yii::getAlias('@customs/zapi/actions/general/gets', false), [
            'only' => ['*Action.php'],
            'recursive' => false
        ]);
        
        $actions = [];
        if (empty($files)) {
            return $actions;
        }

        foreach($files as $file) {
            $name = basename($file, 'Action.php');
            $actionName = Inflector::camel2id($name);
            $className = 'app\customs\zapi\actions\general\gets\\' . basename($file, '.php');
            # get
            $actions["get-{$actionName}"] = $className;
            # set
            $setClassName = str_replace('\gets', '\sets', $className);
            $actions["set-{$actionName}"] = [
                'class' => class_exists($setClassName) ? $setClassName : \app\customs\zapi\actions\general\GeneralSetAction::class,
                'name' => $name
            ];
            $this->restfulActions[] = "set-{$actionName}";
        }
        return $actions;
    }
}
