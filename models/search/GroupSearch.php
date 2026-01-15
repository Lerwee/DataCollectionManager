<?php

namespace app\customs\zapi\models\search;

use app\common\base\BaseModel;
use app\common\helpers\SqlHelper;
use app\common\provider\ActiveDataProvider;
use app\modules\libzbx\models\zbx\HostsGroups;
use app\modules\libzbx\models\zbx\Hstgrp;
use yii\db\Query;

/**
 * @deprecated use [[app\customs\zapi\models\search\group\HostGroupSearch]] instead it
 */
class GroupSearch extends BaseModel
{
    /**
     * @var int[] 主机/模板ID集合
     */
    public $hostids;

    /**
     * @var int[] 分组ID集合
     */
    public $groupids;

    /**
     * @var integer 分组类型
     * - 0 - 主机分组（默认）
     * - 1 - 模板分组
     */
    public $type = 0;

    public $preserveKey = false;

    public $isPage = true;

    /**
     * @var array 查询字段
     */
    public $assignable = ['{{g}}.*'];

    protected $tables = [];

    public function rules(): array
    {
        return [
            [['type'], 'integer'],
            [['hostids', 'groupids'], 'each', 'rule' => ['integer']],
            [['preserveKey', 'isPage'], 'boolean'],
            [['assignable'], 'each', 'rule' => ['string']],
        ];
    }

    /**
     * @param array $params
     * @return ActiveDataProvider
     */
    public function search(array $params)
    {
        $this->setAttributes($params);

        $query = $this->getQuery();
        if ($this->validate()) {
            $this->queryWithCondition($query);
            $this->queryWithGroupBy($query);
            $query->from($this->tables);
            if ($this->preserveKey) {
                $query->indexBy('groupid');
            }
        } else {
            $query->where('1=0');
        }
        $query->select($this->assignable);

        if (count($query->from) > 1) {
            $query->distinct();
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query
        ]);

        if (!$this->isPage) {
            $dataProvider->setPagination(false);
        }

        return $dataProvider;
    }



    public function getQuery()
    {
        $query = new Query();
        $this->tables['g'] = Hstgrp::tableName();
        $query->from($this->tables);

        return $query;
    }

    /**
     * @param Query $query
     */
    public function queryWithCondition($query)
    {
        $query->where(['type' => $this->type]);

        if ($this->hostids) {
            $this->tables['hg'] = HostsGroups::tableName();
            $query->andWhere('g.groupid=hg.groupid')
                ->andWhere(SqlHelper::whereIn('{{hg}}.hostid', $this->hostids));
        }

        if ($this->groupids) {
            $query->andWhere(SqlHelper::whereIn('{{g}}.groupid', $this->groupids));
        }
    }

    /**
     * @param Query $query
     */
    public function queryWithGroupBy($query)
    {
    }
}
