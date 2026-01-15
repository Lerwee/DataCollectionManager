<?php

namespace app\customs\zapi\components\data;

use app\common\components\Result;
use Yii;

/**
 * Class HostPrototypeMassRequestData
 * @package app\customs\zapi\components\data
 */
class HostPrototypeMassRequestData extends HostPrototypeRequestData
{
    /**
     * @return Result
     */
    public function validate(): Result
    {
        // $request = $this->beforeValidate();
        // if (!$request->isSuccess()) {
        //     return $request;
        // }

        $hostIds = (array) $this->getRequest('group_hostid', []);
        if (empty($hostIds)) {
            return $this->error(error_code(60750001, ['attribute' => 'group_hostid', 'error' => Yii::t('yii', '{attribute} cannot be blank.', ['attribute' => ''])]));
        }

        $action = (string) $this->getRequest('action', 'status');
        $status = (int) $this->getRequest('status', 0);

        $update = [];
        foreach ($hostIds as $hostPrototypeId) {
            $update[] = [
                'hostid' => $hostPrototypeId,
                $action => $status
            ];
        }

        return $this->success($update);
    }
}
