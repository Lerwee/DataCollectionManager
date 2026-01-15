<?php

namespace app\customs\zapi\controllers;

use app\common\base\BaseController;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\forms\ImportForm;
use app\customs\zapi\services\HostGroupService;
use app\customs\zapi\services\TemplateService;
use Yii;
use yii\web\Response;

class TemplateController extends BaseController
{
    protected $restfulActions = [
        'create',
        'update',
        'delete',
        'import',
        'export',
        'parsing',
    ];

    /**
     * 模板列表
     *
     * @return Response
     */
    public function actionList(): Response
    {
        $params = Yii::$app->request->get();
        return $this->autoReturn(TemplateService::instance()->getList($params));
    }

    /**
     * 模板新增
     * 
     * @return Response
     */
    public function actionCreate(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(TemplateService::instance()->create($params, false));
    }

    /**
     * 模板修改
     *
     * @return Response
     */
    public function actionUpdate(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(TemplateService::instance()->update($params, false));
    }

    /**
     * 模板删除
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(TemplateService::instance()->delete($params, false));
    }

    /**
     * 筛选项
     *
     * @return Response
     */
    public function actionProfile(): Response
    {
        if ('popup.import' == Yii::$app->request->get('action')) {
            $rules = ImportForm::getDefaultParams($params['rules_preset'] ?? 'template');
            $requestRules = $rules;
		    $requestRules += array_fill_keys(array_keys($rules), []);
            $options = array_fill_keys(['updateExisting', 'createMissing', 'deleteMissing'], 1);
            $filterRules = ['templateDashboards', 'graphs', 'mediaTypes', 'maps', 'images'];
            foreach ($requestRules as $ruleName => $rule) {
                if (in_array($ruleName, $filterRules)) {
                    unset($requestRules[$ruleName]);
                    continue;
                }
                $requestRules[$ruleName] = array_map('intval', $rule);
            }
            unset($rule);
 
            return $this->success($options + $requestRules);
        }

        return $this->success([
            'children_templates' => TemplateHelper::getLinkedTemplates(),
            'groups' => HostGroupService::instance()->getGroups([
                'type' => HOST_GROUP_TYPE_TEMPLATE_GROUP
            ], ['groupid', 'name'])
        ]);
    }

    /**
     * 筛选项
     *
     * @return Response
     */
    public function actionForm(): Response
    {
        $templateId = (int)Yii::$app->request->get('templateid');
        return $this->autoReturn(TemplateService::instance()->getForm($templateId));
    }

    /**
     * 导出
     *
     * @return Response
     */
    public function actionExport(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(TemplateService::instance()->export($params));
    }

    /**
     * 导入
     *
     * @return Response
     */
    public function actionImport(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(TemplateService::instance()->import($params));
    }

    /**
     * 下载
     */
    public function actionDownload()
    {
        $hash = (string) Yii::$app->request->get('hash');
        TemplateService::instance()->exportForce($hash);
    }

    /**
     * 加密模板解析
     *
     * @return Response
     */
    public function actionParsing(): Response
    {
        $params = Yii::$app->request->post();
        return $this->autoReturn(TemplateService::instance()->parsing($params));
    }
}
