<?php

namespace app\customs\zapi\components\data;

use app\common\base\BaseComponent;
use app\common\components\Result;
use app\common\traits\ResultTrait;

/**
 * Class RequestData
 * @package app\customs\zapi\components
 */
abstract class RequestData extends BaseComponent
{
    use ResultTrait;

    /**
     * @var array
     */
    public $data = [];

    /**
     * @var Result
     */
    protected $result;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->result = $this->validate();
    }

    /**
     * @return Result
     */
    abstract public function validate(): Result;

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->result->isSuccess();
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->result->getData();
    }

    /**
     * @return Result
     */
    public function getResult(): Result
    {
        return $this->result;
    }

    /**
     * @param $name
     * @param null $default
     * @return mixed|null
     */
    protected function getRequest($name, $default = null)
    {
        return $this->data[$name] ?? $default;
    }
}