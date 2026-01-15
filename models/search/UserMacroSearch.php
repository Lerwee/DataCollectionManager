<?php

namespace app\customs\zapi\models\search;

use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\helpers\HostHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\components\RelationMap;
use yii\db\Expression;
use yii\db\Query;

/**
 * 模板搜索模型
 *
 * @property string $keyword            关键词
 */
class UserMacroSearch extends BaseSearch
{
    public $is_all = false;

    public $groupids = null;
    public $hostids = null;
    public $hostmacroids = null;
    public $globalmacroids = null;
    public $templateids = null;
    public $globalmacro = null;
    public $inherited = null;
    public $excludeSearch = false;

    public $selectGroups = null;
    public $selectHostGroups = null;
    public $selectTemplateGroups = null;
    public $selectHosts = null;
    public $selectTemplates = null;

    /**
	 * Searches
	 *
	 * @param  array $params
	 * @return ActiveDataProvider
	 */
	public function search(array $params = [])
	{
		$this->checkDeprecatedParam($params, 'selectGroups');
		$this->setAttributes($params);

        $tables = $where = $groupBy = [];
        
        // global macro
		if (!is_null($this->globalmacro)) {
            $this->groupids = null;
            $this->hostmacroids = null;
            $this->hostids = null;
            $this->selectGroups = null;
            $this->selectHostGroups = null;
            $this->selectTemplateGroups = null;
            $this->selectTemplates = null;
            $this->selectHosts = null;
            $this->inherited = null;
		}

        $query = new Query();
        $query->select(['macros' => 'hm.hostmacroid'])
            ->from(['hm' => 'hostmacro']);

        $globalQuery = new Query();
        $globalQuery->select(['macros' => 'gm.globalmacroid'])
            ->from(['gm' => 'globalmacro']);

        // globalmacroids
		if (!is_null($this->globalmacroids)) {
            $globalQuery->andWhere(['gm.globalmacroid' => filter_integer((array)$this->globalmacroids)]);   
		}

		// hostmacroids
		if (!is_null($this->hostmacroids)) {
            $query->andWhere(['hm.hostmacroid' => filter_integer((array)$this->hostmacroids)]);
		}

		// inherited
		if (!is_null($this->inherited)) {
            $tables['h'] = 'hosts';
            $where[] = $this->inherited ? 'h.templateid IS NOT NULL' : 'h.templateid IS NULL';
            $where['hmh'] = 'hm.hostid=h.hostid';
		}

		// groupids
		if (!is_null($this->groupids)) {
            $tables['hg'] = 'hosts_groups';
            $query->andWhere(['hg.groupid' => filter_integer((array)$this->groupids)]);   
            $where['hgh'] = 'hg.hostid=hm.hostid';
		}

		// hostids
		if (!is_null($this->hostids)) {
            $query->andWhere(['hm.hostid' => filter_integer((array)$this->hostids)]);   
		}

		// templateids
		if (!is_null($this->templateids)) {
            $tables['ht'] = 'hosts_templates';
            $query->andWhere(['ht.templateid' => filter_integer((array)$this->templateids)]); 
			$where['hht'] = 'hm.hostid=ht.hostid';
		}

		// sorting
		$this->applyQuerySortOptions($query, 'hostmacro', 'hm', $this->sortfield, $this->sortorder);
		$this->applyQuerySortOptions($globalQuery, 'globalmacro', 'gm', $this->sortfield, $this->sortorder);

        if ($this->limit !== null && $this->limit > 0) {
            $query->limit($this->limit);
            $globalQuery->limit($this->limit);
        }
		
		// init GLOBALS
		if (!is_null($this->globalmacro)) {
			$this->applyQueryFilterOptions($globalQuery, 'globalmacro', 'gm');
			$this->applyQueryOutputOptions($globalQuery, 'globalmacro', 'gm', $this->countOutput ? 'count' : $this->output, $this->getAttributes());
            if ($this->preservekeys) {
                $globalQuery->indexBy('globalmacroid');
            }

            $provider = new ActiveDataProvider([
                'query' => $globalQuery
            ]);
		}
		// init HOSTS
		else {
			$this->applyQueryFilterOptions($query, 'hostmacro', 'hm');
			$this->applyQueryOutputOptions($query, 'hostmacro', 'hm', $this->countOutput ? 'count' : $this->output, $this->getAttributes());

			foreach ($where as $condition) {
				$query->andWhere($condition);
			}
			$query->from($tables + ['hm' => 'hostmacro']);
			$query->groupBy($groupBy);

            $provider = new ActiveDataProvider([
                'query' => $query
            ]);
            if ($this->preservekeys) {
                $query->indexBy('hostmacroid');
            }
		}

        if ($this->is_all) {
            $provider->setPagination(false);
        }

		if ($this->countOutput) {
			return $provider;
		}
        
        $models = $this->format($provider->getModels());
        $provider->setModels($models);
        
        return $provider;
    }

    protected function format(array $models)
    {
		if ($models) {
			$models = $this->addRelatedObjects($models);
			$models = $this->unsetExtraFields($models, ['hostid', 'type'], $this->output);
		}
        return $models;
    }

    /**
	 * Check if a set of parameters contains a deprecated parameter or a parameter with a deprecated value.
	 * If $value is not set, the method will trigger a deprecated notice if $params contains the $paramName key.
	 * If $value is set, the method will trigger a notice if the value of the parameter is equal to the deprecated value
	 * or the parameter is an array and contains a deprecated value.
	 *
	 * @param array  $params
	 * @param string $paramName
	 * @param string $value
	 */
	protected function checkDeprecatedParam(array $params, $paramName, $value = null) {
		if (isset($params[$paramName])) {
			if ($value === null) {
                $error = t('zapi', 'Parameter "{param}" is deprecated.', [
                    'param' => $paramName
                ]);
		        trigger_error($error, E_USER_DEPRECATED);
			}
			elseif (is_array($params[$paramName]) && in_array($value, $params[$paramName]) || $params[$paramName] == $value) {
                $error = t('zapi', 'Value "{value}" for parameter "{param}" is deprecated.', [
                    'value' => $value,
                    'param' => $paramName,
                ]);
		        trigger_error($error, E_USER_DEPRECATED);
			}
		}
	}

    protected function applyQueryOutputOptions(Query &$query, string $tableName, string $tableAlias, $outPut = 'extent', array $options = [])
    {
		// Added type to query because it required to check macro is secret or not.
		if (!$this->outputIsRequested('type', $outPut)) {
			$options['output'][] = 'type';
		}

		parent::applyQueryOutputOptions($query, $tableName, $tableAlias, $outPut, $options);

		if ($options['output'] != API_OUTPUT_COUNT && $this->globalmacro === null) {
			if ($options['selectGroups'] !== null || $this->selectHostGroups !== null
					|| $this->selectTemplateGroups !== null || $this->selectHosts !== null
					|| $this->selectTemplates !== null) {
				$this->addQuerySelect($query, $this->fieldId('hostid', 'hm'));
			}
		}
	}

    /**
     * @param  Query  $query
     * @param  string $tableName
     * @param  string $tableAlias
     */
    protected function applyQueryFilterOptions(Query &$query, $tableName, $tableAlias)
    {
		if (is_array($this->search)) {
			// Do not allow to search by value for macro of type PRS_MACRO_TYPE_SECRET.
			if (array_key_exists('value', $this->search)) {
                $query->andWhere($alias.'.type!='.PRS_MACRO_TYPE_SECRET);
                ZSqlHelper::zbxDbSearch($tableName.' '.$tableAlias, [
                    'searchByAny' => false,
                    'search' => ['value' => $this->search['value']],
                    'startSearch' => $this->startSearch,
                    'excludeSearch' => $this->excludeSearch,
                    'searchWildcardsEnabled' => $this->searchWildcardsEnabled,
                ], $query);
				unset($this->search['value']);
			}

			if ($this->search) {
                ZSqlHelper::zbxDbSearch($tableName.' '.$tableAlias, [
                    'searchByAny' => $this->searchByAny,
                    'search' => $this->search,
                    'startSearch' => $this->startSearch,
                    'excludeSearch' => $this->excludeSearch,
                    'searchWildcardsEnabled' => $this->searchWildcardsEnabled,
                ], $query);
			}
		}

		if (is_array($this->filter)) {
			// Do not allow to filter by value for macro of type PRS_MACRO_TYPE_SECRET.
			if (array_key_exists('value', $this->filter)) {
                $query->andWhere($alias.'.type!='.PRS_MACRO_TYPE_SECRET);
 
				ZSqlHelper::dbFilter($tableName, ['value' => $this->filter['value']], $tableAlias, false);
				unset($this->filter['value']);
			}

			if ($this->filter) {
				ZSqlHelper::dbFilter($tableName, $this->filter, $tableAlias, $this->searchByAny);
			}
		}
	}

        /**
	 * Adds the given field to the SELECT part of the $sqlParts array if it's not already present.
	 * If $sqlParts['select'] not present it is created and field appended.
	 *
	 * @param Query  $query
	 * @param string $fieldId
	 *
	 * @return array
	 */
	protected function addQuerySelect(Query &$query, $fieldId) {
		if (!isset($this->select)) {
            $query->select($fieldId);
		}

		list($tableAlias, $field) = explode('.', $fieldId);

		if (!in_array($fieldId, $query->select) && !in_array($this->fieldId('*', $tableAlias), $query->select)) {
			// if we want to select all of the columns, other columns from this table can be removed
			if ($field == '*') {
				foreach ($query->select as $key => $selectFieldId) {
					list($selectTableAlias,) = explode('.', $selectFieldId);

					if ($selectTableAlias == $tableAlias) {
						unset($query->select[$key]);
					}
				}
			}

            $query->addSelect($fieldId);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $result) {
		$result = parent::addRelatedObjects($result);

		if ($this->globalmacro === null) {
			/*
			 * Adding objects
			 */
			// adding groups
			$this->addRelatedGroups($result, 'selectGroups');
			$this->addRelatedGroups($result, 'selectHostGroups');
			$this->addRelatedGroups($result, 'selectTemplateGroups');

			// adding templates
			if ($this->selectTemplates !== null && $this->selectTemplates != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'hostmacroid', 'hostid');
				$templates = TemplateHelper::getTemplates([
					'output' => $this->selectTemplates,
					'templateids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				$result = $relationMap->mapMany($result, $templates, 'templates');
			}

			// adding templates
			if ($this->selectHosts !== null && $this->selectHosts != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'hostmacroid', 'hostid');
				$templates = HostHelper::getHosts([
					'output' => $this->selectHosts,
					'hostids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				$result = $relationMap->mapMany($result, $templates, 'hosts');
			}
		}

		return $result;
	}

	/**
	 * Adds related host or template groups requested by "select*" options to the resulting object set.
	 *
	 * @param array  $options [IN] Original input options.
	 * @param array  $result  [IN/OUT] Result output.
	 * @param string $option  [IN] Possible values:
	 *                               - "selectGroups" (deprecated);
	 *                               - "selectHostGroups";
	 *                               - "selectTemplateGroups".
	 */
	private function addRelatedGroups(array &$result, string $option): void {
		if ($this->{$option} === null || $this->{$option} === API_OUTPUT_COUNT) {
			return;
		}

        $relationMap = new RelationMap();
        $rows = (new Query())->select(['hm.hostmacroid','hg.groupid'])
            ->from([
                'hm' => 'hostmacro',
                'hg' => 'hosts_groups',
            ])
            ->where('hm.hostid=hg.hostid')
            ->andWhere(['hm.hostmacroid' => array_keys($result)])
            ->all();
        foreach ($rows as $relation) {
            $relationMap->addRelation($relation['hostmacroid'], $relation['groupid']);
        }

		switch ($option) {
			case 'selectGroups':
				$output_tag = 'groups';
				$entities = ['getHostGroups', 'getTemplateGroups'];
				break;

			case 'selectHostGroups':
				$entities = ['getHostGroups'];
				$output_tag = 'hostgroups';
				break;

			case 'selectTemplateGroups':
				$entities = ['getTemplateGroups'];
				$output_tag = 'templategroups';
				break;
		}

		$groups = [];
		foreach ($entities as $entity) {
			$groups += GroupHelper::$entity([
				'output' => $this->{$option},
				'groupids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
		}

		$result = $relationMap->mapMany($result, $groups, $output_tag);
	}

	protected function unsetExtraFields(array $objects, array $fields, $output = []): array {
		foreach ($objects as &$object) {
			if ($object['type'] == PRS_MACRO_TYPE_SECRET) {
				unset($object['value']);
			}
		}
		unset($object);

		return parent::unsetExtraFields($objects, $fields, $output);
	}
}
