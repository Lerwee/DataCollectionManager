<?php

namespace app\customs\zapi\services\assist;

use app\common\components\Result;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\regexp\CGlobalRegexp;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\PSKValidator;
use app\customs\zapi\common\validators\RegexValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\z\CRegexValidator;
use app\modules\libzbx\models\zbx\Regexps;
use Yii;
use yii\base\Exception;
use yii\db\Query;

/**
 * Class RegexpAssist
 * @package app\customs\zapi\services\assist
 */
class AutoRegAssist extends BaseAssist
{
    /**
     * @return array|bool
     */
    public function get()
    {
        $config = (new Query())->select(['tls_accept'=>'autoreg_tls_accept'])
            ->from('config')
            ->limit(1)->one();
        return $config;
    }

    /**
     * @param array $autoreg
     * @return Result
     */
    public function update(array $autoreg): Result
    {
        try {
            $this->validateUpdate($autoreg, $db_autoreg);

            $upd_config = [];
            $upd_config_autoreg_tls = [];

            if (array_key_exists('tls_accept', $autoreg) && $autoreg['tls_accept'] != $db_autoreg['tls_accept']) {
                $upd_config['autoreg_tls_accept'] = $autoreg['tls_accept'];
            }

            // strings
            foreach (['tls_psk_identity', 'tls_psk'] as $field_name) {
                if (array_key_exists($field_name, $autoreg) && $autoreg[$field_name] !== $db_autoreg[$field_name]) {
                    $upd_config_autoreg_tls[$field_name] = $autoreg[$field_name];
                }
            }

            if ($upd_config) {
                DB::update('config', [
                    'values' => $upd_config,
                    'where' => ['configid' => $db_autoreg['configid']]
                ]);
            }

            if ($upd_config_autoreg_tls) {
                DB::update('config_autoreg_tls', [
                    'values' => $upd_config_autoreg_tls,
                    'where' => ['autoreg_tlsid' => $db_autoreg['autoreg_tlsid']]
                ]);
            }

            return $this->success();
        } catch (ValidateException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error(60750802, $e->getMessage());
        }
    }

    /**
     * @param array $autoreg
     * @param array|null $db_autoreg
     * @throws Exception
     * @throws ValidateException
     * @throws \yii\db\Exception
     */
    protected function validateUpdate(array &$autoreg, array &$db_autoreg = null): void
    {
        $fieldRules = [
            'tls_accept' => [Int32Validator::class, 'in' => HOST_ENCRYPTION_NONE . ':' . (HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK)],
            'tls_psk_identity' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('config_autoreg_tls', 'tls_psk_identity')],
            'tls_psk' => [PSKValidator::class, 'length' => DB::getFieldLength('config_autoreg_tls', 'tls_psk')]
        ];


        if (!ValidateHelper::validateObject($autoreg, $fieldRules, ['flags' => API_ALLOW_UNEXPECTED], $error)) {
            self::exception(60750201, $error);
        }


        $db_autoreg = (new Query())
            ->select(['c.configid', 'tls_accept' => 'c.autoreg_tls_accept', 'ca.autoreg_tlsid', 'ca.tls_psk_identity', 'ca.tls_psk'])
            ->from(['c' => 'config', 'ca' => 'config_autoreg_tls'])
            ->one();

        $tls_accept = array_key_exists('tls_accept', $autoreg) ? $autoreg['tls_accept'] : $db_autoreg['tls_accept'];

        // PSK validation.
        foreach (['tls_psk_identity', 'tls_psk'] as $field_name) {
            if ($tls_accept & HOST_ENCRYPTION_PSK) {
                if (!array_key_exists($field_name, $autoreg) && $db_autoreg[$field_name] === '') {
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/', 'error' => t('zapi', 'the parameter "{parameter}" is missing', ['parameter' => $field_name])]);
                    throw new ValidateException(60750201, $error);
                }

                if (array_key_exists($field_name, $autoreg) && $autoreg[$field_name] === '') {
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/', 'error' => t('zapi', 'cannot be empty')]);
                    throw new ValidateException(60750201, $error);
                }
            } else {
                if (array_key_exists($field_name, $autoreg) && $autoreg[$field_name] !== '') {
                    $error = t('zapi', 'Invalid parameter {attribute}, {error}', ['attribute' => '/', 'error' => t('zapi', 'should be empty')]);
                    throw new ValidateException(60750201, $error);
                }

                if (!array_key_exists($field_name, $autoreg) && $db_autoreg[$field_name] !== '') {
                    $autoreg[$field_name] = '';
                }
            }
        }
    }

}