<?php
namespace app\customs\zapi\models\search;

use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use yii\db\Query;

/**
 * Class SettingSearch
 * @package app\customs\zapi\models\search
 */
class SettingSearch extends BaseSearch
{
    /**
     * @var array
     */
    private $output_fields = ['default_theme', 'search_limit', 'max_in_table', 'server_check_interval', 'work_period',
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

    /**
     * searches
     *
     * @param  array $params
     * @return ActiveDataProvider
     */
    public function search(array $params)
    {
        $api_input_rules = ['type' => API_OBJECT, 'fields' => [
            'output' =>	['type' => API_OUTPUT, 'in' => implode(',', $this->output_fields), 'default' => API_OUTPUT_EXTEND]
        ]];

        if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
            self::exception(PRS_API_ERROR_PARAMETERS, $error);
        }

        if ($options['output'] === API_OUTPUT_EXTEND) {
            $options['output'] = $this->output_fields;
        }

        $db_settings = [];

        $result = DBselect($this->createSelectQuery($this->tableName(), $options));
        while ($row = DBfetch($result)) {
            $db_settings[] = $row;
        }
        $db_settings = $this->unsetExtraFields($db_settings, ['configid'], []);

        return $db_settings[0];
    }
}
