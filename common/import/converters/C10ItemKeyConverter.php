<?php
namespace app\customs\zapi\common\import\converters;


/**
 * A converted for converting simple check item keys from 1.8 format to 2.0.
 */
class C10ItemKeyConverter extends CConverter {

	/**
	 * Parser for user macros.
	 *
	 * @var CUserMacroParser
	 */
	protected $user_macro_parser;

	public function __construct() {
		$this->user_macro_parser = new CUserMacroParser();
	}

	public function convert($value) {
		$keys = ['tcp', 'ftp', 'http', 'imap', 'ldap', 'nntp', 'ntp', 'pop', 'smtp', 'ssh'];

		$perfKeys = ['tcp_perf', 'ftp_perf', 'http_perf', 'imap_perf', 'ldap_perf', 'nntp_perf', 'ntp_perf',
			'pop_perf', 'smtp_perf', 'ssh_perf'
		];

		$parts = explode(',', $value);

		if (count($parts) <= 2) {
			$key = $parts[0];

			if (isset($parts[1]) && $parts[1] !== '') {
				if ($this->user_macro_parser->parse($parts[1] == CParser::PARSE_SUCCESS)) {
					$port = ',,'.$parts[1];
				}
				// numeric parameter or empty parameter
				else {
					$pos = 0;
					while (isset($parts[1][$pos]) && $parts[1][$pos] > '0' && $parts[1][$pos] < '9') {
						$pos++;
					}

					if (isset($parts[1][$pos])) {
						return $value;
					}

					$port = ',,'.$parts[1];
				}
			}
			else {
				$port = '';
			}

			if (in_array($key, $keys)) {
				return 'net.tcp.service['.$key.$port.']';
			}
			elseif (in_array($key, $perfKeys)) {
				list($key, $perfSuffix) = explode('_', $key);
				return 'net.tcp.service.perf['.$key.$port.']';
			}
		}

		return $value;
	}
}
