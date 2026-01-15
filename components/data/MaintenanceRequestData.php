<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use app\customs\zapi\common\parsers\CAbsoluteTimeParser;

/**
 * Class MaintenanceRequestData
 * @package app\customs\zapi\components\Maintenance
 */
class MaintenanceRequestData extends RequestData
{
    /**
     * @var string
     */
    public $action = 'create';

    /**
     * @return Result
     */
    public function validate(): Result
    {
        $absoluteTimeParser = new CAbsoluteTimeParser();

        $absoluteTimeParser->parse($this->getRequest('active_since'));
        $activeSince = $absoluteTimeParser->getDateTime(true)->getTimestamp();

        $absoluteTimeParser->parse($this->getRequest('active_till'));
        $activeTill = $absoluteTimeParser->getDateTime(true)->getTimestamp();


        $timePeriodFields = [
            TIMEPERIOD_TYPE_ONETIME => ['timeperiod_type', 'start_date', 'period'],
            TIMEPERIOD_TYPE_DAILY => ['timeperiod_type', 'every', 'start_time', 'period'],
            TIMEPERIOD_TYPE_WEEKLY => ['timeperiod_type', 'every', 'dayofweek', 'start_time', 'period'],
            TIMEPERIOD_TYPE_MONTHLY => ['timeperiod_type', 'every', 'month', 'dayofweek', 'day', 'start_time', 'period']
        ];

        $timePeriods = $this->getRequest('timeperiods', []);

        foreach ($timePeriods as $i => &$timePeriod) {
            if (!array_key_exists('timeperiod_type', $timePeriod) || !isset($timePeriodFields[$timePeriod['timeperiod_type']])) {
                return $this->error(error_code(10000026, ['param' => "/timeperiods/{$i}/timeperiod_type"]));
            }


            $timePeriod = array_intersect_key(
                $timePeriod,
                array_flip($timePeriodFields[$timePeriod['timeperiod_type']])
            );
        }
        unset($timePeriod);

        $maintenance = [
            'name' => $this->getRequest('name'),
            'maintenance_type' => $this->getRequest('maintenance_type'),
            'description' => $this->getRequest('description'),
            'active_since' => $activeSince,
            'active_till' => $activeTill,
            'groups' => prs_toObject($this->getRequest('groupids', []), 'groupid'),
            'hosts' => prs_toObject($this->getRequest('hostids', []), 'hostid'),
            'timeperiods' => $timePeriods
        ];

        if ($maintenance['maintenance_type'] == MAINTENANCE_TYPE_NORMAL) {
            $maintenance += [
                'tags_evaltype' => $this->getRequest('tags_evaltype'),
                'tags' => []
            ];

            foreach ($this->getRequest('tags', []) as $tag) {
                if (
                    array_key_exists('tag', $tag) && array_key_exists('value', $tag)
                    && ($tag['tag'] !== '' || $tag['value'] !== '')
                ) {
                    $maintenance['tags'][] = $tag;
                }
            }
        }

        if ($maintenanceId = $this->getRequest('maintenanceid')) {
            $maintenance['maintenanceid'] = $maintenanceId;
        }

        return $this->success($maintenance);
    }
}
