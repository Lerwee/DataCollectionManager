<?php
namespace app\customs\zapi\common\import\converters;


/**
 * Convert net.tcp.service to net.udp.service.
 */
class C20ItemKeyConverter extends CConverter {

	/**
	 * Item key parser.
	 *
	 * @var CItemKey
	 */
	protected $item_key_parser;

	public function __construct() {
		$this->item_key_parser = new CItemKey();
	}

	/**
	 * Convert item key.
	 *
	 * @param string $value  Item key.
	 *
	 * @return string  Converted item key.
	 */
	public function convert($value) {
		if ($this->item_key_parser->parse($value) != CParser::PARSE_SUCCESS) {
			return $value;
		}

		$item_key = $this->item_key_parser->getKey();
		if ($item_key !== 'net.tcp.service' && $item_key !== 'net.tcp.service.perf') {
			return $value;
		}

		if ($this->item_key_parser->getParamsNum() == 0 || $this->item_key_parser->getParam(0) !== 'ntp') {
			return $value;
		}

		return substr_replace($value, 'udp', 4, 3);
	}
}
