<?php

namespace app\customs\zapi\common\managers;

use app\common\helpers\SqlHelper;
use app\customs\zapi\common\db\DB;
use app\customs\zapi\common\exceptions\ValidateException;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\ItemHelper;
use app\customs\zapi\common\managers\base\BaseManager;
use app\customs\zapi\services\assist\ItemAssist;
use app\modules\libzbx\models\zbx\Hosts;
use app\modules\libzbx\models\zbx\HostsTemplates;
use app\modules\libzbx\models\zbx\Httpstep;
use app\modules\libzbx\models\zbx\HttpstepField;
use app\modules\libzbx\models\zbx\Httpstepitem;
use app\modules\libzbx\models\zbx\Httptest;
use app\modules\libzbx\models\zbx\HttptestField;
use app\modules\libzbx\models\zbx\Httptestitem;
use app\modules\libzbx\models\zbx\HttptestTag;
use app\modules\libzbx\models\zbx\Items;
use app\modules\libzbx\models\zbx\ItemTag;
use yii\db\Query;

/**
 * 拨测管理
 */
class HttpTestManager extends BaseManager
{
    const ITEM_HISTORY = '30d';
    const ITEM_TRENDS = '90d';

    /**
     * Changed steps names.
     * array(
     *   testid1 => array(nameold1 => namenew1, nameold2 => namenew2),
     *   ...
     * )
     *
     * @var array
     */
    protected $changedSteps = [];

    /**
     * Map of parent http test id to child http test id.
     *
     * @var array
     */
    protected $httpTestParents = [];

    /**
     * Array of parent item IDs indexed by parent httptest ID and item key.
     *
     * @var array
     */
    private static $parent_itemids = [];

    /**
     * Save http test to db.
     *
     * @param array $httpTests
     *
     * @return array
     */
    public function persist(array $httpTests)
    {
        $this->changedSteps = $this->findChangedStepNames($httpTests);

        $httpTests = $this->save($httpTests);
        $this->inherit($httpTests);

        return $httpTests;
    }

    /**
     * Find steps where name was changed.
     *
     * @return array
     */
    protected function findChangedStepNames(array $httpTests)
    {
        $httpSteps = [];
        $result = [];
        foreach ($httpTests as $httpTest) {
            if (isset($httpTest['httptestid']) && isset($httpTest['steps'])) {
                foreach ($httpTest['steps'] as $step) {
                    if (isset($step['httpstepid']) && isset($step['name'])) {
                        $httpSteps[$step['httpstepid']] = $step['name'];
                    }
                }
            }
        }
        if (!empty($httpSteps)) {
            $dbSteps = Httpstep::find()
                ->select(['httpstepid', 'httptestid', 'name'])
                ->where(SqlHelper::whereIn('httpstepid', array_keys($httpSteps)))
                ->asArray()
                ->all();

            foreach ($dbSteps as $dbStep) {
                if ($httpSteps[$dbStep['httpstepid']] != $dbStep['name']) {
                    $result[$dbStep['httptestid']][$httpSteps[$dbStep['httpstepid']]] = $dbStep['name'];
                }
            }
        }

        return $result;
    }

    /**
     * Create new HTTP tests.
     *
     * @param array $httptests
     *
     * @return array
     */
    public function create(array $httpTests)
    {
        $httpTestIds = DB::insert(Httptest::tableName(), $httpTests);

        foreach ($httpTests as &$httpTest) {
            $httpTest['httptestid'] = array_shift($httpTestIds);
        }
        unset($httpTest);

        self::createItems($httpTests);
        self::updateFields($httpTests);
        self::updateSteps($httpTests);
        self::updateTags($httpTests);

        return $httpTests;
    }

    /**
     * @param array  $httptests
     */
    private static function createItems(array $httptests): void
    {
        $items = [];

        $type_items = [
            HTTPSTEP_ITEM_TYPE_IN => [
                'value_type' => ITEM_VALUE_TYPE_FLOAT,
                'units' => 'Bps'
            ],
            HTTPSTEP_ITEM_TYPE_LASTSTEP => [
                'value_type' => ITEM_VALUE_TYPE_UINT64,
                'units' => ''
            ],
            HTTPSTEP_ITEM_TYPE_LASTERROR => [
                'value_type' => ITEM_VALUE_TYPE_STR,
                'units' => ''
            ]
        ];

        foreach ($httptests as $httptest) {
            if (!array_key_exists('status', $httptest)) {
                $httptest['status'] = DB::getDefault('httptest', 'status');
            }

            $item_status = $httptest['status'] == HTTPTEST_STATUS_ACTIVE ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;
            $item_tags = array_key_exists('tags', $httptest) ? $httptest['tags'] : [];

            if (!array_key_exists('delay', $httptest)) {
                $httptest['delay'] = DB::getDefault('httptest', 'delay');
            }

            foreach ($type_items as $type => $type_item) {
                $item_key = self::getTestKey($type, $httptest['name']);

                $items[] = [
                    'host_status' => $httptest['host_status'],
                    'flags' => PRS_FLAG_DISCOVERY_NORMAL,
                    'hostid' => $httptest['hostid'],
                    'name' => self::getTestName($type, $httptest['name']),
                    'type' => ITEM_TYPE_HTTPTEST,
                    'key_' => $item_key,
                    'history' => self::ITEM_HISTORY,
                    'trends' => self::ITEM_TRENDS,
                    'status' => $item_status,
                    'tags' => $item_tags,
                    'delay' => $httptest['delay'],
                    'templateid' => array_key_exists('templateid', $httptest)
                        ? self::$parent_itemids[$httptest['templateid']][$item_key]
                        : 0
                ] + $type_item;
            }
        }


        ItemAssist::createItems($items);

        $itemids = array_column($items, 'itemid');

        $ins_httptestitems = [];

        foreach ($httptests as $httptest) {
            foreach ($type_items as $type => $foo) {
                $ins_httptestitems[] = [
                    'httptestid' => $httptest['httptestid'],
                    'itemid' => array_shift($itemids),
                    'type' => $type
                ];
            }
        }

        DB::insertBatch('httptestitem', $ins_httptestitems);
    }

    /**
     * @param array  $httptests
     * @param array  $db_httptests
     */
    private static function updateItems(array $httptests, array $db_httptests): void
    {
        $items = [];
        $db_items = [];

        foreach ($httptests as $httptest) {
            if (!array_key_exists('items', $db_httptests[$httptest['httptestid']])) {
                continue;
            }

            $db_httptest = $db_httptests[$httptest['httptestid']];

            $db_items += $db_httptest['items'];

            foreach ($db_httptest['items'] as $db_item) {
                $item = [];

                if ($httptest['name'] != $db_httptest['name']) {
                    $item += [
                        'name' => self::getTestName($db_item['test_type'], $httptest['name']),
                        'key_' => self::getTestKey($db_item['test_type'], $httptest['name'])
                    ];
                }

                if (array_key_exists('status', $httptest) && $httptest['status'] != $db_httptest['status']) {
                    $item['status'] = $httptest['status'] == HTTPTEST_STATUS_ACTIVE
                        ? ITEM_STATUS_ACTIVE
                        : ITEM_STATUS_DISABLED;
                }

                if (array_key_exists('delay', $httptest) && $httptest['delay'] !== $db_httptest['delay']) {
                    $item['delay'] = $httptest['delay'];
                }

                if (
                    array_key_exists('templateid', $httptest)
                    && bccomp($httptest['templateid'], $db_httptest['templateid']) != 0
                ) {
                    $item_key = array_key_exists('key_', $item) ? $item['key_'] : $db_item['key_'];

                    $item['templateid'] = $httptest['templateid'] == 0
                        ? 0
                        : self::$parent_itemids[$httptest['templateid']][$item_key];
                }

                if (array_key_exists('tags', $httptest)) {
                    $item['tags'] = $httptest['tags'];
                }

                $items[] = ['itemid' => $db_item['itemid']] + $item;
            }

            if (array_key_exists('steps', $httptest)) {
                continue;
            }

            foreach ($db_httptest['steps'] as $db_step) {
                $db_items += $db_step['items'];

                foreach ($db_step['items'] as $db_item) {
                    $item = [];

                    if ($httptest['name'] != $db_httptest['name']) {
                        $item += [
                            'name' => self::getStepName($db_item['test_type'], $httptest['name'], $db_step['name']),
                            'key_' => self::getStepKey($db_item['test_type'], $httptest['name'], $db_step['name'])
                        ];
                    }

                    if (array_key_exists('status', $httptest) && $httptest['status'] != $db_httptest['status']) {
                        $item['status'] = $httptest['status'] == HTTPTEST_STATUS_ACTIVE
                            ? ITEM_STATUS_ACTIVE
                            : ITEM_STATUS_DISABLED;
                    }

                    if (array_key_exists('delay', $httptest) && $httptest['delay'] !== $db_httptest['delay']) {
                        $item['delay'] = $httptest['delay'];
                    }

                    if (
                        array_key_exists('templateid', $httptest)
                        && bccomp($httptest['templateid'], $db_httptest['templateid']) != 0
                    ) {
                        $item_key = array_key_exists('key_', $item) ? $item['key_'] : $db_item['key_'];

                        $item['templateid'] = $httptest['templateid'] == 0
                            ? 0
                            : self::$parent_itemids[$httptest['templateid']][$item_key];
                    }

                    if (array_key_exists('tags', $httptest)) {
                        $item['tags'] = $httptest['tags'];
                    }

                    $items[] = ['itemid' => $db_item['itemid']] + $item;
                }
            }
        }

        if ($items) {
            ItemAssist::updateItems($items, $db_items);
        }
    }


    /**
     * @param array      $httptests
     * @param array|null $db_httptests
     */
    private static function updateFields(array &$httptests, array $db_httptests = null): void
    {
        $ins_fields = [];
        $upd_fields = [];
        $del_fieldids = [];

        foreach ($httptests as &$httptest) {
            if (array_key_exists('headers', $httptest)) {
                $db_headers = $db_httptests !== null ? $db_httptests[$httptest['httptestid']]['headers'] : [];

                foreach ($httptest['headers'] as &$header) {
                    $db_header = array_shift($db_headers);

                    if ($db_header !== null) {
                        $upd_header = DB::getUpdatedValues('httptest_field', $header, $db_header);

                        if ($upd_header) {
                            $upd_fields[] = [
                                'values' => $upd_header,
                                'where' => ['httptest_fieldid' => $db_header['httptest_fieldid']]
                            ];
                        }

                        $header['httptest_fieldid'] = $db_header['httptest_fieldid'];
                    } else {
                        $ins_fields[] = [
                            'httptestid' => $httptest['httptestid'],
                            'type' => PRS_HTTPFIELD_HEADER
                        ] + $header;
                    }
                }
                unset($header);

                $del_fieldids = array_merge($del_fieldids, array_column($db_headers, 'httptest_fieldid'));
            }

            if (array_key_exists('variables', $httptest)) {
                $db_variables = $db_httptests !== null
                    ? array_column($db_httptests[$httptest['httptestid']]['variables'], null, 'name')
                    : [];

                foreach ($httptest['variables'] as &$variable) {
                    if (array_key_exists($variable['name'], $db_variables)) {
                        $db_variable = $db_variables[$variable['name']];

                        $upd_variable = DB::getUpdatedValues('httptest_field', $variable, $db_variable);

                        if ($upd_variable) {
                            $upd_fields[] = [
                                'values' => $upd_variable,
                                'where' => ['httptest_fieldid' => $db_variable['httptest_fieldid']]
                            ];
                        }

                        $variable['httptest_fieldid'] = $db_variable['httptest_fieldid'];
                        unset($db_variables[$variable['name']]);
                    } else {
                        $ins_fields[] = [
                            'httptestid' => $httptest['httptestid'],
                            'type' => PRS_HTTPFIELD_VARIABLE
                        ] + $variable;
                    }
                }
                unset($variable);

                $del_fieldids = array_merge($del_fieldids, array_column($db_variables, 'httptest_fieldid'));
            }
        }
        unset($httptest);

        if ($del_fieldids) {
            DB::delete('httptest_field', ['httptest_fieldid' => $del_fieldids]);
        }

        if ($upd_fields) {
            DB::update('httptest_field', $upd_fields);
        }

        if ($ins_fields) {
            DB::insertBatch('httptest_field', $ins_fields);
        }
    }

    /**
     * @param array      $httptests
     * @param array|null $db_httptests
     */
    private static function updateSteps(array &$httptests, array $db_httptests = null): void
    {
        $ins_steps = [];
        $upd_steps = [];
        $update_step_items = false;
        $del_stepids = [];
        $del_db_items = [];

        foreach ($httptests as $httptest) {
            if (!array_key_exists('steps', $httptest)) {
                continue;
            }

            $db_steps = $db_httptests !== null ? $db_httptests[$httptest['httptestid']]['steps'] : [];

            foreach ($httptest['steps'] as $step) {
                if (array_key_exists('httpstepid', $step)) {
                    if (array_key_exists('posts', $step)) {
                        if (is_array($step['posts'])) {
                            $step['post_type'] = PRS_POSTTYPE_FORM;
                            $step['posts'] = '';
                        } else {
                            $step['post_type'] = PRS_POSTTYPE_RAW;
                        }
                    }

                    $upd_step = DB::getUpdatedValues('httpstep', $step, $db_steps[$step['httpstepid']]);

                    if ($upd_step) {
                        $upd_steps[] = [
                            'values' => $upd_step,
                            'where' => ['httpstepid' => $step['httpstepid']]
                        ];
                    }

                    if (array_key_exists('items', $db_steps[$step['httpstepid']])) {
                        $update_step_items = true;
                    }

                    unset($db_steps[$step['httpstepid']]);
                } else {
                    if (array_key_exists('posts', $step) && is_array($step['posts'])) {
                        $step['post_type'] = PRS_POSTTYPE_FORM;
                        unset($step['posts']);
                    }

                    $ins_steps[] = ['httptestid' => $httptest['httptestid']] + $step;
                }
            }

            $del_stepids = array_merge($del_stepids, array_keys($db_steps));

            foreach ($db_steps as $db_step) {
                if (!array_key_exists('items', $db_step)) {
                    continue;
                }

                foreach ($db_step['items'] as $db_item) {
                    $del_db_items[$db_item['itemid']] = $db_item;
                }
            }
        }

        if ($del_stepids) {
            if ($del_db_items) {
                ItemAssist::addInheritedItems($del_db_items);
                DB::delete('httpstepitem', ['itemid' => array_keys($del_db_items)]);
                ItemAssist::deleteItems($del_db_items);
            }

            DB::delete('httpstep_field', ['httpstepid' => $del_stepids]);
            DB::delete('httpstep', ['httpstepid' => $del_stepids]);
        }

        if ($upd_steps) {
            DB::update('httpstep', $upd_steps);
        }

        if ($update_step_items) {
            self::updateStepItems($httptests, $db_httptests);
        }

        if ($ins_steps) {
            $stepids = DB::insert('httpstep', $ins_steps);

            foreach ($httptests as &$httptest) {
                if (!array_key_exists('steps', $httptest)) {
                    continue;
                }

                foreach ($httptest['steps'] as &$step) {
                    if (!array_key_exists('httpstepid', $step)) {
                        $step['httpstepid'] = current($stepids);
                        next($stepids);
                    }
                }
                unset($step);
            }
            unset($httptest);

            self::createStepItems($httptests, $stepids, $db_httptests);
        }

        self::updateStepFields($httptests, $db_httptests);
    }

    /**
     * @param array $httptests
     * @param array $db_httptests
     */
    private static function updateStepItems(array $httptests, array $db_httptests): void
    {
        $items = [];
        $db_items = [];

        foreach ($httptests as $httptest) {
            if (!array_key_exists('steps', $httptest)) {
                continue;
            }

            $db_httptest = $db_httptests[$httptest['httptestid']];

            foreach ($httptest['steps'] as $step) {
                if (
                    !array_key_exists('httpstepid', $step)
                    || !array_key_exists('items', $db_httptest['steps'][$step['httpstepid']])
                ) {
                    continue;
                }

                $db_step = $db_httptest['steps'][$step['httpstepid']];

                if (!array_key_exists('name', $step)) {
                    $step['name'] = $db_step['name'];
                }

                $db_items += $db_step['items'];

                foreach ($db_step['items'] as $db_item) {
                    $item = [];

                    if ($httptest['name'] != $db_httptest['name'] || $step['name'] !== $db_step['name']) {
                        $item += [
                            'name' => self::getStepName($db_item['test_type'], $httptest['name'], $step['name']),
                            'key_' => self::getStepKey($db_item['test_type'], $httptest['name'], $step['name'])
                        ];
                    }

                    if (array_key_exists('status', $httptest) && $httptest['status'] != $db_httptest['status']) {
                        $item['status'] = $httptest['status'] == HTTPTEST_STATUS_ACTIVE
                            ? ITEM_STATUS_ACTIVE
                            : ITEM_STATUS_DISABLED;
                    }

                    if (array_key_exists('delay', $httptest) && $httptest['delay'] !== $db_httptest['delay']) {
                        $item['delay'] = $httptest['delay'];
                    }

                    if (array_key_exists('tags', $httptest)) {
                        $item['tags'] = $httptest['tags'];
                    }

                    $items[] = ['itemid' => $db_item['itemid']] + $item;
                }
            }
        }

        if ($items) {
            ItemAssist::updateItems($items, $db_items);
        }
    }

    /**
     * @param array      $httptests
     * @param array      $stepids
     * @param array|null $db_httptests
     */
    private static function createStepItems(array $httptests, array $stepids, ?array $db_httptests): void
    {
        $items = [];

        $type_items = [
            HTTPSTEP_ITEM_TYPE_RSPCODE => [
                'value_type' => ITEM_VALUE_TYPE_UINT64,
                'units' => ''
            ],
            HTTPSTEP_ITEM_TYPE_TIME => [
                'value_type' => ITEM_VALUE_TYPE_FLOAT,
                'units' => 's'
            ],
            HTTPSTEP_ITEM_TYPE_IN => [
                'value_type' => ITEM_VALUE_TYPE_FLOAT,
                'units' => 'Bps'
            ]
        ];

        foreach ($httptests as $httptest) {
            if (!array_key_exists('steps', $httptest)) {
                continue;
            }

            if ($db_httptests !== null) {
                $httptest['host_status'] = $db_httptests[$httptest['httptestid']]['host_status'];
            }

            if (!array_key_exists('status', $httptest)) {
                $httptest['status'] = ($db_httptests !== null)
                    ? $db_httptests[$httptest['httptestid']]['status']
                    : DB::getDefault('httptest', 'status');
            }

            $item_status = $httptest['status'] == HTTPTEST_STATUS_ACTIVE ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;

            $item_tags = [];

            if (array_key_exists('tags', $httptest)) {
                $item_tags = $httptest['tags'];
            } elseif ($db_httptests !== null) {
                foreach ($db_httptests[$httptest['httptestid']]['tags'] as $tag) {
                    $item_tags[] = array_intersect_key($tag, array_flip(['tag', 'value']));
                }
            }

            if (!array_key_exists('delay', $httptest)) {
                $httptest['delay'] = ($db_httptests !== null)
                    ? $db_httptests[$httptest['httptestid']]['delay']
                    : DB::getDefault('httptest', 'delay');
            }

            foreach ($httptest['steps'] as $step) {
                if (!in_array($step['httpstepid'], $stepids)) {
                    continue;
                }

                foreach ($type_items as $type => $type_item) {
                    $item_key = self::getStepKey($type, $httptest['name'], $step['name']);

                    $items[] = [
                        'host_status' => $httptest['host_status'],
                        'flags' => PRS_FLAG_DISCOVERY_NORMAL,
                        'hostid' => $httptest['hostid'],
                        'name' => self::getStepName($type, $httptest['name'], $step['name']),
                        'type' => ITEM_TYPE_HTTPTEST,
                        'key_' => $item_key,
                        'history' => self::ITEM_HISTORY,
                        'trends' => self::ITEM_TRENDS,
                        'status' => $item_status,
                        'tags' => $item_tags,
                        'delay' => $httptest['delay'],
                        'templateid' => array_key_exists('templateid', $httptest)
                            ? self::$parent_itemids[$httptest['templateid']][$item_key]
                            : 0
                    ] + $type_item;
                }
            }
        }

        ItemAssist::createItems($items);

        $itemids = array_column($items, 'itemid');

        $ins_httpstepitems = [];

        foreach ($httptests as $httptest) {
            if (!array_key_exists('steps', $httptest)) {
                continue;
            }

            foreach ($httptest['steps'] as $step) {
                if (!in_array($step['httpstepid'], $stepids)) {
                    continue;
                }

                foreach ($type_items as $type => $foo) {
                    $ins_httpstepitems[] = [
                        'httpstepid' => $step['httpstepid'],
                        'itemid' => array_shift($itemids),
                        'type' => $type
                    ];
                }
            }
        }

        DB::insertBatch('httpstepitem', $ins_httpstepitems);
    }

    /**
     * @param array      $httptests
     * @param array|null $db_httptests
     */
    private static function updateStepFields(array &$httptests, ?array $db_httptests): void
    {
        $ins_fields = [];
        $upd_fields = [];
        $del_fieldids = [];

        foreach ($httptests as &$httptest) {
            if (!array_key_exists('steps', $httptest)) {
                continue;
            }

            $db_httptest = $db_httptests !== null ? $db_httptests[$httptest['httptestid']] : null;

            foreach ($httptest['steps'] as &$step) {
                $db_step = ($db_httptest !== null && array_key_exists($step['httpstepid'], $db_httptest['steps']))
                    ? $db_httptest['steps'][$step['httpstepid']]
                    : null;

                if (array_key_exists('headers', $step)) {
                    $db_headers = $db_step !== null ? $db_step['headers'] : [];

                    foreach ($step['headers'] as &$header) {
                        $db_header = array_shift($db_headers);

                        if ($db_header !== null) {
                            $upd_header = DB::getUpdatedValues('httpstep_field', $header, $db_header);

                            if ($upd_header) {
                                $upd_fields[] = [
                                    'values' => $upd_header,
                                    'where' => ['httpstep_fieldid' => $db_header['httpstep_fieldid']]
                                ];
                            }

                            $header['httpstep_fieldid'] = $db_header['httpstep_fieldid'];
                        } else {
                            $ins_fields[] = [
                                'httpstepid' => $step['httpstepid'],
                                'type' => PRS_HTTPFIELD_HEADER
                            ] + $header;
                        }
                    }
                    unset($header);

                    $del_fieldids = array_merge($del_fieldids, array_column($db_headers, 'httpstep_fieldid'));
                }

                if (array_key_exists('variables', $step)) {
                    $db_variables = $db_step !== null ? array_column($db_step['variables'], null, 'name') : [];

                    foreach ($step['variables'] as &$variable) {
                        if (array_key_exists($variable['name'], $db_variables)) {
                            $db_variable = $db_variables[$variable['name']];

                            $upd_variable = DB::getUpdatedValues('httpstep_field', $variable, $db_variable);

                            if ($upd_variable) {
                                $upd_fields[] = [
                                    'values' => $upd_variable,
                                    'where' => ['httpstep_fieldid' => $db_variable['httpstep_fieldid']]
                                ];
                            }

                            $variable['httpstep_fieldid'] = $db_variable['httpstep_fieldid'];
                            unset($db_variables[$variable['name']]);
                        } else {
                            $ins_fields[] = [
                                'httpstepid' => $step['httpstepid'],
                                'type' => PRS_HTTPFIELD_VARIABLE
                            ] + $variable;
                        }
                    }
                    unset($variable);

                    $del_fieldids = array_merge($del_fieldids, array_column($db_variables, 'httpstep_fieldid'));
                }

                if (array_key_exists('posts', $step)) {
                    if (is_array($step['posts'])) {
                        $db_posts = $db_step !== null && is_array($db_step['posts']) ? $db_step['posts'] : [];

                        foreach ($step['posts'] as &$post) {
                            $db_post = array_shift($db_posts);

                            if ($db_post !== null) {
                                $upd_post = DB::getUpdatedValues('httpstep_field', $post, $db_post);

                                if ($upd_post) {
                                    $upd_fields[] = [
                                        'values' => $upd_post,
                                        'where' => ['httpstep_fieldid' => $db_post['httpstep_fieldid']]
                                    ];
                                }

                                $post['httpstep_fieldid'] = $db_post['httpstep_fieldid'];
                            } else {
                                $ins_fields[] = [
                                    'httpstepid' => $step['httpstepid'],
                                    'type' => PRS_HTTPFIELD_POST_FIELD
                                ] + $post;
                            }
                        }
                        unset($post);

                        $del_fieldids = array_merge($del_fieldids, array_column($db_posts, 'httpstep_fieldid'));
                    } elseif ($db_step !== null && is_array($db_step['posts'])) {
                        $del_fieldids = array_merge($del_fieldids, array_keys($db_step['posts']));
                    }
                }

                if (array_key_exists('query_fields', $step)) {
                    $db_query_fields = $db_step !== null ? $db_step['query_fields'] : [];

                    foreach ($step['query_fields'] as &$query_field) {
                        $db_query_field = array_shift($db_query_fields);

                        if ($db_query_field !== null) {
                            $upd_query_field = DB::getUpdatedValues('httpstep_field', $query_field, $db_query_field);

                            if ($upd_query_field) {
                                $upd_fields[] = [
                                    'values' => $upd_query_field,
                                    'where' => ['httpstep_fieldid' => $db_query_field['httpstep_fieldid']]
                                ];
                            }

                            $query_field['httpstep_fieldid'] = $db_query_field['httpstep_fieldid'];
                        } else {
                            $ins_fields[] = [
                                'httpstepid' => $step['httpstepid'],
                                'type' => PRS_HTTPFIELD_QUERY_FIELD
                            ] + $query_field;
                        }
                    }
                    unset($query_field);

                    $del_fieldids = array_merge($del_fieldids, array_column($db_query_fields, 'httpstep_fieldid'));
                }
            }
            unset($step);
        }
        unset($httptest);

        if ($del_fieldids) {
            DB::delete('httpstep_field', ['httpstep_fieldid' => $del_fieldids]);
        }

        if ($upd_fields) {
            DB::update('httpstep_field', $upd_fields);
        }

        if ($ins_fields) {
            DB::insertBatch('httpstep_field', $ins_fields);
        }
    }

    /**
     * @param array      $httptests
     * @param array|null $db_httptests
     */
    private static function updateTags(array &$httptests, array $db_httptests = null): void
    {
        $ins_tags = [];
        $del_tagids = [];

        foreach ($httptests as &$httptest) {
            if (!array_key_exists('tags', $httptest)) {
                continue;
            }

            $db_tags = $db_httptests !== null ? $db_httptests[$httptest['httptestid']]['tags'] : [];

            foreach ($httptest['tags'] as &$tag) {
                $db_tagid = key(array_filter($db_tags, static function (array $db_tag) use ($tag): bool {
                    return $tag['tag'] == $db_tag['tag']
                        && (!array_key_exists('value', $tag) || $tag['value'] == $db_tag['value']);
                }));

                if ($db_tagid !== null) {
                    $tag['httptesttagid'] = $db_tagid;
                    unset($db_tags[$db_tagid]);
                } else {
                    $ins_tags[] = ['httptestid' => $httptest['httptestid']] + $tag;
                }
            }
            unset($tag);

            $del_tagids = array_merge($del_tagids, array_keys($db_tags));
        }
        unset($httptest);

        if ($del_tagids) {
            DB::delete('httptest_tag', ['httptesttagid' => $del_tagids]);
        }

        if ($ins_tags) {
            DB::insert('httptest_tag', $ins_tags);
        }
    }


    /**
     * Get item key for test item.
     *
     * @param int    $type
     * @param string $test_name
     *
     * @return string
     */
    protected static function getTestKey(int $type, string $test_name): string
    {
        switch ($type) {
            case HTTPSTEP_ITEM_TYPE_IN:
                return 'web.test.in[' . ItemHelper::quoteItemKeyParam($test_name) . ',,bps]';
            case HTTPSTEP_ITEM_TYPE_LASTSTEP:
                return 'web.test.fail[' . ItemHelper::quoteItemKeyParam($test_name) . ']';
            case HTTPSTEP_ITEM_TYPE_LASTERROR:
                return 'web.test.error[' . ItemHelper::quoteItemKeyParam($test_name) . ']';
        }

        return 'unknown';
    }

    /**
     * Get item name for test item.
     *
     * @param int    $type
     * @param string $test_name
     *
     * @return string
     */
    private static function getTestName(int $type, string $test_name): string
    {
        switch ($type) {
            case HTTPSTEP_ITEM_TYPE_IN:
                return 'Download speed for scenario "' . $test_name . '".';
            case HTTPSTEP_ITEM_TYPE_LASTSTEP:
                return 'Failed step of scenario "' . $test_name . '".';
            case HTTPSTEP_ITEM_TYPE_LASTERROR:
                return 'Last error message of scenario "' . $test_name . '".';
        }

        return 'unknown';
    }

    /**
     * Get item key for step item.
     *
     * @param int    $type
     * @param string $test_name
     * @param string $step_name
     *
     * @return string
     */
    private static function getStepKey(int $type, string $test_name, string $step_name): string
    {
        switch ($type) {
            case HTTPSTEP_ITEM_TYPE_IN:
                return 'web.test.in[' . ItemHelper::quoteItemKeyParam($test_name) . ',' . ItemHelper::quoteItemKeyParam($step_name) . ',bps]';
            case HTTPSTEP_ITEM_TYPE_TIME:
                return 'web.test.time[' . ItemHelper::quoteItemKeyParam($test_name) . ',' . ItemHelper::quoteItemKeyParam($step_name) . ',resp]';
            case HTTPSTEP_ITEM_TYPE_RSPCODE:
                return 'web.test.rspcode[' . ItemHelper::quoteItemKeyParam($test_name) . ',' . ItemHelper::quoteItemKeyParam($step_name) . ']';
        }

        return 'unknown';
    }

    /**
     * Get item name for step item.
     *
     * @param int    $type
     * @param string $test_name
     * @param string $step_name
     *
     * @return string
     */
    private static function getStepName(int $type, string $test_name, string $step_name): string
    {
        switch ($type) {
            case HTTPSTEP_ITEM_TYPE_IN:
                return 'Download speed for step "' . $step_name . '" of scenario "' . $test_name . '".';
            case HTTPSTEP_ITEM_TYPE_TIME:
                return 'Response time for step "' . $step_name . '" of scenario "' . $test_name . '".';
            case HTTPSTEP_ITEM_TYPE_RSPCODE:
                return 'Response code for step "' . $step_name . '" of scenario "' . $test_name . '".';
        }

        return 'unknown';
    }


    /**
     * Update http tests.
     *
     * @param array $httptests
     *
     * @return array
     */
    public function update(array $httptests)
    {
        $query = new Query();
        $query->from([
            'ht' => Httptest::tableName(),
            'h' => Hosts::tableName(),
        ]);
        $query->where('ht.hostid=h.hostid')
            ->andWhere(SqlHelper::whereIn('{{ht}}.httptestid', array_column($httptests, 'httptestid')));
        $query->select([
            'ht.httptestid', 'ht.name', 'ht.delay', 'ht.retries', 'ht.agent', 'ht.http_proxy', 'ht.status', 'ht.authentication',
            'ht.http_user', 'ht.http_password', 'ht.verify_peer', 'ht.verify_host', 'ht.ssl_cert_file', 'ht.ssl_key_file',
            'ht.ssl_key_password', 'ht.hostid', 'ht.templateid', 'host_status' => 'h.status'
        ]);

        $query->indexBy('httptestid');

        $db_httptests = $query->all();


        self::addAffectedObjects($httptests, $db_httptests);

        $upd_httptests = [];

        foreach ($httptests as $httptest) {
            $upd_httptest = DB::getUpdatedValues('httptest', $httptest, $db_httptests[$httptest['httptestid']]);

            if ($upd_httptest) {
                $upd_httptests[] = [
                    'values' => $upd_httptest,
                    'where' => ['httptestid' => $httptest['httptestid']]
                ];
            }
        }

        if ($upd_httptests) {
            DB::update('httptest', $upd_httptests);
        }
        self::updateItems($httptests, $db_httptests);
        self::updateFields($httptests, $db_httptests);
        self::updateSteps($httptests, $db_httptests);
        self::updateTags($httptests, $db_httptests);

        return $httptests;
    }


    /**
     * @param array $httptests
     * @param array $db_httptests
     */
    private static function addAffectedObjects(array $httptests, array &$db_httptests): void
    {
        self::addAffectedItems($httptests, $db_httptests);
        self::addAffectedFields($httptests, $db_httptests);
        self::addAffectedSteps($httptests, $db_httptests);
        self::addAffectedTags($httptests, $db_httptests);
    }

    /**
     * @param array $httptests
     * @param array $db_httptests
     */
    private static function addAffectedItems(array $httptests, array &$db_httptests): void
    {
        $httptestids = [];

        foreach ($httptests as $httptest) {
            $db_httptest = $db_httptests[$httptest['httptestid']];

            $name_updated = $httptest['name'] != $db_httptest['name'];
            $status_updated = array_key_exists('status', $httptest) && $httptest['status'] != $db_httptest['status'];
            $delay_updated = array_key_exists('delay', $httptest) && $httptest['delay'] !== $db_httptest['delay'];
            $templateid_updated = array_key_exists('templateid', $httptest)
                && bccomp($httptest['templateid'], $db_httptest['templateid']) != 0;

            if (
                $name_updated || $status_updated || $delay_updated || $templateid_updated
                || array_key_exists('tags', $httptest)
            ) {
                $httptestids[] = $httptest['httptestid'];
            }
        }

        if (!$httptestids) {
            return;
        }

        $query = new Query();
        $query->from([
            'hi' => Httptestitem::tableName(),
            'i' => Items::tableName(),
        ]);
        $query->where('hi.itemid=i.itemid')
            ->andWhere(SqlHelper::whereIn('hi.httptestid', $httptestids));
        $query->select(['hi.httptestid', 'hi.itemid', 'test_type' => 'hi.type', 'i.name', 'i.key_', 'i.status', 'i.delay', 'i.templateid']);

        $rows = $query->all();

        foreach ($rows as $row) {
            $db_httptests[$row['httptestid']]['items'][$row['itemid']] =
                array_diff_key($row, array_flip(['httptestid']));
        }

        self::addAffectedItemTags($httptests, $db_httptests);
    }

    /**
     * @param array $httptests
     * @param array $db_httptests
     */
    private static function addAffectedItemTags(array $httptests, array &$db_httptests): void
    {
        $httptestids = [];

        foreach ($httptests as $httptest) {
            if (!array_key_exists('tags', $httptest)) {
                continue;
            }

            $httptestids[] = $httptest['httptestid'];

            foreach ($db_httptests[$httptest['httptestid']]['items'] as &$db_item) {
                $db_item['tags'] = [];
            }
            unset($db_item);
        }

        if (!$httptestids) {
            return;
        }

        $query = new Query();
        $query->from([
            'hti' => Httptestitem::tableName(),
            'it' => ItemTag::tableName(),
        ]);
        $query->where('hti.itemid=it.itemid')
            ->andWhere(SqlHelper::whereIn('hti.httptestid', $httptestids));
        $query->select(['hti.httptestid', 'hti.itemid', 'it.itemtagid', 'it.tag', 'it.value']);

        $rows = $query->all();

        foreach ($rows as $row) {
            $db_httptests[$row['httptestid']]['items'][$row['itemid']]['tags'][$row['itemtagid']] =
                array_diff_key($row, array_flip(['httptestid', 'itemid']));
        }
    }

    /**
     * @param array $httptests
     * @param array $db_httptests
     */
    private static function addAffectedFields(array $httptests, array &$db_httptests): void
    {
        $httptestids = [];
        $types = [];

        foreach ($httptests as $httptest) {
            if (array_key_exists('headers', $httptest)) {
                $httptestids[$httptest['httptestid']] = true;
                $types[PRS_HTTPFIELD_HEADER] = true;
                $db_httptests[$httptest['httptestid']]['headers'] = [];
            }

            if (array_key_exists('variables', $httptest)) {
                $httptestids[$httptest['httptestid']] = true;
                $types[PRS_HTTPFIELD_VARIABLE] = true;
                $db_httptests[$httptest['httptestid']]['variables'] = [];
            }
        }

        if (!$httptestids) {
            return;
        }


        $query = HttptestField::find()
            ->select(['httptest_fieldid', 'httptestid', 'type', 'name', 'value'])
            ->where([
                'httptestid' => array_keys($httptestids),
                'type' => array_keys($types)
            ])
            ->orderBy('httptest_fieldid');


        $rows = $query->asArray()->all();

        foreach ($rows as $row) {
            $field_name = ($row['type'] == PRS_HTTPFIELD_HEADER) ? 'headers' : 'variables';

            if (array_key_exists($field_name, $db_httptests[$row['httptestid']])) {
                $db_httptests[$row['httptestid']][$field_name][$row['httptest_fieldid']] =
                    array_diff_key($row, array_flip(['httptestid']));
            }
        }
    }

    /**
     * @param array $httptests
     * @param array $db_httptests
     */
    private static function addAffectedSteps(array $httptests, array &$db_httptests): void
    {
        $httptestids = [];

        foreach ($httptests as $httptest) {
            $name_updated = $httptest['name'] != $db_httptests[$httptest['httptestid']]['name'];

            if (array_key_exists('steps', $httptest) || $name_updated) {
                $httptestids[] = $httptest['httptestid'];
                $db_httptests[$httptest['httptestid']]['steps'] = [];
            }
        }

        if ($httptestids) {

            $query = Httpstep::find()
                ->select([
                    'httpstepid', 'httptestid', 'name', 'no', 'url', 'timeout', 'posts', 'required',
                    'status_codes', 'follow_redirects', 'retrieve_mode', 'post_type'
                ])
                ->where([
                    'httptestid' => $httptestids,
                ])
                ->orderBy('httpstepid')
                ->asArray();
            $rows = $query->all();
            foreach ($rows as $row) {
                $db_httptests[$row['httptestid']]['steps'][$row['httpstepid']] =
                    array_diff_key($row, array_flip(['httptestid']));
            }
        }

        self::addAffectedStepItems($httptests, $db_httptests);
        self::addAffectedStepFields($httptests, $db_httptests);
    }

    /**
     * @param array $httptests
     * @param array $db_httptests
     */
    private static function addAffectedStepItems(array $httptests, array &$db_httptests): void
    {
        $httptestids = [];

        foreach ($httptests as $httptest) {
            $db_httptest = $db_httptests[$httptest['httptestid']];

            $name_updated = $httptest['name'] != $db_httptest['name'];
            $status_updated = array_key_exists('status', $httptest) && $httptest['status'] != $db_httptest['status'];
            $delay_updated = array_key_exists('delay', $httptest) && $httptest['delay'] != $db_httptest['delay'];
            $templateid_updated = array_key_exists('templateid', $httptest)
                && bccomp($httptest['templateid'], $db_httptest['templateid']) != 0;

            if (
                array_key_exists('steps', $httptest) || $name_updated || $status_updated || $delay_updated
                || $templateid_updated || array_key_exists('tags', $httptest)
            ) {
                $httptestids[] = $httptest['httptestid'];
            }
        }

        if (!$httptestids) {
            return;
        }

        $query = new Query();
        $query->from([
            'hs' => Httpstep::tableName(),
            'hsi' => Httpstepitem::tableName(),
            'i' => Items::tableName(),
        ]);
        $query->where('hs.httpstepid=hsi.httpstepid')
            ->andWhere('hsi.itemid=i.itemid')
            ->andWhere(SqlHelper::whereIn('hs.httptestid', $httptestids));
        $query->select(['hs.httptestid', 'hs.httpstepid', 'test_type' => 'hsi.type', 'hsi.itemid', 'i.name', 'i.key_', 'i.status', 'i.delay', 'i.templateid']);

        $rows = $query->all();

        foreach ($rows as $row) {
            $db_httptests[$row['httptestid']]['steps'][$row['httpstepid']]['items'][$row['itemid']] =
                array_diff_key($row, array_flip(['httptestid', 'httpstepid']));
        }

        self::addAffectedStepItemTags($httptests, $db_httptests);
    }

    /**
     * @param array $httptests
     * @param array $db_httptests
     */
    private static function addAffectedStepItemTags(array $httptests, array &$db_httptests): void
    {
        $httptestids = [];

        foreach ($httptests as $httptest) {
            if (!array_key_exists('tags', $httptest)) {
                continue;
            }

            $httptestids[] = $httptest['httptestid'];

            foreach ($db_httptests[$httptest['httptestid']]['steps'] as &$db_step) {
                if (!array_key_exists('items', $db_step)) {
                    continue;
                }

                foreach ($db_step['items'] as &$db_item) {
                    $db_item['tags'] = [];
                }
                unset($db_item);
            }
            unset($db_step);
        }

        if (!$httptestids) {
            return;
        }

        $query = new Query();
        $query->from([
            'hs' => Httpstep::tableName(),
            'hsi' => Httpstepitem::tableName(),
            'it' => ItemTag::tableName(),
        ]);
        $query->where('hs.httpstepid=hsi.httpstepid')
            ->andWhere('hsi.itemid=it.itemid')
            ->andWhere(SqlHelper::whereIn('hs.httptestid', $httptestids));
        $query->select(['hs.httptestid', 'hs.httpstepid', 'hsi.itemid', 'it.itemtagid', 'it.tag', 'it.value']);


        $rows = $query->all();

        foreach ($rows as $row) {
            $db_httptests[$row['httptestid']]['steps'][$row['httpstepid']]['items'][$row['itemid']]['tags'][$row['itemtagid']] =
                array_diff_key($row, array_flip(['httptestid', 'httpstepid', 'itemid']));
        }
    }

    /**
     * @param array $httptests
     * @param array $db_httptests
     */
    private static function addAffectedStepFields(array $httptests, array &$db_httptests): void
    {
        $httpstepids = [];
        $types = [];

        $field_names = [
            PRS_HTTPFIELD_HEADER => 'headers',
            PRS_HTTPFIELD_VARIABLE => 'variables',
            PRS_HTTPFIELD_POST_FIELD => 'posts',
            PRS_HTTPFIELD_QUERY_FIELD => 'query_fields'
        ];

        foreach ($httptests as $httptest) {
            if (!array_key_exists('steps', $httptest)) {
                continue;
            }

            foreach ($httptest['steps'] as $step) {
                if (
                    !array_key_exists('httpstepid', $step)
                    || !array_key_exists($step['httpstepid'], $db_httptests[$httptest['httptestid']]['steps'])
                ) {
                    continue;
                }

                $db_step = &$db_httptests[$httptest['httptestid']]['steps'][$step['httpstepid']];

                if (array_key_exists('headers', $step)) {
                    $httpstepids[$step['httpstepid']] = true;
                    $types[PRS_HTTPFIELD_HEADER] = true;
                    $db_step['headers'] = [];
                }

                if (array_key_exists('variables', $step)) {
                    $httpstepids[$step['httpstepid']] = true;
                    $types[PRS_HTTPFIELD_VARIABLE] = true;

                    $db_step['variables'] = [];
                }

                if (array_key_exists('posts', $step) && $db_step['post_type'] == PRS_POSTTYPE_FORM) {
                    $httpstepids[$step['httpstepid']] = true;
                    $types[PRS_HTTPFIELD_POST_FIELD] = true;

                    $db_step['posts'] = [];
                }

                if (array_key_exists('query_fields', $step)) {
                    $httpstepids[$step['httpstepid']] = true;
                    $types[PRS_HTTPFIELD_QUERY_FIELD] = true;

                    $db_step['query_fields'] = [];
                }
            }
        }

        unset($db_step);

        if (!$httpstepids) {
            return;
        }

        $query = new Query();
        $query->from([
            'hs' => Httpstep::tableName(),
            'hsf' => HttpstepField::tableName(),
        ]);
        $query->where('hs.httpstepid=hsf.httpstepid')
            ->andWhere(SqlHelper::whereIn('hs.httpstepid', array_keys($httpstepids)))
            ->andWhere(SqlHelper::whereIn('hsf.type', array_keys($types)));
        $query->select(['hs.httptestid', 'hs.httpstepid', 'hsf.httpstep_fieldid', 'hsf.type', 'hsf.name', 'hsf.value']);

        $query->orderBy('{{hsf}}.httpstep_fieldid');

        $rows = $query->all();

        foreach ($rows as $row) {
            $field_name    = $field_names[$row['type']];

            $db_step = &$db_httptests[$row['httptestid']]['steps'][$row['httpstepid']];

            if (array_key_exists($field_name, $db_step)) {
                $db_step[$field_name][$row['httpstep_fieldid']] = [
                    'httpstep_fieldid' => $row['httpstep_fieldid'],
                    'name' => $row['name'],
                    'value' => $row['value']
                ];
            }
        }

        unset($db_step);
    }

    /**
     * @param array $httptests
     * @param array $db_httptests
     */
    private static function addAffectedTags(array $httptests, array &$db_httptests): void
    {
        $httptestids = [];

        foreach ($httptests as $httptest) {
            $steps_to_create_exists = array_key_exists('steps', $httptest)
                && count($httptest['steps']) > count(array_column($httptest['steps'], 'httpstepid'));

            if (array_key_exists('tags', $httptest) || $steps_to_create_exists) {
                $httptestids[] = $httptest['httptestid'];
                $db_httptests[$httptest['httptestid']]['tags'] = [];
            }
        }

        if (!$httptestids) {
            return;
        }

        $rows = HttptestTag::find()
            ->select(['httptesttagid', 'httptestid', 'tag', 'value'])
            ->where(['httptestid' => $httptestids])
            ->asArray()
            ->all();


        foreach ($rows as $row) {
            $db_httptests[$row['httptestid']]['tags'][$row['httptesttagid']] =
                array_diff_key($row, array_flip(['httptestid']));
        }
    }

    /**
     * Link http tests in template to hosts.
     *
     * @param $templateId
     * @param $hostIds
     */
    public function link($templateId, $hostIds)
    {
        $hostIds = prs_toArray($hostIds);
        $query = Httptest::find()
            ->with()
            ->where(['hostid' => $templateId])
            ->indexBy('httptestid')
            ->asArray();

        if ($httpTests = $query->all()) {
            $httpSteps = Httpstep::find()
                ->where(['httptestid' => array_keys($httpTests)])
                ->orderBy('no')
                ->asArray()
                ->all();

            $httpStepFields = HttptestField::find()
                ->where(['httpstepid' => array_column($httpSteps, 'httpstepid')])
                ->asArray()
                ->all();

            $fields = [];
            foreach ($httpStepFields as $httpStepField) {
                $fields[$httpStepField['httpstepid']][$httpStepField['type']][] = [
                    'name' => $httpStepField['name'],
                    'value' => $httpStepField['value'],
                ];
            }
            unset($httpStepField);

            $steps = [];
            foreach ($httpSteps as $httpStep) {
                if (isset($fields[$httpStep['httpstepid']])) {
                    $buf = $fields[$httpStep['httpstepid']];
                    $httpStep['variables'] = $buf[PRS_HTTPFIELD_HEADER] ?? [];
                    $httpStep['headers'] = $buf[PRS_HTTPFIELD_VARIABLE] ?? [];
                    $httpStep['query_fields'] = $buf[PRS_HTTPFIELD_QUERY_FIELD] ?? [];
                    $httpStep['posts'] = $buf[PRS_HTTPFIELD_POST_FIELD] ?? [];
                } else {
                    $httpStep['variables'] = [];
                    $httpStep['headers'] = [];
                    $httpStep['query_fields'] = [];
                    $httpStep['posts'] = [];
                }
                $steps[$httpStep['httptestid']][] = $httpStep;
            }

            foreach ($httpTests as $httpTestId => $httpTest) {
                $httpTest['steps'] = $steps[$httpTestId] ?? [];
            }
            unset($httpTest);
        }

        $this->inherit($httpTests, $hostIds);
    }

    /**
     * Inherit passed http tests to hosts.
     * If $hostIds is empty that means that we need to inherit all $httpTests to hosts which are linked to templates
     * where $httpTests belong.
     *
     * @param array $httpTests
     * @param array $hostIds
     *
     * @return bool
     */
    public function inherit(array $httpTests, array $hostIds = [])
    {
        $template_hosts = $this->getTemplateHosts($httpTests, $hostIds);

        if (!$template_hosts) {
            return true;
        }

        foreach ($httpTests as $i => $httpTest) {
            if (!array_key_exists($httpTest['hostid'], $template_hosts)) {
                unset($httpTests[$i]);
            }
        }

        self::$parent_itemids = self::getItemIds($httpTests);
        $preparedHttpTests = $this->prepareInheritedHttpTests($httpTests, $template_hosts);
        $inheritedHttpTests = $this->save($preparedHttpTests);
        $this->inherit($inheritedHttpTests);

        return true;
    }

    /**
     * Get hosts to which is necessary to inherit the given web scenarios indexed by template ID.
     *
     * @param array $httpTests
     * @param array $hostIds
     *
     * @return array
     */
    private static function getTemplateHosts(array $httpTests, array $hostIds): array
    {
        $templateHosts = [];

        $query = new Query();
        $query->from([
            'ht' => HostsTemplates::tableName(),
            'h' => Hosts::tableName(),
        ]);

        $query->where('ht.hostid=h.hostid')
            ->andWhere(SqlHelper::whereIn('{{ht}}.templateid', array_column($httpTests, 'hostid')));
        if ($hostIds) {
            $query->andWhere(SqlHelper::whereIn('{{ht}}.hostid', $hostIds));
        }

        foreach ($query->each() as $row) {
            $templateHosts[$row['templateid']][$row['hostid']] =  array_diff_key($row, array_flip(['templateid']));
        }

        return $templateHosts;
    }

    /**
     * Get item IDs array of the given web scenarios and their steps indexed by httptest ID and item key.
     *
     * @param array $httpTests
     *
     * @return array
     */
    private static function getItemIds(array $httpTests): array
    {
        $httpTestItemIds = [];

        $httpTestIds = array_column($httpTests, 'httptestid');

        $query = new Query();
        $query->from([
            'hti' => Httptestitem::tableName(),
            'i' => Items::tableName(),
        ]);

        $query->select(['hti.httptestid', 'hti.itemid', 'i.key_']);
        $query->where('hti.itemid=i.itemid');
        $query->andWhere(SqlHelper::whereIn('hti.httptestid', $httpTestIds));

        foreach ($query->each() as $row) {
            $httpTestItemIds[$row['httptestid']][$row['key_']] = $row['itemid'];
        }

        $query = new Query();
        $query->from([
            'hs' => Httpstep::tableName(),
            'hsi' => Httpstepitem::tableName(),
            'i' => Items::tableName()
        ]);
        $query->select(['hti.httptestid', 'hti.itemid', 'i.key_']);
        $query->where('hs.httpstepid=hsi.httpstepid')
            ->andWhere('hsi.itemid=i.itemid');
        $query->andWhere(SqlHelper::whereIn('hs.httptestid', $httpTestIds));

        foreach ($query->each() as $row) {
            $httpTestItemIds[$row['httptestid']][$row['key_']] = $row['itemid'];
        }

        return $httpTestItemIds;
    }

    /**
     * Generate http tests data for inheritance.
     * Using passed parameters decide if new http tests must be created on host or existing ones must be updated.
     *
     * @param array $httpTests which we need to inherit
     * @param array $template_hosts
     *
     * @throws Exception
     * @return array with http tests, existing apps have 'httptestid' key.
     */
    protected function prepareInheritedHttpTests(array $httpTests, array $template_hosts)
    {
        $hostHttpTests = $this->getHostHttpTests($template_hosts);

        $result = [];
        foreach ($httpTests as $httpTest) {
            $httpTestId = $httpTest['httptestid'];
            foreach ($hostHttpTests as $hostId => $hostHttpTest) {
                // if http test template is not linked to host we skip it
                if (!array_key_exists($hostId, $template_hosts[$httpTest['hostid']])) {
                    continue;
                }

                $exHttpTest = null;
                // update by templateid
                if (isset($hostHttpTest['byTemplateId'][$httpTestId])) {
                    $exHttpTest = $hostHttpTest['byTemplateId'][$httpTestId];

                    /*
					 * 'templateid' needs to be checked here too in case we update linked httptest to name
					 * that already exists on a linked host.
					 */
                    if (
                        isset($httpTest['name']) && isset($hostHttpTest['byName'][$httpTest['name']])
                        && !((string)$exHttpTest['templateid'] === (string)$hostHttpTest['byName'][$httpTest['name']]['templateid'])
                    ) {
                        self::checkWebHostExists($hostId, $exHttpTest['name']);
                    }
                }
                // update by name
                elseif (isset($hostHttpTest['byName'][$httpTest['name']])) {
                    $exHttpTest = $hostHttpTest['byName'][$httpTest['name']];

                    if (
                        bccomp($exHttpTest['templateid'], $httpTestId) == 0
                        || $exHttpTest['templateid'] != 0
                        || !$this->compareHttpSteps($httpTest, $exHttpTest)
                    ) {
                        self::checkWebHostExists($hostId, $exHttpTest['name']);
                    }
                }

                $newHttpTest = $httpTest;
                $newHttpTest['uuid'] = '';
                $newHttpTest['hostid'] = $hostId;
                $newHttpTest['templateid'] = $httpTestId;
                if ($exHttpTest) {
                    $newHttpTest['httptestid'] = $exHttpTest['httptestid'];

                    foreach (['headers', 'variables'] as $field_name) {
                        if (array_key_exists($field_name, $newHttpTest)) {
                            foreach ($newHttpTest[$field_name] as &$variable) {
                                unset($variable['httptest_fieldid']);
                            }
                            unset($variable);
                        }
                    }

                    if (isset($hostHttpTest['byTemplateId'][$httpTestId])) {
                        $this->setHttpTestParent($exHttpTest['httptestid'], $httpTestId);

                        if (isset($newHttpTest['steps'])) {
                            $newHttpTest['steps'] = $this->prepareHttpSteps(
                                $httpTest['steps'],
                                $exHttpTest['httptestid']
                            );
                        }
                    } elseif (isset($hostHttpTest['byName'][$httpTest['name']])) {
                        unset($newHttpTest['steps']);
                    }
                } else {
                    unset($newHttpTest['httptestid']);
                    $newHttpTest['host_status'] = $template_hosts[$httpTest['hostid']][$hostId]['status'];

                    foreach ($newHttpTest['steps'] as &$step) {
                        unset($step['httpstepid']);
                    }
                    unset($step);
                }

                $result[] = $newHttpTest;
            }
        }

        return $result;
    }

    /**
     * Find and set first parent id for http test.
     *
     * @param $id
     * @param $parentId
     */
    protected function setHttpTestParent($id, $parentId)
    {
        while (isset($this->httpTestParents[$parentId])) {
            $parentId = $this->httpTestParents[$parentId];
        }
        $this->httpTestParents[$id] = $parentId;
    }

    /**
     * Get hosts http tests for each passed hosts.
     * Each host has two hashes with http tests, one with name keys other with templateid keys.
     *
     * Resulting structure is:
     * array(
     *     'hostid1' => array(
     *         'byName' => array(ht1data, ht2data, ...),
     *         'nyTemplateId' => array(ht1data, ht2data, ...)
     *     ), ...
     * );
     *
     * @param array $template_hosts
     *
     * @return array
     */
    protected function getHostHttpTests(array $template_hosts)
    {
        $hostHttpTests = [];

        foreach ($template_hosts as $hosts) {
            foreach ($hosts as $hostid => $foo) {
                $hostHttpTests[$hostid] = ['byName' => [], 'byTemplateId' => []];
            }
        }

        $dbHttpTests = Httptest::find()
            ->alias('ht')
            ->select(['ht.httptestid', 'ht.name', 'ht.delay', 'ht.agent', 'ht.hostid', 'ht.templateid', 'ht.http_proxy', 'ht.retries'])
            ->where(SqlHelper::whereIn('ht.hostid', array_keys($hostHttpTests)))
            ->all();

        foreach ($dbHttpTests as $dbHttpTest) {
            $hostHttpTests[$dbHttpTest['hostid']]['byName'][$dbHttpTest['name']] = $dbHttpTest;
            if ($dbHttpTest['templateid']) {
                $hostHttpTests[$dbHttpTest['hostid']]['byTemplateId'][$dbHttpTest['templateid']] = $dbHttpTest;
            }
        }

        return $hostHttpTests;
    }

    private function checkWebHostExists($hostId, $name)
    {
        $host = Hosts::find()->select('name')->where(['hostid' => $hostId])->asArray()->scalar();
        throw new ValidateException(10000021, t('zapi', 'Web scenario "{src_name}" already exists on host "{dst_name}".', [
            'src_name' => $name,
            'src_name' => $host,
        ]));
    }

    /**
     * Compare steps for http tests.
     *
     * @param array $httpTest steps must be included under 'steps'
     * @param array $exHttpTest
     *
     * @return bool
     */
    protected function compareHttpSteps(array $httpTest, array $exHttpTest)
    {
        $firstHash = '';
        $secondHash = '';

        CArrayHelper::sort($httpTest['steps'], ['no']);
        foreach ($httpTest['steps'] as $step) {
            $firstHash .= $step['no'] . $step['name'];
        }

        $dbHttpTestSteps = Httpstep::find()
            ->select(['name', 'no'])
            ->where(['httptestid' => $exHttpTest['httptestid']])
            ->asArray()
            ->all();


        CArrayHelper::sort($dbHttpTestSteps, ['no']);
        foreach ($dbHttpTestSteps as $dbHttpStep) {
            $secondHash .= $dbHttpStep['no'] . $dbHttpStep['name'];
        }

        return ($firstHash === $secondHash);
    }

    /**
     * Save http tests. If http test has httptestid it gets updated otherwise a new one is created.
     *
     * @param array $http_tests
     *
     * @return array
     */
    protected function save(array $http_tests)
    {
        $http_tests_to_create = [];
        $http_tests_to_update = [];

        foreach ($http_tests as $num => $http_test) {
            if (array_key_exists('httptestid', $http_test)) {
                $http_tests_to_update[] = $http_test;
            } else {
                $http_tests_to_create[] = $http_test;
            }

            /*
			 * Unset $http_tests and (later) put it back with actual httptestid as a key right after creating/updating
			 * it. This is done in such a way because $http_tests array holds items with incremental keys which are not
			 * a real httptestids.
			 */
            unset($http_tests[$num]);
        }

        if ($http_tests_to_create) {
            $new_http_tests = $this->create($http_tests_to_create);

            foreach ($new_http_tests as $new_http_test) {
                $http_tests[$new_http_test['httptestid']] = $new_http_test;
            }
        }

        if ($http_tests_to_update) {
            $updated_http_tests = $this->update($http_tests_to_update);

            foreach ($updated_http_tests as $updated_http_test) {
                $http_tests[$updated_http_test['httptestid']] = $updated_http_test;
            }
        }

        return $http_tests;
    }

    /**
     * @param array $steps
     * @param $exHttpTestId
     *
     * @return array
     */
    protected function prepareHttpSteps(array $steps, $exHttpTestId)
    {
        $exSteps = Httpstep::find()
            ->select(['httpstepid', 'name'])
            ->where(['httptestid' => $exHttpTestId])
            ->indexBy('name')
            ->asArray()
            ->column();

        $result = [];
        foreach ($steps as $step) {
            $parentTestId = $this->httpTestParents[$exHttpTestId];
            if (isset($this->changedSteps[$parentTestId][$step['name']])) {
                $stepName = $this->changedSteps[$parentTestId][$step['name']];
            } else {
                $stepName = $step['name'];
            }

            if (isset($exSteps[$stepName])) {
                $step['httpstepid'] = $exSteps[$stepName];
            } else {
                unset($step['httpstepid']);
            }

            foreach (['headers', 'variables', 'posts', 'query_fields'] as $field_name) {
                if (array_key_exists($field_name, $step)) {
                    foreach ($step[$field_name] as &$variable) {
                        unset($variable['httpstep_fieldid']);
                    }
                    unset($variable);
                }
            }

            $result[] = $step;
        }

        return $result;
    }
}
