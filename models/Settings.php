<?php
namespace app\customs\zapi\models;

use yii\db\Query;


/**
 * Class containing methods for operations with the main part of administration settings.
 */
class Settings
{
    /**
     * Get the fields of the Settings API object that are used by parts of the UI where authentication is not required.
     */
    public static function getPublic(): array
    {
        $output_fields = ['default_theme', 'show_technical_errors', 'severity_color_0', 'severity_color_1',
            'severity_color_2', 'severity_color_3', 'severity_color_4', 'severity_color_5', 'custom_color',
            'problem_unack_color', 'problem_ack_color', 'ok_unack_color', 'ok_ack_color', 'default_lang',
            'default_timezone', 'x_frame_options', 'auditlog_enabled'
        ];
        return (new Query())->from('config')
            ->select($output_fields)->one();
    }

    /**
     * Get the private settings used in UI.
     */
    public static function getPrivate(): array
    {
        $output_fields = ['session_key', 'dbversion_status', 'server_status'];

        $db_settings = (new Query())->from('config')
            ->select($output_fields)->one();

        $db_settings['dbversion_status'] = json_decode($db_settings['dbversion_status'], true) ?: [];

        return $db_settings;
    }
}
