<?php declare(strict_types = 0);

namespace app\customs\zapi\common\helpers;
use Yii;

class RegexHelper {

	public static function expression_type2str(int $type = null) {
		$types = [
			EXPRESSION_TYPE_INCLUDED => Yii::t('zapi', 'Character string included'),
			EXPRESSION_TYPE_ANY_INCLUDED => Yii::t('zapi', 'Any character string included'),
			EXPRESSION_TYPE_NOT_INCLUDED => Yii::t('zapi', 'Character string not included'),
			EXPRESSION_TYPE_TRUE => Yii::t('zapi', 'Result is TRUE'),
			EXPRESSION_TYPE_FALSE => Yii::t('zapi', 'Result is FALSE')
		];

		if ($type === null) {
			return $types;
		}

		return array_key_exists($type, $types) ? $types[$type] : Yii::t('zapi', 'Unknown');
	}

	public static function expressionDelimiters(): array {
		return [
			',' => ',',
			'.' => '.',
			'/' => '/'
		];
	}
}
