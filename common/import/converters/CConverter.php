<?php
namespace app\customs\zapi\common\import\converters;

/**
 * Base class for implementing any kind of data conversion.
 */
abstract class CConverter {

	abstract public function convert(array $data);

}
