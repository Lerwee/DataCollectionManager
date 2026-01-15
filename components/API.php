<?php

namespace app\customs\zapi\components;

use app\common\base\BaseApi;
use app\customs\zapi\services\HostService;
use app\customs\zapi\services\HttpTestService;
use app\customs\zapi\services\ScriptService;
use app\customs\zapi\services\TemplateService;
use yii\base\UnknownClassException;

/**
 * 内部Service
 *
 * For example:
 * ```php
 * $params = [];
 * // 创建模板
 * API::Template()->create($params);
 * // 更新模板
 * API::Template()->update($params);
 * // 删除模板
 * API::Template()->delete($params);
 *
 * ```
 * 
 * @method static TemplateService Template() Template
 * @method static HostService Host()         Host
 * @method static HttpTestService Httptest() Http
 * @method static ScriptService Script()     Script
 */
final class API
{
    /**
     * aliases
     *
     * @var array
     */
    private static $aliases = [];

    /**
     * This static method can directly call the specific service.
     *
     * @param string $resource
     * @param array  $arguments
     *
     * @return BaseApi
     * @throws UnknownClassException
     */
    public static function __callStatic($resource, $arguments)
    {
        $aliases = static::$aliases;
        if (array_key_exists($resource, $aliases)) {
            $resource = $aliases[$resource];
        }
        $class = 'app\customs\zapi\components\api\\' . ucfirst($resource);
        if (\class_exists($class)) {
            return $class::instance();
        }
        throw new UnknownClassException($class);
    }
}