<?php

namespace app\customs\zapi\common\helpers;

use app\customs\zapi\models\search\host\TemplateSearch;
use yii\db\Query;

class TemplateHelper
{
    /**
     * 获取模板数据
     *
     * @param  array $params
     * @return array|int
     */
    public static function getTemplates(array $params)
    {
        $searcher = new TemplateSearch();
        $searcher->is_all = true;
        $dataProvider = $searcher->search($params);
        if ($searcher->countOutput) {
            $models = $dataProvider->getModels();
            if ($searcher->groupCount) {
                return $models;
            } else {
                return empty($models) ? 0 : current($models)['rowscount'];
            }
        }
        return $dataProvider->getModels();
    }

    /**
     * 模板仪表盘数据
     *
     * @todo TODO:
     * @param  array $params
     * @return array
     */
    public static function getDashboards(array $params): array
    {
        return [];
    }

    /**
     * 返回被链接的模板数据（子模板）
     *
     * @param  array $params
     * @return array
     */
    public static function getLinkedTemplates(array $params = []): array
    {
        $query = new Query();
        $query->from([
            't' => 'hosts',
            'ht' => 'hosts_templates'
        ]);

        $query->where('t.hostid=ht.templateid')
            ->andWhere(['t.status' => 3])
            ->andWhere(['NOT IN', 'ht.hostid', (new Query())->from('hosts')->select('hostid')->where(['status' => [0, 1]])]);

        $query->select(['templateid' => 't.hostid'])
            ->addSelect(['name'])
            ->distinct();

        $query->orderBy('t.name');

        return $query->all();
    }
}
