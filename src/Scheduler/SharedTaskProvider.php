<?php

namespace App\Scheduler;

use App\Event\CheckTransactionEventMessage;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('shared')]
class SharedTaskProvider implements ScheduleProviderInterface
{
    public function __construct()
    {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::every('2 minutes', new CheckTransactionEventMessage()));
    }
}
