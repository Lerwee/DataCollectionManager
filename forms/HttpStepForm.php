<?php

namespace app\customs\zapi\forms;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\validators\HttpPostValidator;
use app\customs\zapi\common\validators\IdValidator;
use app\customs\zapi\common\validators\Int32Validator;
use app\customs\zapi\common\validators\ObjectsValidator;
use app\customs\zapi\common\validators\TimeUnitValidator;
use app\customs\zapi\common\validators\Utf8StringValidator;
use app\customs\zapi\common\validators\VariableNameValidator;

class HttpStepForm extends BaseForm
{
    /**
     * @param string $method
     * @return array
     */
    public static function getValidationRules(string $method = 'create', bool $full = true): array
    {
        $fields = [
            'httpstepid' => [IdValidator::class],
            'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httpstep', 'name')],
            'no' => [Int32Validator::class],
            'url' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httpstep', 'url')],
            'query_fields' => static::getQueryFieldsValidationRules($method),
            'posts' => [
                HttpPostValidator::class,
                'length' => DB::getFieldLength('httpstep', 'posts'),
                'nameLength' => DB::getFieldLength('httpstep_field', 'name'),
                'valueLength' => DB::getFieldLength('httpstep_field', 'value')
            ],
            'variables' => static::getVariablesValidationRules($method),
            'headers' => static::getHeadersValidationRules($method),
            'follow_redirects' => [Int32Validator::class, 'in' => [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON]],
            'retrieve_mode' => [Int32Validator::class, 'in' => [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]],
            'timeout' => [TimeUnitValidator::class, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => [[1, SEC_PER_HOUR]]],
            'required' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('httpstep', 'required')],
            'status_codes' => [Utf8StringValidator::class, 'length' => DB::getFieldLength('httpstep', 'status_codes')]
        ];

        if ($method == 'create') {
            $fields['no']['flags'] = API_REQUIRED;
            unset($fields['httpstepid']);
        } else {
            $fields['name']['flags'] = API_NOT_EMPTY;
            $fields['url']['flags'] = API_NOT_EMPTY;
        }

        if ($full) {
            return [
                ObjectsValidator::class,
                'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['name'], ['no']],
                'fields' => $fields
            ];
        }

        return $fields;
    }

    public static function getQueryFieldsValidationRules(string $method = 'create', $full = true): array
    {
        $fields = [
            'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httpstep_field', 'name')],
            'value' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httpstep_field', 'value')]
        ];

        if ($full) {
            return [
                ObjectsValidator::class,
                'fields' => $fields
            ];
        }

        return $fields;
    }

    public static function getVariablesValidationRules(string $method = 'create', bool $full = true): array
    {
        $fields = [
            'name' => [VariableNameValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httpstep_field', 'name')],
            'value' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httpstep_field', 'value')]
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
            'name' => [Utf8StringValidator::class, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httpstep_field', 'name')],
            'value' => [Utf8StringValidator::class, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httpstep_field', 'value')]
        ];

        if ($full) {
            return [
                ObjectsValidator::class,
                'fields' => $fields
            ];
        }

        return $fields;
    }
}
