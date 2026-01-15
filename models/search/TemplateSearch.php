<?php

namespace app\customs\zapi\models\search;

use app\common\base\BaseModel;
use app\common\helpers\ArrayHelper;
use app\common\helpers\SqlHelper;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\services\HostGroupService;
use app\modules\libzbx\models\Templates;
use app\modules\libzbx\models\zbx\Functions;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\HostsGroups;
use app\modules\libzbx\models\zbx\HostsTemplates;
use app\modules\libzbx\models\zbx\Items;
use app\modules\libzbx\models\zbx\Triggers;
use yii\db\Expression;
use yii\db\Query;

/**
 * 模板搜索模型
 *
 * @property string $keyword            关键词
 * @property int[]  $hostids            主机ID集合
 * @property int[]  $templateids        模板ID集合
 * @property int[]  $groupids           分组ID集合
 * @property int[]  $children_templateids 字模板ID集合
 * @property int[]  $parent_templateids 父模板ID集合
 * @property string $sortName           排序字段
 * @property string $sortOrder          排序方式
 * @deprecated use [[`app\customs\zapi\models\search\host\TemplateSearch`]] instead it
 */
class TemplateSearch extends BaseModel
{
    public $keyword;
    public $hostids;
    public $templateids;
    public $groupids;
    public $children_templateids;
    public $parent_templateids;
    public $sortName;
    public $sortOrder;

    public $selectTags = null;

    /**
     * @var bool 主键下标索引
     */
    public $preserveKey = false;

    /**
     * @var boolean 启用分页
     */
    public $isPage = true;

    /**
     * @var boolean 启用排序
     */
    public $enableOrderBy = true;

    /**
     * @var boolean 仅查询子模板(链接的模板)
     */
    public $queryChildrenOnly = false;

    /**
     * @var boolean 仅查询父模板(被链接的模板)
     * 与条件`$queryChildrenOnly`不能同时存在
     */
    public $queryParentOnly = false;

    /**
     * @var array 查询字段
     */
    public $assignable = ['t.hostid', 't.host', 't.name'];

    protected $tables = [];

    public function rules(): array
    {
        return [
            [['keyword'], 'string'],
            [['hostids', 'templateids', 'groupids', 'parent_templateids', 'children_templateids'], 'each', 'rule' => ['integer']],
            [['preserveKey', 'isPage', 'enableOrderBy', 'queryChildrenOnly', 'queryParentOnly'], 'boolean'],
            [['assignable', 'selectTags'], 'safe'],
            ['sortOrder', 'default', 'value' => 'asc'],
            ['sortOrder', 'in', 'range' => ['asc', 'desc']],
        ];
    }

    public function search(array $params = [])
    {
        $params && $this->setAttributes($params);

        $query = $this->getQuery();
        if ($this->validate()) {
            $this->queryWithCondition($query);
            $this->queryWithGroupBy($query);
            $query->from($this->tables);
            if ($this->preserveKey) {
                $query->indexBy('hostid');
            }
        } else {
            $query->where('1=0');
        }

        $query->select(['templateid' => 't.hostid'])
            ->distinct();
        $query->addSelect($this->assignable);
        $dataProvider = new ActiveDataProvider([
            'query' => $query
        ]);

        if (!$this->isPage) {
            $dataProvider->setPagination(false);
        }

        return $dataProvider;
    }

    /**
     * @return Query
     */
    public function getQuery()
    {
        $query = new Query();
        $this->tables['t'] = Templates::tableName();
        $query->from($this->tables);

        return $query;
    }

    /**
     * @param Query $query
     */
    public function queryWithCondition($query)
    {
        $query->where(['t.status' => 3]);

        // templateids
        if ($this->templateids) {
            $query->andWhere(SqlHelper::whereIn('{{t}}.hostid', $this->templateids));
        }

        // 主机ID集合与负模板ID集合不能同时存在作为条件
        if ($this->hostids) {
            $this->tables['ht'] = HostsTemplates::tableName();
            $query->andWhere('t.hostid=ht.templateid')
                ->andWhere(SqlHelper::whereIn('{{ht}}.hostid', $this->hostids));
        } else {
            if ($this->parent_templateids || $this->children_templateids) {
                $this->tables['ht'] = HostsTemplates::tableName();
                $query->andWhere('t.hostid=ht.hostid')
                    ->andWhere(SqlHelper::whereIn('{{ht}}.templateid', $this->parent_templateids ?: $this->children_templateids));
            }
        }

        if ($this->groupids) {
            $this->tables['hg'] = HostsGroups::tableName();
            $query->andWhere('t.hostid=hg.hostid')
                ->andWhere(SqlHelper::whereIn('{{hg}}.groupid', $this->groupids));
        }

        if ($this->keyword !== null && $this->keyword !== '') {
            $kwConditions = [
                'OR',
                [DB_LIKE, 't.host', $this->keyword], //hosts name
                [DB_LIKE, 't.name', $this->keyword], //hosts host
            ];
            $query->andWhere($kwConditions);
        }

        if ($this->queryChildrenOnly) {
            $subQuery = Hosts::find()
                ->select('hostid')
                ->where(['status' => [0, 1]]);
            $this->tables['ht'] = HostsTemplates::tableName();
            $query->andWhere('t.hostid=ht.templateid')
                ->andWhere(['NOT IN', 'ht.hostid', $subQuery]);
        } else {
            if ($this->queryParentOnly) {
                $subQuery = HostsTemplates::find()
                    ->alias('ht')
                    ->select(new Expression('NULL'))
                    ->where('ht.hostid=t.hostid');
                $query->andWhere(['EXISTS', $subQuery]);
            }
        }
    }

    /**
     * @param Query $query
     */
    public function queryWithGroupBy($query)
    {
        if (!$this->enableOrderBy) {
            return;
        }

        $sort = $this->sortOrder == 'desc' ? SORT_DESC : SORT_ASC;

        switch ($this->sortName) {
            case 'hostid':
                $orderBy = ['t.hostid' => $sort];
                break;
            case 'host':
                $orderBy = ['t.host' => $sort];
                break;
            default:
                $orderBy = ['t.name' => $sort];
                break;
        }
        $query->orderBy($orderBy);
    }

    public function format(array $models)
    {
        if (empty($models)) {
            return [];
        }
        $templateIds = array_column($models, 'hostid');
        $idWhereIn = SqlHelper::whereIn('hostid', $templateIds);
        $hosts = self::countHostsByTemplateIds($idWhereIn);
        $items = self::countItemsByTemplateIds($idWhereIn);
        $rules = self::countItemsByTemplateIds($idWhereIn, PRS_FLAG_DISCOVERY_RULE);
        $triggers = self::countTriggersByTemplateIds($idWhereIn);
        $groups = self::getGroupsByTemplateIds($templateIds);
        $sTemplates = self::getLinkTemplatesByTemplateIds($templateIds);
        $pTemplates = self::getLinkTemplatesByTemplateIds($templateIds, true);
        foreach ($models as &$model) {
            !$model['name'] && $model['name'] = $model['host'];
            $model['count_hosts'] = $hosts[$model['hostid']] ?? 0;
            $model['count_items'] = $items[$model['hostid']] ?? 0;
            $model['count_triggers'] = $triggers[$model['hostid']] ?? 0;
            $model['count_rules'] = $rules[$model['hostid']] ?? 0;
            $model['groups'] = $groups[$model['hostid']] ?? [];
            $model['children_templates'] = $sTemplates[$model['hostid']] ?? [];
            $model['parent_templates'] = $pTemplates[$model['hostid']] ?? [];
        }
        unset($model);
        if ($this->selectTags) {
            $models = array_values($this->showTags($models));
        }
        return $models;
    }

    public static function countHostsByTemplateIds($idWhereIn)
    {
        $query = new Query();
        $query->from([
            'h' => Hosts::tableName(),
            'ht' => HostsTemplates::tableName(),
        ]);
        $query->select(['total' => new Expression('COUNT(DISTINCT h.hostid)'), 'ht.templateid']);

        $query->where(str_replace('hostid', '{{ht}}.templateid', $idWhereIn))
            ->andWhere('h.hostid=ht.hostid')
            ->andWhere(['h.status' => [0, 1]])
            ->andWhere(['h.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]]);

        $query->groupBy('{{ht}}.templateid')
            ->indexBy('templateid');

        return $query->column();

    }

    /**
     * @param string $idWhereIn
     * @param int    $flag
     * @return array
     */
    public static function countItemsByTemplateIds($idWhereIn, $flag = PRS_FLAG_DISCOVERY_NORMAL): array
    {
        $query = Items::find();
        $query->select(['total' => 'count(1)', 'hostid']);
        $query->where($idWhereIn)
            ->andWhere(['flags' => $flag]);
        $query->groupBy('hostid')
            ->indexBy('hostid');

        return $query->column();
    }

    /**
     * @param string $idWhereIn
     * @return array
     */
    public static function countTriggersByTemplateIds($idWhereIn): array
    {
        $query = new Query();
        $query->from([
            't' => Triggers::tableName(),
            'f' => Functions::tableName(),
            'i' => Items::tableName(),
        ]);
        $query->select(['total' => new Expression('COUNT(DISTINCT t.triggerid)'), 'i.hostid']);

        $query->where(str_replace('hostid', '{{i}}.hostid', $idWhereIn))
            ->andWhere('f.triggerid=t.triggerid')
            ->andWhere('f.itemid=i.itemid')
            ->andWhere(['t.flags' => [PRS_FLAG_DISCOVERY_NORMAL, PRS_FLAG_DISCOVERY_CREATED]]);

        $query->groupBy('{{i}}.hostid')
            ->indexBy('hostid');

        return $query->column();
    }

    /**
     * 返回给定模板的分组数据集合
     *
     * @param array $templateIds
     * @return array
     */
    public static function getGroupsByTemplateIds(array $templateIds): array
    {
        $groups = HostGroupService::instance()->getGroups([
            'type' => HOST_GROUP_TYPE_TEMPLATE_GROUP,
            'hostids' => $templateIds,
            'assignable' => ['g.groupid', 'g.name', 'hg.hostid']
        ]);

        $data = [];
        foreach ($groups as $group) {
            $data[$group['hostid']][] = array_diff_key($group, ['hostid' => 1]);
        }
        unset($group);
        unset($groups);

        return $data;
    }

    /** 
     * 返回给定模板的链接模板集合
     *
     * @param array $templateIds
     * @param boolean $byLinked 被链接到
     * @return array
     */
    public static function getLinkTemplatesByTemplateIds(array $templateIds, bool $byLinked = false): array
    {
        $searcher = new static();
        $searcher->isPage = false;
        $searcher->enableOrderBy = false;
        if ($byLinked) {
            $searcher->assignable = ['t.host', 't.name', 'parent_templateid' => 'ht.templateid'];
            $searcher->parent_templateids = $templateIds;
        } else {
            $searcher->assignable = ['t.host', 't.name', 'parent_templateid' => 'ht.hostid'];
            $searcher->hostids = $templateIds;
        }
        $dataProvider = $searcher->search();
        $templates = $dataProvider->query->all();

        $data = [];
        foreach ($templates as $template) {
            $data[$template['parent_templateid']][] = array_diff_key($template, ['parent_templateid' => 1]);
        }
        return $data;
    }

    /**
     * @param array $models
     * @return array
     */
    protected function showTags(array $models): array
    {
        $rows = ArrayHelper::index($models, 'hostid');
        $templateIds = array_column($rows, 'hostid');
        foreach ($rows as &$row) {
            $row['tags'] = [];
        }
        unset($row);

        if ($this->selectTags === API_OUTPUT_EXTEND) {
            $output = ['hosttagid', 'hostid', 'tag', 'value'];
        }
        else {
            $output = array_unique(array_merge(['hosttagid', 'hostid'], $this->selectTags));
        }

        $db_tags = (new Query())->select($output)
            ->where(['hostid' => $templateIds])
            ->all();

        foreach ($db_tags as $db_tag) {
            $hostid = $db_tag['hostid'];
            unset($db_tag['hosttagid'], $db_tag['hostid']);
            $rows[$hostid]['tags'][] = $db_tag;
        }
        return $rows;
    }
}
