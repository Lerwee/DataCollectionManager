<?php declare(strict_types = 0);

namespace app\customs\zapi\common\export\writers;

/**
 * Class for returning array with export data as is.
 */
class CRawExportWriter extends CExportWriter {

	/**
	 * Return array with export data as is.
	 *
	 * @param array $array
	 *
	 * @return string
	 */
	public function write(array $array): array {
		return $array;
	}
}
