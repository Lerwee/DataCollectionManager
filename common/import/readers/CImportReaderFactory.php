<?php
namespace app\customs\zapi\common\import\readers;


class CImportReaderFactory {

	public const YAML = 'yaml';
	public const XML = 'xml';
	public const JSON = 'json';

	/**
	 * Get reader class for required format.
	 *
	 * @static
	 *
	 * @throws \Exception
	 *
	 * @param string $format
	 *
	 * @return CImportReader
	 */
	public static function getReader(string $format): CImportReader {
		switch ($format) {
			case self::YAML:
				return new CYamlImportReader();

			case self::XML:
				return new CXmlImportReader();

			case self::JSON:
				return new CJsonImportReader();

			default:
				throw new \Exception(t('zapi', 'Unsupported import format "{format}".', ['format' => $format]));
		}
	}

	/**
	 * Converts file extension to associated import format.
	 *
	 * @static
	 *
	 * @throws Exception
	 *
	 * @param string $ext
	 *
	 * @return string
	 */
	public static function fileExt2ImportFormat(string $ext): string {
		switch ($ext) {
			case 'yaml':
			case 'yml':
				return self::YAML;

			case 'xml':
				return self::XML;

			case 'json':
				return self::JSON;

			default:
				throw new \Exception(t('zapi', 'Unsupported import file extension "{extension}".', ['ext' => $ext]));
		}
	}
}
