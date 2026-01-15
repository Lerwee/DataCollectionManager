<?php

namespace app\customs\zapi\common\validators;

use app\customs\zapi\common\validators\base\BaseZValidator;
use app\customs\zapi\models\search\EventSearch;

/**
 *
 * Class EventSourceObjectValidator
 * @package app\customs\zapi\common\validators
 */
class EventSourceObjectValidator extends BaseZValidator
{
    /**
     * Supported source-object pairs.
     *
     * @var array
     */
    public $pairs = [
        EVENT_SOURCE_TRIGGERS => [
            EVENT_OBJECT_TRIGGER => 1
        ],
        EVENT_SOURCE_DISCOVERY => [
            EVENT_OBJECT_DHOST => 1,
            EVENT_OBJECT_DSERVICE => 1
        ],
        EVENT_SOURCE_AUTOREGISTRATION => [
            EVENT_OBJECT_AUTOREGHOST => 1
        ],
        EVENT_SOURCE_INTERNAL => [
            EVENT_OBJECT_TRIGGER => 1,
            EVENT_OBJECT_ITEM => 1,
            EVENT_OBJECT_LLDRULE => 1
        ],
        EVENT_SOURCE_SERVICE => [
            EVENT_OBJECT_SERVICE => 1
        ]
    ];

    /**
     * Checks if the given value belongs to some set.
     *
     * @param $value
     *
     * @return
     */
    public function validateValue($value)
    {
        $pairs = $this->pairs;

        $objects = $pairs[$value['source']];
        if (!isset($objects[$value['object']])) {
            $supportedObjects = '';
            foreach ($objects as $object => $i) {
                $supportedObjects .= $object . ' - ' . EventSearch::eventObject($object) . ', ';
            }
            return $this->setValueError(
                t(
                    'zapi',
                    'Incorrect event object "{object}" ({eventObject}) for event source "{source}" ({eventSource}), only the following objects are supported: {supportedObjects}.',
                    [
                        'object' => $value['object'],
                        'eventObject' => EventSearch::eventObject($value['object']),
                        'source' => $value['source'],
                        'eventSource' => EventSearch::eventSource($value['source']),
                        'supportedObjects' => rtrim($supportedObjects, ', ')]
                )
            );
        }
        return null;
    }
}
