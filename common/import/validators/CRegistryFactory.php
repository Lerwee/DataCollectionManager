<?php

namespace app\customs\zapi\common\import\validators;


/**
 * A class for creating a storing instances of global objects.
 */
class CRegistryFactory {

	/**
	 * Array of defined objects. Each object can be defined either using the name of its class, or a closure that
	 * returns an instance of the object.
	 *
	 * @var array
	 */
	protected $objects = [];

	/**
	 * An array of created object instances.
	 *
	 * @var array
	 */
	protected $instances = [];

	/**
	 * @param array $objects	array of defined objects
	 */
	public function __construct(array $objects = []) {
		$this->objects = $objects;
	}

	/**
	 * Creates and returns an instance of the given object.
	 *
	 * @param $object
	 *
	 * @return object
	 */
	public function getObject($object) {
		if (!isset($this->instances[$object])) {
			$definition = $this->objects[$object];
			$this->instances[$object] = ($definition instanceof \Closure) ? $definition() : new $definition();
		}

		return $this->instances[$object];
	}

	/**
	 * Returns true if the given object is defined in the factory.
	 *
	 * @param string $object
	 *
	 * @return bool
	 */
	public function hasObject($object) {
		return isset($this->objects[$object]);
	}

}
