<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\services\HttpTestService;

/**
 * Class HttpTestRequestData
 * @package app\customs\zapi\components\Maintenance
 */
class HttpTestRequestData extends RequestData
{
    /**
     * @var string
     */
    public $action = 'create';

    /**
     * @return Result
     */
    public function validate(): Result
    {
        $steps = $this->getRequest('steps', []);
        $field_names = ['headers', 'variables', 'post_fields', 'query_fields'];
        $i = 1;

        foreach ($steps as &$step) {
            $step['no'] = $i++;
            $step['follow_redirects'] = $step['follow_redirects']
                ? HTTPTEST_STEP_FOLLOW_REDIRECTS_ON
                : HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF;

            foreach ($field_names as $field_name) {
                $step[$field_name] = [];
            }

            if (array_key_exists('pairs', $step)) {
                foreach ($field_names as $field_name) {
                    foreach ($step['pairs'] as $pair) {
                        if (array_key_exists('type', $pair) && $field_name === $pair['type']) {
                            $name = array_key_exists('name', $pair) ? trim($pair['name']) : '';
                            $value = array_key_exists('value', $pair) ? trim($pair['value']) : '';

                            if ($name === '' && $value === '') {
                                continue;
                            }

                            $step[$field_name][] = [
                                'name' => $name,
                                'value' => $value
                            ];
                        }
                    }
                }
                unset($step['pairs']);
            }

            if ($step['post_type'] == PRS_POSTTYPE_FORM) {
                $step['posts'] = $step['post_fields'];
            } else {
                $step['posts'] = trim($step['posts']);
            }

            unset($step['post_fields'], $step['post_type']);
        }
        unset($step);

        $tags = $this->getRequest('tags', []);
        foreach ($tags as $key => $tag) {
            if ($tag['tag'] === '' && $tag['value'] === '') {
                unset($tags[$key]);
            } elseif (array_key_exists('type', $tag) && !($tag['type'] & PRS_PROPERTY_OWN)) {
                unset($tags[$key]);
            } else {
                unset($tags[$key]['type']);
            }
        }

        $authentication = $this->getRequest('authentication');


        $httpTest = [
            'hostid' => $this->getRequest('hostid'),
            'name' => $this->getRequest('name'),
            'authentication' => $authentication,
            'delay' => getRequest('delay', DB::getDefault('httptest', 'delay')),
            'retries' => $this->getRequest('retries'),
            'status' => hasRequest('status') ? HTTPTEST_STATUS_ACTIVE : HTTPTEST_STATUS_DISABLED,
            'agent' => hasRequest('agent_other') ? getRequest('agent_other') : getRequest('agent'),
            'variables' => [],
            'http_proxy' => $this->getRequest('http_proxy'),
            'steps' => $steps,
            'http_user' => $authentication == PRS_HTTP_AUTH_NONE ? '' : $this->getRequest('http_user'),
            'http_password' => $authentication == PRS_HTTP_AUTH_NONE ? '' : $this->getRequest('http_password'),
            'verify_peer' => $this->getRequest('verify_peer', PRS_HTTP_VERIFY_PEER_OFF),
            'verify_host' => $this->getRequest('verify_host', PRS_HTTP_VERIFY_HOST_OFF),
            'ssl_cert_file' => $this->getRequest('ssl_cert_file'),
            'ssl_key_file' => $this->getRequest('ssl_key_file'),
            'ssl_key_password' => $this->getRequest('ssl_key_password'),
            'headers' => [],
            'tags' => $tags
        ];


        if ($httpTestId = $this->getRequest('httptestid')) {
            $dbHttpTest = HttpTestService::instance()->getHttpTestsByIds([$httpTestId]);
            $dbHttpTest = reset($dbHttpTest);
            $dbHttpSteps = prs_toHash($dbHttpTest['steps'], 'httpstepid');
            $httpTest = CArrayHelper::unsetEqualValues($httpTest, $dbHttpTest);
            foreach (['headers', 'variables'] as $field_name) {
                if (count($httpTest[$field_name]) !== count($dbHttpTest[$field_name])) {
                    continue;
                }

                $changed = false;
                foreach ($httpTest[$field_name] as $key => $field) {
                    if (
                        $dbHttpTest[$field_name][$key]['name'] !== $field['name']
                        || $dbHttpTest[$field_name][$key]['value'] !== $field['value']
                    ) {
                        $changed = true;
                        break;
                    }
                }

                if (!$changed) {
                    unset($httpTest[$field_name]);
                }
            }

            foreach ($httpTest['steps'] as $snum => $step) {
                if ($step['httpstepid'] < 1) {
                    unset($step['httpstepid']);
                    unset($httpTest['steps'][$snum]['httpstepid']);
                }
                if (array_key_exists('httpstepid', $step) && array_key_exists($step['httpstepid'], $dbHttpSteps)) {
                    $db_step = $dbHttpSteps[$step['httpstepid']];
                    $new_step = CArrayHelper::unsetEqualValues($step, $db_step, ['httpstepid']);
                    foreach (['headers', 'variables', 'posts', 'query_fields'] as $field_name) {
                        if (
                            !array_key_exists($field_name, $new_step)
                            || !is_array($new_step[$field_name]) || !is_array($db_step[$field_name])
                            || count($new_step[$field_name]) !== count($db_step[$field_name])
                        ) {
                            continue;
                        }

                        $changed = false;
                        foreach ($new_step[$field_name] as $key => $field) {
                            if (
                                $db_step[$field_name][$key]['name'] !== $field['name']
                                || $db_step[$field_name][$key]['value'] !== $field['value']
                            ) {
                                $changed = true;
                                break;
                            }
                        }

                        if (!$changed) {
                            unset($new_step[$field_name]);
                        }
                    }
                    $httpTest['steps'][$snum] = $new_step;
                }
            }

            $httpTest['httptestid'] = $httpTestId;
        } else {
            foreach ($httpTest['steps'] as &$step) {
                unset($step['httptestid'], $step['httpstepid']);
            }
            unset($step);
        }
        return $this->success($httpTest);
    }
}
