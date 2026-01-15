<?php
namespace app\customs\zapi\common\helpers;

use app\common\components\Result;
use app\customs\zapi\common\macros\CMacrosResolver;
use app\customs\zapi\models\search\HttpTestSearch;
use app\customs\zapi\services\HttpTestService;

class HttpTestHelper
{
    /**
     * @param  array $params
     * @return array
     */
    public static function getHttpTests(array $params): array
    {
        $search = new HttpTestSearch();
        $search->is_all = true;
        $provider = $search->search($params);
        return $provider->getModels();
    }

    /**
     * Resolve http tests macros.
     *
     * @param array $httpTests
     * @param bool  $resolveName
     * @param bool  $resolveStepName
     *
     * @return array
     */
    public static function resolveHttpTestMacros(array $httpTests, $resolveName = true, $resolveStepName = true)
    {
        $names = [];

        $i = 0;
        foreach ($httpTests as $test) {
            if ($resolveName) {
                $names[$test['hostid']][$i++] = $test['name'];
            }

            if ($resolveStepName) {
                foreach ($test['steps'] as $step) {
                    $names[$test['hostid']][$i++] = $step['name'];
                }
            }
        }

        $macrosResolver = new CMacrosResolver();
        $names = $macrosResolver->resolve([
            'config' => 'httpTestName',
            'data' => $names
        ]);

        $i = 0;
        foreach ($httpTests as $tnum => $test) {
            if ($resolveName) {
                $httpTests[$tnum]['name'] = $names[$test['hostid']][$i++];
            }

            if ($resolveStepName) {
                foreach ($httpTests[$tnum]['steps'] as $snum => $step) {
                    $httpTests[$tnum]['steps'][$snum]['name'] = $names[$test['hostid']][$i++];
                }
            }
        }

        return $httpTests;
    }

    /**
     * 克隆探测
     *
     * @param  integer $srcHostId
     * @param  integer $dstHostId
     * @return Result
     */
    public static function copyHttpTests($srcHostId, $dstHostId): Result
    {
        $httpTests = self::getHttpTests([
            'output' => ['name', 'delay', 'status', 'variables', 'agent', 'authentication',
                'http_user', 'http_password', 'http_proxy', 'retries', 'ssl_cert_file', 'ssl_key_file',
                'ssl_key_password', 'verify_peer', 'verify_host', 'headers'
            ],
            'hostids' => $srcHostId,
            'selectTags' => ['tag', 'value'],
            'selectSteps' => ['name', 'no', 'url', 'query_fields', 'timeout', 'posts', 'required', 'status_codes',
                'variables', 'follow_redirects', 'retrieve_mode', 'headers'
            ],
            'inherited' => false
        ]);

        if (!$httpTests) {
            return Result::instance()->setSuccess();
        }

        foreach ($httpTests as &$httpTest) {
            $httpTest['hostid'] = $dstHostId;

            unset($httpTest['httptestid']);
        }
        unset($httpTest);

        return HttpTestService::instance()->create($httpTests);
    }
}
