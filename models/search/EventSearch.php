<?php

namespace app\customs\zapi\models\search;

use app\common\base\BaseModel;
use app\common\helpers\SqlHelper;
use app\common\provider\ActiveDataProvider;
use app\modules\libzbx\models\zbx\HostsGroups;
use app\modules\libzbx\models\zbx\Hstgrp;
use yii\db\Query;

class EventSearch extends BaseModel
{
    public $hosts;

    public static function eventObject($object = null)
    {
        $objects = [
            EVENT_OBJECT_TRIGGER => t('zapi', 'trigger'),
            EVENT_OBJECT_DHOST => t('zapi', 'discovered host'),
            EVENT_OBJECT_DSERVICE => t('zapi', 'discovered service'),
            EVENT_OBJECT_AUTOREGHOST => t('zapi', 'autoregistered host'),
            EVENT_OBJECT_ITEM => t('zapi', 'item'),
            EVENT_OBJECT_LLDRULE => t('zapi', 'low-level discovery rule'),
            EVENT_OBJECT_SERVICE => t('zapi', 'service')
        ];

        if ($object === null) {
            return $objects;
        } elseif (isset($objects[$object])) {
            return $objects[$object];
        } else {
            return t('zapi', 'Unknown');
        }
    }

    public static function eventSourceObjects(): array
    {
        return [
            ['source' => EVENT_SOURCE_TRIGGERS, 'object' => EVENT_OBJECT_TRIGGER],
            ['source' => EVENT_SOURCE_DISCOVERY, 'object' => EVENT_OBJECT_DHOST],
            ['source' => EVENT_SOURCE_DISCOVERY, 'object' => EVENT_OBJECT_DSERVICE],
            ['source' => EVENT_SOURCE_AUTOREGISTRATION, 'object' => EVENT_OBJECT_AUTOREGHOST],
            ['source' => EVENT_SOURCE_INTERNAL, 'object' => EVENT_OBJECT_TRIGGER],
            ['source' => EVENT_SOURCE_INTERNAL, 'object' => EVENT_OBJECT_ITEM],
            ['source' => EVENT_SOURCE_INTERNAL, 'object' => EVENT_OBJECT_LLDRULE],
            ['source' => EVENT_SOURCE_SERVICE, 'object' => EVENT_OBJECT_SERVICE]
        ];
    }

    public static function eventSource($source = null)
    {
        $sources = [
            EVENT_SOURCE_TRIGGERS => t('zapi', 'trigger'),
            EVENT_SOURCE_DISCOVERY => t('zapi', 'discovery'),
            EVENT_SOURCE_AUTOREGISTRATION => t('zapi', 'autoregistration'),
            EVENT_SOURCE_INTERNAL => t('zapi', 'event source'),
            EVENT_SOURCE_SERVICE => t('zapi', 'service')
        ];

        if ($source === null) {
            return $sources;
        }
        return array_key_exists($source, $sources) ? $sources[$source] : t('zapi', 'Unknown');
    }
}
