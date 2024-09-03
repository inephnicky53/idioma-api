<?php

namespace App\Scheduler;

use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('shared')]
class SharedTaskProvider implements ScheduleProviderInterface
{
    public function __construct() {}

    public function getSchedule(): Schedule
    {
        return (new Schedule());
    }
}
