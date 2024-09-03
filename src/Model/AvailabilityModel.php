<?php

namespace App\Model;

class AvailabilityModel
{
    public function __construct(
        public string   $day,
        /** @var TimeSlot[] $programs */
        public array $programs
    )
    {
    }
}