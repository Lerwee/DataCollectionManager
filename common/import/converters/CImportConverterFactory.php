<?php
namespace app\customs\zapi\common\import\converters;

use app\customs\zapi\common\import\validators\CRegistryFactory;

/**
 * Factory for creating import conversions.
 */
final class CImportConverterFactory extends CRegistryFactory {

	private const SEQUENTIAL_CONVERTERS = [
		'1.0' => 'app\customs\zapi\common\import\converters\C10ImportConverter',
		'2.0' => 'app\customs\zapi\common\import\converters\C20ImportConverter',
		'3.0' => 'app\customs\zapi\common\import\converters\C30ImportConverter',
		'3.2' => 'app\customs\zapi\common\import\converters\C32ImportConverter',
		'3.4' => 'app\customs\zapi\common\import\converters\C34ImportConverter',
		'4.0' => 'app\customs\zapi\common\import\converters\C40ImportConverter',
		'4.2' => 'app\customs\zapi\common\import\converters\C42ImportConverter',
		'4.4' => 'app\customs\zapi\common\import\converters\C44ImportConverter',
		'5.0' => 'app\customs\zapi\common\import\converters\C50ImportConverter',
		'5.2' => 'app\customs\zapi\common\import\converters\C52ImportConverter',
		'5.4' => 'app\customs\zapi\common\import\converters\C54ImportConverter',
		'6.0' => 'app\customs\zapi\common\import\converters\C60ImportConverter',
		'6.2' => 'app\customs\zapi\common\import\converters\C62ImportConverter'
	];

	public function __construct() {
		parent::__construct(self::SEQUENTIAL_CONVERTERS);
	}

	public static function getSequentialVersions() {
		return array_keys(self::SEQUENTIAL_CONVERTERS);
	}
}
