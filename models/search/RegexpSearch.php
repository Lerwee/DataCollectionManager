<?php

namespace app\customs\zapi\models\search;

use app\common\provider\ActiveDataProvider;
use app\modules\libzbx\models\zbx\Regexps;
use yii\db\Exception;
use yii\db\Query;

/**
 * Class RegexpSearch
 * @package app\customs\zapi\models\search
 */
class RegexpSearch extends BaseSearch
{
    public $regexpids = null;
    public $keyword = null;

    // output
    public $selectExpressions = null;

    public $is_all = false;

    /**
     * @param array $params
     * @return ActiveDataProvider
     * @throws Exception
     */
    public function search(array $params = []): ActiveDataProvider
    {
        $this->load($params, '');
        $query = (new Query())->from(['r' => Regexps::tableName()])
            ->select(['r.regexpid'])
            ->orderBy(['name' => SORT_ASC])
            ->indexBy('regexpid');

        $provider = new ActiveDataProvider([
            'query' => $query
        ]);
        if ($this->is_all) {
            $provider->setPagination(false);
        }
        // regexpids
        if (!is_null($this->regexpids)) {
            $query->andWhere(['r.regexpid' => filter_integer((array)$this->regexpids)]);
        }

        // regexpids
        if (!is_null($this->keyword)) {
            $query->andWhere(['like', 'upper(r.name)', strtoupper($this->keyword)]);
        }

        $this->applyQueryOutputOptions($query, Regexps::tableName(), 'r', $this->countOutput ? 'count' : $this->output);
        $this->applyQuerySortOptions($query, Regexps::tableName(), 'r', $this->sortfield, $this->sortorder);

        $db_regexs = $provider->getModels();
        if ($db_regexs) {
            $db_regexs = $this->addRelatedObjects($db_regexs);
            $db_regexs = $this->unsetExtraFields($db_regexs, ['regexpid'], $this->output);

            if (!$this->preservekeys) {
                $db_regexs = array_values($db_regexs);
            }
            $provider->setModels($db_regexs);
        }

        return $provider;
    }

    protected function addRelatedObjects(array $db_regexs): array
    {
        $db_regexs = parent::addRelatedObjects($db_regexs);

        if ($this->selectExpressions !== null) {
            foreach ($db_regexs as &$db_regex) {
                $db_regex['expressions'] = [];
            }
            unset($db_regex);

            $db_expressions = (new Query())->from(['expressions'])
                ->select($this->outputExtend($this->selectExpressions, ['regexpid']))
                ->where(['regexpid' => array_keys($db_regexs)])
                ->all();

            foreach ($db_expressions as $db_expression) {
                $regexpid = $db_expression['regexpid'];
                unset($db_expression['expressionid'], $db_expression['regexpid']);

                $db_regexs[$regexpid]['expressions'][] = $db_expression;
            }
        }

        return $db_regexs;
    }
}