<?php
namespace app\customs\zapi\common\parsers;

/**
 * Class is used to validate if given string is valid HashiCorp Vault secret.
 *
 * Valid token must match format:
 * - <namespace>/<path/to/secret>:<key>
 * - <namespace>/<path/to/secret>
 *
 * Mutlibyte strings are supported.
 */
class CVaultSecretParser extends CParser {

	private $options = [
		'provider' => PRS_VAULT_TYPE_UNKNOWN,
		'with_key' => true
	];

	private $cyberark_has_appid = true;
	private $cyberark_has_key = true;

	/**
	 * @param array $options
	 * @param int   $options['provider']  Vault provider.
	 * @param bool  $options['with_key']  (optional) Validated string must contain key.
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;
	}

	/**
	 * @inheritDoc
	 */
	public function parse($source, $pos = 0) {
		switch ($this->options['provider']) {
			case PRS_VAULT_TYPE_HASHICORP:
				return $this->parseHashiCorp($source, $pos);

			case PRS_VAULT_TYPE_CYBERARK:
				return $this->parseCyberArk($source, $pos);

			default:
				return self::PARSE_FAIL;
		}
	}

	private function parseHashiCorp($source, $pos) {
		$this->errorClear();

		$src_size = strlen($source);
		$namespace_sep = strpos($source, '/');
		if ($namespace_sep === false || $namespace_sep == 0 || $namespace_sep == $src_size - 1) {
			$this->errorPos($source, 0);

			return self::PARSE_FAIL;
		}

		if ($this->options['with_key']) {
			$path_pos = $namespace_sep + 1;
			$key_sep = strpos($source, ':', $path_pos);

			if ($source[$key_sep - 1] === '/') {
				$this->errorPos($source, $namespace_sep + 1);

				return self::PARSE_FAIL;
			}

			if ($key_sep === false || $key_sep == $path_pos || $key_sep == $src_size - 1) {
				$this->errorPos($source, $path_pos);

				return self::PARSE_FAIL;
			}

			if (strrpos($source, '//', $key_sep - $src_size) !== false) {
				$this->errorPos($source, $path_pos);

				return self::PARSE_FAIL;
			}
		}
		else {
			if (strpos($source, '//') !== false) {
				$this->errorPos($source, $namespace_sep + 1);

				return self::PARSE_FAIL;
			}

			if ($source[$src_size - 1] === '/') {
				$this->errorPos($source, $namespace_sep + 1);

				return self::PARSE_FAIL;
			}
		}

		return self::PARSE_SUCCESS;
	}

	private function parseCyberArk($source, $pos) {
		$start = $pos;
		$this->errorClear();
		$this->cyberark_has_appid = true;
		$this->cyberark_has_key = true;
		$has_appid = false;

		while (preg_match('/^(?<parameter>[A-Za-z]+)=(?<value>[^+&%:]*)/', substr($source, $pos), $matches) == 1) {
			if ($matches['parameter'] === 'AppID') {
				$has_appid = true;
			}

			$pos += strlen($matches[0]);

			if (!isset($source[$pos]) || $source[$pos] !== '&') {
				break;
			}

			$pos++;
		}

		if ($start == $pos) {
			$this->errorPos($source, $pos);

			return self::PARSE_FAIL;
		}

		if ($this->options['with_key']) {
			if (!isset($source[$pos]) || $source[$pos] !== ':' || !isset($source[++$pos])) {
				$this->errorPos($source, $pos);
				$this->cyberark_has_key = false;

				return self::PARSE_FAIL;
			}

			$pos += strlen(substr($source, $pos));
		}

		if (isset($source[$pos])) {
			$this->errorPos($source, $pos);

			return self::PARSE_FAIL;
		}

		if (!$has_appid) {
			$this->cyberark_has_appid = false;
			$this->errorPos($source, $pos);

			return self::PARSE_FAIL;
		}

		return self::PARSE_SUCCESS;
	}

	/**
	 * @inheritDoc
	 */
	public function getError(): string {
		if ($this->options['provider'] == PRS_VAULT_TYPE_CYBERARK) {
			if (!$this->cyberark_has_appid) {
				return t('zapi', 'mandatory parameter "{param}" is missing', ['param' => 'AppID']);
			}
			elseif (!$this->cyberark_has_key) {
				return t('zapi', 'mandatory key is missing');
			}
		}

		return parent::getError();
	}
}
