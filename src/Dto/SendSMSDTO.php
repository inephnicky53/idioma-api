<?php

namespace App\Dto;

class SendSMSDTO
{
    public function __construct(
        public string $to,
        public string $message
    )
    {
    }
}