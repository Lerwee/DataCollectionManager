<?php

namespace app\customs\zapi\models\search;

use app\common\helpers\SqlHelper;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\modules\libzbx\models\zbx\Valuemap;
use app\modules\libzbx\models\zbx\ValuemapMapping;
use yii\db\Query;

class ValueMapSearch extends BaseSearch
{
    public $valuemapids = null;
    public $hostids = null;
    public $filter = null;
    public $search = null;
    public $searchByAny = null;
    public $startSearch = null;
    public $excludeSearch = null;
    public $searchWildcardsEnabled = null;
    // output
    public $selectMappings =	null;
    public $is_all = false;


    /**
     * @param  array $params
     * @return ActiveDataProvider
     */
    public function search(array $params =[])
    {
        $this->setAttributes($params);

        $query = (new Query())
            ->from(['vm' => Valuemap::tableName()]);

        $provider = new ActiveDataProvider([
            'query' => $query
        ]);

        if ($this->is_all) {
            $provider->setPagination(false);
        }

        if ($this->hostids !== null) {
            $query->andWhere(SqlHelper::whereIn('vm.hostid', filter_integer((array) $this->hostids)));
        }

        $this->applyQueryOutputOptions($query, Valuemap::tableName(), 'vm', $this->countOutput ? 'count' : $this->output);
        $this->applyQuerySortOptions($query, Valuemap::tableName(), 'vm', $this->sortfield, $this->sortorder);

        if ($this->countOutput) {
            return $provider;
        }

        if ($this->preservekeys) {
            if (!is_array($this->output) || in_array('valuemapid', $this->output)) {
                $query->addSelect(['vm.valuemapid']);
            }
            $query->indexBy('valuemapid');
        }

        $models = $this->format($provider->getModels());
        $provider->setModels($models);
        return $provider;
    }

    protected function format(array $models)
    {
        if ($models) {
            $models = $this->addRelatedObjects($models);
			$models = $this->unsetExtraFields($models, ['valuemapid'], $this->output);
		}
        return $models;
    }

    protected function addRelatedObjects(array $models) {
		$models = parent::addRelatedObjects($models);

		// Select mappings for value map.
		if ($this->selectMappings !== null) {
			$def_mappings = ($this->selectMappings == API_OUTPUT_COUNT) ? '0' : [];

			foreach ($models as $valuemapid => $model) {
				$models[$valuemapid]['mappings'] = $def_mappings;
			}
            $query = new Query();
            $query->from([ValuemapMapping::tableName()]);
            $query->where(SqlHelper::whereIn('valuemapid', array_column($models, 'valuemapid')));
			if ($this->selectMappings == API_OUTPUT_COUNT) {
                $query->select([
                    'valuemapid',
                    'cnt' => 'count(*)',
                ]);
                $query->groupBy('valuemapid');
				$mappings = $query->all();
                
				foreach($mappings as $mapping) {
					$models[$mapping['valuemapid']]['mappings'] = $mapping['cnt'];
				}
			}
			else {
                $query->select($this->outputExtend($this->selectMappings, ['valuemapid', 'valuemap_mappingid', 'sortorder']));
                $mappings = $query->all();
				CArrayHelper::sort($mappings, [['field' => 'sortorder', 'order' => PRS_SORT_UP]]);

				foreach ($mappings as $mapping) {
					$valuemapid = $mapping['valuemapid'];
					unset($mapping['valuemap_mappingid'], $mapping['valuemapid'], $mapping['sortorder']);

					$models[$valuemapid]['mappings'][] = $mapping;
				}
			}
		}
		return $models;
	}
}