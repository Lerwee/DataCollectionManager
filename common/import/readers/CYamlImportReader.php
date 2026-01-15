<?php declare(strict_types = 0);
namespace app\customs\zapi\common\import\readers;


use Symfony\Component\Yaml\Yaml;

/**
 * Class for converting YAML data stream to PHP array.
 */
class CYamlImportReader extends CImportReader {

	/**
	 * Convert YAML data stream to PHP array.
	 * Known issues:
	 *   - Error messages coming from Symfony YAML are not translatable;
	 *   - Symfony parser incorrectly interprets YAML as JSON;
	 *   - Symfony parser recognizes patterns (like dates) in unquoted strings which are not supported by Perseus;
	 *   - Symfony parser support only one YAML document per file.
	 *
	 * @param string $string
	 *
	 * @throws ErrorException
	 *
	 * @return array
	 */
	public function read($string): array {
		try {
			$data = Yaml::parse($string);
		}
		catch (Exception $exception) {
			throw new ErrorException($exception->getMessage());
		}

		if ($data === null) {
			throw new ErrorException(_s('Cannot read YAML: %1$s.', _('File is empty')));
		}
		elseif (!is_array($data)) {
			throw new ErrorException(_s('Cannot read YAML: %1$s.', _('Invalid YAML file contents')));
		}

		return self::trimEmptyLine($data);
	}

	/**
	 * Removes trailing empty line from multiline strings.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	private static function trimEmptyLine(array $data): array {
		foreach ($data as &$value) {
			if (is_array($value)) {
				$value = self::trimEmptyLine($value);
			}
			else if (is_string($value) && $value !== '' && $value[strlen($value) - 1] === "\n") {
				$value = substr($value, 0, -1);
			}
		}
		unset($value);

		return $data;
	}
}
