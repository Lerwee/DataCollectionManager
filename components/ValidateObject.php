<?php

namespace app\customs\zapi\components;

use app\common\base\BaseModel;

/**
 * Class ValidateObject
 * @package app\customs\zapi\common\helpers
 */
class ValidateObject extends BaseModel
{
    /**
     * @var array
     */
    private $data = array();

    public function getData(): array
    {
        return $this->data;
    }

    public function loadData(array $data)
    {
        foreach ($data as $datum => $value) {
            $this->data[$datum] = $value;
        }
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
        return null;
    }

    public function __unset($name)
    {
        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
        }
    }
}