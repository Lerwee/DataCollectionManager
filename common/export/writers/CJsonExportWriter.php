<?php

namespace app\customs\zapi\common\export\writers;

/**
 * Class for converting array with export data to JSON format.
 */
class CJsonExportWriter extends CExportWriter {

	/**
	 * Convert array with export data to JSON format.
	 *
	 * @param array $array
	 *
	 * @return string
	 */
	public function write(array $array) {
		$options = JSON_UNESCAPED_SLASHES;

		if ($this->formatOutput) {
			$options |= JSON_PRETTY_PRINT;
		}

		return json_encode($array, $options);
	}
}
