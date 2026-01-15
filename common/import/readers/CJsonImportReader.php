<?php
namespace app\customs\zapi\common\import\readers;


class CJsonImportReader extends CImportReader {

	/**
	 * Convert string with data in JSON format to php array.
	 *
	 * @param string $string
	 *
	 * @return array
	 */
	public function read($string) {
		$data = json_decode($string, true);

		if ($data === null){
			throw new \Exception(_s('Cannot read JSON: %1$s.', json_last_error_msg()));
		}

		return $data;
	}
}
