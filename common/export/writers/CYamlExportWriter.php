<?php declare(strict_types = 0);
namespace app\customs\zapi\common\export\writers;

use Symfony\Component\Yaml\Yaml;

/**
 * Class for converting array with export data to YAML format.
 */
class CYamlExportWriter extends CExportWriter {

	/**
	 * Converts array with export data to YAML format.
	 * Known issues:
	 *   - Symfony dumpers second parameter makes the YAML output either too vertical or too horizontal.
	 *
	 * @param mixed $input  Input data. Array or string.
	 *
	 * @return string
	 */
	public function write($input): string {
		$output = Yaml::dump($input, 100, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

		return preg_replace('/^(\s*-)\n\s+/m', '${1} ', $output);
	}
}
