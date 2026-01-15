<?php

namespace app\customs\zapi\commands;

use app\customs\zapi\services\NotifyService;
use app\common\base\BaseConsoleController;

/**
 * Class ZapiController
 * @package app\customs\zapi\commands
 */
class ZapiController extends BaseConsoleController
{
    /**
     * generate table scheme
     */
    public function actionGs()
    {
        $class = \Yii::getAlias('@app/runtime/DB.php', false);
        $data = <<<CLS
<?php

class DB
{
    const FIELD_TYPE_INT = 'int';
    const FIELD_TYPE_CHAR = 'char';
    const FIELD_TYPE_ID = 'id';
    const FIELD_TYPE_FLOAT = 'float';
    const FIELD_TYPE_UINT = 'uint';
    const FIELD_TYPE_BLOB = 'blob';
    const FIELD_TYPE_TEXT = 'text';
    const FIELD_TYPE_NCLOB = 'nclob';
    const FIELD_TYPE_CUID = 'cuid';
}

CLS;
        file_put_contents($class, $data);
        require $class;
        $schemes =  require \Yii::getAlias('@app/runtime/schema.inc.php', false);
        $path = dirname(__DIR__) . '/resource/schemes/';
        foreach ($schemes as $table => $data) {
            $text = \yii\helpers\VarDumper::export($data);
            file_put_contents($path . "{$table}.php", "<?php\n\nreturn {$text};\n");
        }
    }

    /**
     * 采集服务状态监听任务
     *
     * - 检查状态是否异常
     * - 检查是否发生集群切换
     */
    public function actionListen()
    {
        NotifyService::instance()->listen();
    }
}
