<?php

namespace App\Dto;

use App\Model\BookPlanningModel;

class BookPlanningInput
{
    /** @var BookPlanningModel[] $plannings  */
    public ?array $plannings = [];
}