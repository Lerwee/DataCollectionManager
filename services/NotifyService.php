<?php

namespace app\customs\zapi\services;

use app\common\components\console\Stdout;
use app\common\components\Result;
use app\common\provider\ActiveDataProvider;
use app\customs\zapi\common\helpers\NotifyHelper;
use app\customs\zapi\models\Notify;
use app\customs\zapi\models\NotifySetting;
use app\modules\inform\models\MediaFactory;
use app\modules\inform\services\ScriptService;
use app\modules\libzbx\components\ZabbixServer;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the service class for table "{{%perseus_notifies}}".
 */
class NotifyService extends \app\common\base\BaseService
{
    /**
     * Returns list
     *
     * @return Result
     */
    public function getList(array $params = []): Result
    {
        $query = Notify::find();

        $query->with('user');

        if (isset($params['userid']) && $params['userid'] !== '') {
            $query->where(['userid' => (int) $params['userid']]);
        }

        if (empty($params['status'])) {
            $query->where(['status' => [Notify::STATUS_SEND_OK, Notify::STATUS_SEND_FAIL]]);
        } else {
            $query->where(['status' => (int) $params['status']]);
        }

        $query->orderBy([
            'id' => SORT_DESC,
        ]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        return $this->success([
            'rows' => $this->format($dataProvider->getModels()),
            'total' => $dataProvider->getTotalCount(),
        ]);
    }

    /**
     * @param  Notify[] $models
     * @return array
     */
    protected function format($models)
    {
        $rows = [];
        $medias = array_column(MediaFactory::getDrivers(), 'label', 'value');
        foreach ($models as $model) {
            $row = $model->getAttributes();
            $row['username'] = $model->user ? $model->user->getUserName() : [];
            $row['media_label'] = $medias[$model->media_type] ?? $model->media_type;
            $row['media_extra'] = $model->getMediaExtra();
            $rows[] = $row;
        }
        return $rows;
    }

    public function getProfile()
    {
        $filter = [9]; // [MediaFactory::ID_MEDIA_TICKET];
        return $this->success([
            'medias' => MediaFactory::getDrivers($filter),
        ]);
    }

    /**
     * 设置
     *
     * @param  array  $params
     * @return Result
     */
    public function setting(array $params = []): Result
    {
        $model = NotifySetting::instance()->reload();

        $data = $model->getAttributes();
        $msg = '';
        if ($params) {
            $model->setAttributes($params);
            if (!$model->save()) {
                return $this->error(60020001, current($model->getFirstErrors()), $model->getErrors());
            }

            $audits = [];
            foreach ($data as $attr => $oldVal) {
                if (array_key_exists($attr, $params)) {
                    $newVal = $params[$attr];
                    if (is_array($newVal)) {
                        asort($newVal);
                        $newVal = json_encode($newVal);
                    }
                    if (is_array($oldVal)) {
                        asort($oldVal);
                        $oldVal = json_encode($oldVal);
                    }
                    if ($oldVal != $newVal) {
                        $audits[] = audit_detail($attr, $oldVal, $newVal);
                    }
                }
            }

            $msg = Yii::t('msg', 'Save Success');
            $this->auditUpdate(6002, $model->getResourceLabel(), $msg, $audits);

            $data = array_merge($data, $params);
        }

        $data['notify_users'] = $model->getNotifyUsers();
        $data['notify_roles'] = $model->getNotifyRoles();

        return $this->success($data, $msg);
    }

    /**
     * 采集服务状态监听任务
     *
     * - 检查状态是否异常
     * - 检查是否发生集群切换
     * @return void
     */
    public function listen()
    {
        $stdout = Stdout::instance();
        $setting = NotifySetting::instance()->reload();
        if (!$setting->status) {
            $stdout->warningLine('采集服务器：【未启用】状态监听服务');
            return;
        }

        [$host, $port] = SysInfoService::instance()->getServerAddress();
        $server = new ZabbixServer(['host' => $host, 'port' => $port]);
        try {
            $isRunning = $server->isRunning;
        } catch (\Exception $e) {
            Yii::error(parse_exception($e));
            $isRunning = false;
        }

        $log = $this->logServerState($host);

        // 采集状态异常
        if (!$isRunning) {
            if ($log['running']) {
                $this->logServerState($host, [$isRunning, $host]);
                $stdout->warningLine('采集服务器：采集异常');
                $result = $this->createNotification($setting);
                $this->send($result);
                return;
            } else {
                $stdout->warningLine('采集服务器：采集异常，已发送过通知，上次检查时间 - ' . date('Y-m-d H:i:s', $log['updated_at']));
                $this->logServerState($host, [$isRunning, $host]);
                return;
            }
        }

        // server IP 发生改变
        if ($host == env('ZABBIX_SERVER', '127.0.0.1') && $host == $log['host']) {
            $this->logServerState($host, [$isRunning, $host]);
            return;
        }

        $this->logServerState($host, [$isRunning, $host]);
        $stdout->warningLine("采集服务器：采集的服务IP发生切换变更-{$log['host']} => {$host}");
        $result = $this->createNotification($setting, Notify::EVENT_TYPE_IP_CHANGE);
        $this->send($result);
    }

    /**
     * 创建通知信息
     *
     * @param  NotifySetting $setting
     * @param  int           $eventType
     * @return Result
     */
    public function createNotification(NotifySetting $setting, int $eventType = Notify::EVENT_TYPE_UNAVAILABLE): Result
    {
        $medias = $setting->getUserMedias(false);
        NotifyHelper::loadNotifyId();
        if (empty($medias)) {
            $error = Yii::t('zapi', 'The user does not have any media configured');
            if ($rows = NotifyHelper::packFailure($setting->getReceivers(), $setting->subject, $setting->message, $error, $eventType)) {
                Notify::batchInsert($rows);
            }
            if ($row = NotifyHelper::packScript($setting, $eventType)) {
                Notify::batchInsert([$row]);
                $rows[] = $row;
                return $this->success($rows);
            }
            return $this->error(60020002, $error, $rows);
        }
        $rows = NotifyHelper::pack($setting, $medias, $eventType);
        Notify::batchInsert($rows);
        return $this->success($rows);
    }

    public function send(Result $result)
    {
        $stdout = Stdout::instance();
        if (!$result->isSuccess()) {
            $count = count($result->getData());
            $r = "记录异常通知数：{$count}，发送成功数：0；【{$result->getErrcode()}】{$result->getErrmsg()}";
            $stdout->warningLine("采集服务器：{$r}");
            return false;
        }
        $rows = $result->getData();

        $providers = ArrayHelper::index(MediaFactory::getDrivers([], false), 'id');
        $success = 0;
        $count = count($rows);
        foreach ($rows as $row) {
            if ($row['status'] !== Notify::STATUS_SEND_WAIT) {
                continue;
            }

            try {
                if ($row['media_type'] == MediaFactory::ID_MEDIA_SCRIPT) {
                    $scriptId = json_decode($row['media_extra'], true)['script_id'] ?? 0;
                    $service = ScriptService::instance()->execute($scriptId, $row);
                    if ($service->isSuccess()) {
                        $r = $service->getData();
                        if ($r['code'] != 0) {
                            $data = [
                                'status' => Notify::STATUS_SEND_FAIL,
                                'error' => $r['error'] ?: $r['result'],
                                'sended_at' => time(),
                            ];
                        } else {
                            $data = [
                                'status' => Notify::STATUS_SEND_OK,
                                'sended_at' => time(),
                            ];
                            $success++;
                        }
                    } else {
                        $data = [
                            'status' => Notify::STATUS_SEND_FAIL,
                            'error' => '脚本不存在或已删除',
                            'sended_at' => time(),
                        ];
                    }

                } else {
                    /** @var \app\modules\inform\services\Sender $sender */
                    $sender = ($providers[$row['media_type']])->getSender();
                    $sender->setTo($row['send_to']);
                    $sender->setSubject($row['subject']);
                    $sender->setContent($row['message']);
                    if ($sender->send()) {
                        $data = [
                            'status' => Notify::STATUS_SEND_OK,
                            'sended_at' => time(),
                        ];
                        $success++;
                    } else {
                        $data = [
                            'status' => Notify::STATUS_SEND_FAIL,
                            'error' => $sender->getLastError(),
                            'sended_at' => time(),
                        ];
                    }
                }

            } catch (\Exception $e) {
                $data = [
                    'status' => Notify::STATUS_SEND_FAIL,
                    'error' => $e->getMessage(),
                    'sended_at' => time(),
                ];
            }
            Notify::updateAll($data, ['id' => $row['id']]);
        }
        $stdout->successLine("采集服务器：记录异常通知数：{$count}，发送成功数：{$success}");
    }

    /**
     * 读写取采集状态
     *
     * @param  string $host
     * @param  array  $change
     * @return array
     */
    public function logServerState(string $host, array $change = [])
    {
        $log = configure('prs_server_listen', DATA_ARRAY, []) + [
            'running' => true,
            'host' => $host,
            'updated_at' => time(),
        ];
        if ($change) {
            [$log['running'], $log['host']] = $change;
            $log['updated_at'] = time();
            configure_set('prs_server_listen', $log);
        }

        return $log;
    }
}
