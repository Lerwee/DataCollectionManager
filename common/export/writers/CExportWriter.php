<?php
namespace app\customs\zapi\common\export\writers;

/**
 * Abstract class for all export writers.
 */
abstract class CExportWriter {

	/**
	 * Determines if output should be formatted.
	 *
	 * @var bool
	 */
	protected  $formatOutput = true;

	/**
	 * Convert array with export data to required format.
	 *
	 * @abstract
	 *
	 * @param array $array
	 *
	 * @return string
	 */
	abstract public function write(array $array);

	/**
	 * Enable or disable output formatting. Enabled by default.
	 *
	 * @param bool $value
	 *
	 * @return mixed
	 */
	public function formatOutput($value) {
		$this->formatOutput = $value;
	}
}
