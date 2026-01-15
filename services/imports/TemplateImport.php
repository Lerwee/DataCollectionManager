<?php

namespace app\customs\zapi\services\imports;

use app\common\base\BaseService;
use app\common\components\Result;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\export\writers\CExportWriterFactory;
use app\customs\zapi\common\export\writers\CYamlExportWriter;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\import\CConfigurationImport;
use app\customs\zapi\common\import\CConfigurationImportcompare;
use app\customs\zapi\common\import\CImportDataAdapter;
use app\customs\zapi\common\import\CImportedObjectContainer;
use app\customs\zapi\common\import\CImportReferencer;
use app\customs\zapi\common\import\converters\CConstantImportConverter;
use app\customs\zapi\common\import\converters\CDefaultImportConverter;
use app\customs\zapi\common\import\converters\CImportConverterFactory;
use app\customs\zapi\common\import\converters\CImportDataNormalizer;
use app\customs\zapi\common\import\readers\CImportReaderFactory;
use app\customs\zapi\common\import\validators\CImportValidatorFactory;
use app\customs\zapi\common\import\validators\CXmlValidator;
use app\customs\zapi\services\exports\ExportBuilder;
use app\customs\zapi\services\exports\TemplateExport;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\HostsTemplates;
use Yii;
use yii\db\Query;

class TemplateImport extends BaseService
{
	public const CHANGE_NONE = 0;
	public const CHANGE_ADDED = 1;
	public const CHANGE_REMOVED = 2;

	/**
     * @var array Array with data.
     */
	protected $rules;

	protected $format;

	/**
     * @var array Array with data.
     */
	protected $schema;

	/**
     * @var array Array with data that compare.
     */
	protected $compare;

	/**
     * @var array Array with data that must be imported.
     */
    protected $data;
	
	private $toc = [];
	private $id_counter = 0;

    /**
     * @return $this
     */
    public function load(array $params)
    {
        if (empty($params['format']) && empty($params['source'])) {
            return $this;
        }

		$this->rules = $params['rules'];
		$this->format = $params['format'];

        $reader = CImportReaderFactory::getReader($params['format']);
		$data = $reader->read($params['source']);
        if (array_key_exists(ExportBuilder::MARKUP, $data)) {
            $data['perseus_export'] = $data[ExportBuilder::MARKUP];
            unset($data[ExportBuilder::MARKUP]);
        }
        if (empty($data['perseus_export']['version'])) {
            $data['perseus_export']['version'] = PERSEUS_EXPORT_VERSION;
        }

        $import_validator_factory = new CImportValidatorFactory($params['format']);
		$import_converter_factory = new CImportConverterFactory();

		$validator = new CXmlValidator($import_validator_factory, $params['format']);

		$this->data = $data;

		$data = $validator
			->setStrict(true)
			->setPreview(true)
			->validate($data, '/');

		foreach ($import_converter_factory::getSequentialVersions() as $version) {
			if ($data['perseus_export']['version'] !== $version) {
				continue;
			}

			$data = $import_converter_factory
				->getObject($version)
				->convert($data);

			$data = $validator
				// Must not use XML_INDEXED_ARRAY key validation for the converted data.
				->setStrict(false)
				->setPreview(true)
				->validate($data, '/');
		}

		// Get schema for converters.
		$schema = $import_validator_factory
			->getObject(PERSEUS_EXPORT_VERSION)
			->getSchema();

		$this->schema = $schema;

		// Normalize array keys and strings.
		$data = (new CImportDataNormalizer($schema))
			->setPreview(true)
			->normalize($data);

		$adapter = new CImportDataAdapter();
		$adapter->load($data);

		$import = $adapter->getData();

        $imported_entities = [];

		$entities = [
			'host_groups' => 'name',
			'template_groups' => 'name',
			'templates' => 'template'
		];

		foreach ($entities as $entity => $name_field) {
			if (array_key_exists($entity, $import)) {
				$imported_entities[$entity]['uuid'] = array_column($import[$entity], 'uuid');
				$imported_entities[$entity][$name_field] = array_column($import[$entity], $name_field);
			}
		}

		$imported_ids = [];

		foreach ($imported_entities as $entity => $data) {
			switch ($entity) {
				case 'host_groups':
					$groups = GroupHelper::getTemplateGroups([
						'output' => ['groupid'],
						'filter' => [
							'uuid' => $data['uuid'],
							'name' => $data['name'],
						],
						'preservekeys' => true
					]);
					$imported_ids['host_groups'] = array_keys($groups);
					break;

				case 'template_groups':
					$groups = GroupHelper::getHostGroups([
						'output' => ['groupid'],
						'filter' => [
							'uuid' => $data['uuid'],
							'name' => $data['name'],
						],
						'preservekeys' => true
					]);
					$imported_ids['template_groups'] = array_keys($groups);
					break;
				case 'templates':
                    $query = Hosts::find()
                        ->where(['status' => HOST_STATUS_TEMPLATE])
                        ->andWhere([
                            'OR',
                            ['uuid' => $data['uuid']],
                            ['host' => $data['template']],
                        ])
                        ->select(['templateid' => 'hostid', 'uuid', 'name'])
                        ->indexBy('templateid')
                        ->asArray();
                 
                    $db_templates = $query->all();
                         
                    $templateIds = array_keys($db_templates);
					if ($templateIds && $params['rules']['templateLinkage']['deleteMissing']) {
                        $query = new Query();
                        $query->from([
                            'h' => Hosts::tableName(),
                            'ht' => HostsTemplates::tableName(),
                        ]);
                        $query->where('h.hostid=ht.templateid')
                            ->andWhere(['h.status' => HOST_STATUS_TEMPLATE])
                            ->andWhere(SqlHelper::whereIn('ht.hostid',$templateIds));
                        $query->select(['templateid' => 'h.hostid', 'h.name', 'ht.hostid'])
                            ->indexBy('hostid');
                        $rows = $query->all();
                        $parentTemplates = [];
                        foreach ($rows as $row) {
                            $parentTemplates[$row['hostid']][] = array_diff_key($row, ['hostid' => 1]);
                        }
                        unset($rows);
                        foreach($db_templates as $templateId => &$db_template) {
                            $db_template['parentTemplates'] = $parentTemplates[$templateId] ?? [];
                        }
                        unset($db_template);
					}
					$imported_ids['templates'] = $templateIds;
					break;

				default:
					break;
			}
		}

		$unlink_templates_data = [];

		if ($params['rules']['templateLinkage']['deleteMissing']) {
			$import_tmp_parent_tmp_names = [];

			foreach ($import['templates'] as $template) {
				if (array_key_exists('templates', $template)) {
					$parent_tmp = array_column($template['templates'], 'name');

					$import_tmp_parent_tmp_names[$template['name']] = $parent_tmp;
					$import_tmp_parent_tmp_names[$template['uuid']] = $parent_tmp;
				}
				else {
					$import_tmp_parent_tmp_names[$template['name']] = [];
					$import_tmp_parent_tmp_names[$template['uuid']] = [];
				}
			}

			foreach ($db_templates as $db_template) {
				$db_parent_tmp_names = array_column($db_template['parentTemplates'], 'name', 'templateid');

				if ($db_parent_tmp_names) {
					$unlink_templateids = array_key_exists($db_template['uuid'], $import_tmp_parent_tmp_names)
						? array_diff($db_parent_tmp_names, $import_tmp_parent_tmp_names[$db_template['uuid']])
						: array_diff($db_parent_tmp_names, $import_tmp_parent_tmp_names[$db_template['name']]);

					if ($unlink_templateids) {
						$unlink_templates_data[$db_template['templateid']] = [
							'templateid' => $db_template['templateid'],
							'unlink_templateids' => array_keys($unlink_templateids)
						];
					}
				}
			}
		}

		// Get current state of templates in same format, as import to compare this data.
        $export = TemplateExport::instance()->compare([
			'format' => CExportWriterFactory::RAW,
			'prettyprint' => false,
			'options' => $imported_ids,
			'unlink_parent_templates' => $unlink_templates_data
		]);
		$export['perseus_export'] = $export[ExportBuilder::MARKUP];
		unset($export[ExportBuilder::MARKUP]);
		// Normalize array keys and strings.
		$export = (new CImportDataNormalizer($schema))
			->setPreview(true)
			->normalize($export);
		$export = $export['perseus_export'];
		$importcompare = new CConfigurationImportcompare($params['rules']);

		$this->compare = $importcompare->importcompare($export, $import);
		
        return $this;
    }

	/**
	 * Saves
	 *
	 * @return Result
	 */
    public function compare(): Result
    {
		if ($this->compare === null) {
			$msg = Yii::t('yii', 'Invalid data received for parameter "{param}".', [
				'param' => 'file'
			]);
			return $this->error(60750101, $msg);
		}
		
		if (empty($this->compare) || empty($this->compare['templates'])) {
			return $this->error(60750101, t('zapi', 'No changes.'));
		}

		$data = $this->blocksToDiff($this->compare, 1);
		return $this->success($data);
    }

	private function blocksToDiff(array $blocks, int $depth, string $outer_change_type = 'updated'): array {
		$change_types = [
			'added' => self::CHANGE_ADDED,
			'removed' => self::CHANGE_REMOVED,
			'updated' => self::CHANGE_NONE
		];

		$rows = [];
		foreach ($blocks as $entity_type => $changes) {
			$rows[] = [
				'value' => $entity_type . ':',
				'depth' => $depth,
				'change_type' => $change_types[$outer_change_type]
			];

			foreach ($changes as $change_type => $entities) {
				foreach ($entities as $entity) {
					$before = array_key_exists('before', $entity) ? $entity['before'] : [];
					$after = array_key_exists('after', $entity) ? $entity['after'] : [];
					$object = $before ?: $after;
					unset($entity['before'], $entity['after']);

					$id = $this->id_counter++;

					$this->toc[$change_type][$entity_type][] = [
						'name' => $this->nameForToc($entity_type, $object),
						'id' => $id
					];

					$rows = array_merge($rows, $this->objectToRows($before, $after, $depth + 1, $id));

					// Process any sub-entities.
					if ($entity) {
						$rows = array_merge($rows, $this->blocksToDiff($entity, $depth + 2, $change_type));
					}
				}
			}
		}

		return $rows;
	}

	/**
	 * Show exactly which array elements were added/removed/updated. Only on first depth level.
	 *
	 * @param string $key
	 * @param array $before
	 * @param array $after
	 * @param int $depth
	 *
	 * @return array
	 */
	private function arrayToRows(string $key, array $before, array $after, int $depth): array {
		$rows = [[
			'value' => $key . ':',
			'depth' => $depth,
			'change_type' => self::CHANGE_NONE
		]];

		$is_hash = CArrayHelper::isHash($before) || CArrayHelper::isHash($after);

		// Make sure, order changes are also taken into account.
		$unchanged_map = [];
		$before_keys = array_keys($before);
		$after_keys = array_keys($after);
		$last_after_index = -1;

		foreach ($before_keys as $before_key) {
			$after_keys_count = count($after_keys);
			for ($j = $last_after_index + 1; $j < $after_keys_count; $j++) {
				$after_key = $after_keys[$j];

				if ($is_hash) {
					if ($before_key === $after_key && $before[$before_key] === $after[$after_key]) {
						$unchanged_map[$before_key] = $after_key;
						$last_after_index = $j;
					}
				}
				else {
					if ($before[$before_key] === $after[$after_key]) {
						$unchanged_map[$before_key] = $after_key;
						$last_after_index = $j;
					}
				}
			}
		}
		unset($after_key, $last_after_index);

		$unchanged_before_keys = array_keys($unchanged_map);
		$next_unchanged_before_index = 0;
		$next_after_index = 0;

		foreach ($before_keys as $before_key) {
			if (array_key_exists($next_unchanged_before_index, $unchanged_before_keys)
					&& $before_key === $unchanged_before_keys[$next_unchanged_before_index]) {
				// Show all added after entries.
				while (array_key_exists($next_after_index, $after_keys)
						&& $after_keys[$next_after_index] !== $unchanged_map[$before_key]) {
					$after_key = $after_keys[$next_after_index];
					$yaml_key = $this->prepareYamlKey($after_key, $is_hash);
					$rows[] = [
						'value' => $this->convertToYaml([$yaml_key => $after[$after_key]]),
						'depth' => $depth + 1,
						'change_type' => self::CHANGE_ADDED
					];
					$next_after_index++;
				}

				// Show unchanged entry.
				$yaml_key = $this->prepareYamlKey($before_key, $is_hash);
				$rows[] = [
					'value' => $this->convertToYaml([$yaml_key => $before[$before_key]]),
					'depth' => $depth + 1,
					'change_type' => self::CHANGE_NONE
				];
				$next_unchanged_before_index++;
				$next_after_index++;
			}
			else {
				// Show all removed before entries.
				$yaml_key = $this->prepareYamlKey($before_key, $is_hash);
				$rows[] = [
					'value' => $this->convertToYaml([$yaml_key => $before[$before_key]]),
					'depth' => $depth + 1,
					'change_type' => self::CHANGE_REMOVED
				];
			}
		}

		// Show remaining after entries.
		$after_keys_count = count($after_keys);
		for ($after_index = $next_after_index; $after_index < $after_keys_count; $after_index++) {
			$after_key = $after_keys[$after_index];
			$yaml_key = $this->prepareYamlKey($after_key, $is_hash);
			$rows[] = [
				'value' => $this->convertToYaml([$yaml_key => $after[$after_key]]),
				'depth' => $depth + 1,
				'change_type' => self::CHANGE_ADDED
			];
		}

		return $rows;
	}

	/**
	 * Prepares key, with which each array element will be passed to YAML converter. Makes sure the key will be
	 * properly converted.
	 *
	 * @param mixed $key
	 * @param boolean $is_hash
	 *
	 * @return mixed
	 */
	private function prepareYamlKey($key, bool $is_hash) {
		if ($is_hash) {
			// Make sure, array with "zero" key element is not considered as indexed array in YAML converter.
			// \xE2\x80\x8B is UTF-8 code for zero width space.
			$yaml_key = ($key === 0) ? "\xE2\x80\x8B".'0' : $key;
		}
		else {
			// For indexed array any index should be replaced by "-" in YAML converter.
			// Passing single-entry array with '0' key, does exactly that.
			$yaml_key = 0;
		}

		return $yaml_key;
	}

	private function objectToRows(array $before, array $after, int $depth, int $id): array {
		$all_keys = [];

		foreach (array_keys($before) as $key) {
			if (array_key_exists($key, $after)) {
				if ($before[$key] == $after[$key]) {
					$all_keys[$key] = 'no_change';
				}
				else if (is_array($before[$key])) {
					$all_keys[$key] = 'updated_array';
				}
				else {
					$all_keys[$key] = 'updated';
				}
			}
			else {
				$all_keys[$key] = 'removed';
			}
		}

		foreach (array_keys($after) as $key) {
			if (!array_key_exists($key, $before)) {
				$all_keys[$key] = 'added';
			}
		}

		unset($all_keys['uuid']);

		$rows = [];

		foreach ($all_keys as $key => $change_type) {
			switch ($change_type) {
				case 'no_change':
					$rows[] = [
						'value' => $this->convertToYaml([$key => $before[$key]]),
						'depth' => $depth + 1,
						'change_type' => self::CHANGE_NONE
					];

					break;

				case 'updated':
					$rows[] = [
						'value' => $this->convertToYaml([$key => $before[$key]]),
						'depth' => $depth + 1,
						'change_type' => self::CHANGE_REMOVED
					];
					$rows[] = [
						'value' => $this->convertToYaml([$key => $after[$key]]),
						'depth' => $depth + 1,
						'change_type' => self::CHANGE_ADDED
					];

					break;

				case 'removed':
					$rows[] = [
						'value' => $this->convertToYaml([$key => $before[$key]]),
						'depth' => $depth + 1,
						'change_type' => self::CHANGE_REMOVED
					];

					break;

				case 'added':
					$rows[] = [
						'value' => $this->convertToYaml([$key => $after[$key]]),
						'depth' => $depth + 1,
						'change_type' => self::CHANGE_ADDED
					];

					break;

				case 'updated_array':
					$rows = array_merge($rows, $this->arrayToRows($key, $before[$key], $after[$key], $depth + 1));

					break;
			}
		}

		if ($rows) {
			$rows[0] += ['id' => $id];
		}

		return $rows;
	}

	private function nameForToc(string $entity_type, array $object): string {
		switch ($entity_type) {
			case 'templates':
				return array_key_exists('name', $object) ? $object['name'] : $object['template'];
			case 'host_prototypes':
				return array_key_exists('name', $object) ? $object['name'] : $object['host'];
			default:
				return $object['name'];
		}
	}

	private function convertToYaml($object): string {
		$writer = new CYamlExportWriter();

		return $writer->write($object);
	}

	/**
	 * @param bool $compare 对比
	 * @return Result
	 */
	public function import(bool $compare = true): Result
	{
		$r = [];
		if ($compare) {
			$result = $this->compare();
			if (!$result->isSuccess()) {
				return $result;
			}
			$r = $result->getData();
		}

		$import_validator_factory = new CImportValidatorFactory($this->format);
		$import_converter_factory = new CImportConverterFactory();
		$validator = new CXmlValidator($import_validator_factory, $this->format);

		$data = $validator
			->setStrict(true)
			->validate($this->data, '/');

		foreach ($import_converter_factory::getSequentialVersions() as $version) {
			if ($data['perseus_export']['version'] !== $version) {
				continue;
			}

			$data = $import_converter_factory
				->getObject($version)
				->convert($data);

			$data = $validator
				// Must not use XML_INDEXED_ARRAY key validation for the converted data.
				->setStrict(false)
				->validate($data, '/');
		}

		// Convert human readable import constants to values Perseus API can work with.
		$data = (new CConstantImportConverter($this->schema))->convert($data);

		// Add default values in place of missed tags.
		$data = (new CDefaultImportConverter($this->schema))->convert($data);

		// Normalize array keys and strings.
		$data = (new CImportDataNormalizer($this->schema))->normalize($data);

		$adapter = new CImportDataAdapter();
		$adapter->load($data);


		$importer = new CConfigurationImport(
			$this->rules,
			new CImportReferencer(),
			new CImportedObjectContainer()
		);
		$importer->import($adapter);
		return $this->success($r, t('zapi', 'Imported successfully'));
	}
}