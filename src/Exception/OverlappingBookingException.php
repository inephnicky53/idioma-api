<?php

namespace App\Exception;

class OverlappingBookingException extends \Exception
{
    public function __construct(string $message = "This booking overlaps with an existing one or the teacher's availability.")
    {
        parent::__construct($message, 409); // 409 Conflict HTTP status code
    }
}