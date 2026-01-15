<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\CVaultSecretParser;
use app\customs\zapi\common\validators\base\BaseZValidator;

/**
 * API_VAULT_SECRET
 * Class VaultSecretValidator
 * @package app\customs\zapi\common\validators
 */
class VaultSecretValidator extends BaseZValidator
{
    /**
     * @var null|int
     */
    public $length = null;

    public $provider;

    /**
     * @param mixed $value
     * @return array|null
     */
    public function validateValue($value): ?array
    {
        $utfValidator = new Utf8StringValidator(['flags' => API_NOT_EMPTY]);
        $result = $utfValidator->validateValue($value);
        if (!empty($result)) {
            return $result;
        }

        $options = [];
        $providers = [PRS_VAULT_TYPE_HASHICORP, PRS_VAULT_TYPE_CYBERARK];


        if (!in_array($this->provider, $providers)) {
            return [t('zapi', 'value must be one of {value}'), ['value' => implode(', ', $providers)]];
        } else {
            $options['provider'] = $this->provider;
        }

        if ($this->length !== null && mb_strlen($value) > $this->length) {
            return [t('zapi', 'Invalid parameter {attribute}, {error}'), ['attribute' => $this->getPath(), 'error' => t('zapi', 'value is too long')]];
        }

        $vaultSecretParser = new CVaultSecretParser($options);
        if ($vaultSecretParser->parse($value) != CParser::PARSE_SUCCESS) {
            return [t('zapi', 'Invalid parameter {attribute}, {error}'), ['attribute' => $this->getPath(), 'error' => $vaultSecretParser->getError()]];
        }

        return null;
    }
}
