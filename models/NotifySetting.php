<?php

namespace app\customs\zapi\models;

use app\common\base\BaseModel;
use app\modules\auth\models\Role;
use app\modules\auth\models\User;
use app\modules\auth\models\UserMedia;
use app\modules\auth\models\UserRole;
use app\modules\inform\models\MediaFactory;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * @var string $subject
 * @var string $message
 * @var int    $status
 * @var int[]  $medias
 * @var int[]  $users
 * @var int[]  $roles
 * @var array  $media_extra
 */
class NotifySetting extends BaseModel
{
    public $subject;
    public $message;
    public $status;
    public $medias;
    public $users;
    public $roles;
    public $media_extra;

    /**
     * @var int[] 用户ID集合
     */
    private $receiver;

    /**
     * 配置名称
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'perseus_notify_setting';
    }

    /**
     * {@inheritDoc}
     */
    public function rules()
    {
        return [
            [['subject', 'message', 'medias'], 'required'],
            [['status'], 'default', 'value' => 1],
            [
                ['users', 'roles'], 'required',
                'when' => function () {
                    return (empty($this->users) && empty($this->roles));
                }
            ],
            [['users', 'roles'], 'each', 'rule' => ['integer']],
            [ 'medias', 'in', 'range' => array_column(MediaFactory::getDrivers(), 'value'), 'allowArray' => true],
            ['users', 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id', 'allowArray' => true],
            ['roles', 'exist', 'targetClass' => Role::class, 'targetAttribute' => 'id', 'allowArray' => true],
            [['media_extra'], 'safe'],
        ];
    }

    /**
     * Saves
     *
     * @return boolean
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        return configure_set(static::tableName(), $this->getAttributes());
    }
    

    /**
     * @return $this
     */
    public function reload()
    {
        $data = configure(static::tableName(), DATA_ARRAY, $this->getDefaultConfig());
        $this->setAttributes($data);
        return $this;
    }

    /**
     * @return array
     */
    public function getDefaultConfig(): array
    {
        return [
            'subject' => '监控系统采集服务状态发生异常',
            'message' => '系统采集服务状态出现异常或IP发生切换，请及时排查',
            'status' => 1,
            'medias' => [MediaFactory::ID_MEDIA_MAIL],
            'media_extra' => [],
            'users' => [User::SYSTEM_USER_ID],
            'roles' => [],
        ];
    }

    /**
     * 返回通知用户信息
     *
     * @return array
     */
    public function getNotifyUsers(): array
    {
        return $this->users ? User::getAvailableUsers($this->users) : [];
    }

    /**
     * 返回通知角色信息
     *
     * @return array
     */
    public function getNotifyRoles():array
    {
        return $this->roles ? Role::getAvailableColumns($this->roles, false, 'name', 'id'): [];
    }

    /**
     * @return array
     */
    public function getResourceLabel()
    {
        return [60020014 => t('zapi', 'Perseus Server') . '(' . t('zapi', 'State Setting') . ')'];
    }

     /**
     * 是否启用容量检查
     *
     * @return boolean
     */
    public function getIsCapacityChecked(): bool
    {
        return $this->capacity > 0;
    }

    /**
     * 用户ID集合
     *
     * @return array
     */
    public function getReceivers()
    {
        if ($this->receiver === null) {
            if ($this->roles) {
                $userIds = array_merge(UserRole::find()->where(['roleid' => $this->roles])->select(['userid'])->column(), $this->users);
            } else {
                $userIds = $this->users;
            }
            $this->receiver = array_keys(User::getAvailableColumns($userIds));
        }
        return $this->receiver;
    }

    /**
     * 返回用户媒介信息
     *
     * Note: 若不存在某个媒介，返回值则无该媒介下标
     *
     * @param bool $raw 返回原始列表数据
     * 如果`$raw`值是`false`, 数据格式如下:
     * ```
     *  return [
     *      1 => [ // email
     *          ['userid' => 1, 'account' => 'admin@admin.cn', 'type' => 1],
     *          ['userid' => 1, 'account' => 'admin@test.cn', 'type' => 1],
     *          ['userid' => 2, 'account' => 'demo@admin.cn', 'type' => 1],
     *      ],
     *      2 => [ // sms
     *          ['userid' => 1, 'account' => '13800138000', 'type' => 2],
     *          ['userid' => 2, 'account' => '13800138001', 'type' => 2],
     *      ],
     *      3 => [ // wechat (work)
     *          ['userid' => 1, 'account' => 'wx_admin', 'type' => 3],
     *          ['userid' => 2, 'account' => 'wx_demo', 'type' => 3],
     *      ],
     *      4 => [ // dingtalk
     *          ['userid' => 1, 'account' => 'dd_admin', 'type' => 4],
     *          ['userid' => 2, 'account' => 'dd_demo', 'type' => 4],
     *      ],
     * ];
     * ```
     * @return array 媒介信息
     */
    public function getUserMedias($raw = true): array
    {
        $receiver = $this->getReceivers();
        if (empty($receiver)) {
            return [];
        }
        $medias = UserMedia::find()->where(['userid' => $receiver])->asArray()->all();
        return $raw ? $medias : ArrayHelper::index($medias, null, 'type');
    }
}
