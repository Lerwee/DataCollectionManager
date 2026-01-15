<?php

namespace app\customs\zapi\models\search\item;

use app\common\helpers\ArrayHelper;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\models\search\BaseSearch;
use app\modules\libzbx\models\Hosts;
use yii\db\Exception;
use yii\db\Query;

/**
 * Class BaseItemSearch
 * @package app\customs\zapi\models\search\item
 */
class BaseItemSearch extends BaseSearch
{
    public $preservekeys = false;
    // output
    public $output = API_OUTPUT_EXTEND;
    public $countOutput = false;
    public $groupCount = false;
    public $selectHosts = null;
    public $selectInterfaces = null;
    public $selectTags = null;
    public $selectTriggers = null;
    public $selectGraphs = null;
    public $selectDiscoveryRule = null;
    public $selectItemDiscovery = null;
    public $selectPreprocessing = null;
    public $selectValueMap = null;
    public $sortfield = '';
    public $sortorder = '';
    public $limitSelects = '';
    public $is_all = false;

    /**
     * @param Query $query
     * @param string $tableName
     * @param string $tableAlias
     * @param string|array $outPut
     * @param array $options
     * @throws Exception
     */
    protected function applyQueryOutputOptions(Query &$query, string $tableName, string $tableAlias, $outPut = 'extent', array $options = [])
    {
        if ($outPut != 'count' && self::dbDistinct($query)) {
            $schema = DB::getSchema($tableName);
            $nclob_fields = [];

            foreach ($schema['fields'] as $field_name => $field) {
                if ($field['type'] == DB::FIELD_TYPE_NCLOB
                    && $this->outputIsRequested($field_name, $outPut)) {
                    $nclob_fields[] = $field_name;
                }
            }
            if ($nclob_fields) {
                $output = ($outPut === API_OUTPUT_EXTEND) ? array_keys($schema['fields']) : $outPut;
                $outPut = array_diff($output, $nclob_fields);
            }
        }

        parent::applyQueryOutputOptions($query, $tableName, $tableAlias, $outPut, $options);
    }

    /**
     * @param array $models
     * @return array
     */
    public function addRelations(array $models): array
    {
        $models = ArrayHelper::index($models, 'itemid');
        $itemIds = ArrayHelper::getColumn($models, 'itemid');
        // adding hosts
        if ($this->selectHosts !== null && $this->selectHosts != API_OUTPUT_COUNT) {
            $relationMap = $this->createRelationMap($models, 'itemid', 'hostid');
            $columns = $this->selectHosts == API_OUTPUT_EXTEND ? '*' : $this->selectHosts;
            $hosts = Hosts::find()->select($columns)
                ->addSelect('hostid')
                ->where(['hostid' => $relationMap->getRelatedIds()])
                ->andWhere(['status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE]])
                ->indexBy('hostid')
                ->asArray()->all();
            $models = $relationMap->mapMany($models, $hosts, 'hosts');
        }

        // adding preprocessing
        if ($this->selectPreprocessing !== null && $this->selectPreprocessing != API_OUTPUT_COUNT) {
            $itemProcList = (new Query())->select($this->outputExtend($this->selectPreprocessing, ['itemid', 'step']))
                ->from('item_preproc')
                ->where(['itemid' => $itemIds])
                ->all();
            foreach ($models as &$model) {
                $model['preprocessing'] = [];
            }
            unset($model);
            foreach ($itemProcList as $itemProc) {
                $itemId = $itemProc['itemid'];
                unset($itemProc['item_preprocid'], $itemProc['itemid'], $itemProc['step']);

                if (array_key_exists($itemId, $models)) {
                    $models[$itemId]['preprocessing'][] = $itemProc;
                }
            }
        }

        // Add value mapping.
        if (($this instanceof ItemPrototypeSearch || $this instanceof ItemSearch) && $this->selectValueMap !== null) {
            if ($this->selectValueMap === API_OUTPUT_EXTEND) {
                $this->selectValueMap = ['valuemapid', 'name', 'mappings'];
            }

            foreach ($models as &$model) {
                $model['valuemap'] = [];
            }
            unset($model);


            $valuemaps = (new Query())->select($this->outputExtend($this->selectPreprocessing, ['itemid', 'step']))
                ->from('valuemap')
                ->where(['valuemapid' => array_keys(array_flip(array_column($models, 'valuemapid')))])
                ->indexBy('valuemapid')
                ->all();

            if ($this->outputIsRequested('mappings', $this->selectValueMap) && $valuemaps) {

                $mappings = (new Query())->select(['valuemapid', 'type', 'value', 'newvalue'])
                    ->from('valuemap_mapping')
                    ->where(['valuemapid' => array_keys($valuemaps)])
                    ->orderBy(['sortorder' => SORT_ASC])
                    ->all();

                foreach ($mappings as $mapping) {
                    $valuemaps[$mapping['valuemapid']]['mappings'][] = [
                        'type' => $mapping['type'],
                        'value' => $mapping['value'],
                        'newvalue' => $mapping['newvalue']
                    ];
                }
            }

            foreach ($models as &$item) {
                if (array_key_exists('valuemapid', $item) && array_key_exists($item['valuemapid'], $valuemaps)) {
                    $item['valuemap'] = array_intersect_key($valuemaps[$item['valuemapid']],
                        array_flip($this->selectValueMap)
                    );
                }
            }
            unset($item);
        }

        if ($this->output != 'count' && $this->outputIsRequested('parameters', $this->output)) {
            $itemParameters = (new Query())->select(['ip.itemid', 'ip.name', 'ip.value'])
                ->from(['ip' => 'item_parameter'])
                ->where(['ip.itemid' => array_keys($models)])
                ->all();

            foreach ($models as &$item) {
                $item['parameters'] = [];
            }
            unset($item);

            foreach ($itemParameters as $itemParameter) {
                $models[$itemParameter['itemid']]['parameters'][] = [
                    'name' => $itemParameter['name'],
                    'value' => $itemParameter['value']
                ];
            }
        }

        return array_values($models);
    }

    /**
     * Add NCLOB type fields if there was DISTINCT in query.
     *
     * @param array $result Query results.
     * @param mixed $output    Array of query options.
     *
     * @return array    The result array with added NCLOB fields.
     */
    protected function addNclobFieldValues(array $result, $output): array
    {
        $schema = DB::getSchema('items');
        $nclob_fields = [];

        foreach ($schema['fields'] as $field_name => $field) {
            if ($field['type'] == DB::FIELD_TYPE_NCLOB && $this->outputIsRequested($field_name, $output)) {
                $nclob_fields[] = $field_name;
            }
        }

        if (!$nclob_fields) {
            return $result;
        }
        $pk = $schema['key'];

        $nclob_fields[] = $pk;
        $query = (new Query())
            ->from('items')
            ->select($nclob_fields)
            ->where(SqlHelper::whereIn($pk, array_column($result, $pk)));
 
        $fieldValues = $query->indexBy($pk)->all();

        foreach($result as &$item) {
            $item += array_diff_key($fieldValues[$item[$pk]], [$pk => 1]);
        }
        unset($item);
        return $result;
    }

    /**
     * fmt query_fields
     * @param  array $result
     * @return array
     */
    protected function formatQueryFields(array $result)
    {
        // Decode ITEM_TYPE_HTTPAGENT encoded fields.
		foreach ($result as &$item) {
			if (array_key_exists('query_fields', $item)) {
				$query_fields = ($item['query_fields'] !== '') ? json_decode($item['query_fields'], true) : [];
				$item['query_fields'] = json_last_error() ? [] : $query_fields;
			}

			if (array_key_exists('headers', $item)) {
				$item['headers'] = ItemHelper::headersStringToArray($item['headers']);
			}
		}
		unset($item);
        return $result;
    }
}