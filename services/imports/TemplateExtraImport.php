<?php

namespace app\customs\zapi\services\imports;

use app\common\base\BaseService;
use app\common\components\Result;
use app\common\helpers\ArrayHelper;
use app\common\helpers\AuthHelper;
use app\common\helpers\SqlHelper;
use app\modules\libzbx\models\Defines;
use app\modules\libzbx\models\TemplateConf;
use app\modules\libzbx\models\TemplateExtra;
use app\modules\libzbx\models\Templates;
use app\modules\libzbx\models\Triggers;
use app\modules\magpie\models\NestleCard;
use app\modules\magpie\models\TemplateConfig;
use app\modules\magpie\services\NestleService;
use yii\db\Query;
use Yii;

class TemplateExtraImport extends BaseService
{
    protected $data;

    protected $host2id = [];

    private $auditData;

    /**
     * @param  array $params
     * @return $this
     */
    public function load(array $params)
    {
        $this->data = $params;
        return $this;
    }

    public function import(): Result
    {
        if (empty($this->data['migrates'])) {
            return $this->success();
        }

        $this->host2id = Templates::find()->select(['hostid', 'host'])
            ->where(['host' => array_column($this->data['migrates'], 'host')])
            ->andWhere(['status' => Defines::PRS_HOST_STATUS_TEMPLATE])
            ->indexBy('host')
            ->column();

        if (empty($this->host2id)) {
            return $this->error(10000404, 'templates not found.');
        }

        $this->auditData = [];

        $this->migrate();
        $this->nestle();
        $this->helper();

        if ($this->auditData) {
            foreach($this->auditData as $auditData) {
                $this->auditUpdate(RESOURCE_ZAPI, $auditData[0], $auditData[1], $auditData[2]);
            }
        }

        return $this->success();
    }

    /**
     * 迁移模板
     *
     * @return Result
     */
    public function migrate(): Result
    {
        $templates = $this->formatMigrates($this->data['migrates']);
        // if (empty($templates)) {
        //     return $this->error(10000404, 'Template matching failed.');
        // }

        /** @var TemplateConf[] */
        $models = TemplateConf::find()
            ->where(SqlHelper::whereIn('hostid', array_keys($templates)))
            ->indexBy('hostid')
            ->all();

        $data = [
            'success' => [],
            'failure' => []
        ];

        
        $msg = Yii::t('magpie', 'Template migrated successfully');

    
        foreach ($templates as $id => $template) {
            if (array_key_exists($id, $models)) {
                continue;
            }

            
            $model = new TemplateConf();
            $model->setAttributes($template);
            if ($model->save()) {
                // 迁移相关主机
                $model->renewHostDetail();
                $data['success'][] = $id;

                $this->auditData[] = [[$model->hostid => (string)(@$template['name'] ?: @$template['host'])], $msg, null];
            } else {
                $data['failure'][] = $id;

            }
        }
        return $this->success($data);
    }

    /**
     * 写入帮助说明
     *
     * @return Result
     */
    public function helper(): Result
    {
        if (empty($this->data['helpers'])) {
            return $this->success();
        }

        $userId = AuthHelper::getCurrentUserId(1);

        $id2id = TemplateExtra::find()
            ->where(SqlHelper::whereIn('template_id', array_values($this->host2id)))
            ->select(['template_id'])
            ->indexBy('template_id')
            ->column();

        $msg = Yii::t('magpie', 'Example saved successfully');

        $helpers = [];
        foreach ($this->data['helpers'] as $helper) {
            if (empty($helper['template_host']) || !array_key_exists($helper['template_host'], $this->host2id)) {
                continue;
            }

            $id = $this->host2id[$helper['template_host']];
            // 已存在的说明，不做更新
            if (array_key_exists($id, $id2id)) {
                continue;
            }

            $helpers[$id] = array_intersect_key($helper, array_flip(['example', 'help_text', 'status'])) + [
                'updated_by' => $userId,
                'template_id' => $id
            ];

            
            $this->auditData[] = [[$id => (string)($helper['template_name'] ?? $helper['template_host'])], $msg, null];
        }

        if ($helpers) {
            $columns = array_keys(current($helpers));
            TemplateExtra::getDb()->createCommand()
                ->batchInsert(TemplateExtra::tableName(), $columns, $helpers)
                ->execute();
        }

        return $this->success();
    }

    /**
     * 配置详情卡片
     *
     * @return Result
     */
    public function nestle(): Result
    {
        if (empty($this->data['nestles'])) {
            return $this->success();
        }

        /** @var TemplateConfig[] $templates */
        $templates = ArrayHelper::index(TemplateConfig::findByTemplateIds(array_values($this->host2id)), 'host');

        $hasNestles = NestleCard::find()->select('COUNT(1)')->groupBy('template_id')->indexBy('template_id')->column();

        foreach ($this->data['nestles'] as $data) {
            $host = $data['template']['host'];
            if (!array_key_exists($host, $templates)) {
                continue;
            }
            $template = $templates[$host];

            // 忽略已配置卡片
            if (array_key_exists($template->hostid, $hasNestles)) {
                continue;
            }
            NestleService::instance()->importDataByTemplate($template, $data);
        }
        return $this->success();
    }

    protected function formatMigrates(array $params)
    {
        $templates = [];
        foreach ($params as $param) {
            if (empty($param['host']) || !array_key_exists($param['host'], $this->host2id)) {
                continue;
            }
            $templateId = $this->host2id[$param['host']];
            // 绑定触发器
            $triggerIds = [];

            if (array_key_exists('triggers', $param)) {
                if ($param['triggers']) {
                    $subQuery = (new Query());
                    $subQuery->select('{{f}}.triggerid')
                        ->from(['f' => 'functions', 'i' => 'items'])
                        ->where('{{f}}.itemid={{i}}.itemid')
                        ->andWhere(['i.hostid' => $templateId])
                        ->groupBy('{{f}}.triggerid');
                    $query = Triggers::find()
                        ->select('triggerid')
                        ->where(['triggerid' => $subQuery])
                        ->andWhere(['description' => $param['triggers']]);
                    $triggerIds = $query->column();
                }
                unset($param['triggers']);
            }

            $templates[$templateId] = [
                'hostid' => $templateId, // 模板id
                'triggerid' => implode(',', $triggerIds)
            ] + $param;
        }
        return $templates;
    }
}
