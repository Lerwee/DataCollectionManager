<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\common\helpers\SecurityHelper;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\export\writers\CExportWriterFactory;
use app\customs\zapi\common\helpers\AuditHelper;
use app\customs\zapi\common\helpers\CuidHelper;
use app\customs\zapi\common\helpers\DiscoverRuleHelper;
use app\customs\zapi\common\helpers\GroupHelper;
use app\customs\zapi\common\helpers\HttpTestHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\helpers\MacroHelper;
use app\customs\zapi\common\helpers\TemplateHelper;
use app\customs\zapi\common\helpers\TriggerHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\import\readers\CImportReaderFactory;
use app\customs\zapi\components\data\TemplateRequestData;
use app\customs\zapi\forms\hosts\TemplateForm;
use app\customs\zapi\forms\ImportForm;
use app\customs\zapi\models\search\TemplateSearch;
use app\customs\zapi\services\assist\DiscoverRuleAssist;
use app\customs\zapi\services\exports\TemplateExport;
use app\customs\zapi\services\hosts\HostGeneralService;
use app\customs\zapi\services\imports\TemplateExtraImport;
use app\customs\zapi\services\imports\TemplateImport;
use app\modules\libzbx\models\Templates;
use app\modules\libzbx\models\zbx\Hostmacro;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\HostsGroups;
use app\modules\libzbx\models\zbx\HostsTemplates;
use app\modules\libzbx\models\zbx\HostTag;
use app\modules\libzbx\models\zbx\LldOverrideOpdiscover;
use app\modules\libzbx\models\zbx\LldOverrideOperation;
use app\modules\libzbx\models\zbx\LldOverrideOpinventory;
use app\modules\libzbx\models\zbx\LldOverrideOpstatus;
use app\modules\libzbx\models\zbx\LldOverrideOptemplate;
use app\modules\libzbx\models\zbx\Valuemap;
use Yii;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;
use ZipArchive;
use \yii\base\Exception;

/**
 * Class TemplateService
 * @package app\customs\zapi\services
 */
class TemplateService extends HostGeneralService
{
    public function getList(array $params = []): Result
    {
        $searcher = new TemplateSearch();
        $dataProvider = $searcher->search($params);

        return $this->success([
            'rows' => $searcher->format($dataProvider->getModels()),
            'total' => $dataProvider->getTotalCount()
        ]);
    }

    /**
     * create
     *
     * @param array $params
     * @return Result
     */
    public function create(array $params, bool $internal = true): Result
    {
        $request = null;
        if (!$internal) {
            $request = new TemplateRequestData(['data' => $params, 'action' => __FUNCTION__]);
            if (!$request->isSuccess()) {
                return $request->getResult();
            }
            $params = $request->getData();
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = $this->createByInternal($params);
            if ($result->isSuccess()) {
                $result->setErrmsg(Yii::t('msg', 'Create Success'));
                $templateId = current(current($result->getData()));
                array_key_exists('valuemaps', $params) && $this->renewValueMaps($templateId, $params['valuemaps']);

                // 克隆对象，需要克隆监控项、触发器、发现规则等
                if ($request && $request->templateId) {
                    /*
                     * First copy web scenarios with web items, so that later regular items can use web item as their master item.
                     */
                    $cResult = HttpTestHelper::copyHttpTests($request->templateId, $templateId);
                    if (!$cResult->isSuccess()) {
                        return $cResult;
                    }

                    $dst_templates = [$templateId => $params + ['status' => HOST_STATUS_TEMPLATE]];
                    $cResult = ItemHelper::copyItemsToHosts('templateids', [$request->templateId], $dst_templates);
                    if (!$cResult->isSuccess()) {
                        return $cResult;
                    }

                    // copy triggers
                    $cResult = TriggerHelper::copyTriggersToHosts([$templateId], $request->templateId);
                    if (!$cResult->isSuccess()) {
                        return $cResult;
                    }

                    // copy graphs

                    // copy discovery rules
                    $dbDiscoveryRules = DiscoverRuleHelper::getDiscoverRules([
                        'output' => ['itemid'],
                        'hostids' => $request->templateId,
                        'inherited' => false
                    ]);

                    if ($dbDiscoveryRules) {
                        $cResult = DiscoverRuleAssist::instance()->copy([
                            'discoveryids' => prs_objectValues($dbDiscoveryRules, 'itemid'),
                            'hostids' => [$templateId]
                        ]);
                        if (!$cResult->isSuccess()) {
                            return $cResult;
                        }
                    }

                    // Copy template dashboards.

                }
                $transaction->commit();
                AuditHelper::save($result->getErrmsg(), AuditHelper::ACTION_CREATE);
            } else {
                $transaction->rollBack();
            }

        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->errorException($e, 60750103);
        }

        return $result;
    }

    /**
     * create(内部调用)
     *
     * @return Result
     */
    public function createByInternal(array $params): Result
    {
        $rules = TemplateForm::getValidationRules();
        $bool = ValidateHelper::validateObjects($params, $rules, [
            'flags' => API_NOT_EMPTY | API_NORMALIZE,
            'uniq' => [['uuid'], ['templateid'], ['host'], ['name']]
        ], $error);
        if (!$bool) {
            return $this->error(error_code(60750001), $error);
        }

        try {
            TemplateForm::checkVendorFields($params);
            CuidHelper::addUUID($params);
            // 检查UUID是存在
            TemplateForm::checkFieldDuplicates($params, null, 'uuid', 'hostid', ['status' => HOST_STATUS_TEMPLATE]);
            // 检查host、name
            TemplateForm::checkDuplicates($params);
            TemplateForm::checkGroups($params);
            TemplateForm::checkTemplates($params);
        } catch (Exception $e) {
            return $this->errorException($e);
        }

        $templates = [];

        foreach ($params as $param) {
            unset($params['groups'], $param['templates'], $param['tags'], $param['macros']);

            $templates[] = $param + ['status' => HOST_STATUS_TEMPLATE];
        }

        $templateIds = DB::insert(Hosts::tableName(), $templates);
        foreach ($params as $index => &$param) {
            $param['templateid'] = $templateIds[$index];
        }
        $this->checkTemplatesLinks($params);
        $this->updateGroups($params);
        $this->updateTags($params);
        $this->updateMacros($params);
        $this->updateTemplates($params);
        foreach ($templates as $index => $template) {
            $tid = $templateIds[$index];
            AuditHelper::collect([$tid => $template['name']]);
            AuditHelper::collectDetails($tid, $param);
        }

        return $this->success(['templateids' => $templateIds]);
    }

    public function update(array $params, bool $internal = true): Result
    {
        if (!$internal) {
            $request = new TemplateRequestData(['data' => $params, 'action' => __FUNCTION__]);
            if (!$request->isSuccess()) {
                return $request->getResult();
            }
            $params = $request->getData();
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $tResult = $this->updateByInternal($request->getData());
            if ($tResult->isSuccess()) {
                $tResult->setErrmsg(Yii::t('msg', 'Update Success'));
                if (array_key_exists('valuemaps', $params)) {
                    $templateId = current(current($tResult->getData()));
                    $this->renewValueMaps($templateId, $params['valuemaps']);
                }
                $transaction->commit();
                AuditHelper::save($tResult->getErrmsg(), AuditHelper::ACTION_UPDATE);
            } else {
                $transaction->rollBack();
            }
        } catch (Exception $e) {
            $transaction->rollBack();
            Yii::error(parse_exception($e));
            return $this->errorException($e, 60750102);
        }

        return $tResult;
    }

    public function updateByInternal(array $params): Result
    {
        $rules = TemplateForm::getValidationRules('update');
        $bool = ValidateHelper::validateObjects($params, $rules, [
            'flags' => API_NOT_EMPTY | API_NORMALIZE,
            'uniq' => [['templateid'], ['host'], ['name']]
        ], $error);
        if (!$bool) {
            return $this->error(error_code(10000021), $error);
        }

        $dbTemplates = TemplateHelper::getTemplates([
            'output' => ['uuid', 'templateid', 'host', 'name', 'description', 'vendor_name', 'vendor_version'],
            'templateids' => array_column($params, 'templateid'),
            'editable' => true,
            'preservekeys' => true
        ]);

        if (count($params) != count($dbTemplates)) {
            return $this->error(10000404);
        }

        try {
            $this->addAffectedObjects($params, $dbTemplates);

            TemplateForm::checkVendorFields($params, $dbTemplates);
            TemplateForm::checkFieldDuplicates($params, $dbTemplates, 'uuid', 'templateid', ['status' => HOST_STATUS_TEMPLATE]);
            // 检查host、name
            TemplateForm::checkDuplicates($params, $dbTemplates, 'templateid');
            TemplateForm::checkGroups($params, $dbTemplates, 'templateid');
            TemplateForm::checkTemplates($params, $dbTemplates, 'templateid');
            $this->checkTemplatesLinks($params, $dbTemplates);
            $params = $this->validateHostMacros($params, $dbTemplates);
        } catch (Exception $e) {
            return $this->errorException($e);
        }

        $updateTemplates = [];

        foreach ($params as $param) {
            $updateTemplate = DB::getUpdatedValues(Hosts::tableName(), $param, $dbTemplates[$param['templateid']]);

            AuditHelper::collect([$param['templateid'] => $param['name'] ?? $dbTemplates[$param['templateid']]['name']]);
            if ($updateTemplate) {
                $updateTemplates[] = [
                    'values' => $updateTemplate,
                    'where' => ['hostid' => $param['templateid']]
                ];
                AuditHelper::collectDetails($param['templateid'], $updateTemplate, $dbTemplates[$param['templateid']]);
            }
        }

        if ($updateTemplates) {
            DB::update(Hosts::tableName(), $updateTemplates);
        }
        $this->updateGroups($params, $dbTemplates);
        $this->updateTags($params, $dbTemplates);
        $this->updateMacros($params, $dbTemplates);
        $this->updateTemplates($params, $dbTemplates);

        return $this->success(['templateids' => array_column($params, 'templateid')]);
    }

    protected function renewValueMaps(int $templateId, array $valueMaps)
    {
        $insValueMaps = [];
        $updValueMaps = [];
        $delValueMaps = [];

        $delValueMaps = Valuemap::find()->where(['hostid' => $templateId])->indexBy('valuemapid')->asArray()->all();

        foreach ($valueMaps as $valueMap) {
            if (array_key_exists('valuemapid', $valueMap)) {
                $updValueMaps[] = $valueMap;
                unset($delValueMaps[$valueMap['valuemapid']]);
            } else {
                $insValueMaps[] = $valueMap + ['hostid' => $templateId];
            }
        }

        if ($updValueMaps) {
            $result = ValueMapService::instance()->update($updValueMaps);
            if (!$result->isSuccess()) {
                return $result;
            }
            AuditHelper::collectDetail($templateId, 'value_mapping:update', $insValueMaps, $delValueMaps);
        }

        if ($insValueMaps) {
            $result = ValueMapService::instance()->create($insValueMaps);
            if (!$result->isSuccess()) {
                return $result;
            }
            AuditHelper::collectDetail($templateId, 'value_mapping:create', $insValueMaps);
        }

        if ($delValueMaps) {
            $result = ValueMapService::instance()->delete(array_keys($delValueMaps));
            if (!$result->isSuccess()) {
                return $result;
            }
        }

    }

    /**
     * delete
     *
     * @return Result
     */
    public function delete(array $params, bool $internal = true): Result
    {
        if ($internal) {
        }
        if (empty($params['templates'])) {
            $this->error(error_code(10000021, ['param' => 'templates']));
        }
        $templateIds = filter_integer((array) $params['templates']);
        if (empty($templateIds)) {
            $this->error(error_code(10000026, ['param' => 'templates']));
        }

        try {
            // 删除并清理
            if (array_key_exists('clear', $params) && $params['clear']) {
                $hosts = [];
                if ($hosts) {
                    HostService::instance()->massRemove($hosts);
                }

                $templates = TemplateHelper::getTemplates([
                    'output' => [],
                    'parentTemplateids' => $templateIds,
                    'preservekeys' => true
                ]);

                if ($templates) {
                    $result = $this->massRemove([
                        'templateids' => array_keys($templates),
                        'templateids_link' => $templateIds
                    ]);
                    if (!$result->isSuccess()) {
                        return $result;
                    }
                }
            }

            $result = $this->deleteByInternal($templateIds);
            if ($result->isSuccess()) {
                $result->setErrmsg(Yii::t('msg', 'Delete Success'));
                AuditHelper::save($result->getErrmsg(), AuditHelper::ACTION_DELETE);
            }

            return $result;
        } catch (Exception $e) {
            return $this->errorException($e, 60750103);
        }
    }

    /**
     * delete(内部调用)
     *
     * @return Result
     */
    public function deleteByInternal(array $templateIds): Result
    {
        // TODO: 参数验证
        $dbTemplates = TemplateHelper::getTemplates([
            'output' => ['templateid', 'host', 'name'],
            'templateids' => $templateIds,
            'editable' => true,
            'preservekeys' => true
        ]);

        if (count($dbTemplates) != count($templateIds)) {
            return $this->error(error_code(10000026), t('yii', 'Invalid data received for parameter "{param}".', [
                'param' => 'templates'
            ]));
        }

        foreach ($dbTemplates as $dbTemplate) {
            AuditHelper::collect([$dbTemplate['templateid'] => $dbTemplate['name']], $dbTemplate);
        }

        $idWhereIn = SqlHelper::whereIn('templateid', $templateIds);

        $delTemplates = [];
        $query = new Query();
        $query->from([
            'ht' => HostsTemplates::tableName(),
            'htt' => HostsTemplates::tableName()
        ]);

        $query->where('ht.hostid=htt.hostid')
            ->andWHere('ht.templateid!=htt.templateid')
            ->andWhere(str_replace('templateid', '{{ht}}.templateid', $idWhereIn))
            ->andWhere(SqlHelper::whereIn('htt.templateid', $templateIds, true));
        $query->select(['del_templateid' => 'ht.templateid', 'ht.hostid', 'htt.templateid']);
        foreach ($query->each() as $row) {
            $delTemplates[$row['del_templateid']][$row['hostid']][] = $row['templateid'];
        }

        $delLinkClear = [];
        $query = HostsTemplates::find()
            ->select(['templateid', 'hostid'])
            ->where($idWhereIn);

        foreach ($query->each() as $row) {
            if (!in_array($row['hostid'], $templateIds)) {
                $delLinkClear[$row['templateid']][$row['hostid']] = true;
            }
        }

        $hostIdWhereIn = str_replace('templateid', 'hostid', $idWhereIn);

        $transaction = Hosts::getDb()->beginTransaction();
        try {
            if ($delTemplates) {
                $this->checkTriggerExpressionsOfDelTemplates($delTemplates);
            }
            if ($delLinkClear) {
                $this->checkTriggerDependenciesOfHostTriggers($delLinkClear);
            }
            self::unlinkTemplatesObjects($templateIds, null, true);
            // 首选删除发现规则
            self::deleteDiscoveryRules($hostIdWhereIn);
            // 删除常规指标
            self::deletePlainItems($hostIdWhereIn);
            // delete host from maps
            self::deleteMapsByHostIds($templateIds);
            // 禁用动作和清理动作条件
            $this->disableActionsWithClearConditions($templateIds, $hostIdWhereIn);
            // 删除拨测
            self::deleteHttpTest($hostIdWhereIn);
            $this->deleteLldOverrideOperations($idWhereIn);

            HostTag::deleteAll($hostIdWhereIn);
            Hosts::deleteAll($hostIdWhereIn);
            $transaction->commit();

            return $this->success(['templatesids' => $templateIds]);
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->errorException($e, 60750103);
        }
    }

    protected function deleteLldOverrideOperations($templateIdWhereIn)
    {
        // Get host prototype operations from LLD overrides where this template is linked.
        $query = LldOverrideOperation::find()
            ->alias('loo')
            ->select(['loo.lld_override_operationid'])
            ->asArray();

        $query->where([
            'EXISTS',
            LldOverrideOptemplate::find()
                ->alias('lot')
                ->select(new Expression('NULL'))
                ->where('lot.lld_override_operationid=loo.lld_override_operationid')
                ->andWhere(str_replace('templateid', '{{lot}}.templateid', $templateIdWhereIn))
        ]);

        if ($lldOverrideIds = $query->column()) {
            LldOverrideOptemplate::deleteAll($templateIdWhereIn);

            // Make sure there no other operations left to safely delete the operation.
            $query = LldOverrideOperation::find()
                ->alias('loo')
                ->select(['loo.lld_override_operationid'])
                ->asArray();

            $subQueryTables = [
                'los' => LldOverrideOpstatus::tableName(),
                'lod' => LldOverrideOpdiscover::tableName(),
                'loi' => LldOverrideOpinventory::tableName(),
                'lot' => LldOverrideOptemplate::tableName()
            ];

            foreach ($subQueryTables as $alias => $table) {
                $subQuery = new Query();
                $subQuery->from([$alias => $table])
                    ->where('{{' . $alias . '}}.lld_override_operationid=loo.lld_override_operationid');
                $query->where(['NOT EXISTS', $subQuery]);
            }
            $query->andWhere(SqlHelper::whereIn('loo.lld_override_operationid', $lldOverrideIds));

            if ($ids = $query->column()) {
                LldOverrideOperation::deleteAll(['lld_override_operationid' => $ids]);
            }
        }
    }

    /**
     * Add given template groups, macros and templates to given templates.
     *
     * @param array $data
     *
     * @return Result
     */
    public function massAdd(array $data): Result
    {
        $db_templates = [];
        $this->validateMassAdd($data, $db_templates);

        $templates = $this->getObjectsByData($data, $db_templates);

        $this->updateGroups($templates, $db_templates);
        $this->updateMacros($templates, $db_templates);
        $this->updateTemplates($templates, $db_templates);

        // TODO: zbx audit

        return $this->success(['templateids' => array_column($data['templates'], 'templateid')]);
    }

    /**
     * @param array      $data
     * @param array|null $db_templates
     *
     * @throws ValidateException if the input is invalid.
     */
    private function validateMassAdd(array &$data,  ? array &$db_templates) : void
    {
        $rules = TemplateForm::getMassValidationRules('massAdd');
        $bool = ValidateHelper::validateObject($data, $rules, [], $error);
        if (!$bool) {
            self::exception(60750001, $error);
        }

        $db_templates = TemplateHelper::getTemplates([
            'output' => ['templateid', 'host'],
            'templateids' => array_column($data['templates'], 'templateid'),
            'editable' => true,
            'preservekeys' => true
        ]);

        if (count($db_templates) != count($data['templates'])) {
            self::exception(60750001, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        if (array_key_exists('groups', $data) && $data['groups']) {
            $groupids = array_column($data['groups'], 'groupid');

            $count = GroupHelper::getTemplateGroups([
                'countOutput' => true,
                'groupids' => $groupids,
                'editable' => true
            ]);

            if ($count != count($groupids)) {
                self::exception(60750001, t('zapi', 'No permissions to referred object or it does not exist!'));
            }

            $this->massAddAffectedObjects('groups', $groupids, $db_templates);
        }

        if (array_key_exists('macros', $data) && $data['macros']) {
            $macros = [];

            foreach ($data['macros'] as $macro) {
                $macros[MacroHelper::trimMacro($macro['macro'])] = $macro['macro'];
            }

            $query = new Query();
            $query->from('hostmacro')
                ->select(['hostid', 'macro'])
                ->where(SqlHelper::whereIn('hostid', array_keys($db_templates)));

            $db_macros = $query->all();

            foreach ($db_macros as $db_macro) {
                $trimmed_db_macro = MacroHelper::trimMacro($db_macro['macro']);

                if (array_key_exists($trimmed_db_macro, $macros)) {
                    self::exception(60750001, t('zapi', 'Macro "{macro}" already exists on "{name}".', [
                        'macro' => $macros[$trimmed_db_macro],
                        'name' => $db_templates[$db_macro['hostid']]['host']
                    ]));
                }
            }

            foreach ($db_templates as &$db_template) {
                $db_template['macros'] = [];
            }
            unset($db_host);
        }

        if (array_key_exists('templates_link', $data) && $data['templates_link']) {
            $templateids = array_column($data['templates_link'], 'templateid');

            $count = TemplateHelper::getTemplates([
                'countOutput' => true,
                'templateids' => $templateids
            ]);

            if ($count != count($templateids)) {
                self::exception(60750001, t('zapi', 'No permissions to referred object or it does not exist!'));
            }

            $this->massAddAffectedObjects('templates', $templateids, $db_templates);

            $this->massCheckTemplatesLinks('massadd', $templateids, $db_templates);
        }
    }

    /**
     * Replace template groups, macros and templates on the given templates.
     *
     * @param array $data
     *
     * @return Result
     */
    public function massUpdate(array $data): Result
    {
        $db_templates = [];
        $this->validateMassUpdate($data, $db_templates);

        $templates = $this->getObjectsByData($data, $db_templates);

        $this->updateGroups($templates, $db_templates);
        $this->updateMacros($templates, $db_templates);
        $this->updateTemplates($templates, $db_templates);

        // TODO: zbx audit

        return $this->success(['templateids' => array_column($data['templates'], 'templateid')]);
    }

    /**
     * @param array      $data
     * @param array|null $db_templates
     *
     * @throws ValidateException if the input is invalid.
     */
    private function validateMassUpdate(array &$data,  ? array &$db_templates) : void
    {
        $rules = TemplateForm::getMassValidationRules('massUpdate');
        $bool = ValidateHelper::validateObject($data, $rules, [], $error);
        if (!$bool) {
            self::exception(60750001, $error);
        }

        $db_templates = $this->get([
            'output' => ['templateid', 'host'],
            'templateids' => array_column($data['templates'], 'templateid'),
            'editable' => true,
            'preservekeys' => true
        ]);

        if (count($db_templates) != count($data['templates'])) {
            self::exception(60750001, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        if (array_key_exists('groups', $data)) {
            $groupids = array_column($data['groups'], 'groupid');

            $count = GroupHelper::getTemplateGroups([
                'countOutput' => true,
                'groupids' => $groupids
            ]);

            if ($count != count($groupids)) {
                self::exception(60750001, t('zapi', 'No permissions to referred object or it does not exist!'));
            }

            $this->massAddAffectedObjects('groups', [], $db_templates);

            $groupids = array_flip($groupids);
            $edit_groupids = [];

            foreach ($db_templates as $db_template) {
                $_groupids = $groupids;

                foreach ($db_template['groups'] as $db_group) {
                    if (array_key_exists($db_group['groupid'], $_groupids)) {
                        unset($_groupids[$db_group['groupid']]);
                    } else {
                        $edit_groupids[$db_group['groupid']] = true;
                    }
                }

                $edit_groupids += $_groupids;
            }

            if ($edit_groupids) {
                $count = GroupHelper::getTemplateGroups([
                    'countOutput' => true,
                    'groupids' => array_keys($edit_groupids),
                    'editable' => true
                ]);

                if ($count != count($edit_groupids)) {
                    self::exception(60750001, t('zapi', 'No permissions to referred object or it does not exist!'));
                }
            }
        }

        if (array_key_exists('macros', $data)) {
            $this->massAddAffectedObjects('macros', [], $db_templates);
        }

        if (array_key_exists('templates_link', $data)
            || (array_key_exists('templates_clear', $data) && $data['templates_clear'])) {
            if (array_key_exists('templates_link', $data) && array_key_exists('templates_clear', $data)) {
                $path_clear = '/templates_clear';
                $path = '/templates_link';

                foreach ($data['templates_clear'] as $i1_clear => $template_clear) {
                    foreach ($data['templates_link'] as $i1 => $template) {
                        if (bccomp($template['templateid'], $template_clear['templateid']) == 0) {
                            self::exception(60750101, t('zapi', 'Invalid parameter {parameter}, {error}', [
                                'parameter' => $path_clear . '/' . ($i1_clear + 1) . '/templateid',
                                'error' => t('zapi', 'cannot be specified the value of parameter "{parameter}"', [
                                    'parameter' => $path . '/' . ($i1 + 1) . '/templateid'
                                ])
                            ]));
                        }
                    }
                }
            }

            $this->massAddAffectedObjects('templates', [], $db_templates);

            $templateids_link = array_key_exists('templates_link', $data)
            ? array_column($data['templates_link'], 'templateid')
            : [];
            $templateids_clear = array_key_exists('templates_clear', $data)
            ? array_column($data['templates_clear'], 'templateid')
            : [];

            $edit_templateids = array_flip($templateids_clear);

            if ($templateids_link) {
                foreach ($db_templates as $db_template) {
                    $edit_templateids += array_flip(array_diff(array_column($db_template['templates'], 'templateid'),
                        $templateids_link
                    ));
                }
            }

            if ($edit_templateids) {
                $count = $this->get([
                    'countOutput' => true,
                    'templateids' => array_keys($edit_templateids)
                ]);

                if ($count != count($edit_templateids)) {
                    self::exception(60750001, t('zapi', 'No permissions to referred object or it does not exist!'));
                }

                if (array_key_exists('templates_link', $data)) {
                    $this->massCheckTemplatesLinks('massupdate', $templateids_link, $db_templates, $templateids_clear);
                } else {
                    $this->massCheckTemplatesLinks('massremove', $templateids_clear, $db_templates, $templateids_clear);
                }
            }
        }
    }

    /**
     * @param array $templates
     * @return Result
     * @throws Exception
     */
    public function massRemove(array $templates): Result
    {
        $rules = TemplateForm::getMassValidationRules(__FUNCTION__);
        $bool = ValidateHelper::validateObject($templates, $rules, [], $error);
        if (!$bool) {
            return $this->error(error_code(10000021), $error);
        }

        $dbTemplates = TemplateHelper::getTemplates([
            'output' => ['templateid', 'host'],
            'templateids' => $templates['templateids'],
            'preserveKey' => true
        ]);

        if (count($dbTemplates) != count($templates['templateids'])) {
            return $this->error(error_code(10000026), t('yii', 'Invalid data received for parameter "{param}".', [
                'param' => 'templates'
            ]));
        }

        if (array_key_exists('groupids', $templates) && $templates['groupids']) {
            $count = HostGroupService::instance()->count([
                'groupids' => $templates['groupids']
            ]);
            if ($count != count($templates['groupids'])) {
                return $this->error(error_code(10000026), t('yii', 'Invalid data received for parameter "{param}".', [
                    'param' => 'groupids'
                ]));
            }
            HostGroupService::checkTemplatesWithoutGroups($dbTemplates, $templates['groupids']);
            $this->massAddAffectedObjects('groups', $templates['groupids'], $dbTemplates);
        }

        $templates = $this->getObjectsByData($templates, $dbTemplates);

        $this->updateGroups($templates, $dbTemplates);
        $this->updateMacros($templates, $dbTemplates);
        $this->updateTemplates($templates, $dbTemplates);

        return $this->success(['templateids' => $templates['templateids']]);
    }

    /**
     * form
     */
    public function getForm(int $templateId = 0): Result
    {
        if ($templateId) {
            if (!($template = Templates::findByTemplateId($templateId))) {
                $this->error(10000404);
            }

            $groupIds = HostsGroups::find()
                ->select('groupid')
                ->where(['hostid' => $templateId])
                ->asArray()
                ->column();
            $macros = Hostmacro::find()->where(['hostid' => $templateId])->asArray()->all();
            $valueMaps = self::gatherValueMappingGroupByHostId('hostid=' . $templateId, true);
            $valueMaps = $valueMaps ? array_values($valueMaps[$templateId]) : [];
            $templateIds = HostsTemplates::find()->where(['hostid' => $templateId])->select('templateid')->column();
        } else {
            $template = new Templates();
            $groupIds = $macros = $valueMaps = $templateIds = [];
        }

        $form = [
            'templateid' => $templateId ?: '',
            'template_name' => (string) ($template->host ?? ''),
            'visiblename' => (string) ($template->name ?? ''),
            'templates' => $templateIds,
            'groups' => $groupIds,
            'description' => (string) ($template->description ?? ''),
            'macros' => $macros,
            'valuemaps' => $valueMaps
        ];

        $data = [
            'form' => $form,
            'groups' => [],
            'templates' => []
        ];

        return $this->success($data);
    }

    /**
     * @param  array $params
     * @param  array $output
     * @return array
     * @deprecated use [[`TemplateHelper::getTemplates()`]] instead it
     */
    public function getTemplates(array $params = [], array $output = [])
    {
        $searcher = new TemplateSearch();
        if ($output) {
            $searcher->assignable = $output;
        }
        $searcher->isPage = false;
        $dataProvider = $searcher->search($params);
        return $dataProvider->getModels();
    }

    /**
     * 导出
     *
     * @param  array $params
     * @return Result
     */
    public function export(array $params = [])
    {
        if (empty($params['templates'])) {
            $this->error(error_code(10000021, ['param' => 'templates']));
        }

        // 暂时只导出xml格式
        $format = array_key_exists('format', $params) ? $params['format'] : 'xml';
        $raw = array_key_exists('raw', $params) ? (bool) $params['raw'] : false;

        $extensions = ['yaml', 'xml', 'perseusz'];
        if (!in_array($format, $extensions)) {
            return $this->error(60750001, Yii::t('yii', 'Only files with these extensions are allowed: {extensions}.', [
                'extensions' => implode(', ', $extensions)
            ]));
        }

        $withExtraData = false;
        if ($format == 'perseusz') {
            $withExtraData = true;
            $format = 'json';
        }

        try {
            $exporter = TemplateExport::instance()->load([
                'withExtraData' => $withExtraData,
                'format' => $format,
                'raw' => $raw,
                'isAny' => array_key_exists('scope', $params) ? 3 == $params['scope'] : false,
                'options' => [
                    'templates' => filter_integer((array) $params['templates'])
                ]
            ]);

            $exporter->gatherData();

            if ($exporter->withExtraData) {
                $exporter->gatherExtraData();
            }

            $hash = \app\common\components\RequestObject::instance()->getRequestId();
            $r = Yii::$app->cache->set($hash, $exporter, 60);
            return $this->success([
                'down' => '/zapi/template/download',
                'hash' => $hash,
                'sets' => $r
            ]);
        } catch (Exception $e) {
            return $this->errorException($e);
        }
    }

    public function exportForce(string $hash)
    {
        $exporter = Yii::$app->cache->get($hash);
        if (empty($exporter) || !($exporter instanceof TemplateExport)) {
            Yii::$app->response->data = [
                'code' => 10000404,
                'message' => '',
                'data' => []
            ];
            Yii::$app->end();
        }

        try {
            $output = $exporter->raw ? $exporter->output() : SecurityHelper::encrypt($exporter->output());
            if ($exporter->withExtraData) {
                $path = APP_PATH . '/runtime/zapi/' . $hash;
                // 创建临时目录
                FileHelper::createDirectory($path);
                # 保存模板文件
                file_put_contents($path . '/' . TemplateExport::ZIP_FILE_NAME_TPL, $output);

                $extraData = $exporter->outputExtra();

                $outputExtra = $exporter->raw ? json_encode($extraData) : SecurityHelper::encrypt($extraData);
                # 保存附加信息文件
                file_put_contents($path . '/' . TemplateExport::ZIP_FILE_NAME_TPL_EXTRA, $outputExtra);
 
                $zip = new ZipArchive();
                $file = $path . TemplateExport::ZIP_FILE_EXT;
                $ret = $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $zip->addFile($path . '/' . TemplateExport::ZIP_FILE_NAME_TPL, TemplateExport::ZIP_FILE_NAME_TPL);
                $zip->addFile($path . '/' . TemplateExport::ZIP_FILE_NAME_TPL_EXTRA, TemplateExport::ZIP_FILE_NAME_TPL_EXTRA);
                // $zip->addEmptyDir('extra');
                foreach ($extraData['images'] as $image) {
                    $zip->addFile(APP_PATH . '/web/' . $image, ltrim($image, '/'));
                }
                $zip->close();
                // 压缩后删除目录
                FileHelper::removeDirectory($path);
                Yii::$app->response->sendFile($file, pathinfo($exporter->getFileName(), PATHINFO_FILENAME) . TemplateExport::ZIP_FILE_EXT);
                Yii::$app->response->send();
                FileHelper::unlink($file);
                exit();
            }
            Yii::$app->response->sendContentAsFile($output, $exporter->getFileName(), ['mimeType' => CExportWriterFactory::getMimeType($exporter->format)]);
            Yii::$app->response->send();
        } catch (Exception $e) {
            Yii::error(parse_exception($e));
            exit(format_exception($e));
        }
    }

    /**
     * 导入
     *
     * @param  array $params
     * @return Result
     */
    public function import(array $params = [])
    {
        // 支持文件导入方式
        $uploadFile = UploadedFile::getInstanceByName('file');
        if (!$uploadFile) {
            return $this->error(60750101, t('yii', 'Please upload a file.'));
        }

        $extensions = ['xml', 'yaml', 'yml', 'perseusz'];
        $fileExt = $uploadFile->getExtension();
        if (!in_array($fileExt, $extensions)) {
            return $this->error(60750101, t('yii', 'File upload failed.'));
        }

        $extras = [];

        if ($fileExt === 'perseusz') {
            $hash = hash_file('md5', $uploadFile->tempName);
            $path = APP_PATH . '/runtime/zapi/' . $hash;
            // 创建临时目录
            FileHelper::createDirectory($path);
            $zip = new ZipArchive();

            // 打开ZIP文件
            if ($zip->open($uploadFile->tempName) === true) {
                // 解压所有文件到指定目录
                $zip->extractTo($path);
                // 关闭ZIP文件
                $zip->close();
            } else {
                return $this->error(60750101, t('yii', 'Only files with these extensions are allowed: {extensions}.', $extensions));
            }

            // 存在上传目录
            if (file_exists($path . '/uploads')) {
                FileHelper::copyDirectory($path . '/uploads', Yii::getAlias('@upload'));
            }

            $tplFile = $path . '/' . TemplateExport::ZIP_FILE_NAME_TPL;
            if (!is_file($tplFile)) {
                return $this->error(60750101, t('yii', 'File upload failed.'));
            }
            // 模板内容配置
            $content = file_get_contents($tplFile);
            if ($temp = SecurityHelper::decrypt($content)) {
                $content = $temp;
            }

            // 模板附加内容配置
            $tplExtraFile = $path . '/' . TemplateExport::ZIP_FILE_NAME_TPL_EXTRA;
            $extra = file_get_contents($tplExtraFile);
            if ($tmp = SecurityHelper::decrypt($extra)) {
                $extras = $tmp;
            } else {
                $extras = json_decode($extra);
            }

            // 移除临时目录
            FileHelper::removeDirectory($path);

            $extension = pathinfo($tplFile, PATHINFO_EXTENSION);
        } else {
            $content = file_get_contents($uploadFile->tempName);
            if ($temp = SecurityHelper::decrypt($content)) {
                $content = $temp;
            }
            $extension = $uploadFile->getExtension();
        }

        $keys = ['updateExisting', 'createMissing', 'deleteMissing'];
        $rules = ImportForm::getDefaultParams($params['rules_preset'] ?? 'template');
        $requestRules = empty($params) ? $rules : array_intersect_key($params, $rules);
        $requestRules += array_fill_keys(array_keys($rules), []);
        $options = array_fill_keys($keys, false);

        foreach ($requestRules as $ruleName => &$rule) {
            $rule = array_map('boolval', array_intersect_key($rule + $options, $rules[$ruleName]));
        }
        unset($rule);
        $options = [
            'format' => CImportReaderFactory::fileExt2ImportFormat($extension),
            'source' => $content,
            'rules' => $requestRules
        ];

        try {
            $this->validateImport($options);
            $result = TemplateImport::instance()->load($options)->import();
            if ($result->isSuccess()) {
                AuditHelper::save($result->getErrmsg(), AuditHelper::ACTION_CREATE);
            }
            // 存在附加信息
            if ($extras) {
                TemplateExtraImport::instance()->load($extras)->import();
            }

            return $result;
        } catch (Exception $e) {
            return $this->errorException($e);
        }
    }

    /**
     * @param  array $params
     * @return ValidateException
     */
    public function validateImport(array &$params)
    {
        $rules = ImportForm::getValidationRules();
        $bool = ValidateHelper::validateObject($params, $rules, [], $error);
        if (!$bool) {
            self::exception(60750001, $error);
        }

        if (mb_substr($params['source'], 0, 1) === pack('H*', 'EFBBBF')) {
            $params['source'] = mb_substr($params['source'], 1);
        }
    }

    /**
     * 加密模板解析
     *
     * @param  array $params
     * @return Result
     */
    public function parsing(array $params = [])
    {
        // 支持文件导入方式
        $uploadFile = UploadedFile::getInstanceByName('file');
        if (!$uploadFile) {
            return $this->error(60750101, t('yii', 'Please upload a file.'));
        }

        $fileExt = $uploadFile->getExtension();
        if (!in_array($fileExt, ['xml'])) {
            return $this->error(60750101, t('yii', 'File upload failed.'));
        }

        $raw = empty($params['prs_tag']);

        $content = file_get_contents($uploadFile->tempName);
        if ($temp = SecurityHelper::decrypt($content)) {
            exit($raw ? $temp : str_replace('lwops_export', 'perseus_export', $temp));
        }

        return $this->error(60750101, 'parse failed');
    }
}
