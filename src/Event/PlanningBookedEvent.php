<?php


namespace App\Event;


use App\Entity\Planning;

readonly class PlanningBookedEvent
{
    public function __construct(private Planning $planning)
    {
    }

    public function getPlanning(): Planning
    {
        return $this->planning;
    }
}
