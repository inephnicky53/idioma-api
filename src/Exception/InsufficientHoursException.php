<?php

namespace App\Exception;

class InsufficientHoursException extends \Exception
{
    public function __construct(string $message = "You don't have enough hours to book this planning.")
    {
        parent::__construct($message, 403); // 403 Forbidden HTTP status code
    }
}