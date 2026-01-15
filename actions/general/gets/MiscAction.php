<?php

namespace app\customs\zapi\actions\general\gets;

use app\customs\zapi\actions\general\GeneralGetAction;
use app\customs\zapi\common\helpers\GroupHelper;

class MiscAction extends GeneralGetAction
{
    /**
     * {@inheritDoc}
     */
    protected function getFormFields():array
    {
        return [
            # 'url',
            'discovery_groupid',
            # 'default_inventory_mode',
            # 'snmptrap_logging',
            # 'login_attempts',
            # 'login_block',
            # 'vault_provider',
            # 'validate_uri_schemes',
            # 'uri_valid_schemes',
            # 'x_frame_header_enabled',
            # 'x_frame_options',
            # 'iframe_sandboxing_enabled',
            # 'iframe_sandboxing_exceptions',
            'socket_timeout',
            'connect_timeout',
            'media_type_test_timeout',
            'script_timeout',
            'item_test_timeout',
            'report_test_timeout'
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getFormOptions():array
    {
        return [
            'groups' => GroupHelper::getHostGroups([
                'output' => ['groupid', 'name'],
                'sortfield' => 'name'
            ])
        ];
    }
}