<?php


namespace App\Event;


use App\Entity\Planning;

class PlanningCreatedEvent
{
    public function __construct(private readonly Planning $planning)
    {
    }

    public function getPlanning(): Planning
    {
        return $this->planning;
    }
}
