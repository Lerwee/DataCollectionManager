<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\TimezoneHelper;
use app\customs\zapi\common\validators\ColorValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\TimePeriodValidator;
use app\customs\zapi\common\validators\TimeUnitValidator;
use app\customs\zapi\common\validators\UrlValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\models\Settings;


class SettingsForm extends BaseForm
{
    public static function getValidationRules(string $name): array
    {
        if ($name == 'Housekeeping' || $name == 'Audit') {
            $fields = static::getHousekeepingValidationRules();
            if ($name== 'Audit') {
                $fields += ['auditlog_enabled' => [Int32Validator::class, 'in' => [0,1]]];
            }
            return $fields;
        }


        $fields = [
			'default_theme' =>					[Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'in' => array_keys(getThemes())],
			'search_limit' =>					[Int32Validator::class, 'in' => [0, [1, 999999]]],
			'max_in_table' =>					[Int32Validator::class, 'in' => [0, [1, 99999]]],
			'server_check_interval' =>			[Int32Validator::class, 'in' => [0, SERVER_CHECK_INTERVAL]],
			'work_period' =>					[TimePeriodValidator::class, 'flags' => API_ALLOW_USER_MACRO],
			'show_technical_errors' =>			[Int32Validator::class, 'in' => [0,1]],
			'history_period' =>					[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [0, [SEC_PER_DAY, 7 * SEC_PER_DAY]]],
			'period_default' =>					[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_TIME_UNIT_WITH_YEAR, 'in' => [0, [SEC_PER_MIN, 10 * SEC_PER_YEAR]]],
			'max_period' =>						[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_TIME_UNIT_WITH_YEAR, 'in' => [0, [SEC_PER_YEAR, 10 * SEC_PER_YEAR]]],
			'severity_color_0' =>				[ColorValidator::class, 'flags' => API_NOT_EMPTY],
			'severity_color_1' =>				[ColorValidator::class, 'flags' => API_NOT_EMPTY],
			'severity_color_2' =>				[ColorValidator::class, 'flags' => API_NOT_EMPTY],
			'severity_color_3' =>				[ColorValidator::class, 'flags' => API_NOT_EMPTY],
			'severity_color_4' =>				[ColorValidator::class, 'flags' => API_NOT_EMPTY],
			'severity_color_5' =>				[ColorValidator::class, 'flags' => API_NOT_EMPTY],
			'severity_name_0' =>				[Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'severity_name_0')],
			'severity_name_1' =>				[Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'severity_name_1')],
			'severity_name_2' =>				[Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'severity_name_2')],
			'severity_name_3' =>				[Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'severity_name_3')],
			'severity_name_4' =>				[Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'severity_name_4')],
			'severity_name_5' =>				[Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'severity_name_5')],
			'custom_color' =>					[Int32Validator::class, 'in' => [EVENT_CUSTOM_COLOR_DISABLED, EVENT_CUSTOM_COLOR_ENABLED]],
			'ok_period' =>						[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [0,[0, SEC_PER_DAY]]],
			'blink_period' =>					[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [0,[0, SEC_PER_DAY]]],
			'problem_unack_color' =>			[ColorValidator::class, 'flags' => API_NOT_EMPTY],
			'problem_ack_color' =>				[ColorValidator::class, 'flags' => API_NOT_EMPTY],
			'ok_unack_color' =>					[ColorValidator::class, 'flags' => API_NOT_EMPTY],
			'ok_ack_color' =>					[ColorValidator::class, 'flags' => API_NOT_EMPTY],
			'problem_unack_style' =>			[Int32Validator::class, 'in' => [0,1]],
			'problem_ack_style' =>				[Int32Validator::class, 'in' => [0,1]],
			'ok_unack_style' =>					[Int32Validator::class, 'in' => [0,1]],
			'ok_ack_style' =>					[Int32Validator::class, 'in' => [0,1]],
			'discovery_groupid' =>				[IdValidator::class],
			'default_inventory_mode' =>			[Int32Validator::class, 'in' => [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]],
			'alert_usrgrpid' =>					[IdValidator::class],
			'snmptrap_logging' =>				[Int32Validator::class, 'in' => [0,1]],
			'default_lang' =>					[Utf8StringValidator::class, 'in' => array_keys(getLocales())],
			'default_timezone' =>				[Utf8StringValidator::class, 'in' => [PRS_DEFAULT_TIMEZONE, array_keys(TimezoneHelper::getList())]],
			'login_attempts' =>					[Int32Validator::class, 'in' => '1:32'],
			'login_block' =>					[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [0,[0, SEC_PER_HOUR]]],
			'validate_uri_schemes' =>			[Int32Validator::class, 'in' => [0,1]],
			'uri_valid_schemes' =>				[Utf8StringValidator::class, 'length' => DB::getFieldLength('config', 'uri_valid_schemes')],
			'x_frame_options' =>				[Utf8StringValidator::class, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'x_frame_options')],
			'iframe_sandboxing_enabled' =>		[Int32Validator::class, 'in' => [0,1]],
			'iframe_sandboxing_exceptions' =>	[Utf8StringValidator::class, 'length' => DB::getFieldLength('config', 'iframe_sandboxing_exceptions')],
			'max_overview_table_size' =>		[Int32Validator::class, 'in' => [0, [5, 999999]]],
			'connect_timeout' =>				[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [0, [1, 300]]],
			'socket_timeout' =>					[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [0, [1, 300]]],
			'media_type_test_timeout' =>		[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [0, [1, 300]]],
			'script_timeout' =>					[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [0, [1, 300]]],
			'item_test_timeout' =>				[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [0, [1, 300]]],
			'url' =>							[Utf8StringValidator::class, 'length' => DB::getFieldLength('config', 'url')],
			'report_test_timeout' =>			[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [0, [1, 300]]],
			'auditlog_enabled' =>				[Int32Validator::class, 'in' => [0,1]],
			'geomaps_tile_provider' =>			[Utf8StringValidator::class, 'in' => [0, array_keys(getTileProviders())]],
			'geomaps_tile_url' =>				[UrlValidator::class, 'length' => DB::getFieldLength('config', 'geomaps_tile_url')],
			'geomaps_max_zoom' =>				[Int32Validator::class, 'in' => [0,[0,PRS_GEOMAP_MAX_ZOOM]]],
			'geomaps_attribution' =>			[Utf8StringValidator::class, 'length' => DB::getFieldLength('config', 'geomaps_attribution')],
			'vault_provider' =>					[Int32Validator::class, 'flags' => API_NOT_EMPTY, 'in' => [PRS_VAULT_TYPE_HASHICORP , PRS_VAULT_TYPE_CYBERARK]]
		];

        return $fields;
    }

    public static function getHousekeepingValidationRules()
    {
        $fields = [
            'hk_events_mode' =>         [Int32Validator::class, 'in' => [0,1]],
			'hk_events_trigger' =>		[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [[SEC_PER_DAY, 25 * SEC_PER_YEAR]], 'length' => DB::getFieldLength('config', 'hk_events_trigger')],
			'hk_events_service' =>		[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [[SEC_PER_DAY, 25 * SEC_PER_YEAR]], 'length' => DB::getFieldLength('config', 'hk_events_service')],
			'hk_events_internal' =>		[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [[SEC_PER_DAY, 25 * SEC_PER_YEAR]], 'length' => DB::getFieldLength('config', 'hk_events_internal')],
			'hk_events_discovery' =>	[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [[SEC_PER_DAY, 25 * SEC_PER_YEAR]], 'length' => DB::getFieldLength('config', 'hk_events_discovery')],
			'hk_events_autoreg' =>		[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [[SEC_PER_DAY, 25 * SEC_PER_YEAR]], 'length' => DB::getFieldLength('config', 'hk_events_autoreg')],
			'hk_services_mode' =>		[Int32Validator::class, 'in' => [0,1]],
			'hk_services' =>			[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [[SEC_PER_DAY, 25 * SEC_PER_YEAR]], 'length' => DB::getFieldLength('config', 'hk_services')],
			'hk_audit_mode' =>			[Int32Validator::class, 'in' => [0,1]],
			'hk_audit' =>				[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [[SEC_PER_DAY, 25 * SEC_PER_YEAR]], 'length' => DB::getFieldLength('config', 'hk_audit')],
			'hk_sessions_mode' =>		[Int32Validator::class, 'in' => [0,1]],
			'hk_sessions' =>			[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [[SEC_PER_DAY, 25 * SEC_PER_YEAR]], 'length' => DB::getFieldLength('config', 'hk_sessions')],
			'hk_history_mode' =>		[Int32Validator::class, 'in' => [0,1]],
			'hk_history_global' =>		[Int32Validator::class, 'in' => [0,1]],
			'hk_history' =>				[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [0,[SEC_PER_HOUR, 25 * SEC_PER_YEAR]], 'length' => DB::getFieldLength('config', 'hk_history')],
			'hk_trends_mode' =>			[Int32Validator::class, 'in' => [0,1]],
			'hk_trends_global' =>		[Int32Validator::class, 'in' => [0,1]],
			'hk_trends' =>				[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [0,[SEC_PER_DAY, 25 * SEC_PER_YEAR]], 'length' => DB::getFieldLength('config', 'hk_trends')],
			'compression_status' =>		[Int32Validator::class, 'in' => [0,1]],
			'compress_older' =>			[TimeUnitValidator::class, 'flags' => API_NOT_EMPTY, 'in' => [[SEC_PER_DAY, 25 * SEC_PER_YEAR]], 'length' => DB::getFieldLength('config', 'compress_older')]
        ];

        return $fields;
    }
}