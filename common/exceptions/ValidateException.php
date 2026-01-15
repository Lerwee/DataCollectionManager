<?php

namespace app\customs\zapi\common\exceptions;

use Throwable;
use yii\base\Exception;

/**
 * Class ParamValidateException
 * @package app\customs\zapi\common\exceptions
 */
class ValidateException extends Exception
{
    /**
     * @var int|string
     */
    protected $errorCode;

    /**
     * @param int|string $errorCode
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($errorCode, string $message = "", $code = 0, Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return int|string
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }
}