<?php declare(strict_types = 0);
namespace app\customs\zapi\common\helpers;

class CSeverityHelper {

	/**
	 * Get severity name by given state and configuration.
	 *
	 * @param int $severity
	 *
	 * @return string
	 */
	public static function getName(int $severity): string {
		switch ($severity) {
			case PRS_SEVERITY_OK:
				return t('zapi', 'OK');
			case TRIGGER_SEVERITY_NOT_CLASSIFIED:
				return t('zapi', CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_0));
			case TRIGGER_SEVERITY_INFORMATION:
				return t('zapi', CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_1));
			case TRIGGER_SEVERITY_WARNING:
				return t('zapi', CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_2));
			case TRIGGER_SEVERITY_AVERAGE:
				return t('zapi', CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_3));
			case TRIGGER_SEVERITY_HIGH:
				return t('zapi', CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_4));
			case TRIGGER_SEVERITY_DISASTER:
				return t('zapi', CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_5));
			default:
				return t('zapi', 'Unknown');
		}
	}

	/**
	 * Get severity css style name.
	 *
	 * @param int|null $severity
	 * @param bool     $type
	 *
	 * @return string|null
	 */
	public static function getStyle(?int $severity, bool $type = true): ?string {
		if (!$type) {
			return PRS_STYLE_NORMAL_BG;
		}

		switch ($severity) {
			case PRS_SEVERITY_OK:
				return PRS_STYLE_NORMAL_BG;
			case TRIGGER_SEVERITY_DISASTER:
				return PRS_STYLE_DISASTER_BG;
			case TRIGGER_SEVERITY_HIGH:
				return PRS_STYLE_HIGH_BG;
			case TRIGGER_SEVERITY_AVERAGE:
				return PRS_STYLE_AVERAGE_BG;
			case TRIGGER_SEVERITY_WARNING:
				return PRS_STYLE_WARNING_BG;
			case TRIGGER_SEVERITY_INFORMATION:
				return PRS_STYLE_INFO_BG;
			case TRIGGER_SEVERITY_NOT_CLASSIFIED:
				return PRS_STYLE_NA_BG;
			default:
				return null;
		}
	}

	/**
	 * Get severity status css style name.
	 *
	 * @param int $severity
	 *
	 * @return string|null
	 */
	public static function getStatusStyle(int $severity): ?string {
		switch ($severity) {
			case TRIGGER_SEVERITY_DISASTER:
				return PRS_STYLE_STATUS_DISASTER_BG;
			case TRIGGER_SEVERITY_HIGH:
				return PRS_STYLE_STATUS_HIGH_BG;
			case TRIGGER_SEVERITY_AVERAGE:
				return PRS_STYLE_STATUS_AVERAGE_BG;
			case TRIGGER_SEVERITY_WARNING:
				return PRS_STYLE_STATUS_WARNING_BG;
			case TRIGGER_SEVERITY_INFORMATION:
				return PRS_STYLE_STATUS_INFO_BG;
			case TRIGGER_SEVERITY_NOT_CLASSIFIED:
				return PRS_STYLE_STATUS_NA_BG;
			default:
				return null;
		}
	}

	/**
	 * Get severity color from configuration.
	 *
	 * @param int $severity
	 *
	 * @return string|null
	 */
	public static function getColor(int $severity): ?string {
		switch ($severity) {
			case TRIGGER_SEVERITY_DISASTER:
				return CSettingsHelper::get(CSettingsHelper::SEVERITY_COLOR_5);
			case TRIGGER_SEVERITY_HIGH:
				return CSettingsHelper::get(CSettingsHelper::SEVERITY_COLOR_4);
			case TRIGGER_SEVERITY_AVERAGE:
				return CSettingsHelper::get(CSettingsHelper::SEVERITY_COLOR_3);
			case TRIGGER_SEVERITY_WARNING:
				return CSettingsHelper::get(CSettingsHelper::SEVERITY_COLOR_2);
			case TRIGGER_SEVERITY_INFORMATION:
				return CSettingsHelper::get(CSettingsHelper::SEVERITY_COLOR_1);
			case TRIGGER_SEVERITY_NOT_CLASSIFIED:
			default:
				return CSettingsHelper::get(CSettingsHelper::SEVERITY_COLOR_0);
		}
	}

	/**
	 * Generate array with severities options.
	 *
	 * @param int $min  Minimal severity.
	 * @param int $max  Maximum severity.
	 *
	 * @return array
	 */
	public static function getSeverities(int $min = TRIGGER_SEVERITY_NOT_CLASSIFIED,
			int $max = TRIGGER_SEVERITY_COUNT - 1): array {
		$severities = [];

		foreach (range($min, $max) as $severity) {
			$severities[] = [
				'label' => self::getName($severity),
				'value' => $severity,
				'style' => self::getStyle($severity)
			];
		}

		return $severities;
	}
}
