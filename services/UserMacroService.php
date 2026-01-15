<?php

namespace app\customs\zapi\services;

use app\common\components\Result;
use app\common\helpers\StringHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\MacroHelper;
use app\customs\zapi\common\helpers\SettingsHelper;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\helpers\ZSqlHelper;
use app\customs\zapi\common\validators\VaultSecretValidator;
use app\customs\zapi\forms\GlobalMacroForm;
use app\customs\zapi\services\assist\BaseAssist;
use Yii;
use yii\db\Query;

class UserMacroService extends BaseAssist
{
    public function getGlobalMacros(): Result
    {
		$macros = MacroHelper::getGlobalMacros([
            'output' => ['globalmacroid', 'macro', 'value', 'type', 'description'],
            'globalmacro' => true
        ]);
        return $this->success(array_values(order_macros($macros, 'macro')));
    }
    
    /**
     * update
     *
     * @param  array  $macros
     * @return Result
     */
    public function update(array $macros): Result
    {
		foreach ($macros as &$macro) {
			$macro['macro'] = trim($macro['macro']);

			if (array_key_exists('value', $macro)) {
				$macro['value'] = trim($macro['value']);
			}

			$macro['description'] = trim($macro['description']);
		}
		unset($macro);

		foreach ($macros as $idx => $macro) {
			if (!array_key_exists('globalmacroid', $macro) && $macro['macro'] === ''
					&& (!array_key_exists('value', $macro) || $macro['value'] === '') && $macro['description'] === '') {
				unset($macros[$idx]);
			}
		}

        $dbMacros = MacroHelper::getGlobalMacros([
            'output' => ['globalmacroid', 'macro', 'value', 'type', 'description'],
            'globalmacro' => true,
			'preservekeys' => true
        ]);

        $macros_to_update = [];
		foreach ($macros as $idx => $macro) {
			if (array_key_exists('globalmacroid', $macro) && array_key_exists($macro['globalmacroid'], $dbMacros)) {
				$dbMacro = $dbMacros[$macro['globalmacroid']];
				// Remove item from new macros array.
				unset($macros[$idx], $dbMacros[$macro['globalmacroid']]);

				// If the macro is unchanged - skip it.
				if ($macro['type'] == PRS_MACRO_TYPE_SECRET) {
					if (!array_key_exists('value', $macro)) {
						if ($dbMacro['macro'] === $macro['macro'] && $dbMacro['type'] == $macro['type']
								&& $dbMacro['description'] === $macro['description']) {
							continue;
						}
					}
				}
				else {
					if ($dbMacro['type'] == PRS_MACRO_TYPE_SECRET) {
						if ($dbMacro['macro'] === $macro['macro']
								&& $dbMacro['type'] == $macro['type']
								&& $dbMacro['description'] === $macro['description']) {
							continue;
						}
					}
					else {
						if ($dbMacro['macro'] === $macro['macro'] && $dbMacro['value'] === $macro['value']
								&& $dbMacro['type'] == $macro['type']
								&& $dbMacro['description'] === $macro['description']) {
							continue;
						}
					}
				}

				$macros_to_update[] = $macro;
			}
		}

        $result = true;

		if ($macros_to_update || $dbMacros || $macros) {
			if ($macros_to_update) {
				$result = $this->updateGlobal($macros_to_update)->isSuccess();
			}

			if ($dbMacros) {
				$result = $result && $this->deleteGlobal($dbMacros)->isSuccess();
			}

			if ($macros) {
				$result = $result && $this->createGlobal(array_values($macros))->isSuccess();
			}
		}
        return $this->success([], Yii::t('msg', '{name} updated successfully', [
			'name' => Yii::t('libzbx', 'Macro')
		]));
    }

    /**
	 * @param array $globalmacros
	 *
	 * @return array
	 */
	public function createGlobal(array $globalmacros): Result
    {
		$this->validateCreateGlobal($globalmacros);

		$globalmacroids = DB::insert('globalmacro', $globalmacros);

        $msg = Yii::t('msg', '{name} created successfully', [
            'name' => Yii::t('libzbx', 'Macro')
        ]);
		foreach ($globalmacros as $index => &$globalmacro) {
			$globalmacro['globalmacroid'] = $globalmacroids[$index];
            $details = [];
            foreach($globalmacro as $field => $value) {
                $details[$field] = audit_detail($field, '', $value);
            }
            if (@$globalmacro['type'] == PRS_MACRO_TYPE_SECRET && array_key_exists('value', $globalmacro)) {
                $details['value'] = audit_detail($field, '', StringHelper::toPrivacy($globalmacro['value']));
            }
            $this->auditAdd(RESOURCE_ZAPI, [$globalmacroids[$index] => $globalmacro['macro']], $msg, $details);
		}
		unset($globalmacro);
 
		return $this->success(['globalmacroids' => $globalmacroids], $msg);
	}

	/**
	 * @param array $globalmacros
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreateGlobal(array &$globalmacros)
    {
        $rules = GlobalMacroForm::getValidationRules('create');
        if (!ValidateHelper::validateObjects($globalmacros, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['macro']]], $error)) {
            self::exception(60750001, $error);
        }

		$this->checkDuplicates($globalmacros);
	}

    /**
	 * @param array $globalmacros
	 *
	 * @return array
	 */
	public function updateGlobal(array $globalmacros) {
		$this->validateUpdateGlobal($globalmacros, $db_globalmacros);

		$upd_globalmacros = [];

		foreach ($globalmacros as $globalmacro) {
			$db_globalmacro = $db_globalmacros[$globalmacro['globalmacroid']];

			$upd_globalmacro = DB::getUpdatedValues('globalmacro', $globalmacro, $db_globalmacro);

			if ($upd_globalmacro) {
				$upd_globalmacros[] = [
					'values'=> $upd_globalmacro,
					'where'=> ['globalmacroid' => $globalmacro['globalmacroid']]
				];
			}
		}

        $msg = Yii::t('msg', '{name} updated successfully', [
            'name' => Yii::t('libzbx', 'Macro')
        ]);

		if ($upd_globalmacros) {
			DB::update('globalmacro', $upd_globalmacros);

            foreach($upd_globalmacros as $upd_globalmacro) {
			    $db_globalmacro = $db_globalmacros[$upd_globalmacro['where']['globalmacroid']];
                $details = [];
                foreach($upd_globalmacro['values'] as $field => $value) {
                    $details[$field] = audit_detail($field, $db_globalmacro[$field], $value);
                }
                if (@$db_globalmacro['type'] == PRS_MACRO_TYPE_SECRET) {
                    $details['value'] = audit_detail($field, StringHelper::toPrivacy($db_globalmacro[$field]), StringHelper::toPrivacy($globalmacro['value']));
                }

                $this->auditAdd(RESOURCE_ZAPI, [$db_globalmacro['globalmacroid'] => $globalmacro['macro']], $msg, $details);
            }
		}

		return $this->success(['globalmacroids' => array_column($globalmacros, 'globalmacroid')], $msg);
	}

	/**
	 * @param array $globalmacros
	 * @param array $db_globalmacros
	 *
	 * @throws ValidateException if the input is invalid
	 */
	private function validateUpdateGlobal(array &$globalmacros, array &$db_globalmacros = null) 
    {
        $rules = GlobalMacroForm::getValidationRules('update');
        if (!ValidateHelper::validateObjects($hostmacros, $rules, ['flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['globalmacroid'], ['macro']]], $error)) {
            self::exception(60750001, $error);
        }
        $db_globalmacros = $this->getQuery(array_column($globalmacros, 'globalmacroid') )->all();

		if (count($globalmacros) != count($db_globalmacros)) {
            self::exception(60750203);
		}

		$globalmacros = $this->extendObjectsByKey($globalmacros, $db_globalmacros, 'globalmacroid', ['type']);

		foreach ($globalmacros as $index => &$globalmacro) {
			$db_globalmacro = $db_globalmacros[$globalmacro['globalmacroid']];

			if ($globalmacro['type'] != $db_globalmacro['type']) {
				if ($db_globalmacro['type'] == PRS_MACRO_TYPE_SECRET) {
					$globalmacro += ['value' => ''];
				}

				if ($globalmacro['type'] == PRS_MACRO_TYPE_VAULT) {
					$globalmacro += ['value' => $db_globalmacro['value']];
				}
			}

			if (array_key_exists('value', $globalmacro) && $globalmacro['type'] == PRS_MACRO_TYPE_VAULT) {
                $validator = new VaultSecretValidator([
                    'provider' => SettingsHelper::get(SettingsHelper::VAULT_PROVIDER)
                ]);

                if ($validator->validate($globalmacro['value'], $error)) {
                    self::exception(60750001, $error);
                }
			}
		}
		unset($globalmacro);

		$this->checkDuplicates($globalmacros, $db_globalmacros);
	}

    /**
	 * @param array $globalmacros
	 *
	 * @return array
	 */
	public function deleteGlobal(array $globalmacros): Result
    {
        $globalmacroIds = array_keys($globalmacros);
		$this->validateDeleteGlobal($globalmacroIds, $db_globalmacros);

		DB::delete('globalmacro', ['globalmacroid' => $globalmacroIds]);

        $msg = Yii::t('msg', '{name} deleted successfully', [
            'name' => Yii::t('libzbx', 'Macro')
        ]);

        foreach ($globalmacros as $index => &$globalmacro) {
            $details = [];
            foreach($globalmacro as $field => $value) {
                $details[$field] = audit_detail($field, $value, '');
            }
            if (@$globalmacro['type'] == PRS_MACRO_TYPE_SECRET && array_key_exists('value', $globalmacro)) {
                $details['value'] = audit_detail($field, '', StringHelper::toPrivacy($globalmacro['value']));
            }
            $this->auditDelete(RESOURCE_ZAPI, [$globalmacro['globalmacroid'] => $globalmacro['macro']], $msg, $details);
		}

		return $this->success(['globalmacroids' => $globalmacroIds]);
	}

	/**
	 * @param array $globalmacroIds
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDeleteGlobal(array &$globalmacroIds, array &$db_globalmacros = null) {
		$validator = new \app\customs\zapi\common\validators\IdsValidator([
            'flags' => API_NOT_EMPTY,
            'uniq' => true
        ]);

		if (!$validator->validate($globalmacroIds, $error)) {
            self::exception(60750001, $error);
		}
 
        $db_globalmacros = $this->getQuery($globalmacroIds, 'macro')->column();

		if (count($globalmacroIds) != count($db_globalmacros)) {
            self::exception(60750203);
		}
	}

    /**
	 * Check for duplicated macros.
	 *
	 * @param array      $globalmacros
	 * @param string     $globalmacros[]['globalmacroid']  (optional if $db_globalmacros is null)
	 * @param string     $globalmacros[]['macro']          (optional if $db_globalmacros is not null)
	 * @param array|null $db_globalmacros
	 *
	 * @throws APIException if macros already exists.
	 */
	private function checkDuplicates(array $globalmacros, array $db_globalmacros = null): void {
		$macros = [];

		foreach ($globalmacros as $globalmacro) {
			if ($db_globalmacros === null || (array_key_exists('macro', $globalmacro)
					&& MacroHelper::trimMacro($globalmacro['macro'])
						!== MacroHelper::trimMacro($db_globalmacros[$globalmacro['globalmacroid']]['macro']))) {
				$macros[] = $globalmacro['macro'];
			}
		}

		if (!$macros) {
			return;
		}

		$db_macros = $this->getQuery(null, 'macro')->column();

		foreach ($macros as $macro) {
			if (in_array(MacroHelper::trimMacro($macro), $db_macros)) {
                $error = t('zapi', 'Macro "{macro}" already exists.', [
                    'macro' => $macro
                ]);
                self::exception(60750001, $error);
			}
		}
	}

    /**
     * Query represents a SELECT SQL statement in a way that is independent of DBMS. 
     *
     * @param  array|null $ids
     * @param  string     $assignable
     * @return Query
     */
    private function getQuery(?array $ids = null, $assignable = '*'):Query
    {
        $query = (new Query)
            ->select($assignable)
            ->from('globalmacro')
            ->indexBy('globalmacroid');
        if ($ids) {
            $query->where(ZSqlHelper::dbConditionInt('globalmacroid', $ids));
        }
        return $query;
    }
}
