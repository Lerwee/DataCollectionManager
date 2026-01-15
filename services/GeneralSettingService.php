<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\helpers\SettingsHelper;
use app\customs\zapi\common\helpers\TimezoneHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\forms\SettingsForm;
use app\customs\zapi\models\Settings;
use app\customs\zapi\services\assist\BaseAssist;
use Yii;
use yii\db\Query;

class GeneralSettingService extends BaseAssist
{
    /**
	 * @param array $settings
	 *
	 * @return Result
	 */
	public function update(array $settings, string $name): Result 
    {
        try {
            $db_settings = $this->validateUpdate($settings, $name);
    
            $upd_config = DB::getUpdatedValues('config', $settings, $db_settings);
    
            $detail = [];
            if ($upd_config) {
                DB::update('config', [
                    'values' => $upd_config,
                    'where' => ['configid' => $db_settings['configid']]
                ]);

                foreach($upd_config as $key => $value) {
                    $detail[] = audit_detail($key, $db_settings[$key], $value);
                }
            }
            $msg = Yii::t('zapi', 'General settings updated successfully');
            if ($name) {
                $msg = Yii::t('zapi', 'General settings "{label}" updated successfully', ['label' => Yii::t('zapi', "{$name} setting")]);
            }

            $this->auditUpdate(RESOURCE_ZAPI, Yii::t('zapi', 'General Settings'), $msg, $detail);
    
            return $this->success(array_keys($settings), $msg);

        } catch (\Exception $e) {
            return $this->errorException($e, 60750101);
        }
	}

    /**
     * 验证更新
     *
     * @param  array  $settings
     * @param  string $name
     * @return array
     */
    protected function validateUpdate(array &$settings, string $name): array 
    {
        $rules = SettingsForm::getValidationRules($name);
        # pd($rules, $settings);
        if (!ValidateHelper::validateObject($settings, $rules, [], $error)) {
            self::exception(60750001, $error);
        }
        if (array_key_exists('discovery_groupid', $settings)) {
            $db_hstgrp_exists = GroupHelper::getHostGroups([
				'countOutput' => true,
				'groupids' => $settings['discovery_groupid'],
				'filter' => ['flags' => PRS_FLAG_DISCOVERY_NORMAL],
				'editable' => true
			]);


			if (!$db_hstgrp_exists) {
                self::exception(60750001, t('zapi', 'Host group with ID "{id}" is not available.', ['id' => $settings['discovery_groupid']]));
			}
		}

		if (array_key_exists('alert_usrgrpid', $settings)) {
			unset($settings['alert_usrgrpid']);
		}

		if (array_key_exists('geomaps_tile_provider', $settings) && $settings['geomaps_tile_provider'] !== '') {
			$settings['geomaps_tile_url'] = DB::getDefault('config', 'geomaps_tile_url');
			$settings['geomaps_max_zoom'] = DB::getDefault('config', 'geomaps_max_zoom');
			$settings['geomaps_attribution'] = DB::getDefault('config', 'geomaps_attribution');
		}

		$period_default_updated = array_key_exists('period_default', $settings);
		$max_period_updated = array_key_exists('max_period', $settings);
		if ($period_default_updated || $max_period_updated) {
			$period_default = $period_default_updated
				? timeUnitToSeconds($settings['period_default'], true)
				: timeUnitToSeconds(SettingsHelper::get(SettingsHelper::PERIOD_DEFAULT), true);

			$max_period = $max_period_updated
				? timeUnitToSeconds($settings['max_period'], true)
				: timeUnitToSeconds(SettingsHelper::get(SettingsHelper::MAX_PERIOD), true);

			if ($period_default > $max_period) {
				$field = 'period_default';
				$message = t('zapi', 'time filter default period exceeds the max period');

				if (!$period_default_updated) {
					$field = 'max_period';
					$message = t('zapi', 'max period is less than time filter default period');
				}

				$error = t('zapi', 'Incorrect value for field "{attribute}", {error}.', ['attribute' => $field, 'error' => $message]);
                self::exception(60750001, t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                    'attribute' => $field,
                    'error' => $message
                ]));
 
                self::exception(60750001, $error);
			}
		}

        return $this->getConfig($name) ?: [];
    }

    protected function getConfig(string $name)
    {
        if ($name == 'Audit' || $name == 'Housekeeping') {
            $output_fields = ['hk_events_mode', 'hk_events_trigger', 'hk_events_service', 'hk_events_internal',
                'hk_events_discovery', 'hk_events_autoreg', 'hk_services_mode', 'hk_services', 'hk_audit_mode', 'hk_audit',
                'hk_sessions_mode', 'hk_sessions', 'hk_history_mode', 'hk_history_global', 'hk_history', 'hk_trends_mode',
                'hk_trends_global', 'hk_trends', 'db_extension', 'compression_status', 'compress_older'
            ];
            if ($name == 'Audit') {
                $output_fields[] = 'auditlog_enabled';
            }
        } else {
            $output_fields = [
                'default_theme', 'search_limit', 'max_in_table', 'server_check_interval', 'work_period',
                'show_technical_errors', 'history_period', 'period_default', 'max_period', 'severity_color_0',
                'severity_color_1', 'severity_color_2', 'severity_color_3', 'severity_color_4', 'severity_color_5',
                'severity_name_0', 'severity_name_1', 'severity_name_2', 'severity_name_3', 'severity_name_4',
                'severity_name_5', 'custom_color', 'ok_period', 'blink_period', 'problem_unack_color', 'problem_ack_color',
                'ok_unack_color', 'ok_ack_color', 'problem_unack_style', 'problem_ack_style', 'ok_unack_style',
                'ok_ack_style', 'discovery_groupid', 'default_inventory_mode', 'alert_usrgrpid',
                'snmptrap_logging', 'default_lang', 'default_timezone', 'login_attempts', 'login_block', 'validate_uri_schemes',
                'uri_valid_schemes', 'x_frame_options', 'iframe_sandboxing_enabled', 'iframe_sandboxing_exceptions',
                'max_overview_table_size', 'connect_timeout', 'socket_timeout', 'media_type_test_timeout', 'script_timeout',
                'item_test_timeout', 'url', 'report_test_timeout', 'auditlog_enabled', 'ha_failover_delay',
                'geomaps_tile_provider', 'geomaps_tile_url', 'geomaps_max_zoom', 'geomaps_attribution', 'vault_provider'
            ];
        }
        return (new Query())->from('config')
            ->select($output_fields)->addSelect('configid')->one();
    }
}
