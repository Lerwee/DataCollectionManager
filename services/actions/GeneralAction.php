<?php

namespace app\customs\zabbix\services\actions;

use app\common\helpers\ZabbixHelper;
use yii\base\Action;

class GeneralAction extends Action
{
    /**
     * run action
     */
    public function run()
    {
        $this->controller->hackerZabbix();
        return $this->controller->render('/common/normal', [
            'file' => $this->realAction()
        ]);
    }

    /**
     * accept actions
     *
     * Note: zabbix 5.x +
     *
     * @return array
     */
    public static function acceptActions()
    {
        return [
            'gui.edit', // 界面设置
            'autoreg.edit', // 自动注册
            'housekeeping.edit', // 管家
            'image.list', // 图片
            'iconmap.list', // 图标映射
            'regex.list', // 正则表达式
            'macros.edit', // 宏
            'valuemap.list', // 值映射
            'workingtime.edit', // 工作时间
            'trigseverity.edit', // 触发器设置
            'trigdisplay.edit', // 触发器显示选项
            'module.list', // Modules
            'miscconfig.edit', // 其它
        ];
    }

    /**
     * real action
     *
     * @return string
     */
    protected function realAction()
    {
        if (5 <= ZabbixHelper::getVersion()) {
            $_GET['action']     = $this->id;
            $_GET['fullscreen'] = 1;
            $action             = 'zabbix';
        } else {
            $mappings = $this->mappings();
            return $mappings[$this->id] ?? $this->id;
        }
        return $action;
    }

    /**
     * mappings
     *
     * @return array
     */
    protected function mappings()
    {
        return [
            'gui.edit' => 'adm.gui', // 界面设置
            'autoreg.edit' => 'autoreg.edit', // 自动注册
            'housekeeping.edit' => 'adm.housekeeper', // 管家
            'image.list' => 'adm.images', // 图片
            'iconmap.list' => 'adm.iconmapping', // 图标映射
            'regex.list' => 'adm.regexps', // 正则表达式
            'macros.edit' => 'adm.macros', // 宏
            'valuemap.list' => 'adm.valuemapping', // 值映射
            'workingtime.edit' => 'adm.workingtime', // 工作时间
            'trigseverity.edit' => 'adm.triggerseverities', // 触发器设置
            'trigdisplay.edit' => 'adm.triggerdisplayoptions', // 触发器显示选项
            'module.list' => 'module.list', // Modules
            'miscconfig.edit' => 'adm.other', // 其它
        ];
    }
}
