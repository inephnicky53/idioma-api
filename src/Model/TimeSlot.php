<?php

namespace App\Model;

class TimeSlot
{
    public function __construct(
        public string $start,
        public string $end
    )
    {
    }
}