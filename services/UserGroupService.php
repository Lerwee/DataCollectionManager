<?php

namespace app\customs\zapi\services;

use app\customs\zapi\common\db\DB;
use app\customs\zapi\services\assist\BaseAssist;

class UserGroupService extends BaseAssist
{
    /**
     * @static
     *
     * @param array $usrgrps
     * @param array $db_usrgrps
     */
    public static function updateForce($usrgrps, $db_usrgrps): void
    {
        $upd_usrgrps = [];

        foreach ($usrgrps as $usrgrp) {
            $db_usrgrp = $db_usrgrps[$usrgrp['usrgrpid']];

            $upd_usrgrp = DB::getUpdatedValues('usrgrp', $usrgrp, $db_usrgrp);

            if ($upd_usrgrp) {
                $upd_usrgrps[] = [
                    'values' => $upd_usrgrp,
                    'where' => ['usrgrpid' => $usrgrp['usrgrpid']],
                ];
            }
        }

        if ($upd_usrgrps) {
            DB::update('usrgrp', $upd_usrgrps);
        }

        self::updateRights($usrgrps, $db_usrgrps);
        self::updateTagFilters($usrgrps, $db_usrgrps);
        self::updateUsers($usrgrps, $db_usrgrps);

    }

    /**
     * @param array      $usrgrps
     * @param null|array $db_usrgrps
     */
    private static function updateRights(array &$usrgrps, array $db_usrgrps = null): void
    {
        $ins_rights = [];
        $upd_rights = [];
        $del_rightids = [];

        foreach (['hostgroup_rights', 'templategroup_rights'] as $parameter) {
            foreach ($usrgrps as &$usrgrp) {
                if (!array_key_exists($parameter, $usrgrp)) {
                    continue;
                }

                $db_rights = $db_usrgrps !== null
                ? array_column($db_usrgrps[$usrgrp['usrgrpid']][$parameter], null, 'id')
                : [];

                foreach ($usrgrp[$parameter] as &$right) {
                    if (array_key_exists($right['id'], $db_rights)) {
                        $db_right = $db_rights[$right['id']];
                        unset($db_rights[$right['id']]);

                        $right['rightid'] = $db_right['rightid'];

                        $upd_right = DB::getUpdatedValues('rights', $right, $db_right);

                        if ($upd_right) {
                            $upd_rights[] = [
                                'values' => $upd_right,
                                'where' => ['rightid' => $db_right['rightid']],
                            ];
                        }
                    } else {
                        $ins_rights[] = [
                            'groupid' => $usrgrp['usrgrpid'],
                            'id' => $right['id'],
                            'permission' => $right['permission'],
                        ];
                    }
                }
                unset($right);

                $del_rightids = array_merge($del_rightids, array_column($db_rights, 'rightid'));
            }
            unset($usrgrp);
        }

        if ($ins_rights) {
            $rightids = DB::insertBatch('rights', $ins_rights);
        }

        if ($upd_rights) {
            DB::update('rights', $upd_rights);
        }

        if ($del_rightids) {
            DB::delete('rights', ['rightid' => $del_rightids]);
        }

        foreach (['hostgroup_rights', 'templategroup_rights'] as $parameter) {
            foreach ($usrgrps as &$usrgrp) {
                if (!array_key_exists($parameter, $usrgrp)) {
                    continue;
                }

                foreach ($usrgrp[$parameter] as &$right) {
                    if (!array_key_exists('rightid', $right)) {
                        $right['rightid'] = array_shift($rightids);
                    }
                }
                unset($right);
            }
            unset($usrgrp);
        }
    }

    /**
     * @param array      $usrgrps
     * @param null|array $db_usrgrps
     */
    private static function updateTagFilters(array &$usrgrps, array $db_usrgrps = null): void
    {
        $ins_tag_filters = [];
        $del_tag_filterids = [];

        foreach ($usrgrps as &$usrgrp) {
            if (!array_key_exists('tag_filters', $usrgrp)) {
                continue;
            }

            $db_tag_filterids_by_tag_value = [];
            $db_tag_filters = $db_usrgrps !== null
            ? $db_usrgrps[$usrgrp['usrgrpid']]['tag_filters']
            : [];

            foreach ($db_tag_filters as $db_tag_filter) {
                $db_tag_filterids_by_tag_value[$db_tag_filter['groupid']][$db_tag_filter['tag']][
                    $db_tag_filter['value']
                ] = $db_tag_filter['tag_filterid'];
            }

            foreach ($usrgrp['tag_filters'] as &$tag_filter) {
                $groupid = $tag_filter['groupid'];
                $tag = $tag_filter['tag'];
                $value = $tag_filter['value'];

                if (array_key_exists($groupid, $db_tag_filterids_by_tag_value)
                    && array_key_exists($tag, $db_tag_filterids_by_tag_value[$groupid])
                    && array_key_exists($value, $db_tag_filterids_by_tag_value[$groupid][$tag])) {
                    $tag_filterid = $db_tag_filterids_by_tag_value[$groupid][$tag][$value];
                    unset($db_tag_filters[$tag_filterid]);

                    $tag_filter['tag_filterid'] = $tag_filterid;
                } else {
                    $ins_tag_filters[] = [
                        'usrgrpid' => $usrgrp['usrgrpid'],
                        'groupid' => $tag_filter['groupid'],
                        'tag' => $tag_filter['tag'],
                        'value' => $tag_filter['value'],
                    ];
                }
            }
            unset($tag_filter);

            $del_tag_filterids = array_merge($del_tag_filterids, array_column($db_tag_filters, 'tag_filterid'));
        }
        unset($usrgrp);

        if ($ins_tag_filters) {
            $tag_filterids = DB::insertBatch('tag_filter', $ins_tag_filters);
        }

        if ($del_tag_filterids) {
            DB::delete('tag_filter', ['tag_filterid' => $del_tag_filterids]);
        }

        foreach ($usrgrps as &$usrgrp) {
            if (!array_key_exists('tag_filters', $usrgrp)) {
                continue;
            }

            foreach ($usrgrp['tag_filters'] as &$tag_filter) {
                if (!array_key_exists('tag_filterid', $tag_filter)) {
                    $tag_filter['tag_filterid'] = array_shift($tag_filterids);
                }
            }
            unset($tag_filter);
        }
        unset($usrgrp);
    }

    /**
     * @param array      $groups
     * @param null|array $db_groups
     */
    private static function updateUsers(array &$groups, array $db_groups = null): void
    {
        $users = [];
        $db_users = [];

        foreach ($groups as &$group) {
            if (!array_key_exists('users', $group)) {
                continue;
            }

            $_db_users = $db_groups !== null
            ? array_column($db_groups[$group['usrgrpid']]['users'], null, 'userid')
            : [];

            foreach ($group['users'] as $user) {
                $userid = $user['userid'];

                if (array_key_exists($userid, $_db_users)) {
                    unset($_db_users[$userid]);
                } else {
                    $users[$userid]['userid'] = $userid;
                    $users[$userid]['usrgrps'][] = ['usrgrpid' => $group['usrgrpid']];

                    $db_users[$userid]['userid'] = $userid;
                    $db_users[$userid]['usrgrps'] = [];
                }
            }

            foreach ($_db_users as $userid => $db_user) {
                $users[$userid]['userid'] = $userid;
                $users[$userid] += ['usrgrps' => []];

                $db_users[$userid]['userid'] = $userid;
                $db_users[$userid]['usrgrps'][$db_user['id']] = [
                    'id' => $db_user['id'],
                    'usrgrpid' => $group['usrgrpid'],
                ];
            }

            unset($group['users'], $db_groups[$group['usrgrpid']]['users']);
        }
        unset($group);

        if (!$users) {
            return;
        }

        $options = [
            'output' => ['userid', 'username'],
            'userids' => array_keys($users),
        ];

        $query = DB::makeQuery('users', $options);

        $rows = $query->all();

        foreach ($rows as $row) {
            $db_users[$row['userid']]['username'] = $row['username'];
        }

        // TODO: update users
        // CUser::updateForce(array_values($users), $db_users);
    }
}
