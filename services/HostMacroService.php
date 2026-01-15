<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\MacroHelper;
use app\customs\zapi\common\helpers\SettingHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\IdsValidator;
use app\customs\zapi\common\validators\VaultSecretValidator;
use app\customs\zapi\forms\HostMacroForm;
use app\customs\zapi\services\assist\BaseAssist;
use app\modules\libzbx\models\zbx\HostDiscovery;
use app\modules\libzbx\models\zbx\Hostmacro;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\Items;
use yii\db\Query;

class HostMacroService extends BaseAssist
{
    public function create(array $params): Result
    {
        $this->validateCreate($params);

        $this->createReal($params);

        if ($tpl_hostmacros = $this->getMacrosToInherit($params)) {
            $this->inherit($tpl_hostmacros);
        }

        return $this->success(['hostmacroids' => array_column($params, 'hostmacroid')]);
    }

    public function update(array $hostMacros): Result
    {
        $this->validateUpdate($hostMacros, $dbHostMacros);

        $this->updateReal($hostMacros, $dbHostMacros);

        if ($tplHostMacros = $this->getMacrosToInherit($hostMacros, $dbHostMacros)) {
            $this->inherit($tplHostMacros);
        }

        return $this->success(['hostmacroids' => array_column($hostMacros, 'hostmacroid')]);
    }

    /**
     * @param array $hostMacroIds
     * @return Result
     */
    public function delete(array $hostMacroIds): Result
    {
        $this->validateDelete($hostMacroIds, $dbHostMacros);

        DB::delete(Hostmacro::tableName(), ['hostmacroid' => $hostMacroIds]);

        if ($tplHostMacros = $this->getMacrosToInherit($dbHostMacros)) {
            $this->inherit($tplHostMacros, true);
        }

        return $this->success(['hostmacroids' => $hostMacroIds]);
    }

    /**
     * Inserts hostmacros records into the database.
     *
     * @param array $hostMacros
     */
    private function createReal(array &$hostMacros): void
    {
        $hostMacroIds = DB::insert(Hostmacro::tableName(), $hostMacros);

        foreach ($hostMacros as $index => &$hostMacro) {
            $hostMacro['hostmacroid'] = $hostMacroIds[$index];
        }
        unset($hostMacro);
    }

    /**
     * Updates hostmacros records in the database.
     *
     * @param array $hostmacros
     * @param array $db_hostmacros
     */
    private function updateReal(array $hostmacros, array $db_hostmacros)
    {
        $upd_hostmacros = [];

        foreach ($hostmacros as $hostmacro) {
            $db_hostmacro = $db_hostmacros[$hostmacro['hostmacroid']];

            $upd_hostmacro = DB::getUpdatedValues(Hostmacro::tableName(), $hostmacro, $db_hostmacro);

            if ($upd_hostmacro) {
                $upd_hostmacros[] = [
                    'values' => $upd_hostmacro,
                    'where' => ['hostmacroid' => $hostmacro['hostmacroid']]
                ];
            }
        }

        if ($upd_hostmacros) {
            DB::update(Hostmacro::tableName(), $upd_hostmacros);
        }
    }

    /**
     * @param array $hostmacros
     *
     * @throws APIException if the input is invalid.
     */
    protected function validateCreate(array &$hostmacros)
    {
        $rules = HostMacroForm::getValidationRules('create');
        if (!ValidateHelper::validateObjects($hostmacros, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostid', 'macro']]], $error)) {
            self::exception(10000026, $error);
        }
        // TODO: checkHostPermissions

        $this->checkHostDuplicates($hostmacros);
    }

    /**
     * @param array $hostmacros
     *
     * @throws APIException if the input is invalid.
     */
    protected function validateUpdate(array &$hostmacros, array &$db_hostmacros = null)
    {
        $rules = HostMacroForm::getValidationRules('update');
        if (!ValidateHelper::validateObjects($hostmacros, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostmacroid']]], $error)) {
            self::exception(10000026, $error);
        }

        $db_hostmacros = Hostmacro::find()
            ->select(['hostmacroid', 'hostid', 'macro', 'value', 'type', 'description', 'automatic'])
            ->where(SqlHelper::whereIn('hostmacroid', array_column($hostmacros, 'hostmacroid')))
            ->indexBy('hostmacroid')
            ->asArray()
            ->all();

        if (count($hostmacros) != count($db_hostmacros)) {
            self::exception(10000026, t('zapi', 'No permissions to referred object or it does not exist!'));
        }

        $hostmacros = $this->extendObjectsByKey($hostmacros, $db_hostmacros, 'hostmacroid', ['hostid', 'type']);

        foreach ($hostmacros as $index => &$hostmacro) {
            $db_hostmacro = $db_hostmacros[$hostmacro['hostmacroid']];

            if ($db_hostmacro['automatic'] == PRS_USERMACRO_AUTOMATIC && !array_key_exists('automatic', $hostmacro)) {
                self::exception(
                    10000026,
                    t('zapi', 'Not allowed to modify automatic user macro "{name}".', ['name' => $db_hostmacro['macro']])
                );
            }

            if ($hostmacro['type'] != $db_hostmacro['type']) {
                if ($db_hostmacro['type'] == PRS_MACRO_TYPE_SECRET) {
                    $hostmacro += ['value' => ''];
                }

                if ($hostmacro['type'] == PRS_MACRO_TYPE_VAULT) {
                    $hostmacro += ['value' => $db_hostmacro['value']];
                }
            }

            // if (array_key_exists('value', $hostmacro) && $hostmacro['type'] == PRS_MACRO_TYPE_VAULT) {

            //     $bool = ValidateHelper::validateObject($hostmacro, [
            //         'value' => [VaultSecretValidator::class, 'provider' => SettingHelper::get(SettingHelper::VAULT_PROVIDER)]
            //     ], [
            //         '_path' => '/' . ($index + 1) . '/value'
            //     ], $error);

            //     if ($bool) {
            //         self::exception(10000026, $error);
            //     }
            // }
        }
        unset($hostmacro);

        if (!ValidateHelper::validateObjects($hostmacros, HostMacroForm::getUniqueValidationRules(), ['flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['hostid', 'macro']]], $error)) {
            self::exception(10000026, $error);
        }

        $this->checkHostDuplicates($hostmacros, $db_hostmacros);
    }

    /**
     * Undocumented function
     *
     * @param array $hostMacroIds
     * @param array|null $dbHostMacros
     * @throws ValidateException
     */
    protected function validateDelete(array &$hostMacroIds, array &$dbHostMacros = null)
    {
        $rules = [
            'ids' => [IdsValidator::class, 'flags' => API_NOT_EMPTY, 'uniq' => true],
        ];
        $params = [
            'ids' => $hostMacroIds
        ];
        if (!ValidateHelper::validateObject($params, $rules, [], $error)) {
            self::exception(10000026, $error);
        }

        $dbHostMacros = Hostmacro::find()
            ->select(['hostmacroid', 'hostid', 'macro'])
            ->where(SqlHelper::whereIn('hostmacroid', $hostMacroIds))
            ->indexBy('hostmacroid')
            ->asArray()
            ->all();

        if (count($hostMacroIds) != count($dbHostMacros)) {
            self::exception(10000404, t('zapi', 'unexpected parameter "{parameter}"', ['parameter' => 'hostmacroid']));
        }
    }

    /**
     * Checks if any of the given host macros already exist on the corresponding hosts. If the macros are updated and
     * the "hostmacroid" field is set, the method will only fail, if a macro with a different hostmacroid exists.
     * Assumes the "macro", "hostid" and "hostmacroid" fields are valid.
     *
     * @param array      $hostmacros
     * @param string     $hostmacros[]['hostmacroid']  (optional if $db_hostmacros is null)
     * @param string     $hostmacros[]['hostid']
     * @param string     $hostmacros[]['macro']        (optional if $db_hostmacros is not null)
     * @param array|null $db_hostmacros
     *
     * @throws APIException if any of the given macros already exist.
     */
    private function checkHostDuplicates(array $hostmacros, array $db_hostmacros = null)
    {
        $macro_names = [];
        $existing_macros = [];

        // Parse each macro, get unique names and, if context exists, narrow down the search.
        foreach ($hostmacros as $index => $hostmacro) {
            if ($db_hostmacros !== null && (!array_key_exists('macro', $hostmacro)
                || MacroHelper::trimMacro($hostmacro['macro'])
                === MacroHelper::trimMacro($db_hostmacros[$hostmacro['hostmacroid']]['macro']))) {
                unset($hostmacros[$index]);

                continue;
            }

            $trimmed_macro = MacroHelper::trimMacro($hostmacro['macro']);
            [$macro_name] = explode(':', $trimmed_macro, 2);
            $macro_name = !isset($trimmed_macro[strlen($macro_name)]) ? '{$' . $macro_name : '{$' . $macro_name . ':';

            $macro_names[$macro_name] = true;
            $existing_macros[$hostmacro['hostid']] = [];
        }

        if (!$existing_macros) {
            return;
        }

        $query = Hostmacro::find()
            ->select(['hostmacroid', 'hostid', 'macro'])
            ->where(SqlHelper::whereIn('hostid', array_keys($existing_macros)));

        $likes = ['OR'];
        foreach ($macro_names as $macro_name => $bool) {
            $likes[] = [DB_LIKE, 'macro', strtr($macro_name, ['!' => '!!', '%' => '!%', '_' => '!_']) . "% ESCAPE '!'", false];
        }
        $query->andWhere($likes);
        $db_hostmacros = $query->asArray()->all();

        // Collect existing unique macro names and their contexts for each host.
        foreach ($db_hostmacros as $db_hostmacro) {
            $trimmed_macro = MacroHelper::trimMacro($db_hostmacro['macro']);

            $existing_macros[$db_hostmacro['hostid']][$trimmed_macro] = $db_hostmacro['hostmacroid'];
        }

        // Compare each macro name and context to existing one.
        foreach ($hostmacros as $hostmacro) {
            $hostid = $hostmacro['hostid'];
            $trimmed_macro = MacroHelper::trimMacro($hostmacro['macro']);
            if (array_key_exists($trimmed_macro, $existing_macros[$hostid])) {
                $host = Hosts::find()->where(['hostid' => $hostid])
                    ->select(['name'])
                    ->asArray()
                    ->scalar();

                self::exception(
                    60750101,
                    t('zapi', 'Macro "{name}" already exists on "{host_name}".', [
                        'name' => $hostmacro['macro'],
                        'host_name' => $host
                    ])
                );
            }
        }
    }

    /**
     * Forms the array of hostmacros, which are support the inheritance, from the passed hostmacros array.
     *
     * @param array      $hostmacros
     * @param string     $hostmacros[]['hostmacroid']
     * @param string     $hostmacros[]['hostid']
     * @param string     $hostmacros[]['macro']                  (optional)
     * @param string     $hostmacros[]['value']                  (optional)
     * @param string     $hostmacros[]['description']            (optional)
     * @param int        $hostmacros[]['type']                   (optional)
     * @param array|null $dbHostMacros                          Used to set the old macro name in case when it was
     *                                                           updated.
     * @param string     $dbHostMacros[<hostmacroid>]['macro']
     *
     * @return array
     */
    private function getMacrosToInherit(array $hostmacros, array $dbHostMacros = null): array
    {

        $query = new Query();
        $query->from([
            'hd' => HostDiscovery::tableName(),
            'i' => Items::tableName(),
            'h' => Hosts::tableName(),
        ]);

        $query->where('hd.parent_itemid=i.itemid')
            ->andWhere('i.hostid=h.hostid')
            ->andWhere(['i.status' => HOST_STATUS_TEMPLATE])
            ->andWhere(SqlHelper::whereIn('{{hd}}.hostid', array_unique(array_column($hostmacros, 'hostid'))));

        $query->select(['hd.hostid']);

        $templated_host_prototypeids = $query->column();

        if (!$templated_host_prototypeids) {
            return [];
        }

        foreach ($hostmacros as $index => &$hostmacro) {
            if (!in_array($hostmacro['hostid'], $templated_host_prototypeids)) {
                unset($hostmacros[$index]);

                continue;
            }

            if ($dbHostMacros) {
                $db_hostmacro = $dbHostMacros[$hostmacro['hostmacroid']];
                $hostmacro += array_intersect_key($db_hostmacro, array_flip(['macro']));

                if ($hostmacro['macro'] !== $db_hostmacro['macro']) {
                    $hostmacro['macro_old'] = $db_hostmacro['macro'];
                }
            }
        }
        unset($hostmacro);

        return $hostmacros;
    }

    /**
     * Prepares and returns an array of child hostmacros, inherited from hostmacros $tpl_hostmacros on the all hosts.
     *
     * @param array  $tpl_hostmacros
     * @param string $tpl_hostmacros[]['hostmacroid']
     * @param string $tpl_hostmacros[]['hostid']
     * @param string $tpl_hostmacros[]['macro']
     * @param string $tpl_hostmacros[]['value']        (optional)
     * @param string $tpl_hostmacros[]['description']  (optional)
     * @param int    $tpl_hostmacros[]['type']         (optional)
     * @param string $tpl_hostmacros[]['macro_old']    (optional)
     * @param array  $ins_hostmacros
     * @param array  $upd_hostmacros
     * @param array  $db_hostmacros
     */
    private function prepareInheritedObjects(array $tpl_hostmacros, array &$ins_hostmacros = null, array &$upd_hostmacros = null, array &$db_hostmacros = null): void
    {
        $ins_hostmacros = [];
        $upd_hostmacros = [];
        $db_hostmacros = [];

        $templateids_hostids = [];
        $hostids = [];

        $query = Hosts::find()
            ->select(['hostid', 'templateid'])
            ->where(['templateid' => array_unique(array_column($tpl_hostmacros, 'hostid'))])
            ->asArray();

        foreach ($query->each() as $chd_host) {
            $templateids_hostids[$chd_host['templateid']][] = $chd_host['hostid'];
            $hostids[] = $chd_host['hostid'];
        }

        if (!$templateids_hostids) {
            return;
        }

        $macros = [];
        foreach ($tpl_hostmacros as $tpl_hostmacro) {
            if (array_key_exists('macro_old', $tpl_hostmacro)) {
                $macros[$tpl_hostmacro['macro_old']] = true;
            } else {
                $macros[$tpl_hostmacro['macro']] = true;
            }
        }

        $chd_hostmacros = Hostmacro::find()
            ->select(['hostmacroid', 'hostid', 'macro', 'type', 'value', 'description'])
            ->where(['hostid' => $hostids, 'macro' => array_keys($macros)])
            ->asArray()
            ->all();

        $host_macros = array_fill_keys($hostids, []);

        foreach ($chd_hostmacros as $hostmacroid => $hostmacro) {
            $host_macros[$hostmacro['hostid']][$hostmacro['macro']] = $hostmacroid;
        }

        foreach ($tpl_hostmacros as $tpl_hostmacro) {
            $templateid = $tpl_hostmacro['hostid'];

            if (!array_key_exists($templateid, $templateids_hostids)) {
                continue;
            }

            foreach ($templateids_hostids[$templateid] as $hostid) {
                if (array_key_exists('macro_old', $tpl_hostmacro)) {
                    $hostmacroid = $host_macros[$hostid][$tpl_hostmacro['macro_old']];

                    $upd_hostmacros[] = ['hostmacroid' => $hostmacroid, 'hostid' => $hostid] + $tpl_hostmacro;
                    $db_hostmacros[$hostmacroid] = $chd_hostmacros[$hostmacroid];

                    unset($chd_hostmacros[$hostmacroid], $host_macros[$hostid][$tpl_hostmacro['macro_old']]);
                } elseif (array_key_exists($tpl_hostmacro['macro'], $host_macros[$hostid])) {
                    $hostmacroid = $host_macros[$hostid][$tpl_hostmacro['macro']];

                    $upd_hostmacros[] = ['hostmacroid' => $hostmacroid, 'hostid' => $hostid] + $tpl_hostmacro;
                    $db_hostmacros[$hostmacroid] = $chd_hostmacros[$hostmacroid];

                    unset($chd_hostmacros[$hostmacroid], $host_macros[$hostid][$tpl_hostmacro['macro']]);
                } else {
                    $ins_hostmacros[] = ['hostid' => $hostid] + $tpl_hostmacro;
                }
            }
        }
    }

    /**
     * Updates the macros for the children of host prototypes and propagates the inheritance to the child host
     * prototypes.
     *
     * @param array  $tpl_hostmacros
     * @param string $tpl_hostmacros[]['hostmacroid']
     * @param string $tpl_hostmacros[]['hostid']
     * @param string $tpl_hostmacros[]['macro']
     * @param string $tpl_hostmacros[]['value']        (optional)
     * @param string $tpl_hostmacros[]['description']  (optional)
     * @param int    $tpl_hostmacros[]['type']         (optional)
     * @param string $tpl_hostmacros[]['macro_old']    (optional)
     * @param bool   $isDelete                        Whether the passed hostmacros are intended to delete.
     */
    private function inherit(array $tpl_hostmacros, bool $isDelete = false): void
    {
        $this->prepareInheritedObjects($tpl_hostmacros, $ins_hostmacros, $upd_hostmacros, $db_hostmacros);

        if ($ins_hostmacros) {
            $this->createReal($ins_hostmacros);
        }

        if ($upd_hostmacros) {
            if ($isDelete) {
                DB::delete(Hostmacro::tableName(), ['hostmacroid' => array_column($upd_hostmacros, 'hostmacroid')]);
            } else {
                $this->updateReal($upd_hostmacros, $db_hostmacros);
            }
        }

        if ($ins_hostmacros || $upd_hostmacros) {
            $this->inherit(array_merge($ins_hostmacros, $upd_hostmacros), $isDelete);
        }
    }
}
