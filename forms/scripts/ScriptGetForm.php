<?php

namespace app\customs\zapi\forms\scripts;


use app\customs\zapi\common\validators\ZFilterValidator;
use app\customs\zapi\forms\BaseForm;

class ScriptGetForm extends BaseForm
{
    public $scriptids;
    public $hostids;
    public $groupids;
    public $usrgrpids;
    public $filter;
    public $search;
    public $searchByAny;
    public $startSearch;
    public $excludeSearch;
    public $searchWildcardsEnabled;
    public $output;
    public $selectGroups;
    public $selectHostGroups;
    public $selectHosts;
    public $selectActions;
    public $countOutput;
    public $sortfield;
    public $sortorder;
    public $limit;
    public $editable;
    public $preservekeys;
    public $is_all = false;


    private $sort_fields = ['scriptid', 'name'];

    private $filter_fields = ['scriptid', 'name', 'command', 'host_access', 'usrgrpid', 'groupid', 'confirmation',
        'type', 'url', 'new_window', 'execute_on', 'scope', 'menu_path'];
    private $search_fields = ['name', 'command', 'url', 'description', 'confirmation', 'username', 'menu_path'];

    private $action_fields = ['actionid', 'name', 'eventsource', 'status', 'esc_period', 'pause_suppressed',
        'notify_if_canceled', 'pause_symptoms'
    ];

    private $group_fields = ['groupid', 'name', 'flags', 'uuid'];
    private $host_fields = ['hostid', 'host', 'name', 'description', 'status', 'proxy_hostid', 'inventory_mode', 'flags',
        'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'maintenanceid', 'maintenance_status',
        'maintenance_type', 'maintenance_from', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject'
    ];
    private $output_fields = ['scriptid', 'name', 'command', 'host_access', 'usrgrpid', 'groupid', 'description',
        'confirmation', 'type', 'execute_on', 'timeout', 'parameters', 'scope', 'port', 'authtype', 'username',
        'password', 'publickey', 'privatekey', 'menu_path', 'url', 'new_window'
    ];

    public function rules()
    {
        return [
            [['scriptids', 'hostids', 'groupids', 'usrgrpids'], 'integer'],
            [['filter'], ZFilterValidator::class, 'fields' => $this->filter_fields],
            [['search'], 'safe'],
            [['searchByAny', 'searchWildcardsEnabled',], 'boolean'],
            [['startSearch', 'excludeSearch'], 'safe'],
            [['output'], 'each', 'rule' => ['in', 'range' => $this->output_fields]],
            [['selectGroups', 'selectHostGroups'], 'each', 'rule' => ['in', 'range' => $this->group_fields]],
            [['selectHosts'], 'each', 'rule' => ['in', 'range' => $this->host_fields]],
            [['selectActions'], 'each', 'rule' => ['in', 'range' => $this->action_fields]],
            [['countOutput'], 'safe'],
            [['sortfield'], 'each', 'rule' => ['in', 'range' => $this->sort_fields]],
            [['sortfield', 'sortorder'], 'default', 'value' => []],
            ['limit', 'integer', 'min' => 1,],
            [['editable', 'preservekeys'], 'boolean'],
            [['editable', 'preservekeys', 'countOutput', 'searchByAny', 'startSearch', 'excludeSearch', 'searchWildcardsEnabled'], 'default', 'value' => false],
            [['is_all'], 'boolean'],
        ];
    }
}
