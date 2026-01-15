<?php
namespace app\customs\zapi\common\export\writers;

class CExportWriterFactory {

	const YAML = 'yaml';
	const XML = 'xml';
	const JSON = 'json';
	const RAW = 'raw';

	/**
	 * Get the writer object for specified type.
	 *
	 * @static
	 * @throws \Exception
	 *
	 * @param string $type
	 *
	 * @return CExportWriter
	 */
	public static function getWriter($type) {
		switch ($type) {
			case self::YAML:
				return new CYamlExportWriter();

			case self::XML:
				return new CXmlExportWriter();

			case self::JSON:
				return new CJsonExportWriter();

			case self::RAW:
				return new CRawExportWriter();

			default:
				throw new \Exception('Incorrect export writer type.');
		}
	}

	/**
	 * Get content mime-type for specified type.
	 *
	 * @static
	 * @throws \Exception
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	public static function getMimeType(string $type): string {
		switch ($type) {
			case self::YAML:
				// See https://github.com/rails/rails/blob/d41d586/actionpack/lib/action_dispatch/http/mime_types.rb#L39
				return 'text/yaml';

			case self::XML:
				// See https://www.ietf.org/rfc/rfc2376.txt
				return 'text/xml';

			case self::JSON:
				// See https://www.ietf.org/rfc/rfc4627.txt
				return 'application/json';

			default:
				throw new \Exception('Incorrect export writer type.');
		}
	}
}
