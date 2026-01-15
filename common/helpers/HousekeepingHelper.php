<?php

namespace app\customs\zapi\common\helpers;

use app\modules\libzbx\models\zbx\Config;
use yii\base\Exception;

class HousekeepingHelper
{
    public const COMPRESS_OLDER = 'compress_older';
    public const COMPRESSION_STATUS = 'compression_status';
    public const DB_EXTENSION = 'db_extension';
    public const HK_AUDIT = 'hk_audit';
    public const HK_AUDIT_MODE = 'hk_audit_mode';
    public const HK_EVENTS_AUTOREG = 'hk_events_autoreg';
    public const HK_EVENTS_DISCOVERY = 'hk_events_discovery';
    public const HK_EVENTS_INTERNAL = 'hk_events_internal';
    public const HK_EVENTS_MODE = 'hk_events_mode';
    public const HK_EVENTS_TRIGGER = 'hk_events_trigger';
    public const HK_EVENTS_SERVICE = 'hk_events_service';
    public const HK_HISTORY = 'hk_history';
    public const HK_HISTORY_GLOBAL = 'hk_history_global';
    public const HK_HISTORY_MODE = 'hk_history_mode';
    public const HK_SERVICES = 'hk_services';
    public const HK_SERVICES_MODE = 'hk_services_mode';
    public const HK_SESSIONS = 'hk_sessions';
    public const HK_SESSIONS_MODE = 'hk_sessions_mode';
    public const HK_TRENDS = 'hk_trends';
    public const HK_TRENDS_GLOBAL = 'hk_trends_global';
    public const HK_TRENDS_MODE = 'hk_trends_mode';

    public const OVERRIDE_NEEDED_HISTORY =    'hk_needs_override_history';
    public const OVERRIDE_NEEDED_TRENDS =    'hk_needs_override_trends';

    private const DBVERSION_COMPRESSED_CHUNKS_HISTORY = 'compressed_chunks_history';
    private const DBVERSION_COMPRESSED_CHUNKS_TRENDS = 'compressed_chunks_trends';

    protected static $params = [];

    /**
     * Get the value of the given Housekeeping API object's field.
     *
     * @param string $field
     *
     * @throws Exception
     *
     * @return string
     */
    public static function get(string $field): string
    {
        if (empty(self::$params)) {
            self::$params = Config::find()
                ->select([
                    'hk_events_mode', 'hk_events_trigger', 'hk_events_service', 'hk_events_internal',
                    'hk_events_discovery', 'hk_events_autoreg', 'hk_services_mode', 'hk_services', 'hk_audit_mode',
                    'hk_audit', 'hk_sessions_mode', 'hk_sessions', 'hk_history_mode', 'hk_history_global', 'hk_history',
                    'hk_trends_mode', 'hk_trends_global', 'hk_trends', 'db_extension', 'compression_status',
                    'compress_older'
                ])
                ->asArray()->limit(1)->one();
        }

        if (self::$params === null) {
            throw new Exception(t('zapi', 'Unable to load housekeeping API parameters.'));
        }

        return self::$params[$field];
    }
}
