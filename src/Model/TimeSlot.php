<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class TimeSlot
{
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/',
        message: 'The time must be in HH:MM format (e.g., 09:00).'
    )]
    public string $start;

    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/',
        message: 'The time must be in HH:MM format (e.g., 17:30).'
    )]
    #[Assert\Expression(
        "this.start < this.end",
        message: "The end time must be after the start time."
    )]
    public string $end;
}