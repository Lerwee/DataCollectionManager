<?php

namespace app\customs\zapi\actions\general;

use app\common\base\BaseAction;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\models\Settings;
use Yii;
use yii\db\Query;
use yii\web\Response;

/**
 * 常规设置
 */
class GeneralGetAction extends BaseAction
{
    public function run(): Response
    {
        $fields = $this->getFormFields();

        $data = (new Query())->from('config')
            ->select($fields)->one();

        $defaults = [];

        foreach($fields as $field) {
            $defaults[$field] = DB::getDefault('config', $field);
        }

        return $this->success([
            'form' => $data,
            'options' => $this->getFormOptions(),
            'default' => $defaults
        ]);
    }

    /**
     * 表单属性字段
     *
     * @return array
     */
    protected function getFormFields():array
    {
        return Settings::getOutputFields();
    }

    /**
     * 表单选项数据
     *
     * @return array
     */
    protected function getFormOptions():array
    {
        return [];
    }
}