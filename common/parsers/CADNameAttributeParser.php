<?php
namespace app\customs\zapi\common\parsers;
/**
 * A parser for AD samAccountName or userPrincipalName parser.
 */
class CADNameAttributeParser extends CParser {

	const PRS_TYPE_UNKNOWN = 0;
	const PRS_TYPE_SAMA = 0x1;
	const PRS_TYPE_UPN = 0x2;

	/**
	 * User name attribute type.
	 *
	 * @var int
	 */
	private $name_type;

	/**
	 * @var string
	 */
	private $user_name;

	/**
	 * @var string
	 */
	private $domain_name;

	/**
	 * @var array
	 */
	private $options;

	/**
	 * Create instance of parser.
	 *
	 * @param array $options              Array of options.
	 * @param int   $options['strict']    For sAMAccount name check length of parsed domain and user. Default false.
	 * @param int   $options['nametype']  Bit mask what type of user name should be parsed.
	 *                                    Default parse sAMAccountName and UserPrincipalName.
	 */
	public function __construct(array $options = []) {
		$options += ['nametype' => self::PRS_TYPE_SAMA | self::PRS_TYPE_UPN];
		$this->options = [
			'strict' => array_key_exists('strict', $options) && (bool) $options['strict'],
			'type_sama' => array_key_exists('nametype', $options) && ($options['nametype'] & self::PRS_TYPE_SAMA),
			'type_upn' => array_key_exists('nametype', $options) && ($options['nametype'] & self::PRS_TYPE_UPN)
		];
	}

	/**
	 * Parse given name attribute value.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->user_name = '';
		$this->domain_name = '';
		$this->match = '';
		$this->name_type = self::PRS_TYPE_UNKNOWN;

		if (($this->options['type_upn']) && $this->parseUserPrincipalName($source, $pos) == self::PARSE_SUCCESS) {
			$this->name_type = self::PRS_TYPE_UPN;
		}
		elseif ($this->options['type_sama'] && $this->parseSamAccountName($source, $pos) == self::PARSE_SUCCESS) {
			$this->name_type = self::PRS_TYPE_SAMA;
		}
		else {
			return self::PARSE_FAIL;
		}

		return isset($source[$pos]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Get parsed name attribute type.
	 *
	 * @return int
	 */
	public function getNameType() {
		return $this->name_type;
	}

	/**
	 * Get parsed user name value.
	 *
	 * @return string|null
	 */
	public function getUserName() {
		return ($this->name_type == self::PRS_TYPE_UNKNOWN) ? null : $this->user_name;
	}

	/**
	 * Get parsed user domain name value.
	 *
	 * @return string|null
	 */
	public function getDomainName() {
		return ($this->name_type == self::PRS_TYPE_UNKNOWN) ? null : $this->domain_name;
	}

	/**
	 * Parse string searching for samAccountName.
	 * https://docs.microsoft.com/en-us/windows/desktop/ADSchema/a-samaccountname
	 * https://social.technet.microsoft.com/wiki/contents/articles/11216.active-directory-requirements-for-creating-objects.aspx
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 *
	 * @return int
	 */
	private function parseSamAccountName($source, &$pos) {
		$strict = $this->options['strict'];
		$regex = '/^(?<domain>[^\\\\\/\:\*\?\"\<\>]'.
			($strict ? '{1,15}' : '+').
			')\\\(?<user>[^\\\\\/\:\*\?\"\<\>@]'.
			($strict ? '{1,20}' : '+').
			')/i';

		if (preg_match($regex, substr($source, $pos), $matches)) {
			$this->length = strlen($matches[0]);
		}
		else {
			return self::PARSE_FAIL;
		}

		$this->match = $matches[0];
		$this->domain_name = $matches['domain'];
		$this->user_name = $matches['user'];
		$pos += $this->length;

		return self::PARSE_SUCCESS;
	}

	/**
	 * Parse string searching for UserPrincipalName.
	 * https://docs.microsoft.com/en-us/windows/desktop/ADSchema/a-userprincipalname
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 *
	 * @return int
	 */
	private function parseUserPrincipalName($source, &$pos) {
		$regex = '/^(?<user>[_a-z0-9-@]+(\.[_a-z0-9-]+)*)@(?<domain>[a-z0-9-]+(\.[a-z0-9-]+)*)/i';

		if (preg_match($regex, substr($source, $pos), $matches)) {
			$this->length = strlen($matches[0]);
		}
		else {
			return self::PARSE_FAIL;
		}

		$this->match = $matches[0];
		$this->domain_name = $matches['domain'];
		$this->user_name = $matches['user'];
		$pos += $this->length;

		return self::PARSE_SUCCESS;
	}
}
