<?php

namespace app\customs\zapi\common\helpers;

use app\customs\zapi\models\search\host\TemplateSearch;
use yii\db\Query;

class TimezoneHelper
{
    /**
	 * Get list of supported timezones.
	 * Example: ["Europe/London" => "(UTC+00:00) Europe/London", ...]
	 *
	 * @return array
	 */
	public static function getList(): array {
		static $timezones;

		if ($timezones === null) {
			$timezones = [];

			foreach (\DateTimeZone::listIdentifiers() as $timezone) {
				$offset = (new \DateTimeZone($timezone))->getOffset(new \DateTime());

				$timezones[$timezone] = [
					'offset' => $offset,
					'timezone' => $timezone,
					'title' => '(UTC'.($offset < 0 ? '-' : '+').gmdate('H:i', abs($offset)).') '.$timezone
				];
			}

			CArrayHelper::sort($timezones, ['offset', 'timezone']);

			$timezones = array_column($timezones, 'title', 'timezone');
		}

		return $timezones;
	}

	/**
	 * Get timezone title, optionally prefixed.
	 *
	 * @param string      $timezone
	 * @param string|null $prefix
	 *
	 * @return string
	 */
	public static function getTitle(string $timezone, string $prefix = null): string {
		$timezone_title = self::getList()[$timezone];

		if ($prefix === null) {
			return $timezone_title;
		}

		return $prefix.': '.$timezone_title;
	}

	/**
	 * Is timezone supported?
	 *
	 * @param string $timezone
	 *
	 * @return bool
	 */
	public static function isSupported(string $timezone): bool {
		return array_key_exists($timezone, self::getList());
	}

	/**
	 * Get system timezone.
	 * Example: "Europe/London"
	 *
	 * @return string
	 */
	public static function getSystemTimezone(): string {
		$system_timezone_lower = strtolower(ini_get('date.timezone'));

		foreach (array_keys(self::getList()) as $timezone) {
			if ($system_timezone_lower === strtolower($timezone)) {
				return $timezone;
			}
		}

		return 'UTC';
	}
}