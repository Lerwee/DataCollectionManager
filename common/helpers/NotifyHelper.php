<?php


namespace app\customs\zapi\common\helpers;

use app\common\helpers\SqlHelper;
use app\customs\zapi\models\Notify;
use app\customs\zapi\models\NotifySetting;
use app\modules\inform\models\MediaFactory;
use Yii;

class NotifyHelper
{
    static $notifyId;
    /**
     * pack
     *
     * @param  NotifySetting $setting
     * @param  array         $userMedias See [[NotifySetting::getUserMedias()]]
     * @param  int           $event
     * @return array
     */
    public static function pack(NotifySetting $setting, array $userMedias, int $event = Notify::EVENT_TYPE_UNAVAILABLE)
    {
        $mediaDriver = array_column(MediaFactory::getDrivers(), 'label', 'value');
        $userIds = $setting->getReceivers();
        $error = Yii::t('zapi', 'The user does not configure the {media} account');
        $rows = [];
        foreach($setting->medias as $media) {
            if ($media == MediaFactory::ID_MEDIA_SCRIPT) {
                $rows[] = static::packScript($setting, $event);
            } else {
                $err = str_replace('{media}', $mediaDriver[$media] ?? $media, $error);
                if (!array_key_exists($media, $userMedias)) {
                    $rows = array_merge($rows, static::packFailure($userIds, $setting->subject, $setting->message, $err, $event));
                    continue;
                }

                $hasMediaUsers = [];
                foreach($userMedias[$media] as $userMedia) {
                    $hasMediaUsers[$userMedia['userid']] = $userMedia['userid'];
                    $rows[] = static::packSuccess($userMedia, $setting->subject, $setting->message, $event);
                }

                if ($hasMediaUsers && $buf = array_diff($userIds, $hasMediaUsers)) {
                    $rows = array_merge($rows, static::packFailure($buf, $setting->subject, $setting->message, $err, $event, $media));
                }
            }
        }
        return $rows;
    }

    /**
     * pack script data
     *
     * Note：脚本仅需一条记录
     *
     * @param  NotifySetting $setting
     * @param  int           $event
     * @return array|null
     */
    public static function packScript(NotifySetting $setting, int $event = Notify::EVENT_TYPE_UNAVAILABLE)
    {
        if (!in_array(MediaFactory::ID_MEDIA_SCRIPT, $setting->medias)) {
            return null;
        }

        $scriptMedia = [
            'userid' => min($setting->getReceivers()),
            'account' => '',
            'type' => MediaFactory::ID_MEDIA_SCRIPT
        ];
        $extra = is_array($setting->media_extra) ? json_encode($setting->media_extra) : (string) $setting->media_extra;
        return static::packSuccess($scriptMedia, $setting->subject, $setting->message, $event, $extra);
    }

    /**
     * pack notify data (failure)
     *
     * @param  array  $userIds
     * @param  string $subject
     * @param  string $message
     * @param  string $error
     * @param  int    $event
     * @param  int    $media
     * @return array
     */
    public static function packFailure(array $userIds, string $subject, string $message, string $error, int $event, int $media = MediaFactory::ID_MEDIA_MAIL)
    {
        $rows = [];
        $timestamp = time();
        foreach($userIds as $userId) {
            $rows[] = [
                'id' => static::$notifyId ++,
                'userid' => $userId,
                'event_type' => $event,
                'media_type' => $media,
                'media_extra' => '',
                'status' => Notify::STATUS_SEND_FAIL,
                'send_to' => '',
                'subject' => $subject,
                'message' => $message,
                'error' => $error,
                'created_at' => $timestamp,
                'sended_at' => $timestamp,
            ];
        }
        return $rows;
    }

    /**
     * @param  array   $userMedia
     * @param  string  $subject
     * @param  string  $message
     * @param  integer $event
     * @return array
     */
    public static function packSuccess(array $userMedia, string $subject, string $message, int $event, $mediaExtra = '')
    {
        static::$notifyId ++;
        return [
            'id' => static::$notifyId ++,
            'userid' => $userMedia['userid'],
            'event_type' => $event,
            'media_type' => $userMedia['type'],
            'media_extra' => $mediaExtra,
            'status' => Notify::STATUS_SEND_WAIT,
            'send_to' => $userMedia['account'],
            'subject' => $subject,
            'message' => $message,
            'error' => '',
            'created_at' => time(),
            'sended_at' => 0,
        ];
    }

    public static function loadNotifyId()
    {
        static::$notifyId = Notify::find()->max('id') ?: 0;
        static::$notifyId++;
    }

    public static function resetNotifyPrimaryIncrement()
    {
        return SqlHelper::resetSerialSequence(Notify::tableName(), 'id', static::$notifyId);
    }
}