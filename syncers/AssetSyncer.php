<?php

namespace app\customs\zabbix\syncers;

use app\common\traits\LoggerTrait;
use Yii;
use yii\base\BaseObject;

class AssetSyncer extends BaseObject
{
    use LoggerTrait;

    /**
     * init
     */
    public function init()
    {
        $this->initLogger();
    }

    /**
     * 建立当面模块所需要的静态资源
     *
     * 在WEB目录的zbx文件夹内创建所有zbx模块需要的资源文件
     * 在window下，会直接复制相关文件夹，而在linux下会使用软链接
     */
    public function make()
    {
        $targets = $this->getControllerRoutes();

        $dirs = [
            'fonts', 'images', 'img', 'js', 'styles', 'assets',
        ];
        $cmd = IS_WIN ? 'xcopy  "%s" "%s" /e /i /Y' : 'ln -s %s %s 2>&1';

        $zbxPath = \Yii::getAlias('@app') . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'zabbix';
        @exec(IS_WIN ? 'rd /S /Q ' . $zbxPath : 'rm -rf ' . $zbxPath);
        @mkdir($zbxPath);

        $src = config('zabbix_source', 'z');
        if ('z' == $src) {
            $source = Yii::getAlias('@app') . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'z';
        } else {
            $source = rtrim($src, DIRECTORY_SEPARATOR);
        }
        $this->info('Load from ' . $source);
        foreach ($targets as $target) {
            $destination = $zbxPath . DIRECTORY_SEPARATOR . $target;
            foreach ($dirs as $dir) {
                @mkdir($destination);
                $src = $source . DIRECTORY_SEPARATOR . $dir;
                if (file_exists($src)) {
                    $dest = $destination . DIRECTORY_SEPARATOR . $dir;
                    $c    = sprintf($cmd, $src, $dest);
                    $this->info($c);
                    exec($c, $output, $code);
                    $code != 0 && $this->warning(current($output));
                }
            }
        }

        //兼容zabbix4.x版本字体路径
        //@see z/include/defines.inc.php #68
        $src = $source . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'fonts';
        if (file_exists($src)) {
            $dest = Yii::getAlias('@app/web/assets/fonts');
            $c    = sprintf($cmd, $src, $dest);
            $this->info($c);
            $output = [];
            exec($c, $output, $code);
            $code != 0 && $this->warning(current($output));
        }

        $src = $source . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img';
        if (file_exists($src)) {
            $dest = Yii::getAlias('@app/web/zbx/img');
            $c    = sprintf($cmd, $src, $dest);
            $this->info($c, $output, $code);
            $output = [];
            exec($c, $output, $code);
            $code != 0 && $this->warning(current($output));
        }

        $user  = env('FILE_USER', 'itpos');
        $group = env('FILE_GROUP', 'itpos');

        $command = "chown -R  $user:$group " . $zbxPath;
        $this->info($command);
        exec($command);
    }

    /**
     * 返回控制器路由集合
     *
     * Note: 仅返回控制器名称
     *
     * @param bool $onlyName 仅返回控制器名称
     * @return array
     */
    protected function getControllerRoutes($onlyName = true)
    {
        $prefix = '';

        $filterClasses = ['\Controller', '\SiteController'];

        $controllers = [];

        $controllerNamespace = 'app\customs\zabbix\controllers';
        $controllerPath = Yii::getAlias('@' . str_replace('\\', '/', $controllerNamespace));
        if (is_dir($controllerPath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($controllerPath, \RecursiveDirectoryIterator::KEY_AS_PATHNAME)
            );
            $iterator = new \RegexIterator($iterator, '/.*Controller\.php$/', \RecursiveRegexIterator::GET_MATCH);
            
            foreach ($iterator as $matches) {
                $file = $matches[0];
                $relativePath = str_replace($controllerPath, '', $file);
                $class = strtr($relativePath, [
                    '/' => '\\',
                    '.php' => '',
                ]);

                if (in_array($class, $filterClasses)) {
                    continue;
                }

                $controllerClass = $controllerNamespace . $class;
                
                if ($this->validateControllerClass($controllerClass)) {
                    $dir = ltrim(pathinfo($relativePath, PATHINFO_DIRNAME), '\\/');

                    $controllerId = \yii\helpers\Inflector::camel2id(substr(basename($file), 0, -14), '-', true);
                    if (!empty($dir)) {
                        $controllerId = $dir . '/' . $controllerId;
                    }
                    $controllers[$prefix . $controllerId] = $controllerClass;
                }
            }
        }
        return $onlyName ? array_keys($controllers) : $controllers;
    }

    /**
     * Validates if the given class is a valid web or REST controller class.
     * @param string $controllerClass
     * @return bool
     * @throws \ReflectionException
     */
    protected function validateControllerClass($controllerClass)
    {
        if (class_exists($controllerClass)) {
            $class = new \ReflectionClass($controllerClass);
            return !$class->isAbstract()
                && ($class->isSubclassOf('yii\web\Controller') || $class->isSubclassOf('yii\rest\Controller'));
        }

        return false;
    }
}
