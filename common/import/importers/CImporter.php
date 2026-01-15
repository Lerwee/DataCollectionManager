<?php
namespace app\customs\zapi\common\import\importers;

use app\customs\zapi\common\import\CImportedObjectContainer;
use app\customs\zapi\common\import\CImportReferencer;

abstract class CImporter {

	/**
	 * @var CImportReferencer
	 */
	protected $referencer;

	/**
	 * @var CImportedObjectContainer
	 */
	protected $importedObjectContainer;

	/**
	 * @var array
	 */
	protected $options = [];

	/**
	 * @param array						$options					import options "createMissing", "updateExisting" and "deleteMissing"
	 * @param CImportReferencer			$referencer					class containing all importable objects
	 * @param CImportedObjectContainer	$importedObjectContainer	class containing processed host and template IDs
	 */
	public function __construct(array $options, CImportReferencer $referencer,
			CImportedObjectContainer $importedObjectContainer) {
		$this->options = $options;
		$this->referencer = $referencer;
		$this->importedObjectContainer = $importedObjectContainer;
	}

	/**
	 * @abstract
	 *
	 * @param array $elements
	 *
	 * @return mixed
	 */
	abstract public function import(array $elements);
}
