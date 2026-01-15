<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\MultipleValidator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\TimeUnitValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\UuidValidator;
use app\customs\zapi\common\validators\VariableNameValidator;
use app\modules\libzbx\models\zbx\Hstgrp;

class HttpTestForm extends BaseForm
{
    /**
     * @param string $method
     * @return array
     */
    public static function getValidationRules(string $method = 'create'): array
    {
        $rules = [
            'hostid' => [IdValidator::class, 'flags' => API_REQUIRED],
            'httptestid' => [IdValidator::class, 'flags' => API_REQUIRED],
            'uuid' => ['safe'],
            'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httptest', 'name')],
            'delay' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => [[1, SEC_PER_DAY]]],
            'retries' => [Int32Validator::class, 'in' =>  [[1, 10]]],
            'agent' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('httptest', 'agent')],
            'http_proxy' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('httptest', 'http_proxy')],
            'variables' => static::getVariablesValidationRules($method),
            'headers' => static::getHeadersValidationRules($method),
            'status' => [Int32Validator::class, 'in' => [HTTPTEST_STATUS_ACTIVE, HTTPTEST_STATUS_DISABLED]],
            'authentication' => [Int32Validator::class, 'in' => [PRS_HTTP_AUTH_NONE, PRS_HTTP_AUTH_BASIC, PRS_HTTP_AUTH_NTLM, PRS_HTTP_AUTH_KERBEROS, PRS_HTTP_AUTH_DIGEST]],
            'http_user' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('httptest', 'http_user')],
            'http_password' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('httptest', 'http_password')],
            'verify_peer' => [Int32Validator::class, 'in' => [PRS_HTTP_VERIFY_PEER_OFF, PRS_HTTP_VERIFY_PEER_ON]],
            'verify_host' => [Int32Validator::class, 'in' => [PRS_HTTP_VERIFY_HOST_OFF, PRS_HTTP_VERIFY_HOST_ON]],
            'ssl_cert_file' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('httptest', 'ssl_cert_file')],
            'ssl_key_file' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('httptest', 'ssl_key_file')],
            'ssl_key_password' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('httptest', 'ssl_key_password')],
            'steps' => HttpStepForm::getValidationRules($method),
            'tags' => static::getTagsValidationRules($method)
        ];

        if ($method == 'update') {
            unset($rules['hostid']);
            $rules['name']['flags'] = API_NOT_EMPTY;
        } else {
            unset($rules['httptestid']);
        }

        return $rules;
    }

    public static function getVariablesValidationRules(string $method = 'create', bool $full = true): array
    {
        $fields = [
            'name' => [VariableNameValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httptest_field', 'name')],
            'value' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httptest_field', 'value')]
        ];

        if ($full) {
            return [
                ObjectsValidator::class,
                'uniq' => [['name']],
                'fields' => $fields
            ];
        }

        return $fields;
    }

    public static function getHeadersValidationRules(string $method = 'create', bool $full = true): array
    {
        $fields = [
            'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httptest_field', 'name')],
            'value' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httptest_field', 'value')]
        ];

        if ($full) {
            return [
                ObjectsValidator::class,
                'fields' => $fields
            ];
        }

        return $fields;
    }

    public static function getTagsValidationRules(string $method = 'create', bool $full = true): array
    {
        $fields = [
            'tag' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httptest_tag', 'tag')],
            'value' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('httptest_tag', 'value'), 'default' => DB::getDefault('httptest_tag', 'value')]
        ];

        if ($full) {
            return [
                ObjectsValidator::class,
                'uniq' => [['tag', 'value']],
                'fields' => $fields
            ];
        }

        return $fields;
    }

    public static function getUuidValidationRules(string $method = 'create', bool $full = true): array
    {
        $fields = [
            'host_status' => ['safe'],
            'uuid' => [
                MultipleValidator::class,
                'rules' => [
                    UuidValidator::class, 'when' => function ($model) {
                        $model['host_status'] == HOST_STATUS_TEMPLATE;
                    }
                ],
                'else' => [
                    Utf8StringValidator::class, 'in' => DB::getDefault('httptest', 'uuid'), 'unset' => true
                ]
            ]
        ];

        if ($full) {
            return [
                ObjectsValidator::class,
                'flags' => API_ALLOW_UNEXPECTED,
                'uniq' => [['uuid']],
                'fields' => $fields
            ];
        }

        return $fields;
    }

    /**
     * validate uniqueness
     *
     * @param  array $httpTests
     * @throws ValidateException
     */
    public static function validateUniqueness(array $httpTests)
    {
        $rules =  [
            'hostid' => [IdValidator::class],
            'name' => [Utf8StringValidator::class],
            'steps' => [
                ObjectsValidator::class,
                'uniq' => [['name']],
                'flags' => API_ALLOW_UNEXPECTED,
                'fields' => [
                    'name' => [Utf8StringValidator::class],

                ]
            ],
        ];

        $bool = ValidateHelper::validateObjects($httpTests, $rules, ['uniq' => [['hostid', 'name']], 'flags' => API_ALLOW_UNEXPECTED], $error);
        if (!$bool) {
            self::exception(60750001, $error);
        }
    }
}
