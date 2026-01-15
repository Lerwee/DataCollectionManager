<?php


namespace app\customs\zapi\actions\general\gets;

use app\customs\zapi\actions\general\GeneralGetAction;
use app\customs\zapi\common\helpers\TimezoneHelper;


class GuiAction extends GeneralGetAction
{
    /**
     * {@inheritDoc}
     */
    protected function getFormFields():array
    {
        return [
            # 'default_lang',
            # 'default_theme',
            'default_timezone',
            'search_limit',
            'max_overview_table_size',
            'max_in_table',
            'server_check_interval',
            'work_period',
            'show_technical_errors',
            'history_period',
            'period_default',
            'max_period'
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getFormOptions():array
    {
        return [
            'timezone_list' => one2twoAssociative(TimezoneHelper::getList())
        ];
    }
}