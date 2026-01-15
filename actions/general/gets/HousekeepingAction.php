<?php


namespace app\customs\zapi\actions\general\gets;

use app\customs\zapi\actions\general\GeneralGetAction;

class HousekeepingAction extends GeneralGetAction
{
    /**
     * {@inheritDoc}
     */
    protected function getFormFields():array
    {
        return ['hk_events_mode', 'hk_events_trigger', 'hk_events_service', 'hk_events_internal',
            'hk_events_discovery', 'hk_events_autoreg', 'hk_services_mode', 'hk_services',
            'hk_sessions_mode', 'hk_sessions', 'hk_history_mode', 'hk_history_global', 'hk_history', 'hk_trends_mode',
            'hk_trends_global', 'hk_trends', 'compression_status', 'compress_older'
        ];
    }
}