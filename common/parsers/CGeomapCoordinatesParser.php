<?php declare(strict_types = 0);
namespace app\customs\zapi\common\parsers;

/**
 * Class is used to parse comma separated string of geographical coordinates and zoom level.
 *
 * Valid token must match one of following formats:
 * - <latitude>,<longitude>,<zoom>
 * - <latitude>,<longitude>
 */
class CGeomapCoordinatesParser extends CParser {

	/**
	 * @var array
	 */
	public $result;

	/**
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->result = [];

		$regex = '/^(?P<latitude>-?\d+(\.\d+)?),\s*(?P<longitude>-?\d+(\.\d+)?)(,(?P<zoom>\d+))?$/';
		if (!preg_match($regex, substr($source, $pos), $matches)) {
			return self::PARSE_FAIL;
		}

		if ((float) $matches['latitude'] < GEOMAP_LAT_MIN || (float) $matches['latitude'] > GEOMAP_LAT_MAX
				|| (float) $matches['longitude'] < GEOMAP_LNG_MIN || (float) $matches['longitude'] > GEOMAP_LNG_MAX) {
			return self::PARSE_FAIL;
		}

		$this->match = $matches[0];
		$this->length = strlen($this->match);
		$this->result = array_intersect_key($matches, array_flip(['latitude', 'longitude', 'zoom']));

		return self::PARSE_SUCCESS;
	}
}
