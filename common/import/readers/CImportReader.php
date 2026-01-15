<?php
namespace app\customs\zapi\common\import\readers;


abstract class CImportReader {

	/**
	 * Convert string with data in format supported by reader to php array.
	 *
	 * @abstract
	 *
	 * @param $string
	 *
	 * @return array
	 */
	abstract public function read($string);
}
