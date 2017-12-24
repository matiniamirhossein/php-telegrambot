<?php
namespace TelegramBot\Exception;
use Throwable;

class TelegramBotException extends \Exception {
    private $method, $params;
    public function __construct($message, $code = 0, $method, $params, Throwable $previous = null)
    {
        $this->method = $method;
        $this->params = $params;
        parent::__construct($message, $code, $previous);
    }
    public function getMethod(){
        return $this->method;
    }
    public function getParams(){
        return $this->params;
    }
}