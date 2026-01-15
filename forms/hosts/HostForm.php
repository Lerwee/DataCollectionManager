<?php

namespace app\customs\zapi\forms\hosts;

use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\ValidateHelper;
use app\customs\zapi\common\parsers\CHostNameParser;
use app\customs\zapi\common\validators\UnexpectedValidator;
use app\customs\zapi\services\HostGroupService;
use yii\base\Exception;

class HostForm extends BaseHostForm
{
    public static function getUpdateValidationRulesDiscovered(): array
    {
        return [
            [UnexpectedValidator::class, 'error_type' => API_ERR_DISCOVERED],
        ];
    }

    /**
     * @param array $hosts
     * @param array|null $dbHosts
     * @return array
     * @throws ValidateException
     */
    public static function validateHosts(array &$hosts, array $dbHosts = null)
    {
        $groupIds = [];
        $hostNameParser = new CHostNameParser();
        $macroRules = self::getMacrosValidationRules($dbHosts ? 'update' : 'create');
        $macroFieldRules = $macroRules['fields'];
        unset($macroRules[0], $macroRules['fields']);
        foreach ($hosts as $index => &$host) {
            // Validate mandatory fields.
            if ($dbHosts && empty($host['hostid'])) {
                throw new ValidateException(60750101, t('zapi', 'Wrong fields for host "{host}".', [
                    'name' => $host['host'] ?? ''
                ]));
            }
            // Property 'auto_compress' is not supported for hosts.
            if (array_key_exists('auto_compress', $host)) {
                throw new ValidateException(60750101, t('zapi', 'Wrong fields for host "{host}".', [
                    'name' => $host['host'] ?? ''
                ]));
            }

            if ($dbHosts) {
                // Validate host permissions.
                if (!array_key_exists($host['hostid'], $dbHosts)) {
                    throw new ValidateException(60750101, t('yii', 'Missing required parameters: {params}', ['param' => 'hostid']));
                }
            } else {
                // Validate "host" field.
                if ($hostNameParser->parse($host['host']) != CHostNameParser::PARSE_SUCCESS) {
                    throw new ValidateException(
                        60750101,
                        t('zapi', 'Incorrect characters used for host name "{name}".', ['name' => $host['host']])
                    );
                }

                // If visible name is not given or empty it should be set to host name. Required for duplicate checks.
                if (!array_key_exists('name', $host) || trim($host['name']) === '') {
                    $host['name'] = $host['host'];
                }

                // 新增时分组是必填参数
                if (!array_key_exists('groups', $host)) {
                    throw new ValidateException(60750101, t('zapi', 'Host "{host}" cannot be without host group.', ['host' => $host['host']]));
                }
            }

            // Validate "groups" field.
            if (array_key_exists('groups', $host)) {
                if (!is_array($host['groups']) || !$host['groups']) {
                    throw new ValidateException(60750101, t('zapi', 'Host "{host}" cannot be without host group.', [
                        'host' => $dbHosts ? $dbHosts[$host['hostid']]['host'] : $host['host']
                    ]));
                }
                $host['groups'] = to_array($host['groups']);

                foreach ($host['groups'] as $group) {
                    if (!is_array($group) || (is_array($group) && !array_key_exists('groupid', $group))) {
                        throw new ValidateException(60750101, t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' => 'groups',
                            'error' => t('zapi', 'the parameter "{parameter}" is missing', ['parameter' => 'groupid'])
                        ]));
                    }
                    $groupIds[$group['groupid']] = true;
                }
            }

            // Permissions to host groups is validated in massUpdate().
            if (array_key_exists('macros', $host)) {
                if (!ValidateHelper::validateObjects($host['macros'], $macroFieldRules, $macroRules + [
                    '_path' => '/' . ($index + 1) . '/macros'
                ], $error)) {
                    throw new ValidateException(60750101, $error);
                }
            }
        }
        unset($host);

        // Validate permissions to host groups.
        if (!$dbHosts && $groupIds) {
            $dbGroups =  HostGroupService::instance()->getGroups([
                'groupids' => array_keys($groupIds),
                'preserveKey' => true
            ]);
            foreach ($hosts as $host) {
                foreach ($host['groups'] as $g => $group) {
                    if (!array_key_exists($group['groupid'], $dbGroups)) {
                        throw new ValidateException(60750101, t('zapi', 'Incorrect value for field "{attribute}", {error}.', [
                            'attribute' => $host['host'] . '/groups/' . ($g + 1) . '/groupid',
                            'error' => t('zapi', 'the parameter "{parameter}" is missing', ['parameter' => 'groupid'])
                        ]));
                    }
                }
            }
        }
        return $hosts;
    }

    /**
     * @param array $hosts
     * @param array
     * @throws ValidateException
     */
    public static function validateTags(array &$hosts)
    {
        $tagRules = ['tags' => HostForm::getTagsValidationRules()];
        if (!ValidateHelper::validateObjects($hosts, $tagRules, ['flags' => API_ALLOW_UNEXPECTED], $error)) {
            throw new ValidateException(60750101, $error);
        }

        return $hosts;
    }
}
