<?php

namespace app\customs\zapi\actions\general\gets;

use app\customs\zapi\actions\general\GeneralGetAction;

class AuditAction extends GeneralGetAction
{
    /**
     * {@inheritDoc}
     */
    protected function getFormFields():array
    {
        return [
            'auditlog_enabled',
            'hk_audit_mode',
            'hk_audit',
        ];
    }
}